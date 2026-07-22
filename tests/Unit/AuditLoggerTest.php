<?php

namespace Tests\Unit;

use App\Services\AuditLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AuditLoggerTest extends TestCase
{
    #[Test]
    public function it_recursively_redacts_credentials_without_changing_safe_values(): void
    {
        $values = AuditLogger::redact([
            'email' => 'person@example.test',
            'password' => 'plain-text',
            'nested' => [
                'accessToken' => 'token-value',
                'profile' => ['name' => 'Person', 'client_secret' => 'secret-value'],
            ],
        ]);

        $this->assertSame('person@example.test', $values['email']);
        $this->assertSame('[REDACTED]', $values['password']);
        $this->assertSame('[REDACTED]', $values['nested']['accessToken']);
        $this->assertSame('Person', $values['nested']['profile']['name']);
        $this->assertSame('[REDACTED]', $values['nested']['profile']['client_secret']);
    }
}
