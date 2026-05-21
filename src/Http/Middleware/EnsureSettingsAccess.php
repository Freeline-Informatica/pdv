<?php

namespace Freeline\Pdv\Http\Middleware;

use Closure;
use Freeline\Pdv\Contracts\OperatorResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSettingsAccess
{
    public function __construct(private readonly OperatorResolver $operators)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $apiToken = $request->attributes->get('api_token');

        if (! $user || ! $apiToken) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if ($this->operators->isAdmin($user)) {
            return $next($request);
        }

        $accessKey = $request->header('X-Settings-Access');
        $tokenHash = is_string($accessKey) ? hash('sha256', $accessKey) : null;
        $expiresAt = $apiToken->settings_access_expires_at;

        if (
            ! $tokenHash ||
            ! $apiToken->settings_access_hash ||
            $apiToken->settings_access_hash !== $tokenHash ||
            ! $expiresAt ||
            $expiresAt->isPast()
        ) {
            return response()->json([
                'message' => 'Autorização de administrador necessária para acessar as configurações.',
                'code' => 'settings_authorization_required',
            ], 403);
        }

        return $next($request);
    }
}
