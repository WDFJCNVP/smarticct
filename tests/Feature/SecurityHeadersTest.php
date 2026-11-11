<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_guest_page_has_security_headers(): void
    {
        config([
            'security.csp.mode'      => 'enforce',
            'security.hsts.enabled'  => true,
            'security.force_https'   => false,
        ]);

        $response = $this->get('https://localhost/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeaderMissing('X-Powered-By');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_report_only_mode_uses_report_only_header(): void
    {
        config(['security.csp.mode' => 'report-only']);

        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeader('X-Frame-Options', 'DENY'); // still protected while testing CSP
    }
}