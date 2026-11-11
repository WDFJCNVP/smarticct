<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the security headers reported by OWASP ZAP.
 * Same file is used by the website AND the kiosk (copy it to both projects).
 * All switches live in config/security.php (driven by .env).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Optional: send every plain-HTTP request to HTTPS (needs trusted proxies on Heroku).
        if (config('security.force_https') && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        // Create a per-request nonce BEFORE the views render so @vite / Livewire can use it.
        $mode  = config('security.csp.mode', 'off'); // off | report-only | enforce
        $nonce = $mode !== 'off' ? Vite::useCspNonce() : null;

        /** @var Response $response */
        $response = $next($request);
        $headers  = $response->headers;

        // Missing Anti-clickjacking Header
        // (sent separately because CSP frame-ancestors is ignored in report-only mode)
        $headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options Missing
        $headers->set('X-Content-Type-Options', 'nosniff');

        // HSTS (only meaningful on HTTPS responses)
        if (config('security.hsts.enabled') && $request->isSecure()) {
            $value = 'max-age=' . (int) config('security.hsts.max_age');
            if (config('security.hsts.include_subdomains')) {
                $value .= '; includeSubDomains';
            }
            $headers->set('Strict-Transport-Security', $value);
        }

        // Server Leaks X-Powered-By
        $headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        // Content Security Policy
        if ($mode !== 'off') {
            $name = $mode === 'enforce'
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $headers->set($name, $this->buildCsp($nonce, $mode === 'enforce'));
        }

        // Do not let the browser cache pages that show balances / card data
        // (important on a shared kiosk). Skip assets that Laravel serves as public.
        if (config('security.no_store') && ! str_contains((string) $headers->get('Cache-Control'), 'public')) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function buildCsp(?string $nonce, bool $enforce): string
    {
        $cfg = config('security.csp');

        $script = ["'self'", "'nonce-{$nonce}'", "'strict-dynamic'"];
        if ($cfg['unsafe_eval']) {
            $script[] = "'unsafe-eval'"; // Alpine expressions. Remove after enabling livewire csp_safe.
        }

        $connect = array_merge(["'self'"], $cfg['connect']);
        if ($ws = $this->reverbOrigin()) {
            $connect[] = $ws;
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src'  => $script,
            'style-src'   => array_merge(["'self'", "'unsafe-inline'"], $cfg['style_hosts']),
            'font-src'    => array_merge(["'self'", 'data:'], $cfg['style_hosts']),
            'img-src'     => $cfg['img'],
            'connect-src' => $connect,
            'object-src'  => ["'none'"],
            'base-uri'    => ["'self'"],
        ];

        if ($enforce) {
            $directives['frame-ancestors'] = ["'none'"];
        }

        return collect($directives)
            ->map(fn ($values, $name) => $name . ' ' . implode(' ', array_unique($values)))
            ->implode('; ');
    }

    /** wss://reverb-host[:port] so Laravel Echo can connect. */
    private function reverbOrigin(): ?string
    {
        $host = config('security.reverb.host');
        if (! $host) {
            return null;
        }

        $scheme = config('security.reverb.scheme') === 'http' ? 'ws' : 'wss';
        $port   = (int) config('security.reverb.port');
        $suffix = $port && ! in_array($port, [80, 443], true) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$suffix}";
    }
}