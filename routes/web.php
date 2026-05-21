<?php

use Freeline\Pdv\Models\ApiToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

Route::get('/{any?}', function () {
    $bootstrap = null;
    $user = auth()->user();

    if ((bool) config('pdv.web_session_auth', false) && $user) {
        $plainToken = Str::random(64);

        $tokenPayload = [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(7),
        ];

        if (Schema::hasColumn('api_tokens', 'grupo_empresarial_id')) {
            $tokenPayload['grupo_empresarial_id'] = session('grupo_empresarial_id');
        }

        if (Schema::hasColumn('api_tokens', 'estabelecimento_id')) {
            $tokenPayload['estabelecimento_id'] = session('estabelecimento_id');
        }

        ApiToken::query()->create($tokenPayload);

        $grupoEmpresarialId = $tokenPayload['grupo_empresarial_id'] ?? null;
        $estabelecimentoId = $tokenPayload['estabelecimento_id'] ?? null;
        $hasOnlyPdvAccess = method_exists($user, 'hasOnlyPdvAccess')
            ? $user->hasOnlyPdvAccess(
                $grupoEmpresarialId ? (int) $grupoEmpresarialId : null,
                $estabelecimentoId ? (int) $estabelecimentoId : null,
            )
            : false;

        $bootstrap = [
            'token' => $plainToken,
            'context' => [
                'grupo_empresarial_id' => $grupoEmpresarialId,
                'estabelecimento_id' => $estabelecimentoId,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? 'admin' : 'operador',
                'can_access_erp' => ! $hasOnlyPdvAccess,
            ],
        ];
    }

    return view('pdv::app', [
        'bootstrap' => $bootstrap,
    ]);
})->where('any', '.*');
