<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\EarlyWarningController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SwitchingStockController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// Authentication & Quick Switch
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/quick-switch', [AuthController::class, 'quickSwitch'])->name('quick.switch');

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Workflow 1: Procurement
    Route::prefix('procurement')->name('procurement.')->group(function () {
        // PR
        Route::get('/pr', [ProcurementController::class, 'prIndex'])->name('pr.index');
        Route::post('/pr', [ProcurementController::class, 'prStore'])->name('pr.store');
        Route::get('/pr/{id}', [ProcurementController::class, 'prShow'])->name('pr.show');
        Route::put('/pr/{id}', [ProcurementController::class, 'prUpdate'])->name('pr.update');
        Route::delete('/pr/{id}', [ProcurementController::class, 'prDestroy'])->name('pr.destroy');
        Route::post('/pr/{id}/approve', [ProcurementController::class, 'prApprove'])->name('pr.approve');
        Route::post('/pr/{id}/reject', [ProcurementController::class, 'prReject'])->name('pr.reject');

        // PR Approvals
        Route::get('/approvals/pr', [ProcurementController::class, 'prApprovals'])->name('approvals.pr');

        // Approved PR Pool & Consolidation
        Route::get('/consolidation', [ProcurementController::class, 'consolidationPool'])->name('consolidation.index');
        Route::post('/consolidate', [ProcurementController::class, 'consolidateStore'])->name('consolidation.store');

        // PO
        Route::get('/po', [ProcurementController::class, 'poIndex'])->name('po.index');
        Route::get('/po/{id}/print', [ProcurementController::class, 'poPrint'])->name('po.print');
        Route::put('/po/{id}', [ProcurementController::class, 'poUpdate'])->name('po.update');
        Route::delete('/po/{id}', [ProcurementController::class, 'poDestroy'])->name('po.destroy');
        Route::post('/po/{id}/receive-goods', [ProcurementController::class, 'goodsReceiptStore'])->name('po.receive');

        // PO Approvals
        Route::get('/approvals/po', [ProcurementController::class, 'poApprovals'])->name('approvals.po');
        Route::post('/po/{id}/approve', [ProcurementController::class, 'poApprove'])->name('po.approve');
        Route::post('/po/{id}/reject', [ProcurementController::class, 'poReject'])->name('po.reject');
    });

    // Workflow 2: Branch Orders & Fulfillment
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/create', [OrderController::class, 'create'])->name('create');
        Route::post('/', [OrderController::class, 'store'])->name('store');
        Route::get('/approvals', [OrderController::class, 'approvals'])->name('approvals');
        Route::get('/approvals/{id}', [OrderController::class, 'approvalDetail'])->name('approvals.show');
        Route::get('/{id}', [OrderController::class, 'show'])->name('show');
        Route::put('/{id}', [OrderController::class, 'update'])->name('update');
        Route::delete('/{id}', [OrderController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/approve', [OrderController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [OrderController::class, 'reject'])->name('reject');
        Route::post('/{id}/switching', [OrderController::class, 'proposeSwitching'])->name('switching');
    });

    // Workflow 2: Warehouse Picking & Packing
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('/picking', [WarehouseController::class, 'pickingQueue'])->name('picking.queue');
        Route::post('/picking/{id}/process', [WarehouseController::class, 'processPicking'])->name('picking.process');
        Route::get('/packing', [WarehouseController::class, 'packingQueue'])->name('packing.queue');
        Route::post('/packing/{id}/process', [WarehouseController::class, 'processPacking'])->name('packing.process');
    });

    // Workflow 2: Distribution & Ekspedisi
    Route::prefix('distribution')->name('distribution.')->group(function () {
        Route::get('/shipments', [DistributionController::class, 'index'])->name('shipments.index');
        Route::post('/shipments', [DistributionController::class, 'createShipment'])->name('shipments.store');
        Route::get('/shipments/{id}', [DistributionController::class, 'show'])->name('shipments.show');
        Route::get('/manifest/{id}/print', [DistributionController::class, 'printManifest'])->name('manifest.print');
        Route::get('/label/{id}/print', [DistributionController::class, 'printLabel'])->name('label.print');
    });

    // Workflow 2: Receiving & Discrepancy
    Route::prefix('receiving')->name('receiving.')->group(function () {
        Route::get('/po', [ReceivingController::class, 'poIndex'])->name('po.index');
        Route::post('/po/{id}/receive', [ReceivingController::class, 'poReceiveStore'])->name('po.receive');
        Route::get('/', [ReceivingController::class, 'index'])->name('index');
        Route::get('/confirm/{shipmentId}', [ReceivingController::class, 'createReceiptForm'])->name('confirm.form');
        Route::post('/confirm/{shipmentId}', [ReceivingController::class, 'confirmReceipt'])->name('confirm.store');
        Route::get('/discrepancies', [ReceivingController::class, 'discrepancies'])->name('discrepancies');
    });

    // Workflow 2: Inter-unit Financial Settlement
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
        Route::post('/settlements/create/{orderId}', [SettlementController::class, 'createFromOrder'])->name('settlements.create');
        Route::post('/settlements/{id}/approve-post', [SettlementController::class, 'approveAndPost'])->name('settlements.approve');
    });

    // Inventory & Stock Ledgers
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/stock-balances', [InventoryController::class, 'stockBalances'])->name('balances');
        Route::get('/stock-card/{itemId}', [InventoryController::class, 'stockCard'])->name('stock_card');
        Route::post('/adjustments', [InventoryController::class, 'adjustmentStore'])->name('adjustments.store');
        Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('stock_opname');
        Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('stock_opname.store');
        Route::get('/stock-opname/history', [StockOpnameController::class, 'history'])->name('stock_opname.history');
        Route::get('/stock-opname/history/{id}', [StockOpnameController::class, 'showHistory'])->name('stock_opname.history.show');
        Route::get('/early-warning', [EarlyWarningController::class, 'index'])->name('early_warning');
        Route::post('/early-warning/scan', [EarlyWarningController::class, 'scan'])->name('early_warning.scan');
        Route::get('/early-warning/export-csv', [EarlyWarningController::class, 'exportCsv'])->name('early_warning.csv');
        Route::get('/forecasting', [InventoryController::class, 'forecasting'])->name('forecasting');
        Route::get('/switching-stocks', [SwitchingStockController::class, 'index'])->name('switching.index');
        Route::get('/switching-stocks/recommendations', [SwitchingStockController::class, 'recommendations'])->name('switching.recommendations');
        Route::post('/switching-stocks', [SwitchingStockController::class, 'store'])->name('switching.store');
        Route::put('/switching-stocks/{id}', [SwitchingStockController::class, 'update'])->name('switching.update');
        Route::delete('/switching-stocks/{id}', [SwitchingStockController::class, 'destroy'])->name('switching.destroy');
        Route::post('/switching-stocks/{id}/approve', [SwitchingStockController::class, 'approve'])->name('switching.approve');
    });

    // Executive & Operational Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/stock-valuation', [ReportController::class, 'stockValuation'])->name('stock_valuation');
        Route::get('/stock-valuation/export-csv', [ReportController::class, 'exportStockValuationCsv'])->name('stock_valuation.csv');
        Route::get('/procurement-coverage', [ReportController::class, 'procurementCoverage'])->name('procurement_coverage');
        Route::get('/settlements', [ReportController::class, 'settlementsReport'])->name('settlements');
    });

    // Master Data & Access
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/items', [MasterDataController::class, 'itemsIndex'])->name('items');
        Route::post('/items', [MasterDataController::class, 'itemStore'])->name('items.store');
        Route::put('/items/{id}', [MasterDataController::class, 'itemUpdate'])->name('items.update');
        Route::delete('/items/{id}', [MasterDataController::class, 'itemDestroy'])->name('items.destroy');

        // Categories CRUD
        Route::post('/categories', [MasterDataController::class, 'categoryStore'])->name('categories.store');
        Route::put('/categories/{id}', [MasterDataController::class, 'categoryUpdate'])->name('categories.update');
        Route::delete('/categories/{id}', [MasterDataController::class, 'categoryDestroy'])->name('categories.destroy');

        // UOMs
        Route::post('/uoms', [MasterDataController::class, 'uomStore'])->name('uoms.store');
        Route::put('/uoms/{code}', [MasterDataController::class, 'uomUpdate'])->name('uoms.update');
        Route::delete('/uoms/{code}', [MasterDataController::class, 'uomDestroy'])->name('uoms.destroy');

        // Item Conversions CRUD
        Route::post('/conversions', [MasterDataController::class, 'conversionStore'])->name('conversions.store');
        Route::put('/conversions/{id}', [MasterDataController::class, 'conversionUpdate'])->name('conversions.update');
        Route::delete('/conversions/{id}', [MasterDataController::class, 'conversionDestroy'])->name('conversions.destroy');

        // Organizations CRUD
        Route::get('/organizations', [MasterDataController::class, 'orgIndex'])->name('organizations');
        Route::post('/organizations', [MasterDataController::class, 'orgStore'])->name('organizations.store');
        Route::put('/organizations/{id}', [MasterDataController::class, 'orgUpdate'])->name('organizations.update');
        Route::delete('/organizations/{id}', [MasterDataController::class, 'orgDestroy'])->name('organizations.destroy');
        Route::patch('/organizations/{id}/toggle-status', [MasterDataController::class, 'orgToggleStatus'])->name('organizations.toggle_status');

        // Warehouses CRUD
        Route::post('/warehouses', [MasterDataController::class, 'warehouseStore'])->name('warehouses.store');
        Route::put('/warehouses/{id}', [MasterDataController::class, 'warehouseUpdate'])->name('warehouses.update');
        Route::delete('/warehouses/{id}', [MasterDataController::class, 'warehouseDestroy'])->name('warehouses.destroy');
        Route::patch('/warehouses/{id}/toggle-status', [MasterDataController::class, 'warehouseToggleStatus'])->name('warehouses.toggle_status');

        // Vendors & Couriers CRUD
        Route::get('/vendors-couriers', [MasterDataController::class, 'vendorsIndex'])->name('vendors');
        Route::post('/vendors', [MasterDataController::class, 'vendorStore'])->name('vendors.store');
        Route::post('/couriers', [MasterDataController::class, 'courierStore'])->name('couriers.store');

        // Users CRUD
        Route::get('/users', [MasterDataController::class, 'usersIndex'])->name('users');
        Route::post('/users', [MasterDataController::class, 'userStore'])->name('users.store');
        Route::put('/users/{id}', [MasterDataController::class, 'userUpdate'])->name('users.update');
        Route::delete('/users/{id}', [MasterDataController::class, 'userDestroy'])->name('users.destroy');

        // Budgets CRUD
        Route::get('/budgets', [MasterDataController::class, 'budgetIndex'])->name('budgets');
        Route::post('/budgets', [MasterDataController::class, 'budgetStore'])->name('budgets.store');

        // Accounting Master CRUD (CoA & Cost Center)
        Route::get('/accounting', [MasterDataController::class, 'accountingIndex'])->name('accounting');
        Route::post('/coa', [MasterDataController::class, 'coaStore'])->name('coa.store');
        Route::put('/coa/{id}', [MasterDataController::class, 'coaUpdate'])->name('coa.update');
        Route::delete('/coa/{id}', [MasterDataController::class, 'coaDestroy'])->name('coa.destroy');
        Route::patch('/coa/{id}/toggle-status', [MasterDataController::class, 'coaToggleStatus'])->name('coa.toggle_status');

        Route::post('/cost-centers', [MasterDataController::class, 'costCenterStore'])->name('cost_centers.store');
        Route::put('/cost-centers/{id}', [MasterDataController::class, 'costCenterUpdate'])->name('cost_centers.update');
        Route::delete('/cost-centers/{id}', [MasterDataController::class, 'costCenterDestroy'])->name('cost_centers.destroy');
        Route::patch('/cost-centers/{id}/toggle-status', [MasterDataController::class, 'costCenterToggleStatus'])->name('cost_centers.toggle_status');
    });

    // Notifications & Audit Trail
    Route::get('/notifications', [MasterDataController::class, 'notificationsIndex'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [MasterDataController::class, 'markAllNotificationsAsRead'])->name('notifications.mark_all_read');
    Route::post('/notifications/{id}/read', [MasterDataController::class, 'markNotificationAsRead'])->name('notifications.read');
    Route::get('/notifications/{id}/open', [MasterDataController::class, 'openNotification'])->name('notifications.open');
    Route::get('/audit-trail', [MasterDataController::class, 'auditTrailIndex'])->name('audit.index');
});
