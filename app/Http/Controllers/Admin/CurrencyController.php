<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('currencies.index', ['currencies' => Currency::orderByDesc('is_base')->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('currencies.form', ['currency' => new Currency(['decimals' => 2, 'rate' => 1, 'is_active' => true])]);
    }

    public function store(CurrencyRequest $request): RedirectResponse
    {
        Currency::create($request->validated());

        return redirect()->route('mata-uang.index')->with('success', 'Mata uang ditambahkan.');
    }

    public function edit(Currency $currency): View
    {
        return view('currencies.form', compact('currency'));
    }

    public function update(CurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $data = $request->validated();

        if ($currency->is_base) {
            // Mata uang dasar selalu kurs 1 dan aktif.
            $data['rate'] = 1;
            $data['is_active'] = true;
            unset($data['code']);
        }

        $currency->update($data);

        return redirect()->route('mata-uang.index')->with('success', 'Mata uang diperbarui.');
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_base) {
            return back()->with('error', 'Mata uang dasar tidak dapat dihapus.');
        }

        $currency->delete();

        return redirect()->route('mata-uang.index')->with('success', 'Mata uang dihapus.');
    }

    /** Dipakai semua pengguna: memilih mata uang tampilan (tersimpan di session). */
    public function select(Request $request, CurrencyService $service): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:3']]);

        $service->select(strtoupper($data['code']));

        return back();
    }
}
