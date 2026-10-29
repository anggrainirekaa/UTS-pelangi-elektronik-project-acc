<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi Bearer token untuk REST API.
 * Pemakaian: middleware 'api.token:mobile' atau 'api.token:erp'.
 * Token disimpan sebagai hash sha256; format token "id|secret".
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $type = 'mobile'): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findFromPlain($plain, $type) : null;

        if (! $token || ! $token->user) {
            return response()->json(['message' => 'Token tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
