<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Services\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class AutoReportController extends Controller
{
    public function index(Request $request, ReportBuilder $reports): View
    {
        $period = $request->validate(['period' => ['nullable', 'in:daily,weekly,monthly']])['period'] ?? 'daily';

        return view('reports.auto', ['report' => $reports->build($period), 'period' => $period, 'periods' => ReportBuilder::PERIODS]);
    }

    public function export(Request $request, ReportBuilder $reports)
    {
        $v = $request->validate(['period' => ['nullable', 'in:daily,weekly,monthly'], 'format' => ['nullable', 'in:csv,xlsx']]);
        $report = $reports->build($v['period'] ?? 'daily');
        [$headings, $rows] = $reports->rows($report);
        $ext = $v['format'] ?? 'csv';
        $name = 'laporan-'.$report['period'].'-'.now()->format('Y-m-d').'.'.$ext;

        return Excel::download(new ArrayExport($headings, $rows), $name, $ext === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }
}
