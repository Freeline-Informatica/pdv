<?php

namespace Freeline\Pdv\Http\Middleware;

use Freeline\Pdv\Models\ApiToken;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken()
            ?: trim((string) $request->query('access_token', ''));

        if (! $plainToken) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $token = ApiToken::with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Token inválido ou expirado.'], 401);
        }

        if (! $token->user) {
            return response()->json(['message' => 'Token inválido ou expirado.'], 401);
        }

        $grupoEmpresarialId = $token->grupo_empresarial_id ? (int) $token->grupo_empresarial_id : null;
        $estabelecimentoId = $token->estabelecimento_id ? (int) $token->estabelecimento_id : null;

        if (method_exists($token->user, 'canAccessPdv') && ! $token->user->canAccessPdv($grupoEmpresarialId, $estabelecimentoId)) {
            return response()->json(['message' => 'Usuário sem permissão de acesso ao PDV.'], 403);
        }

        $token->forceFill(['last_used_at' => Carbon::now()])->save();

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);
        $request->attributes->set('grupo_empresarial_id', $grupoEmpresarialId);
        $request->attributes->set('estabelecimento_id', $estabelecimentoId);

        return $next($request);
    }
}
