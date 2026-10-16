<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em todas as respostas (achados do teste de invasão
 * de 16/10/2026: HSTS, anti-clickjacking, nosniff, CSP, Permissions-Policy, CORP).
 *
 * A CSP libera 'unsafe-inline'/'unsafe-eval' em scripts porque o Livewire e o
 * Alpine (base do Filament) dependem disso; o ganho está em travar as origens
 * (só o próprio hub e as fontes) e proibir o painel dentro de iframe alheio.
 */
class SecurityHeaders
{
    public const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net; "
        ."font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net; "
        ."img-src 'self' data: blob: https://ui-avatars.com; "
        ."connect-src 'self'; "
        ."frame-ancestors 'self'; "
        ."form-action 'self'; "
        ."base-uri 'self'; "
        ."object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', self::CSP);
        }

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
