<?php

namespace Freeline\Pdv\Http\Middleware;

use Freeline\Pdv\Services\AuditLogger;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditTrail
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldRecord($request, $response)) {
            return $response;
        }

        $operator = $request->user();
        if (! $operator instanceof Authenticatable) {
            return $response;
        }

        $action = $this->resolveAction($request);
        $entityId = $this->resolveEntityId($request, $response);

        $this->auditLogger->record(
            operator: $operator,
            actionKey: $action['key'],
            actionLabel: $action['label'],
            entity: $action['entity'],
            details: $this->buildDetails($request, $operator),
            entityId: $entityId,
            meta: [
                'method' => strtoupper($request->method()),
                'path' => $this->normalizedPath($request),
                'status' => $response->getStatusCode(),
                'request' => $this->sanitizePayload($request->all()),
            ],
        );

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        $method = strtoupper($request->method());
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        return $response->getStatusCode() < 400;
    }

    private function resolveAction(Request $request): array
    {
        $method = strtoupper($request->method());
        $path = $this->normalizedPath($request);

        return match (true) {
            $method === 'POST' && $path === 'auth/logout' => [
                'key' => 'auth.logout',
                'label' => 'Logout Operador',
                'entity' => 'Operador',
            ],
            $method === 'POST' && $path === 'auth/settings/authorize' => [
                'key' => 'auth.settings.authorize',
                'label' => 'Autorização Configurações',
                'entity' => 'Segurança',
            ],
            $method === 'POST' && $path === 'auth/cancel/authorize' => [
                'key' => 'auth.cancel.authorize',
                'label' => 'Autorização Cancelamento',
                'entity' => 'Segurança',
            ],
            $method === 'PUT' && $path === 'settings/company' => [
                'key' => 'settings.company.update',
                'label' => 'Atualizar Empresa',
                'entity' => 'Empresa',
            ],
            $method === 'PUT' && $path === 'settings/fiscal' => [
                'key' => 'settings.fiscal.update',
                'label' => 'Atualizar Fiscal',
                'entity' => 'Fiscal',
            ],
            $method === 'PUT' && $path === 'settings/certificate' => [
                'key' => 'settings.certificate.update',
                'label' => 'Atualizar Certificado',
                'entity' => 'Certificado',
            ],
            $method === 'POST' && $path === 'operators' => [
                'key' => 'operators.create',
                'label' => 'Cadastro Operador',
                'entity' => 'Operador',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^operators/[^/]+$#', $path) === 1 => [
                'key' => 'operators.update',
                'label' => 'Atualizar Operador',
                'entity' => 'Operador',
            ],
            $method === 'POST' && $path === 'categories' => [
                'key' => 'categories.create',
                'label' => 'Cadastro Categoria',
                'entity' => 'Categoria',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^categories/[^/]+$#', $path) === 1 => [
                'key' => 'categories.update',
                'label' => 'Atualizar Categoria',
                'entity' => 'Categoria',
            ],
            $method === 'DELETE' && preg_match('#^categories/[^/]+$#', $path) === 1 => [
                'key' => 'categories.delete',
                'label' => 'Remover Categoria',
                'entity' => 'Categoria',
            ],
            $method === 'POST' && $path === 'products' => [
                'key' => 'products.create',
                'label' => 'Cadastro Produto',
                'entity' => 'Produto',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^products/[^/]+$#', $path) === 1 => [
                'key' => 'products.update',
                'label' => 'Atualizar Produto',
                'entity' => 'Produto',
            ],
            $method === 'DELETE' && preg_match('#^products/[^/]+$#', $path) === 1 => [
                'key' => 'products.delete',
                'label' => 'Remover Produto',
                'entity' => 'Produto',
            ],
            $method === 'POST' && $path === 'payment-methods' => [
                'key' => 'payment_methods.create',
                'label' => 'Cadastro Meio de Pagamento',
                'entity' => 'Meio de Pagamento',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^payment-methods/[^/]+$#', $path) === 1 => [
                'key' => 'payment_methods.update',
                'label' => 'Atualizar Meio de Pagamento',
                'entity' => 'Meio de Pagamento',
            ],
            $method === 'DELETE' && preg_match('#^payment-methods/[^/]+$#', $path) === 1 => [
                'key' => 'payment_methods.delete',
                'label' => 'Remover Meio de Pagamento',
                'entity' => 'Meio de Pagamento',
            ],
            $method === 'POST' && $path === 'payment-plans' => [
                'key' => 'payment_plans.create',
                'label' => 'Cadastro Plano de Pagamento',
                'entity' => 'Plano de Pagamento',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^payment-plans/[^/]+$#', $path) === 1 => [
                'key' => 'payment_plans.update',
                'label' => 'Atualizar Plano de Pagamento',
                'entity' => 'Plano de Pagamento',
            ],
            $method === 'DELETE' && preg_match('#^payment-plans/[^/]+$#', $path) === 1 => [
                'key' => 'payment_plans.delete',
                'label' => 'Remover Plano de Pagamento',
                'entity' => 'Plano de Pagamento',
            ],
            $method === 'POST' && $path === 'acquirers' => [
                'key' => 'acquirers.create',
                'label' => 'Cadastro Adquirente',
                'entity' => 'Adquirente',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^acquirers/[^/]+$#', $path) === 1 => [
                'key' => 'acquirers.update',
                'label' => 'Atualizar Adquirente',
                'entity' => 'Adquirente',
            ],
            $method === 'DELETE' && preg_match('#^acquirers/[^/]+$#', $path) === 1 => [
                'key' => 'acquirers.delete',
                'label' => 'Remover Adquirente',
                'entity' => 'Adquirente',
            ],
            $method === 'POST' && preg_match('#^acquirers/[^/]+/terminals$#', $path) === 1 => [
                'key' => 'terminals.create',
                'label' => 'Cadastro Terminal',
                'entity' => 'Terminal',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^terminals/[^/]+$#', $path) === 1 => [
                'key' => 'terminals.update',
                'label' => 'Atualizar Terminal',
                'entity' => 'Terminal',
            ],
            $method === 'DELETE' && preg_match('#^terminals/[^/]+$#', $path) === 1 => [
                'key' => 'terminals.delete',
                'label' => 'Remover Terminal',
                'entity' => 'Terminal',
            ],
            $method === 'POST' && preg_match('#^terminals/[^/]+/rates$#', $path) === 1 => [
                'key' => 'terminal_rates.create',
                'label' => 'Cadastro Taxa de Terminal',
                'entity' => 'Taxa',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^rates/[^/]+$#', $path) === 1 => [
                'key' => 'terminal_rates.update',
                'label' => 'Atualizar Taxa de Terminal',
                'entity' => 'Taxa',
            ],
            $method === 'DELETE' && preg_match('#^rates/[^/]+$#', $path) === 1 => [
                'key' => 'terminal_rates.delete',
                'label' => 'Remover Taxa de Terminal',
                'entity' => 'Taxa',
            ],
            in_array($method, ['PUT', 'PATCH'], true) && preg_match('#^terminals/[^/]+/tef$#', $path) === 1 => [
                'key' => 'tef.update',
                'label' => 'Atualizar Integração TEF',
                'entity' => 'TEF',
            ],
            $method === 'DELETE' && preg_match('#^tef/[^/]+$#', $path) === 1 => [
                'key' => 'tef.delete',
                'label' => 'Remover Integração TEF',
                'entity' => 'TEF',
            ],
            default => $this->fallbackAction($method, $path),
        };
    }

    private function fallbackAction(string $method, string $path): array
    {
        $segment = Str::of($path)->before('/')->replace('-', '_')->toString();
        $entity = $this->segmentToEntity($segment);

        return match ($method) {
            'POST' => ['key' => $segment.'.create', 'label' => 'Cadastro '.$entity, 'entity' => $entity],
            'PUT', 'PATCH' => ['key' => $segment.'.update', 'label' => 'Atualizar '.$entity, 'entity' => $entity],
            'DELETE' => ['key' => $segment.'.delete', 'label' => 'Remover '.$entity, 'entity' => $entity],
            default => ['key' => $segment.'.action', 'label' => 'Ação em '.$entity, 'entity' => $entity],
        };
    }

    private function segmentToEntity(string $segment): string
    {
        return match ($segment) {
            'settings' => 'Configuração',
            'operators' => 'Operador',
            'categories' => 'Categoria',
            'products' => 'Produto',
            'payment_methods' => 'Meio de Pagamento',
            'payment_plans' => 'Plano de Pagamento',
            'sales' => 'Venda',
            'suppliers' => 'Fornecedor',
            'acquirers' => 'Adquirente',
            'terminals' => 'Terminal',
            'rates' => 'Taxa',
            'tef' => 'TEF',
            default => Str::headline(str_replace('_', ' ', $segment)),
        };
    }

    private function resolveEntityId(Request $request, Response $response): ?string
    {
        if (strtoupper($request->method()) === 'POST' && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data) && isset($data['id']) && is_scalar($data['id'])) {
                return (string) $data['id'];
            }
        }

        $route = $request->route();
        $routeParameters = is_object($route) ? $route->parameters() : [];

        foreach ($routeParameters as $parameter) {
            if ($parameter instanceof Model) {
                return (string) $parameter->getKey();
            }

            if (is_scalar($parameter)) {
                return (string) $parameter;
            }
        }

        return null;
    }

    private function buildDetails(Request $request, Authenticatable $operator): string
    {
        $details = [
            'Operador: '.($operator->name ?? $operator->getAuthIdentifier()),
        ];

        $operatorCode = $this->auditLogger->resolveUserCode($operator);
        if ($operatorCode) {
            $details[] = 'Código: '.$operatorCode;
        }

        $operatorRole = $this->auditLogger->resolveUserRole($operator);
        if ($operatorRole) {
            $details[] = 'Perfil: '.$operatorRole;
        }

        $details = array_merge($details, $this->modelContextDetails($request), $this->payloadContextDetails($request));

        return implode(' · ', array_unique(array_filter($details)));
    }

    private function modelContextDetails(Request $request): array
    {
        $route = $request->route();
        $routeParameters = is_object($route) ? $route->parameters() : [];

        foreach ($routeParameters as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $output = [];
            foreach ([
                'nome' => 'Nome',
                'codigo' => 'Código',
                'cnpj' => 'CNPJ',
                'tipo' => 'Tipo',
                'descricao' => 'Descrição',
                'estacao' => 'Estação',
            ] as $field => $label) {
                $value = $parameter->getAttribute($field);
                if ($this->isInformativeValue($value)) {
                    $output[] = $label.': '.$this->stringifyValue($value);
                }
            }

            $userModel = config('pdv.models.user');
            if (is_string($userModel) && is_a($parameter, $userModel)) {
                $output[] = 'Usuário alvo: '.($parameter->getAttribute('name') ?? $parameter->getKey());
                $targetCode = Str::before((string) $parameter->getAttribute('email'), '@');
                if ($targetCode !== '') {
                    $output[] = 'Código alvo: '.$targetCode;
                }
            }

            return $output;
        }

        return [];
    }

    private function payloadContextDetails(Request $request): array
    {
        $input = $request->except([
            'password',
            'admin_password',
            'pin',
            'admin_pin',
            'senha_hash',
            'token',
            'notagil_token',
            'notagil_webhook_secret',
            'settings_access_key',
        ]);

        $output = [];
        $labels = [
            'nome' => 'Nome',
            'codigo' => 'Código',
            'perfil' => 'Perfil',
            'cnpj' => 'CNPJ',
            'tipo' => 'Tipo',
            'descricao' => 'Descrição',
            'estacao' => 'Estação',
            'formula' => 'Fórmula',
            'razao_social' => 'Razão Social',
            'nome_fantasia' => 'Nome Fantasia',
            'documento' => 'Documento',
            'telefone' => 'Telefone',
            'email' => 'E-mail',
            'endereco' => 'Endereço',
            'ambiente' => 'Ambiente',
            'arquivo_nome' => 'Arquivo',
        ];

        foreach ($labels as $field => $label) {
            if (! Arr::has($input, $field)) {
                continue;
            }

            $value = Arr::get($input, $field);
            if (! $this->isInformativeValue($value)) {
                continue;
            }

            $output[] = $label.': '.$this->stringifyValue($value);
        }

        if (Arr::has($input, 'ativo')) {
            $ativo = filter_var(Arr::get($input, 'ativo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ativo !== null) {
                $output[] = 'Status: '.($ativo ? 'ativo' : 'inativo');
            }
        }

        return $output;
    }

    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, ['password', 'admin_password', 'pin', 'admin_pin', 'senha_hash', 'token', 'notagil_token', 'notagil_webhook_secret'], true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $this->stringifyValue($value);
            }
        }

        return $sanitized;
    }

    private function isInformativeValue(mixed $value): bool
    {
        if ($value === null) return false;
        if (is_string($value) && trim($value) === '') return false;
        return true;
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        $stringValue = (string) $value;
        return Str::limit($stringValue, 120);
    }

    private function normalizedPath(Request $request): string
    {
        $path = trim($request->path(), '/');
        $apiPrefix = trim((string) config('pdv.api_prefix', 'api/pdv'), '/');

        if ($apiPrefix !== '' && Str::startsWith($path, $apiPrefix.'/')) {
            return Str::after($path, $apiPrefix.'/');
        }

        return Str::startsWith($path, 'api/') ? Str::after($path, 'api/') : $path;
    }
}
