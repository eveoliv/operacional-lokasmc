<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\AccessGrant;
use App\Services\AuditLogger;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $auditLogger): void {
            $activeGrants = AccessGrant::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            $hasRootGrant = $activeGrants->contains(fn (AccessGrant $grant): bool => $grant->role()->where('code', 'ROOT_ADMIN')->exists());

            if ($hasRootGrant) {
                $otherRootAdministratorExists = AccessGrant::query()
                    ->where('user_id', '!=', $user->getKey())
                    ->whereNull('revoked_at')
                    ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->whereHas('user', fn ($query) => $query->whereNull('disabled_at'))
                    ->whereHas('role', fn ($query) => $query->where('code', 'ROOT_ADMIN')->where('is_active', true))
                    ->lockForUpdate()
                    ->exists();

                if (! $otherRootAdministratorExists) {
                    throw ValidationException::withMessages([
                        'password' => 'O último administrador raiz não pode desativar a própria conta.',
                    ]);
                }
            }

            $now = now();
            AccessGrant::query()
                ->whereKey($activeGrants->modelKeys())
                ->update([
                    'revoked_at' => $now,
                    'revoked_by_user_id' => $user->getKey(),
                    'revocation_reason' => 'Conta desativada pelo próprio usuário.',
                ]);

            $user->forceFill([
                'disabled_at' => $now,
                'disabled_reason' => 'Conta desativada pelo próprio usuário.',
            ])->save();

            $auditLogger->log(
                'user.disabled',
                $user,
                $user,
                newValues: ['disabled_at' => $now->toISOString()],
                metadata: ['source' => 'profile.account_deletion', 'revoked_grants' => $activeGrants->count()],
            );
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
