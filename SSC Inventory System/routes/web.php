<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\IncidentReportController;
use App\Http\Controllers\InventoryAuditController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// TOTP MFA Setup & Login Challenge (pending or partial session)
Route::get('/mfa/setup', [MfaController::class, 'showSetup'])->name('mfa.setup');
Route::post('/mfa/setup', [MfaController::class, 'confirmSetup'])->name('mfa.setup.confirm');
Route::get('/mfa/verify', [MfaController::class, 'showVerify'])->name('mfa.verify');
Route::post('/mfa/verify', [MfaController::class, 'verify'])->name('mfa.verify.attempt');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'mfa'])->group(function (): void {
    // Step-Up MFA & Backup Code Regeneration
    Route::get('/mfa/step-up', [MfaController::class, 'showStepUp'])->name('mfa.step-up');
    Route::post('/mfa/step-up', [MfaController::class, 'verifyStepUp'])->name('mfa.step-up.verify');
    Route::post('/mfa/backup-codes/regenerate', [MfaController::class, 'regenerateBackupCodes'])
        ->middleware('stepup')
        ->name('mfa.backup-codes.regenerate');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // Profile Settings
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Authenticated Item Media Serving
    Route::get('/media/items/{filename}', [InventoryController::class, 'serveImage'])->name('media.items');

    // Global Search
    Route::get('/search/quick', [GlobalSearchController::class, 'quick'])->name('search.quick');
    Route::get('/search', [GlobalSearchController::class, 'index'])->name('search.index');

    // In-App Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/summary', [NotificationController::class, 'unreadSummary'])->name('notifications.summary');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Inventory Management
    Route::middleware('permission:inventory.manage')->group(function (): void {
        Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('/inventory/bulk', [InventoryController::class, 'bulkCreate'])->name('inventory.bulk.create');
        Route::get('/inventory/bulk/template', [InventoryController::class, 'bulkTemplate'])->name('inventory.bulk.template');
        Route::post('/inventory/bulk', [InventoryController::class, 'bulkStore'])->name('inventory.bulk.store');
        Route::get('/inventory/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
        Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::patch('/inventory/{inventory}/condition', [InventoryController::class, 'updateCondition'])->name('inventory.condition');
    });

    Route::middleware('permission:inventory.archive')->group(function (): void {
        Route::delete('/inventory/{inventory}', [InventoryController::class, 'archive'])
            ->middleware('stepup')
            ->name('inventory.archive');
        Route::post('/inventory/{id}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    });

    Route::middleware('permission:inventory.view')->group(function (): void {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{id}', [InventoryController::class, 'show'])->whereNumber('id')->name('inventory.show');
    });

    // Categories & Storage Locations
    Route::middleware('permission:categories.manage')->group(function (): void {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
    });

    // NIST CSF Incident Response Center
    Route::get('/incidents', [IncidentReportController::class, 'index'])
        ->middleware('permission:incidents.report')
        ->name('incidents.index');
    Route::post('/incidents', [IncidentReportController::class, 'store'])
        ->middleware('permission:incidents.report')
        ->name('incidents.store');
    Route::post('/incidents/{incident}/resolve', [IncidentReportController::class, 'resolve'])
        ->middleware('permission:incidents.resolve')
        ->name('incidents.resolve');

    // Borrowing & Return Management
    Route::middleware('permission:borrowings.request')->group(function (): void {
        Route::get('/borrowings/create', [BorrowingController::class, 'create'])->name('borrowings.create');
        Route::post('/borrowings', [BorrowingController::class, 'store'])->name('borrowings.store');
        Route::post('/borrowings/{borrowing}/return-request', [BorrowingController::class, 'submitReturnRequest'])->name('borrowings.return-request');
    });

    Route::middleware('permission:borrowings.approve')->group(function (): void {
        Route::post('/borrowings/{borrowing}/approve', [BorrowingController::class, 'approve'])->name('borrowings.approve');
        Route::post('/borrowings/{borrowing}/reject', [BorrowingController::class, 'reject'])->name('borrowings.reject');
        Route::post('/borrowings/{borrowing}/notify-return', [BorrowingController::class, 'sendReturnNotice'])->name('borrowings.notify-return');
    });

    Route::middleware('permission:borrowings.process_return')->group(function (): void {
        Route::post('/borrowings/{borrowing}/return', [ReturnController::class, 'store'])->name('borrowings.return.store');
        Route::post('/returns/{returnTransaction}/inspect', [ReturnController::class, 'resolveInspection'])->name('returns.inspect');
    });

    Route::middleware('permission:borrowings.view_own,borrowings.view_all')->group(function (): void {
        Route::get('/borrowings', [BorrowingController::class, 'index'])->name('borrowings.index');
        Route::delete('/borrowings/history/clear', [BorrowingController::class, 'clearHistory'])->name('borrowings.clear-history');
        Route::get('/borrowings/{borrowing}', [BorrowingController::class, 'show'])->name('borrowings.show');
        Route::delete('/borrowings/{borrowing}', [BorrowingController::class, 'destroy'])->name('borrowings.destroy');
    });

    // Stock Movements (linked from item detail for clean stock verification)
    Route::middleware('permission:stock.record')->group(function (): void {
        Route::get('/stock-movements/create', [StockMovementController::class, 'create'])->name('stock-movements.create');
        Route::post('/stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
    });
    Route::get('/stock-movements', [StockMovementController::class, 'index'])
        ->middleware('permission:stock.view')
        ->name('stock-movements.index');

    // Optional Advanced Modules (retained behind permissions)
    Route::middleware('permission:procurement.manage')->group(function (): void {
        Route::get('/procurement/create', [ProcurementController::class, 'create'])->name('procurement.create');
        Route::post('/procurement', [ProcurementController::class, 'store'])->name('procurement.store');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });
    Route::middleware('permission:procurement.view')->group(function (): void {
        Route::get('/procurement', [ProcurementController::class, 'index'])->name('procurement.index');
        Route::get('/procurement/{acquisition}/receipt', [ProcurementController::class, 'downloadReceipt'])->name('procurement.receipt');
    });

    Route::middleware('permission:audits.conduct')->group(function (): void {
        Route::get('/audits/create', [InventoryAuditController::class, 'create'])->name('audits.create');
        Route::post('/audits', [InventoryAuditController::class, 'store'])->name('audits.store');
        Route::put('/audits/{audit}/counts', [InventoryAuditController::class, 'updateCounts'])->name('audits.counts.update');
    });
    Route::middleware('permission:audits.approve')->group(function (): void {
        Route::post('/audits/{audit}/approve', [InventoryAuditController::class, 'approve'])->name('audits.approve');
        Route::post('/audits/{audit}/reject', [InventoryAuditController::class, 'reject'])->name('audits.reject');
    });
    Route::middleware('permission:audits.view')->group(function (): void {
        Route::get('/audits', [InventoryAuditController::class, 'index'])->name('audits.index');
        Route::get('/audits/{audit}', [InventoryAuditController::class, 'show'])->name('audits.show');
    });

    // Streamlined SSC Reports
    Route::middleware('permission:reports.view_basic,reports.view_all')->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    });
    Route::middleware('permission:reports.export')->group(function (): void {
        Route::get('/reports/{type}/pdf', [ReportController::class, 'exportPdf'])->name('reports.pdf');
        Route::get('/reports/{type}/excel', [ReportController::class, 'exportExcel'])->name('reports.excel');
        Route::get('/reports/{type}/csv', [ReportController::class, 'exportCsv'])->name('reports.csv');
    });

    // User Management, Unlock & Role Permissions (Step-Up Protected Mutations)
    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/bulk-template', [UserController::class, 'bulkTemplate'])->name('users.bulk-template');
        Route::post('/users/bulk-import', [UserController::class, 'bulkImport'])->name('users.bulk-import');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');

        Route::middleware('stepup')->group(function (): void {
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::put('/roles/{role}/permissions', [UserController::class, 'updateRolePermissions'])->name('users.roles.permissions');
        });
    });

    // System Settings & Step-Up SQL Backup
    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::middleware('stepup')->group(function (): void {
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
            Route::get('/settings/backup', [SettingController::class, 'downloadBackup'])->name('settings.backup');
        });
    });
});
