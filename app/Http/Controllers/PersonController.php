<?php

namespace App\Http\Controllers;

use App\Enums\PermissionCode;
use App\Enums\PersonStatus;
use App\Http\Requests\People\ArchivePersonRequest;
use App\Http\Requests\People\StorePersonRequest;
use App\Http\Requests\People\UpdatePersonRequest;
use App\Models\Person;
use App\Services\AuditLogger;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', Person::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::PeopleView);

        $people = Person::query()->with(['organizationalUnit:id,name', 'user:id,name,email'])
            ->whereIn('organizational_unit_id', $unitIds)
            ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('archived_at'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->toString()).'%';
                $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('document', 'like', $search));
            })
            ->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('people/Index', [
            'people' => $people,
            'filters' => $request->only(['search', 'archived']),
            'manageableUnitIds' => $authorizer->accessIds($request->user(), PermissionCode::PeopleManage),
        ]);
    }

    public function store(StorePersonRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $person = Person::query()->create($request->validated());
            $audit->log('person.created', $person, $request->user(), $person->organizationalUnit, newValues: $person->only(['organizational_unit_id', 'user_id', 'name', 'email', 'phone', 'document', 'birth_date', 'status']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pessoa criada.']);

        return back();
    }

    public function update(UpdatePersonRequest $request, Person $person, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $person, $audit): void {
            $old = $person->only(['organizational_unit_id', 'user_id', 'name', 'email', 'phone', 'document', 'birth_date', 'status']);
            $person->update($request->validated());
            $audit->log('person.updated', $person, $request->user(), $person->organizationalUnit, $old, $person->only(array_keys($old)));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pessoa atualizada.']);

        return back();
    }

    public function archive(ArchivePersonRequest $request, Person $person, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $person, $audit): void {
            $old = $person->only(['status', 'archived_at']);
            $person->forceFill(['status' => PersonStatus::Archived, 'archived_at' => now()])->save();
            $audit->log('person.archived', $person, $request->user(), $person->organizationalUnit, $old, $person->only(['status', 'archived_at']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pessoa arquivada.']);

        return back();
    }
}
