<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya terapkan CSP header pada response HTML (web pages)
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html') || empty($contentType)) {
            $csp = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-eval' 'unsafe-inline' https://app.midtrans.com https://app.sandbox.midtrans.com https://snap-assets.al-j.co https://*.midtrans.com",
                "script-src-elem 'self' 'unsafe-inline' https://app.midtrans.com https://app.sandbox.midtrans.com https://snap-assets.al-j.co https://*.midtrans.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com data:",
                "img-src 'self' data: blob: https://*.midtrans.com https://app.midtrans.com https://app.sandbox.midtrans.com https://api.qrserver.com https://maps.google.com https://*.google.com https://*.googleapis.com https://upload.wikimedia.org https://images.unsplash.com",
                "connect-src 'self' https://app.midtrans.com https://app.sandbox.midtrans.com https://*.midtrans.com https://api.midtrans.com https://api.sandbox.midtrans.com https://snap-assets.al-j.co https://generativelanguage.googleapis.com",
                "frame-src 'self' https://app.midtrans.com https://app.sandbox.midtrans.com https://*.midtrans.com https://maps.google.com https://www.google.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ];

            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        return $response;
    }
}
