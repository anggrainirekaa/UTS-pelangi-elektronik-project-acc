<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnomalyFlag;
use App\Services\AnomalyDetector;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Temuan anomali hanya ditandai & ditinjau; tidak ada penghapusan/perubahan transaksi otomatis. */
class AnomalyController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'reviewed', 'dismissed'], true) ? $request->query('status') : 'open';

        return view('anomalies.index', [
            'flags' => AnomalyFlag::with('reviewer')->where('status', $status)->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->latest('detected_at')->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => AnomalyFlag::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function scan(AnomalyDetector $detector): RedirectResponse
    {
        $new = $detector->scan();

        return back()->with('success', $new > 0 ? "Pemindaian selesai: {$new} temuan baru." : 'Pemindaian selesai: tidak ada temuan baru.');
    }

    public function review(Request $request, AnomalyFlag $flag): RedirectResponse
    {
        $v = $request->validate([
            'status' => ['required', 'in:reviewed,dismissed'],
            'note' => ['required', 'string', 'max:500'],
        ], ['note.required' => 'Catatan tinjauan wajib diisi.']);

        $flag->update(['status' => $v['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $v['note']]);
        AuditLogger::log('anomaly.reviewed', "Anomali \"{$flag->title}\" ditandai {$v['status']}: {$v['note']}", $flag);

        return back()->with('success', 'Tinjauan anomali disimpan.');
    }
}
