<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('staff.viewAudit');

        $rows = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->query('action')).'%'))
            ->when($request->filled('actor'), function ($q) use ($request) {
                $term = $request->query('actor');
                $ids = User::where('email', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('id', (int) $term)->pluck('id');
                $q->where(fn ($w) => $w->whereIn('actor_id', $ids)->orWhereIn('impersonator_id', $ids));
            })
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_type', $request->query('subject')))
            ->when($request->boolean('acting'), fn ($q) => $q->whereNotNull('impersonator_id'))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('id')->paginate(50)->withQueryString();

        $users = User::whereIn('id', $rows->pluck('actor_id')->merge($rows->pluck('impersonator_id'))->filter()->unique())->pluck('name', 'id');

        return view('admin.audit', [
            'rows' => $rows, 'users' => $users, 'filters' => $request->query(),
            'subjects' => AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
