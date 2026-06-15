<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = trim((string) config('services.external_integration.token', ''));
        $givenToken = trim((string) $request->bearerToken());

        if ($expectedToken === '' || $givenToken === '' || !hash_equals($expectedToken, $givenToken)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return $next($request);
    }
}
