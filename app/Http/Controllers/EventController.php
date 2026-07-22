<?php

namespace App\Http\Controllers;

use App\Actions\CreateEvent;
use App\Actions\TransitionEventStatus;
use App\Actions\UpdateEvent;
use App\Enums\EventStatus;
use App\Enums\PermissionCode;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\TransitionEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Event;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request, ScopeAuthorizer $authorizer): Response
    {
        $this->authorize('viewAny', Event::class);
        $unitIds = $authorizer->accessIds($request->user(), PermissionCode::EventsView);
        $status = EventStatus::tryFrom($request->string('status')->toString());

        $events = Event::query()->with(['organizationalUnit:id,name', 'audiences.organizationalUnit:id,name'])
            ->withinUnits($unitIds)
            ->when(! $request->boolean('archived'), fn ($query) => $query->notArchived())
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->toString()).'%';
                $query->where(fn ($query) => $query->where('title', 'like', $search)->orWhere('description', 'like', $search));
            })
            ->orderBy('starts_at')->orderBy('title')->paginate(25)->withQueryString();

        return Inertia::render('events/Index', [
            'events' => $events,
            'filters' => $request->only(['search', 'status', 'archived']),
            'statuses' => array_column(EventStatus::cases(), 'value'),
            'manageableUnitIds' => $authorizer->accessIds($request->user(), PermissionCode::EventsManage),
        ]);
    }

    public function store(StoreEventRequest $request, CreateEvent $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento criado.']);

        return back();
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $action): RedirectResponse
    {
        $action->handle($request->user(), $event, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evento atualizado.']);

        return back();
    }

    public function transition(TransitionEventRequest $request, Event $event, TransitionEventStatus $action): RedirectResponse
    {
        $action->handle($request->user(), $event, EventStatus::from($request->validated('status')));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status do evento atualizado.']);

        return back();
    }
}
