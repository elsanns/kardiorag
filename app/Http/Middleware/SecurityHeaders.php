<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits baseline security headers (NIS2/KSC hardening). CSP is "Option A": a tight policy
 * with a per-request nonce for our single inline <script>, and 'unsafe-inline' allowed only
 * for styles (inline style attributes are low-risk and can't exfiltrate). Self-hosted assets
 * mean no external origins need allowlisting.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate the CSP nonce before the view renders so Blade can stamp it on the script.
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-{$nonce}'",
            "connect-src 'self'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Frame-Options'         => 'DENY',
            'X-Content-Type-Options'  => 'nosniff',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'geolocation=(), camera=(), microphone=()',
        ];

        // HSTS is only meaningful over TLS; sending it on plain HTTP is ignored, so scope it.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
