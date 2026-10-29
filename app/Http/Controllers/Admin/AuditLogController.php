<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $v = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $logs = AuditLog::with('user')
            ->when($v['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")->orWhere('event', 'like', "%{$s}%")))
            ->when($v['user_id'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($v['start_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($v['end_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')->paginate(20)->withQueryString();

        return view('audit.index', ['logs' => $logs, 'users' => User::whereIn('role', ['owner', 'staff'])->orderBy('name')->get(['id', 'name'])]);
    }
}
