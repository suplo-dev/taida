<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * In production PHP-FPM only ever talks to nginx over loopback, so the request
 * looks like plain HTTP unless the forwarding headers are trusted. Get this
 * wrong and SESSION_SECURE_COOKIE quietly drops every session cookie — login
 * succeeds and the next request is a 401.
 */
class TrustedProxyTest extends TestCase
{
    public function test_forwarded_proto_from_a_trusted_proxy_makes_the_request_secure(): void
    {
        $this->get('/api/v1/ping', ['X-Forwarded-Proto' => 'https']);

        $this->assertTrue(request()->isSecure());
        $this->assertSame('https', request()->getScheme());
    }

    public function test_forwarded_host_from_a_trusted_proxy_is_honoured(): void
    {
        $this->get('/api/v1/ping', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'api.taida.vn',
        ]);

        $this->assertSame('https://api.taida.vn', request()->getSchemeAndHttpHost());
    }

    public function test_forwarded_headers_from_an_untrusted_address_are_ignored(): void
    {
        // Only loopback is trusted by default; anything else could be a client
        // spoofing the header to fake an HTTPS origin.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/api/v1/ping', ['X-Forwarded-Proto' => 'https']);

        $this->assertFalse(request()->isSecure());
    }
}
