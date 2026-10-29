<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Laporan penjualan & akuntansi sederhana (khusus Admin/Owner).
 */
class ReportController extends Controller
{
    public function sales(Request $request): View
    {
        [$start, $end] = $this->period($request);

        $base = fn () => Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

        $summary = [
            'transactions' => $base()->count(),
            'revenue' => (int) $base()->sum('total'),
            'paid' => (int) $base()->where('payment_status', Sale::PAYMENT_PAID)->sum('total'),
            'unpaid' => (int) $base()->where('payment_status', Sale::PAYMENT_UNPAID)->sum('total'),
        ];

        $perDay = $base()
            ->select(DB::raw('DATE(sold_at) as day'), DB::raw('COUNT(*) as transactions'), DB::raw('SUM(total) as revenue'))
            ->groupBy('day')->orderBy('day')->get();

        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('barangs', 'barangs.id', '=', 'sale_items.barang_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('sale_items.barang_id', 'barangs.name', 'barangs.code')
            ->orderByDesc(DB::raw('SUM(sale_items.subtotal)'))
            ->limit(10)
            ->get(['barangs.name', 'barangs.code', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.subtotal) as revenue')]);

        return view('reports.sales', compact('summary', 'perDay', 'topProducts', 'start', 'end'));
    }

    /** Laporan laba rugi: pendapatan - HPP - beban. Dibaca dari jurnal. */
    public function profitLoss(Request $request): View
    {
        [$start, $end] = $this->period($request);

        $rows = $this->balances($start, $end)->whereIn('type', ['revenue', 'expense']);

        $revenue = $rows->where('type', 'revenue');
        $cogs = $rows->where('code', '5101');
        $expenses = $rows->where('type', 'expense')->where('code', '!=', '5101');

        $totalRevenue = $revenue->sum('balance');
        $totalCogs = $cogs->sum('balance');
        $grossProfit = $totalRevenue - $totalCogs;
        $totalExpenses = $expenses->sum('balance');
        $netProfit = $grossProfit - $totalExpenses;

        return view('reports.profit-loss', compact(
            'start', 'end', 'revenue', 'cogs', 'expenses',
            'totalRevenue', 'totalCogs', 'grossProfit', 'totalExpenses', 'netProfit'
        ));
    }

    /** Neraca saldo: total debit harus sama dengan total kredit. */
    public function trialBalance(Request $request): View
    {
        [, $end] = $this->period($request);

        $rows = $this->balances(null, $end);

        return view('reports.trial-balance', [
            'rows' => $rows,
            'totalDebit' => $rows->sum('debit'),
            'totalCredit' => $rows->sum('credit'),
            'end' => $end,
        ]);
    }

    public function ledger(Request $request): View
    {
        $request->validate(['account_id' => ['nullable', 'exists:accounts,id']]);
        [$start, $end] = $this->period($request);

        $accounts = Account::orderBy('code')->get();
        $account = $request->filled('account_id') ? $accounts->firstWhere('id', (int) $request->account_id) : null;

        $lines = collect();
        $openingBalance = 0;

        if ($account) {
            $sign = in_array($account->type, ['asset', 'expense'], true) ? 1 : -1;

            $openingBalance = $sign * (int) JournalLine::where('account_id', $account->id)
                ->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<', $start))
                ->sum(DB::raw('debit - credit'));

            $lines = JournalLine::with('entry')
                ->where('account_id', $account->id)
                ->whereHas('entry', fn ($q) => $q->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()]))
                ->get()
                ->sortBy(fn ($l) => $l->entry->entry_date->format('Ymd').str_pad((string) $l->entry->id, 8, '0', STR_PAD_LEFT))
                ->values();
        }

        return view('reports.ledger', compact('accounts', 'account', 'lines', 'openingBalance', 'start', 'end'));
    }

    public function journal(Request $request): View
    {
        [$start, $end] = $this->period($request);

        $entries = JournalEntry::with('lines.account')
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->latest('entry_date')->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('reports.journal', compact('entries', 'start', 'end'));
    }

    /**
     * Saldo per akun dari jurnal pada rentang tanggal.
     * balance: saldo menurut sisi normal akun (aset/beban = debit - kredit; lainnya = kredit - debit).
     */
    private function balances($start, $end)
    {
        $totals = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($start, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $start->toDateString()))
            ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
            ->groupBy('journal_lines.account_id')
            ->get(['journal_lines.account_id', DB::raw('SUM(journal_lines.debit) as debit'), DB::raw('SUM(journal_lines.credit) as credit')])
            ->keyBy('account_id');

        return Account::orderBy('code')->get()->map(function (Account $account) use ($totals) {
            $debit = (int) ($totals[$account->id]->debit ?? 0);
            $credit = (int) ($totals[$account->id]->credit ?? 0);
            $normalDebit = in_array($account->type, ['asset', 'expense'], true);

            return [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $normalDebit ? $debit - $credit : $credit - $debit,
            ];
        })->filter(fn ($r) => $r['debit'] || $r['credit'])->values();
    }

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} */
    private function period(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            $request->filled('start_date') ? \Illuminate\Support\Carbon::parse($request->start_date) : now()->startOfMonth(),
            $request->filled('end_date') ? \Illuminate\Support\Carbon::parse($request->end_date) : now(),
        ];
    }
}
