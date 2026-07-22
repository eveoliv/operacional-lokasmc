<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'authorization',
        'cookie',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'credential',
        'credentials',
        'private_key',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        Model|string $auditable,
        ?User $actor = null,
        ?OrganizationalUnit $scope = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey() ?? $this->request->user()?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $auditable instanceof Model ? $auditable->getMorphClass() : $auditable,
            'auditable_id' => $auditable instanceof Model && $auditable->exists ? $auditable->getKey() : null,
            'organizational_unit_id' => $scope?->getKey(),
            'correlation_id' => $this->request->header('X-Correlation-ID') ?? (string) Str::uuid(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'old_values' => self::redact($oldValues),
            'new_values' => self::redact($newValues),
            'metadata' => self::redact($metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function redact(array $values): array
    {
        return Arr::map($values, function (mixed $value, string|int $key): mixed {
            if (is_string($key) && self::isSensitive($key)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                return self::redact($value);
            }

            return $value;
        });
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = Str::of($key)->snake()->lower()->toString();

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || Str::contains($normalized, ['password', 'credential', 'secret', 'token']);
    }
}
