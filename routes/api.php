<?php

use Freeline\Pdv\Http\Controllers\Api\AcquirersController;
use Freeline\Pdv\Http\Controllers\Api\AuditLogsController;
use Freeline\Pdv\Http\Controllers\Api\AuthController;
use Freeline\Pdv\Http\Controllers\Api\CashRegistersController;
use Freeline\Pdv\Http\Controllers\Api\CatalogClassificationsController;
use Freeline\Pdv\Http\Controllers\Api\CatalogFamiliesController;
use Freeline\Pdv\Http\Controllers\Api\CatalogProductsController;
use Freeline\Pdv\Http\Controllers\Api\CatalogUnitsController;
use Freeline\Pdv\Http\Controllers\Api\CategoriesController;
use Freeline\Pdv\Http\Controllers\Api\CustomersController;
use Freeline\Pdv\Http\Controllers\Api\MenuFiscalController;
use Freeline\Pdv\Http\Controllers\Api\OperatorsController;
use Freeline\Pdv\Http\Controllers\Api\PaymentMethodsController;
use Freeline\Pdv\Http\Controllers\Api\PaymentPlansController;
use Freeline\Pdv\Http\Controllers\Api\PosController;
use Freeline\Pdv\Http\Controllers\Api\ProductsController;
use Freeline\Pdv\Http\Controllers\Api\PurchaseOrdersController;
use Freeline\Pdv\Http\Controllers\Api\RestaurantCommandCenterController;
use Freeline\Pdv\Http\Controllers\Api\RestaurantOperationsController;
use Freeline\Pdv\Http\Controllers\Api\RestaurantParametersController;
use Freeline\Pdv\Http\Controllers\Api\SalesController;
use Freeline\Pdv\Http\Controllers\Api\SettingsController;
use Freeline\Pdv\Http\Controllers\Api\StockAdjustmentsController;
use Freeline\Pdv\Http\Controllers\Api\StockInventoriesController;
use Freeline\Pdv\Http\Controllers\Api\StockMovementsController;
use Freeline\Pdv\Http\Controllers\Api\SuppliersController;
use Freeline\Pdv\Http\Controllers\Api\TerminalsController;
use Freeline\Pdv\Http\Controllers\Api\NotagilWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/webhooks/notagil', NotagilWebhookController::class);

Route::middleware(['pdv.auth.token', 'pdv.audit.trail'])->group(function (): void {
    $standaloneRoutesEnabled = config('pdv.mode', 'standalone') === 'standalone'
        || (bool) config('pdv.load_standalone_routes', false);

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/settings/authorize', [AuthController::class, 'authorizeSettings']);
    Route::post('/auth/cancel/authorize', [AuthController::class, 'authorizeCancel']);

    Route::get('/pos-terminals', [TerminalsController::class, 'index']);
    Route::get('/settings/fiscal', [SettingsController::class, 'fiscal']);
    Route::get('/payment-methods', [PaymentMethodsController::class, 'index']);
    Route::get('/payment-plans', [PaymentPlansController::class, 'index']);
    Route::get('/customers', [CustomersController::class, 'index']);
    Route::post('/customers', [CustomersController::class, 'store']);
    Route::get('/menu-fiscal/identificacao', [MenuFiscalController::class, 'identificacao']);
    Route::post('/menu-fiscal/arquivo-i', [MenuFiscalController::class, 'arquivoI']);
    Route::post('/menu-fiscal/arquivo-ii', [MenuFiscalController::class, 'arquivoII']);
    Route::post('/menu-fiscal/arquivo-iii', [MenuFiscalController::class, 'arquivoIII']);
    Route::get('/menu-fiscal/mesas-abertas', [MenuFiscalController::class, 'mesasAbertas']);
    Route::post('/menu-fiscal/arquivo-iv', [MenuFiscalController::class, 'arquivoIV']);

    Route::middleware('pdv.settings.access')->group(function () use ($standaloneRoutesEnabled): void {
        Route::get('/settings/company', [SettingsController::class, 'company']);
        Route::put('/settings/company', [SettingsController::class, 'upsertCompany']);
        Route::put('/settings/fiscal', [SettingsController::class, 'upsertFiscal']);
        Route::post('/settings/fiscal/notagil/webhook', [SettingsController::class, 'provisionNotagilWebhook']);
        Route::post('/settings/fiscal/notagil/webhook/secret', [SettingsController::class, 'rotateNotagilWebhookSecret']);
        Route::get('/settings/certificate', [SettingsController::class, 'certificate']);
        Route::put('/settings/certificate', [SettingsController::class, 'upsertCertificate']);
        Route::get('/audit-logs', [AuditLogsController::class, 'index']);

        Route::post('/pos-terminals', [TerminalsController::class, 'store']);
        Route::put('/pos-terminals/{posTerminal}', [TerminalsController::class, 'update']);
        Route::delete('/pos-terminals/{posTerminal}', [TerminalsController::class, 'destroy']);

        Route::get('/sales', [SalesController::class, 'index']);
        Route::get('/sales/{sale}', [SalesController::class, 'show']);
        Route::post('/sales/{sale}/cancel', [SalesController::class, 'cancel']);
        Route::post('/sales/{sale}/fiscal/retry', [SalesController::class, 'retryFiscal']);
        Route::post('/sales/{sale}/fiscal/sync', [SalesController::class, 'syncFiscal']);
        Route::get('/sales/{sale}/fiscal/events', [SalesController::class, 'fiscalEvents']);
        Route::get('/sales/{sale}/fiscal/{artifact}', [SalesController::class, 'fiscalArtifact'])->whereIn('artifact', ['xml', 'pdf']);

        Route::get('/cash-registers', [CashRegistersController::class, 'index']);
        Route::get('/cash-registers/{cashRegister}', [CashRegistersController::class, 'show']);
        Route::post('/cash-registers/open', [CashRegistersController::class, 'open']);
        Route::post('/cash-registers/{cashRegister}/movements', [CashRegistersController::class, 'addMovement']);
        Route::post('/cash-registers/{cashRegister}/close', [CashRegistersController::class, 'close']);

        if (! $standaloneRoutesEnabled) {
            return;
        }

        Route::get('/catalog/products/support-data', [CatalogProductsController::class, 'supportData']);
        Route::get('/catalog/products/ncms', [CatalogProductsController::class, 'searchNcms']);
        Route::get('/catalog/products', [CatalogProductsController::class, 'index']);
        Route::post('/catalog/products', [CatalogProductsController::class, 'store']);
        Route::get('/catalog/products/{produto}', [CatalogProductsController::class, 'show']);
        Route::put('/catalog/products/{produto}', [CatalogProductsController::class, 'update']);
        Route::delete('/catalog/products/{produto}', [CatalogProductsController::class, 'destroy']);

        Route::get('/catalog/families', [CatalogFamiliesController::class, 'index']);
        Route::post('/catalog/families', [CatalogFamiliesController::class, 'store']);
        Route::put('/catalog/families/{family}', [CatalogFamiliesController::class, 'update']);
        Route::delete('/catalog/families/{family}', [CatalogFamiliesController::class, 'destroy']);

        Route::get('/catalog/units', [CatalogUnitsController::class, 'index']);
        Route::post('/catalog/units', [CatalogUnitsController::class, 'store']);
        Route::put('/catalog/units/{unit}', [CatalogUnitsController::class, 'update']);
        Route::delete('/catalog/units/{unit}', [CatalogUnitsController::class, 'destroy']);

        Route::get('/catalog/classifications', [CatalogClassificationsController::class, 'index']);
        Route::post('/catalog/classifications', [CatalogClassificationsController::class, 'store']);
        Route::put('/catalog/classifications/{classification}', [CatalogClassificationsController::class, 'update']);
        Route::delete('/catalog/classifications/{classification}', [CatalogClassificationsController::class, 'destroy']);

        Route::get('/settings/restaurant-parameters', [RestaurantParametersController::class, 'show']);
        Route::put('/settings/restaurant-parameters', [RestaurantParametersController::class, 'upsert']);

        Route::get('/operators', [OperatorsController::class, 'index']);
        Route::post('/operators', [OperatorsController::class, 'store']);
        Route::put('/operators/{operator}', [OperatorsController::class, 'update']);

        Route::get('/categories', [CategoriesController::class, 'index']);
        Route::post('/categories', [CategoriesController::class, 'store']);
        Route::put('/categories/{category}', [CategoriesController::class, 'update']);
        Route::delete('/categories/{category}', [CategoriesController::class, 'destroy']);

        Route::get('/products', [ProductsController::class, 'index']);
        Route::post('/products', [ProductsController::class, 'store']);
        Route::put('/products/{product}', [ProductsController::class, 'update']);
        Route::delete('/products/{product}', [ProductsController::class, 'destroy']);

        Route::get('/suppliers', [SuppliersController::class, 'index']);
        Route::post('/suppliers', [SuppliersController::class, 'store']);
        Route::put('/suppliers/{supplier}', [SuppliersController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SuppliersController::class, 'destroy']);

        Route::put('/customers/{customer}', [CustomersController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomersController::class, 'destroy']);

        Route::get('/purchase-orders', [PurchaseOrdersController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrdersController::class, 'store']);
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrdersController::class, 'show']);
        Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrdersController::class, 'update']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrdersController::class, 'receive']);

        Route::get('/stock-adjustments', [StockAdjustmentsController::class, 'index']);
        Route::post('/stock-adjustments', [StockAdjustmentsController::class, 'store']);
        Route::put('/stock-adjustments/{stockAdjustment}', [StockAdjustmentsController::class, 'update']);
        Route::delete('/stock-adjustments/{stockAdjustment}', [StockAdjustmentsController::class, 'destroy']);

        Route::get('/stock-inventories', [StockInventoriesController::class, 'index']);
        Route::post('/stock-inventories', [StockInventoriesController::class, 'store']);
        Route::get('/stock-inventories/{stockInventory}', [StockInventoriesController::class, 'show']);
        Route::put('/stock-inventories/{stockInventory}/items/{stockInventoryItem}', [StockInventoriesController::class, 'updateItem']);
        Route::post('/stock-inventories/{stockInventory}/send-to-adjustments', [StockInventoriesController::class, 'sendToAdjustments']);

        Route::get('/stock-movements', [StockMovementsController::class, 'index']);

        Route::post('/payment-methods', [PaymentMethodsController::class, 'store']);
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodsController::class, 'update']);
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodsController::class, 'destroy']);

        Route::post('/payment-plans', [PaymentPlansController::class, 'store']);
        Route::put('/payment-plans/{paymentPlan}', [PaymentPlansController::class, 'update']);
        Route::delete('/payment-plans/{paymentPlan}', [PaymentPlansController::class, 'destroy']);

        Route::get('/acquirers', [AcquirersController::class, 'index']);
        Route::post('/acquirers', [AcquirersController::class, 'store']);
        Route::put('/acquirers/{acquirer}', [AcquirersController::class, 'update']);
        Route::delete('/acquirers/{acquirer}', [AcquirersController::class, 'destroy']);

        Route::get('/acquirers/{acquirer}/terminals', [AcquirersController::class, 'terminals']);
        Route::post('/acquirers/{acquirer}/terminals', [AcquirersController::class, 'storeTerminal']);
        Route::put('/terminals/{terminal}', [AcquirersController::class, 'updateTerminal']);
        Route::delete('/terminals/{terminal}', [AcquirersController::class, 'destroyTerminal']);

        Route::get('/terminals/{terminal}/rates', [AcquirersController::class, 'rates']);
        Route::post('/terminals/{terminal}/rates', [AcquirersController::class, 'storeRate']);
        Route::put('/rates/{rate}', [AcquirersController::class, 'updateRate']);
        Route::delete('/rates/{rate}', [AcquirersController::class, 'destroyRate']);

        Route::get('/terminals/{terminal}/tef', [AcquirersController::class, 'tef']);
        Route::put('/terminals/{terminal}/tef', [AcquirersController::class, 'upsertTef']);
        Route::delete('/tef/{tef}', [AcquirersController::class, 'destroyTef']);
    });

    Route::get('/pos/categories', [PosController::class, 'categories']);
    Route::get('/pos/products', [PosController::class, 'products']);
    Route::get('/pos/company-profile', [PosController::class, 'companyProfile']);
    Route::get('/pos/restaurant/command-center', [RestaurantCommandCenterController::class, 'index']);
    Route::post('/pos/restaurant/command-center/reintegrate', [RestaurantCommandCenterController::class, 'reintegrate']);
    Route::post('/pos/restaurant/command-center/transfer', [RestaurantCommandCenterController::class, 'transfer']);
    Route::post('/pos/restaurant/command-center/merge', [RestaurantCommandCenterController::class, 'merge']);
    Route::post('/pos/restaurant/command-center/print', [RestaurantCommandCenterController::class, 'print']);
    Route::post('/pos/restaurant/command-center/conference', [RestaurantCommandCenterController::class, 'conference']);
    Route::get('/pos/restaurant/ordering/context', [RestaurantOperationsController::class, 'orderingContext']);
    Route::post('/pos/restaurant/fichas', [RestaurantOperationsController::class, 'createFicha']);
    Route::get('/pos/restaurant/fichas/{ficha}/summary', [RestaurantOperationsController::class, 'fichaSummary']);
    Route::post('/pos/restaurant/fichas/{ficha}/observation', [RestaurantOperationsController::class, 'saveFichaObservation']);
    Route::post('/pos/restaurant/fichas/{ficha}/close-request', [RestaurantOperationsController::class, 'requestFichaClosing']);
    Route::post('/pos/restaurant/fichas/{ficha}/conference', [RestaurantOperationsController::class, 'conference']);
    Route::post('/pos/restaurant/fichas/{ficha}/orders', [RestaurantOperationsController::class, 'submitFichaOrder']);
    Route::get('/pos/restaurant/production/tickets', [RestaurantOperationsController::class, 'productionTickets']);
    Route::post('/pos/restaurant/production/tickets/{ticket}/status', [RestaurantOperationsController::class, 'updateProductionTicketStatus']);
    Route::post('/pos/sales/finalize', [SalesController::class, 'finalize']);
});
