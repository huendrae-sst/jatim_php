<?php

use App\Http\Controllers\Api\InventoryApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [InventoryApiController::class, 'health']);
    Route::get('/scan-barcode', [InventoryApiController::class, 'scanBarcode']);
    Route::get('/stock/balance/{warehouseId}/{sku}', [InventoryApiController::class, 'getStockBalance']);
    Route::get('/shipments/track/{trackingNumber}', [InventoryApiController::class, 'trackShipment']);
    Route::get('/dashboard/kpi', [InventoryApiController::class, 'getKpis']);
});
