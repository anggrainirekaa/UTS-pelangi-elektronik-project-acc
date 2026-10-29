<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Sale;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use InteractsWithStock, RefreshDatabase;

    private function assertBalanced(): void
    {
        $this->assertGreaterThan(0, JournalLine::count());
        $this->assertSame((int) JournalLine::sum('debit'), (int) JournalLine::sum('credit'), 'Total debit harus sama dengan total kredit.');
    }

    private function sell($user, $barang, int $qty, array $extra = [])
    {
        return $this->actingAs($user)->post('/penjualan', $extra + [
            'warehouse_id' => \App\Models\Warehouse::default()->id,
            'items' => [['barang_id' => $barang->id, 'quantity' => $qty]],
        ]);
    }

    public function test_sale_creates_balanced_journal_with_revenue_and_cogs(): void
    {
        $a = $this->product(10); // beli 10.000, jual 15.000
        $this->sell($this->staff(), $a, 3, ['mark_paid' => 1, 'payment_method' => 'cash']);

        $this->assertBalanced();
        $sale = Sale::first();
        $entry = JournalEntry::where('reference_id', $sale->id)->first();
        $this->assertNotNull($entry);

        $byCode = fn (string $code) => JournalLine::whereHas('account', fn ($q) => $q->where('code', $code))->get();
        $this->assertSame(45000, (int) $byCode('1101')->sum('debit'));   // kas
        $this->assertSame(45000, (int) $byCode('4101')->sum('credit'));  // pendapatan
        $this->assertSame(30000, (int) $byCode('5101')->sum('debit'));   // HPP
        $this->assertSame(30000, (int) $byCode('1201')->sum('credit'));  // persediaan
    }

    public function test_unpaid_sale_goes_to_receivable_and_payment_clears_it(): void
    {
        $a = $this->product(10);
        $staff = $this->staff();
        $this->sell($staff, $a, 2);
        $sale = Sale::first();

        $receivable = fn () => (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', '1102'))->sum('debit')
            - (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', '1102'))->sum('credit');

        $this->assertSame(30000, $receivable());

        $this->actingAs($staff)->post("/penjualan/{$sale->id}/bayar", ['payment_method' => 'cash'])->assertSessionHasNoErrors();

        $this->assertSame(0, $receivable());
        $this->assertBalanced();
    }

    public function test_cancelling_sale_reverses_journal_and_keeps_books_balanced(): void
    {
        $a = $this->product(10);
        $this->sell($this->staff(), $a, 4, ['mark_paid' => 1, 'payment_method' => 'cash']);
        $sale = Sale::first();

        $this->actingAs($this->admin())->post("/penjualan/{$sale->id}/batal", ['reason' => 'Retur']);

        $this->assertBalanced();
        // Setelah pembalikan, saldo pendapatan, kas dan HPP kembali nol.
        foreach (['4101', '5101', '1201'] as $code) {
            $debit = (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', $code))->sum('debit');
            $credit = (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', $code))->sum('credit');
            $this->assertSame($debit, $credit, "Akun {$code} harus bersaldo nol setelah pembatalan.");
        }
        $this->assertNotNull(JournalEntry::where('reference_id', $sale->id)->whereNotNull('reversed_at')->first());
    }

    public function test_purchase_posts_inventory_and_payable_journal(): void
    {
        $a = $this->product(0);
        $this->actingAs($this->staff())->post('/pembelian', [
            'supplier_id' => $this->supplier()->id,
            'warehouse_id' => \App\Models\Warehouse::default()->id,
            'items' => [['barang_id' => $a->id, 'quantity' => 5, 'unit_cost' => 10000]],
        ]);

        $this->assertBalanced();
        $this->assertSame(50000, (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', '1201'))->sum('debit'));
    }

    public function test_expense_is_journaled_and_deleting_it_reverses_the_journal(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/pengeluaran', [
            'expense_date' => now()->toDateString(), 'title' => 'Listrik', 'amount' => 25000,
        ])->assertSessionHasNoErrors();

        $expense = Expense::first();
        $this->assertSame(25000, $expense->amount);
        $this->assertBalanced();

        $this->actingAs($admin)->delete("/pengeluaran/{$expense->id}");
        $this->assertDatabaseCount('expenses', 0);
        $this->assertBalanced();
        $this->assertSame(0, (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', '5201'))->sum('debit')
            - (int) JournalLine::whereHas('account', fn ($q) => $q->where('code', '5201'))->sum('credit'));
    }

    public function test_expense_validation_and_authorization(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/pengeluaran', ['title' => '', 'amount' => 0, 'expense_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['title', 'amount', 'expense_date']);

        $this->actingAs($this->staff())->get('/pengeluaran')->assertForbidden();
        $this->actingAs($this->customer())->get('/pengeluaran')->assertForbidden();
    }

    public function test_profit_and_loss_report(): void
    {
        $a = $this->product(10);
        $this->sell($this->staff(), $a, 3, ['mark_paid' => 1, 'payment_method' => 'cash']); // pendapatan 45.000, HPP 30.000
        $admin = $this->admin();
        $this->actingAs($admin)->post('/pengeluaran', ['expense_date' => now()->toDateString(), 'title' => 'Air', 'amount' => 5000]);

        $response = $this->actingAs($admin)->get('/laporan/laba-rugi');

        $response->assertOk();
        $response->assertViewHas('totalRevenue', 45000);
        $response->assertViewHas('totalCogs', 30000);
        $response->assertViewHas('grossProfit', 15000);
        $response->assertViewHas('totalExpenses', 5000);
        $response->assertViewHas('netProfit', 10000);
    }

    public function test_trial_balance_is_balanced_and_reports_are_admin_only(): void
    {
        $a = $this->product(10);
        $this->sell($this->staff(), $a, 1, ['mark_paid' => 1, 'payment_method' => 'cash']);

        $response = $this->actingAs($this->admin())->get('/laporan/neraca-saldo');
        $response->assertOk();
        $this->assertSame($response->viewData('totalDebit'), $response->viewData('totalCredit'));

        foreach (['laba-rugi', 'neraca-saldo', 'buku-besar', 'jurnal', 'penjualan'] as $report) {
            $this->actingAs($this->admin())->get("/laporan/{$report}")->assertOk();
            $this->actingAs($this->staff())->get("/laporan/{$report}")->assertForbidden();
            $this->actingAs($this->customer())->get("/laporan/{$report}")->assertForbidden();
        }
    }

    public function test_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(\Throwable::class);

        $service = app(JournalService::class);
        $service->ensureAccounts();
        $ref = new \ReflectionMethod($service, 'createEntry');
        $ref->setAccessible(true);
        $cash = \App\Models\Account::where('code', '1101')->first();
        $ref->invoke($service, 'Tidak seimbang', now()->toDateString(), null, null, [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
        ]);
    }
}
