<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_ping_reports_the_resolved_locale(): void
    {
        // Symfony's test request always carries an `en-us` Accept-Language
        // header, so the locale is asserted explicitly rather than by default.
        $this->getJson('/api/v1/ping', ['Accept-Language' => 'vi-VN,vi;q=0.9'])
            ->assertOk()
            ->assertJson(['ok' => true, 'locale' => 'vi'])
            ->assertHeader('Content-Language', 'vi');
    }

    public function test_unsupported_accept_language_falls_back_to_the_default(): void
    {
        $this->getJson('/api/v1/ping', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->assertOk()
            ->assertJson(['locale' => 'vi']);
    }

    public function test_locale_query_parameter_wins(): void
    {
        $this->getJson('/api/v1/ping?locale=en')
            ->assertOk()
            ->assertJson(['locale' => 'en']);
    }

    public function test_accept_language_header_is_honoured(): void
    {
        $this->getJson('/api/v1/ping', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertJson(['locale' => 'en']);
    }

    public function test_unsupported_locale_falls_back_to_the_default(): void
    {
        $this->getJson('/api/v1/ping?locale=fr')
            ->assertOk()
            ->assertJson(['locale' => 'vi']);
    }

    public function test_guests_receive_json_401_rather_than_a_login_redirect(): void
    {
        $this->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
