<?php

namespace Freeline\Pdv;

use Freeline\Pdv\Contracts\CompanyContextResolver;
use Freeline\Pdv\Contracts\FiscalConfigProvider;
use Freeline\Pdv\Contracts\OperatorResolver;
use Freeline\Pdv\Contracts\PaymentMethodRepository;
use Freeline\Pdv\Contracts\ProductCatalogRepository;
use Freeline\Pdv\Contracts\StockMovementService;
use Freeline\Pdv\Http\Middleware\EnsureSettingsAccess;
use Freeline\Pdv\Http\Middleware\RecordAuditTrail;
use Freeline\Pdv\Http\Middleware\TokenAuth;
use Freeline\Pdv\Standalone\StandaloneCompanyContextResolver;
use Freeline\Pdv\Standalone\StandaloneFiscalConfigProvider;
use Freeline\Pdv\Standalone\StandaloneOperatorResolver;
use Freeline\Pdv\Standalone\StandalonePaymentMethodRepository;
use Freeline\Pdv\Standalone\StandaloneProductCatalogRepository;
use Freeline\Pdv\Standalone\StandaloneStockMovementService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PdvServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pdv.php', 'pdv');

        $this->app->bind(ProductCatalogRepository::class, config('pdv.adapters.product_catalog', StandaloneProductCatalogRepository::class));
        $this->app->bind(StockMovementService::class, config('pdv.adapters.stock_movement', StandaloneStockMovementService::class));
        $this->app->bind(OperatorResolver::class, config('pdv.adapters.operator', StandaloneOperatorResolver::class));
        $this->app->bind(CompanyContextResolver::class, config('pdv.adapters.company_context', StandaloneCompanyContextResolver::class));
        $this->app->bind(FiscalConfigProvider::class, config('pdv.adapters.fiscal_config', StandaloneFiscalConfigProvider::class));
        $this->app->bind(PaymentMethodRepository::class, config('pdv.adapters.payment_methods', StandalonePaymentMethodRepository::class));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/app/views', 'pdv');

        $this->publishes([
            __DIR__.'/../config/pdv.php' => config_path('pdv.php'),
        ], 'pdv-config');

        $this->publishes([
            __DIR__.'/../resources/app' => resource_path('vendor/freeline/pdv'),
        ], 'pdv-assets');

        $this->registerMiddlewareAliases();
        $this->registerRoutes();
        $this->registerMigrations();
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('pdv.auth.token', TokenAuth::class);
        $router->aliasMiddleware('pdv.settings.access', EnsureSettingsAccess::class);
        $router->aliasMiddleware('pdv.audit.trail', RecordAuditTrail::class);
    }

    private function registerRoutes(): void
    {
        if (! config('pdv.load_routes')) {
            return;
        }

        Route::middleware(config('pdv.api_middleware', ['api']))
            ->prefix(trim(config('pdv.api_prefix', 'api/pdv'), '/'))
            ->group(__DIR__.'/../routes/api.php');

        Route::middleware(config('pdv.web_middleware', ['web']))
            ->prefix(trim(config('pdv.frontend_path', 'pdv'), '/'))
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerMigrations(): void
    {
        if (! config('pdv.load_migrations')) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/core');

        $loadStandalone = config('pdv.load_standalone_migrations');

        if ($loadStandalone === null) {
            $loadStandalone = config('pdv.mode', 'standalone') === 'standalone';
        }

        if ($loadStandalone) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations/standalone');
        }
    }
}
