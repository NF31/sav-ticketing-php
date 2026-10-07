<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    private mixed $appUrlInitial;

    protected function setUp(): void
    {
        $this->appUrlInitial = $_ENV['APP_URL'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->appUrlInitial === null) {
            unset($_ENV['APP_URL']);
        } else {
            $_ENV['APP_URL'] = $this->appUrlInitial;
        }
    }

    public function testLesProtectionsDeBaseSontToujoursPresentes(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8090';
        $entetes = SecurityHeaders::liste();

        self::assertStringContainsString("frame-ancestors 'none'", $entetes['Content-Security-Policy']);
        self::assertStringContainsString("default-src 'self'", $entetes['Content-Security-Policy']);
        self::assertSame('DENY', $entetes['X-Frame-Options']);
        self::assertSame('nosniff', $entetes['X-Content-Type-Options']);
        self::assertSame('same-origin', $entetes['Referrer-Policy']);
    }

    public function testHstsUniquementEnHttps(): void
    {
        $_ENV['APP_URL'] = 'http://localhost:8090';
        self::assertArrayNotHasKey('Strict-Transport-Security', SecurityHeaders::liste());

        $_ENV['APP_URL'] = 'https://sav.example.com';
        self::assertArrayHasKey('Strict-Transport-Security', SecurityHeaders::liste());
    }
}
