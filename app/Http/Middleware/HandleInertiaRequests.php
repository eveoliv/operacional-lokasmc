<?php

namespace App\Http\Middleware;

use App\Enums\PermissionCode;
use App\Services\ScopeAuthorizer;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => function () use ($user): ?array {
                    if ($user === null) {
                        return null;
                    }

                    $authorizer = app(ScopeAuthorizer::class);
                    $capabilities = collect(PermissionCode::cases())
                        ->filter(fn (PermissionCode $permission): bool => $authorizer->accessIds($user, $permission)->isNotEmpty())
                        ->map(fn (PermissionCode $permission): string => $permission->value)
                        ->values()->all();

                    return [
                        ...$user->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
                        'capabilities' => $capabilities,
                    ];
                },
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
