<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Ask about cardiac drugs');
    }

    public function test_dashboard_renders(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_security_headers_are_present(): void
    {
        $res = $this->get('/');

        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'self'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('nonce-', $res->headers->get('Content-Security-Policy'));
    }
}
