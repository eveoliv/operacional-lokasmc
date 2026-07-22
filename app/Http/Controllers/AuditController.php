<?php

namespace App\Http\Controllers;

use App\Enums\PermissionCode;
use App\Models\AuditLog;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', AuditLog::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::AuditView);

        $logs = AuditLog::query()->with(['actor:id,name,email', 'organizationalUnit:id,name'])
            ->whereIn('organizational_unit_id', $unitIds)
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('actor'), function ($query) use ($request): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('actor')->toString()).'%';
                $query->whereHas('actor', fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return Inertia::render('audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'actor']),
            'actions' => AuditLog::query()->whereIn('organizational_unit_id', $unitIds)
                ->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
