<?php

return [
    'mode' => env('PDV_MODE', 'standalone'),

    'frontend_path' => env('PDV_FRONTEND_PATH', 'pdv'),
    'api_prefix' => env('PDV_API_PREFIX', 'api/pdv'),
    'web_session_auth' => env('PDV_WEB_SESSION_AUTH', false),
    'access_permission' => env('PDV_ACCESS_PERMISSION', 'pdv.acessar'),

    'load_routes' => env('PDV_LOAD_ROUTES', true),
    'load_migrations' => env('PDV_LOAD_MIGRATIONS', true),
    'load_standalone_migrations' => env('PDV_LOAD_STANDALONE_MIGRATIONS', null),
    'load_standalone_routes' => env('PDV_LOAD_STANDALONE_ROUTES', false),

    'web_middleware' => ['web'],
    'api_middleware' => ['api'],

    'models' => [
        'user' => env('PDV_USER_MODEL', Freeline\Pdv\Models\User::class),
        'product' => env('PDV_PRODUCT_MODEL', Freeline\Pdv\Models\Product::class),
        'produto' => env('PDV_PRODUTO_MODEL', Freeline\Pdv\Models\Produto::class),
        'estabelecimento' => env('PDV_ESTABELECIMENTO_MODEL', null),
    ],

    'adapters' => [
        'product_catalog' => Freeline\Pdv\Standalone\StandaloneProductCatalogRepository::class,
        'stock_movement' => Freeline\Pdv\Standalone\StandaloneStockMovementService::class,
        'operator' => Freeline\Pdv\Standalone\StandaloneOperatorResolver::class,
        'company_context' => Freeline\Pdv\Standalone\StandaloneCompanyContextResolver::class,
        'fiscal_config' => Freeline\Pdv\Standalone\StandaloneFiscalConfigProvider::class,
        'payment_methods' => Freeline\Pdv\Standalone\StandalonePaymentMethodRepository::class,
    ],

    'notagil' => [
        'base_url' => env('NOTAGIL_BASE_URL', 'https://api.notagil.com.br/api/v1/integrations'),
        'token' => env('NOTAGIL_TOKEN'),
        'webhook_url' => env('NOTAGIL_WEBHOOK_URL'),
        'webhook_secret' => env('NOTAGIL_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('NOTAGIL_WEBHOOK_TOLERANCE_SECONDS', 300),
        'timeout' => (int) env('NOTAGIL_TIMEOUT_SECONDS', 30),
        'wait_seconds' => (int) env('NOTAGIL_WAIT_SECONDS', 8),
    ],

    'tables' => [
        'users' => env('PDV_USERS_TABLE', 'users'),
        'products' => env('PDV_PRODUCTS_TABLE', 'products'),
        'produto' => env('PDV_PRODUTO_TABLE', 'produto'),
        'estabelecimentos' => env('PDV_ESTABELECIMENTOS_TABLE', 'estabelecimentos'),
    ],

    'vite_inputs' => [
        'vendor/freeline/pdv/resources/app/js/app.js',
    ],
];
