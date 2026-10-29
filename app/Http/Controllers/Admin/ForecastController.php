<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function index(Request $request, ForecastService $forecast): View
    {
        $data = $request->validate([
            'history' => ['nullable', 'integer', 'in:28,56,84,168'],
            'horizon' => ['nullable', 'integer', 'in:14,28,56'],
            'only_reorder' => ['nullable', 'boolean'],
        ]);

        $history = (int) ($data['history'] ?? 84);
        $horizon = (int) ($data['horizon'] ?? 28);

        $rows = $forecast->forecast($history, $horizon);

        if ($request->boolean('only_reorder')) {
            $rows = $rows->filter(fn ($r) => $r['reorder_qty'] > 0)->values();
        }

        return view('reports.forecast', compact('rows', 'history', 'horizon'));
    }
}
