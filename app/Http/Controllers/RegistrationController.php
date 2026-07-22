<?php

namespace App\Http\Controllers;

use App\Actions\CancelRegistration;
use App\Actions\RegisterPersonForEvent;
use App\Enums\PermissionCode;
use App\Enums\RegistrationSource;
use App\Http\Requests\Registrations\CancelRegistrationRequest;
use App\Http\Requests\Registrations\StoreRegistrationRequest;
use App\Models\Event;
use App\Models\Person;
use App\Models\Registration;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', Registration::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::RegistrationsView);

        $registrations = Registration::query()
            ->with(['event:id,title,organizational_unit_id', 'person:id,name,organizational_unit_id'])
            ->whereHas('event', fn ($query) => $query->whereIn('organizational_unit_id', $unitIds))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('registrations/Index', [
            'registrations' => $registrations,
            'filters' => $request->only('status'),
        ]);
    }

    public function store(StoreRegistrationRequest $request, Event $event, RegisterPersonForEvent $action): RedirectResponse
    {
        $person = Person::query()->findOrFail($request->integer('person_id'));
        $source = RegistrationSource::tryFrom($request->string('source')->toString()) ?? RegistrationSource::Operator;
        $action->handle($event, $person, $request->user(), $source);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inscrição realizada.']);

        return back();
    }

    public function cancel(CancelRegistrationRequest $request, Event $event, Registration $registration, CancelRegistration $action): RedirectResponse
    {
        $action->handle($event, $registration, $request->user(), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inscrição cancelada.']);

        return back();
    }
}
