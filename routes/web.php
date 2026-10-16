<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'email'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/branch', [AdminController::class, 'switch'])->middleware('auth')->name('branch.switch');
Route::middleware(['auth', 'branch'])->group(function () {
    Route::get('/supplier-search', [LookupController::class, 'suppliers'])->middleware('permission:accounting.manage')->name('suppliers.search');
    Route::get('/', [ReportController::class, 'dashboard'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/medicines', [CatalogController::class, 'medicines'])->middleware('permission:medicines.view')->name('medicines.index');
    Route::post('/medicines', [CatalogController::class, 'storeMedicine'])->name('medicines.store');
    Route::put('/medicines/{medicine}', [CatalogController::class, 'updateMedicine'])->name('medicines.update');
    foreach (['suppliers', 'customers'] as $party) {
        Route::get('/'.$party, [CatalogController::class, 'parties'])->middleware('permission:'.$party.'.view')->name($party.'.index');
        Route::post('/'.$party, [CatalogController::class, 'storeParty'])->name($party.'.store');
        Route::put('/'.$party.'/{party}', [CatalogController::class, 'updateParty'])->name($party.'.update');
    }
    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::post('/inventory/{batch}/quarantine', [InventoryController::class, 'quarantine'])->middleware('permission:inventory.adjust')->name('inventory.quarantine');
    Route::get('/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view')->name('inventory.movements');
    Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('permission:purchases.view')->name('purchases.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:purchases.view')->name('purchases.show');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
    Route::get('/supplier-payments', [PurchaseController::class, 'payments'])->middleware('permission:accounting.view')->name('supplier-payments.index');
    Route::post('/purchases/{purchase}/returns', [PurchaseController::class, 'returnGoods'])->name('purchases.returns.store');
    Route::post('/supplier-payments', [PurchaseController::class, 'pay'])->name('supplier-payments.store');
    Route::get('/catalog-search', [PurchaseController::class, 'search'])->middleware('permission:purchases.create')->name('catalog.search');
    Route::get('/pos', [SaleController::class, 'pos'])->middleware('permission:sales.create')->name('pos');
    Route::get('/pos/search', [SaleController::class, 'search'])->middleware(['permission:sales.create', 'throttle:120,1'])->name('pos.search');
    Route::get('/pos/customers', [SaleController::class, 'customers'])->middleware('permission:sales.create')->name('pos.customers');
    Route::post('/checkout', [SaleController::class, 'checkout'])->middleware('throttle:30,1')->name('checkout');
    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('sales.show');
    Route::post('/sales/{sale}/payments', [SaleController::class, 'collectPayment'])->name('sales.payments.store');
    Route::post('/sales/{sale}/returns', [SaleController::class, 'refund'])->name('sales.returns.store');
    Route::get('/accounting', [ReportController::class, 'accounting'])->middleware('permission:accounting.view')->name('accounting.index');
    Route::get('/accounting/{report}', [ReportController::class, 'accountingReport'])->whereIn('report', ['trial-balance', 'operating-result', 'stock-reconciliation', 'journal-entries', 'balance-sheet'])->middleware('permission:accounting.view')->name('accounting.report');
    Route::get('/expenses', [ReportController::class, 'expenses'])->middleware('permission:accounting.view')->name('expenses.index');
    Route::post('/expenses', [ReportController::class, 'payExpense'])->name('expenses.store');
    Route::get('/audit', [ReportController::class, 'audit'])->middleware('permission:audit_logs.view')->name('audit.index');
    Route::get('/notifications', [ReportController::class, 'alerts'])->middleware('permission:inventory.view')->name('notifications.index');
    Route::get('/exports/sales', [ReportController::class, 'export'])->middleware('permission:reports.view')->name('sales.export');
    Route::get('/administration', [AdminController::class, 'index'])->middleware('permission:users.manage')->name('administration');
    Route::post('/administration/users', [AdminController::class, 'saveUser'])->middleware('permission:users.manage')->name('users.save');
    Route::post('/administration/branches', [AdminController::class, 'branch'])->middleware('permission:settings.manage')->name('branches.store');
    Route::get('/profile', fn () => Inertia::render('Profile'))->name('profile');
    Route::put('/profile/password', [AuthController::class, 'change'])->name('profile.password');
});
