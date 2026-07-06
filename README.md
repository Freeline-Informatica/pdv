# Freeline PDV

Pacote Laravel do PDV Freeline para uso dentro do SimplesLaravel ou em uma
instalacao standalone. O pacote registra rotas, views, assets, middlewares e
migrations pelo `Freeline\Pdv\PdvServiceProvider`.

## Requisitos

- PHP 8.2 ou superior.
- Laravel 12.
- `notagil/integration-sdk` na versao exigida pelo pacote.
- Acesso ao pacote Composer `freeline/pdv`.

## Instalacao no SimplesLaravel

No diretorio raiz do SimplesLaravel, instale ou atualize o pacote:

```bash
composer require freeline/pdv:^0.2.3 notagil/integration-sdk:^0.4.3 -W
```

Se o Composer ainda nao conhecer o repositorio do pacote, registre o repositorio
VCS antes do `require`:

```bash
composer config repositories.freeline-pdv vcs https://github.com/Freeline-Informatica/pdv
composer require freeline/pdv:^0.2.3 notagil/integration-sdk:^0.4.3 -W
```

Depois limpe caches e confirme a descoberta do provider:

```bash
php artisan optimize:clear
php artisan package:discover
```

## Configuracao para ERP

Publique a configuracao:

```bash
php artisan vendor:publish --tag=pdv-config
```

No SimplesLaravel, o arquivo `config/pdv.php` deve manter o pacote em modo ERP e
desabilitar as migrations standalone:

```php
return [
    'mode' => env('PDV_MODE', 'erp'),
    'load_migrations' => true,
    'load_standalone_migrations' => false,
    'load_standalone_routes' => false,
    'web_session_auth' => true,
    'access_permission' => env('PDV_ACCESS_PERMISSION', 'pdv.acessar'),
    'web_middleware' => ['web', 'auth', 'verified', 'pdv.web.access'],

    'models' => [
        'user' => App\Models\User::class,
        'product' => App\Domain\Produtos\Models\Produto::class,
        'produto' => App\Domain\Produtos\Models\Produto::class,
        'estabelecimento' => App\Domain\Pessoas\Models\Estabelecimento::class,
    ],

    'adapters' => [
        'product_catalog' => App\Infrastructure\Pdv\Adapters\ErpProductCatalogRepository::class,
        'stock_movement' => App\Infrastructure\Pdv\Adapters\ErpStockMovementService::class,
        'operator' => App\Infrastructure\Pdv\Adapters\ErpOperatorResolver::class,
        'company_context' => App\Infrastructure\Pdv\Adapters\ErpCompanyContextResolver::class,
        'fiscal_config' => App\Infrastructure\Pdv\Adapters\ErpFiscalConfigProvider::class,
        'payment_methods' => App\Infrastructure\Pdv\Adapters\ErpPaymentMethodRepository::class,
    ],

    'vite_inputs' => [
        'resources/js/pdv/app.js',
    ],
];
```

Variaveis de ambiente usuais:

```dotenv
PDV_MODE=erp
PDV_LOAD_MIGRATIONS=true
PDV_LOAD_STANDALONE_MIGRATIONS=false
PDV_FRONTEND_PATH=pdv
PDV_API_PREFIX=api/pdv
PDV_ACCESS_PERMISSION=pdv.acessar
```

## Assets e frontend

Publique os assets quando instalar ou atualizar o pacote:

```bash
php artisan vendor:publish --tag=pdv-assets --force
```

No SimplesLaravel, mantenha o entrypoint local apontando para o pacote:

```js
// resources/js/pdv/app.js
import '../../../vendor/freeline/pdv/resources/app/js/app.js';
```

O `vite.config.ts` do SimplesLaravel precisa incluir `resources/js/pdv/app.js`
nos inputs e liberar leitura de `vendor/freeline/pdv` no `server.fs.allow`
durante desenvolvimento.

Depois compile:

```bash
npm install
npm run build
```

## Migrations necessarias

O provider carrega migrations diretamente do pacote. Nao e necessario copiar
arquivos para `database/migrations`.

### SimplesLaravel / ERP

No ERP rode somente as migrations `core`. O proprio provider faz isso quando
`PDV_MODE=erp` e `PDV_LOAD_STANDALONE_MIGRATIONS=false`.

Antes de aplicar em producao, revise o plano:

```bash
php artisan migrate --pretend
```

Execute:

```bash
php artisan migrate --force
```

Se precisar rodar explicitamente apenas as migrations core do pacote:

```bash
php artisan migrate --path=vendor/freeline/pdv/database/migrations/core --force
```

Nao rode `database/migrations/standalone` no SimplesLaravel. Essas migrations
criam tabelas base que o ERP ja possui ou resolve por adapters.

### Standalone

Em uma instalacao standalone, deixe o modo padrao ou configure:

```dotenv
PDV_MODE=standalone
PDV_LOAD_STANDALONE_MIGRATIONS=true
```

Depois rode:

```bash
php artisan migrate --force
```

Nesse modo o provider carrega `database/migrations/core` e
`database/migrations/standalone`.

## Rotas

Por padrao:

- Frontend: `/pdv`
- API: `/api/pdv`

Esses prefixos podem ser alterados por:

```dotenv
PDV_FRONTEND_PATH=pdv
PDV_API_PREFIX=api/pdv
```

## Atualizacao do pacote

Para atualizar o pacote no SimplesLaravel:

```bash
composer update freeline/pdv notagil/integration-sdk -W
php artisan optimize:clear
php artisan vendor:publish --tag=pdv-assets --force
php artisan migrate --force
npm run build
```

Use `php artisan migrate --pretend` antes do `migrate --force` quando estiver
validando uma atualizacao em ambiente critico.
