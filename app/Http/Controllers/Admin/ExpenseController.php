<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::with('user')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest('expense_date')->latest('id')
            ->paginate(10)->withQueryString();

        return view('expenses.index', ['expenses' => $expenses, 'total' => Expense::sum('amount')]);
    }

    public function create(): View
    {
        return view('expenses.create');
    }

    public function store(ExpenseRequest $request, JournalService $journal): RedirectResponse
    {
        DB::transaction(function () use ($request, $journal) {
            $expense = Expense::create($request->validated() + ['user_id' => $request->user()->id]);
            $journal->postExpense($expense);
        });

        return redirect()->route('pengeluaran.index')->with('success', 'Pengeluaran dicatat dan dijurnal.');
    }

    public function destroy(Expense $expense, JournalService $journal): RedirectResponse
    {
        DB::transaction(function () use ($expense, $journal) {
            $journal->reverse($expense, auth()->id());
            $expense->delete();
        });

        return redirect()->route('pengeluaran.index')->with('success', 'Pengeluaran dihapus (jurnal dibalik).');
    }
}
