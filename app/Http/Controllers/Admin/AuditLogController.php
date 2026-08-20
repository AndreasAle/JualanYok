<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::with('user:id,name,username')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->query('action').'%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'actor' => $l->user?->name ?? 'Sistem',
                'subject' => $l->auditable_type
                    ? class_basename($l->auditable_type).'#'.$l->auditable_id
                    : null,
                'reason' => $l->reason,
                'before' => $l->before,
                'after' => $l->after,
                'ip_address' => $l->ip_address,
                'created_at' => $l->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'user_id']),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
