<?php

namespace App\Providers;

use App\Models\AccessGrant;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\OrganizationalUnit;
use App\Models\Person;
use App\Models\Registration;
use App\Models\User;
use App\Policies\AccessGrantPolicy;
use App\Policies\AttendanceRecordPolicy;
use App\Policies\AttendanceSessionPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\EventPolicy;
use App\Policies\OrganizationalUnitPolicy;
use App\Policies\PersonPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AccessGrant::class, AccessGrantPolicy::class);
        Gate::policy(AttendanceRecord::class, AttendanceRecordPolicy::class);
        Gate::policy(AttendanceSession::class, AttendanceSessionPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(OrganizationalUnit::class, OrganizationalUnitPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Registration::class, RegistrationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
