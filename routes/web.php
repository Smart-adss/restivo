<?php

use App\Domain\Identity\Livewire\Login;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchHoursController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Menu\CategoryController;
use App\Http\Controllers\Menu\ProductController;
use App\Http\Controllers\Menu\ThemeController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\OrderBoardController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\PublicOrderPageController;
use App\Http\Controllers\PublicReservationPageController;
use App\Http\Controllers\PublicTablePageController;
use App\Http\Controllers\ReservationBoardController;
use App\Http\Controllers\DayReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KitchenBoardController;
use App\Http\Controllers\PurchasingController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TablesBoardController;
use App\Http\Controllers\Platform\BillingController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SettingController;
use App\Http\Controllers\Platform\SupportTicketController as PlatformSupportController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route(Auth::user()->is_platform_user ? 'platform.tenants.index' : 'app.home');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::middleware('platform')->prefix('platform')->name('platform.')->group(function () {
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/{plan:code}', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan:code}', [PlanController::class, 'update'])->name('plans.update');
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/support', [PlatformSupportController::class, 'index'])->name('support.index');
        Route::get('/support/{publicId}', [PlatformSupportController::class, 'show'])->name('support.show');
        Route::post('/support/{publicId}', [PlatformSupportController::class, 'reply'])->name('support.reply');
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant:public_id}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('/tenants/{tenant:public_id}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{tenant:public_id}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
        Route::post('/tenants/{tenant:public_id}/payments', [BillingController::class, 'pay'])->name('tenants.payments.store');
        Route::post('/tenants/{tenant:public_id}/plan', [BillingController::class, 'changePlan'])->name('tenants.plan.update');
    });

    Route::middleware('tenant.operational')->prefix('app')->group(function () {
        Route::get('/', HomeController::class)->name('app.home');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('app.profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('app.profile.update');
        Route::get('/branches', [BranchController::class, 'index'])->name('app.branches.index');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('app.branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->middleware('subscription.writable')->name('app.branches.store');
        Route::get('/branches/{publicId}', [BranchController::class, 'show'])->name('app.branches.show');
        Route::put('/branches/{publicId}/hours', [BranchHoursController::class, 'update'])->middleware('subscription.writable')->name('app.branches.hours.update');
        Route::post('/branches/{publicId}/qr', [BranchController::class, 'storeQr'])->middleware('subscription.writable')->name('app.branches.qr.store');
        Route::get('/staff', [StaffController::class, 'index'])->name('app.staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('app.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->middleware('subscription.writable')->name('app.staff.store');
        Route::get('/subscription', [SubscriptionController::class, 'show'])->name('app.subscription');
        Route::get('/support', [SupportTicketController::class, 'index'])->name('app.support.index');
        Route::post('/support', [SupportTicketController::class, 'store'])->name('app.support.store');
        Route::get('/pos', [PosController::class, 'show'])->name('app.pos');
        Route::post('/pos', [PosController::class, 'store'])->middleware('subscription.writable')->name('app.pos.store');
        Route::post('/pos/shifts', [PosController::class, 'openShift'])->middleware('subscription.writable')->name('app.pos.shifts.open');
        Route::post('/pos/shifts/{publicId}/close', [PosController::class, 'closeShift'])->middleware('subscription.writable')->name('app.pos.shifts.close');
        Route::post('/pos/shifts/{publicId}/movements', [PosController::class, 'movement'])->middleware('subscription.writable')->name('app.pos.movements.store');
        Route::post('/pos/sales', [PosController::class, 'sale'])->middleware('subscription.writable')->name('app.pos.sales.store');
        Route::get('/pos/receipts/{publicId}', [PosController::class, 'receipt'])->name('app.pos.receipt');
        Route::post('/pos/orders/{publicId}/refunds', [PosController::class, 'refund'])->middleware('subscription.writable')->name('app.pos.refunds.store');
        Route::post('/orders/{publicId}/manual-discount', [PosController::class, 'manualDiscount'])->middleware('subscription.writable')->name('app.orders.manual-discount');

        Route::get('/tables', [TablesBoardController::class, 'index'])->name('app.tables.index');
        Route::post('/tables/areas', [TablesBoardController::class, 'storeArea'])->middleware('subscription.writable')->name('app.tables.areas.store');
        Route::post('/tables/tables', [TablesBoardController::class, 'storeTable'])->middleware('subscription.writable')->name('app.tables.tables.store');
        Route::post('/tables/{publicId}/sessions', [TablesBoardController::class, 'openSession'])->middleware('subscription.writable')->name('app.tables.sessions.open');
        Route::post('/table-sessions/{publicId}/close', [TablesBoardController::class, 'closeSession'])->middleware('subscription.writable')->name('app.tables.sessions.close');
        Route::post('/table-sessions/{publicId}/transfer', [TablesBoardController::class, 'transfer'])->middleware('subscription.writable')->name('app.tables.sessions.transfer');
        Route::post('/orders/{publicId}/splits', [TablesBoardController::class, 'split'])->middleware('subscription.writable')->name('app.orders.splits.store');
        Route::post('/tables/{publicId}/qr', [TablesBoardController::class, 'storeQr'])->middleware('subscription.writable')->name('app.tables.qr.store');

        Route::get('/kitchen', [KitchenBoardController::class, 'index'])->name('app.kitchen.index');
        Route::get('/kitchen/poll', [KitchenBoardController::class, 'poll'])->name('app.kitchen.poll');
        Route::post('/kitchen/stations', [KitchenBoardController::class, 'storeStation'])->middleware('subscription.writable')->name('app.kitchen.stations.store');
        Route::post('/kitchen/tickets/{publicId}/start', [KitchenBoardController::class, 'start'])->middleware('subscription.writable')->name('app.kitchen.tickets.start');
        Route::post('/kitchen/tickets/{publicId}/ready', [KitchenBoardController::class, 'ready'])->middleware('subscription.writable')->name('app.kitchen.tickets.ready');
        Route::get('/reports/day', [DayReportController::class, 'show'])->name('app.reports.day');
        Route::get('/reports/tax', [FinanceReportController::class, 'tax'])->name('app.reports.tax');
        Route::get('/reports/shifts', [FinanceReportController::class, 'shifts'])->name('app.reports.shifts');
        Route::get('/reports/export/orders', [FinanceReportController::class, 'exportOrders'])->name('app.reports.export.orders');
        Route::get('/reports/export/expenses', [FinanceReportController::class, 'exportExpenses'])->name('app.reports.export.expenses');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('app.expenses.index');
        Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->middleware('subscription.writable')->name('app.expenses.categories.store');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('subscription.writable')->name('app.expenses.store');

        Route::get('/inventory', [InventoryController::class, 'index'])->name('app.inventory.index');
        Route::post('/inventory/units', [InventoryController::class, 'storeUnit'])->middleware('subscription.writable')->name('app.inventory.units.store');
        Route::post('/inventory/conversions', [InventoryController::class, 'storeConversion'])->middleware('subscription.writable')->name('app.inventory.conversions.store');
        Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->middleware('subscription.writable')->name('app.inventory.items.store');
        Route::post('/inventory/movements', [InventoryController::class, 'storeMovement'])->middleware('subscription.writable')->name('app.inventory.movements.store');
        Route::post('/inventory/recalculate', [InventoryController::class, 'recalculate'])->middleware('subscription.writable')->name('app.inventory.recalculate');
        Route::post('/inventory/stockout-setting', [InventoryController::class, 'updateStockoutSetting'])->middleware('subscription.writable')->name('app.inventory.stockout-setting');

        Route::get('/purchasing', [PurchasingController::class, 'index'])->name('app.purchasing.index');
        Route::post('/purchasing/suppliers', [PurchasingController::class, 'storeSupplier'])->middleware('subscription.writable')->name('app.purchasing.suppliers.store');
        Route::post('/purchasing/orders', [PurchasingController::class, 'storeOrder'])->middleware('subscription.writable')->name('app.purchasing.orders.store');
        Route::get('/purchasing/orders/{publicId}', [PurchasingController::class, 'show'])->name('app.purchasing.show');
        Route::put('/purchasing/orders/{publicId}', [PurchasingController::class, 'updateOrder'])->middleware('subscription.writable')->name('app.purchasing.update');
        Route::post('/purchasing/orders/{publicId}/receive', [PurchasingController::class, 'receive'])->middleware('subscription.writable')->name('app.purchasing.receive');

        Route::get('/stock-counts', [StockCountController::class, 'index'])->name('app.stock-counts.index');
        Route::post('/stock-counts', [StockCountController::class, 'store'])->middleware('subscription.writable')->name('app.stock-counts.store');
        Route::get('/stock-counts/{publicId}', [StockCountController::class, 'show'])->name('app.stock-counts.show');
        Route::post('/stock-counts/{publicId}/post', [StockCountController::class, 'post'])->middleware('subscription.writable')->name('app.stock-counts.post');

        Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('app.stock-transfers.index');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])->middleware('subscription.writable')->name('app.stock-transfers.store');
        Route::get('/stock-transfers/{publicId}', [StockTransferController::class, 'show'])->name('app.stock-transfers.show');
        Route::post('/stock-transfers/{publicId}/send', [StockTransferController::class, 'send'])->middleware('subscription.writable')->name('app.stock-transfers.send');
        Route::post('/stock-transfers/{publicId}/receive', [StockTransferController::class, 'receive'])->middleware('subscription.writable')->name('app.stock-transfers.receive');

        Route::get('/orders', [OrderBoardController::class, 'index'])->name('app.orders.index');
        Route::get('/orders/poll', [OrderBoardController::class, 'poll'])->name('app.orders.poll');
        Route::post('/orders/{publicId}/status', [OrderBoardController::class, 'status'])->middleware('subscription.writable')->name('app.orders.status');
        Route::post('/orders/{publicId}/cancel', [OrderBoardController::class, 'cancel'])->middleware('subscription.writable')->name('app.orders.cancel');

        Route::get('/reservations', [ReservationBoardController::class, 'index'])->name('app.reservations.index');
        Route::post('/reservations/{publicId}/confirm', [ReservationBoardController::class, 'confirm'])->middleware('subscription.writable')->name('app.reservations.confirm');
        Route::post('/reservations/{publicId}/cancel', [ReservationBoardController::class, 'cancel'])->middleware('subscription.writable')->name('app.reservations.cancel');
        Route::post('/reservations/{publicId}/no-show', [ReservationBoardController::class, 'noShow'])->middleware('subscription.writable')->name('app.reservations.no-show');
        Route::post('/dining-tables', [DiningTableController::class, 'store'])->middleware('subscription.writable')->name('app.dining-tables.store');

        Route::prefix('menu')->name('app.menu.')->group(function () {
            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('/categories', [CategoryController::class, 'store'])->middleware('subscription.writable')->name('categories.store');
            Route::get('/categories/{publicId}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{publicId}', [CategoryController::class, 'update'])->middleware('subscription.writable')->name('categories.update');

            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->middleware('subscription.writable')->name('products.store');
            Route::get('/products/{publicId}', [ProductController::class, 'show'])->name('products.show');
            Route::put('/products/{publicId}', [ProductController::class, 'update'])->middleware('subscription.writable')->name('products.update');
            Route::post('/products/{publicId}/branches', [ProductController::class, 'storeBranchSetting'])->middleware('subscription.writable')->name('products.branches.store');
            Route::post('/products/{publicId}/option-groups', [ProductController::class, 'storeOptionGroup'])->middleware('subscription.writable')->name('products.option-groups.store');
            Route::post('/products/{publicId}/images', [ProductController::class, 'storeImage'])->middleware('subscription.writable')->name('products.images.store');
            Route::post('/products/{publicId}/recipe', [ProductController::class, 'storeRecipe'])->middleware('subscription.writable')->name('products.recipe.store');
            Route::post('/option-groups/{groupPublicId}/options', [ProductController::class, 'storeOption'])->middleware('subscription.writable')->name('options.store');
            Route::post('/options/{optionPublicId}/branches', [ProductController::class, 'storeOptionBranchSetting'])->middleware('subscription.writable')->name('options.branches.store');

            Route::get('/theme', [ThemeController::class, 'edit'])->name('theme.edit');
            Route::put('/theme', [ThemeController::class, 'update'])->middleware('subscription.writable')->name('theme.update');
        });
    });
});

Route::get('/{slug}/menu/{branchPublicId}', [PublicMenuController::class, 'show'])
    ->where('slug', '[a-z0-9-]{3,40}')
    ->name('public.menu.show');
Route::get('/{slug}/menu/{branchPublicId}/qr.png', [PublicMenuController::class, 'qr'])
    ->where('slug', '[a-z0-9-]{3,40}')
    ->name('public.menu.qr');
Route::get('/{slug}/table/{tablePublicId}', [PublicTablePageController::class, 'show'])
    ->where('slug', '[a-z0-9-]{3,40}')
    ->name('public.table.show');
Route::get('/{slug}/orders/{orderPublicId}', [PublicOrderPageController::class, 'track'])
    ->where('slug', '[a-z0-9-]{3,40}')
    ->name('public.orders.track');
Route::get('/{slug}/reservations/{branchPublicId}', [PublicReservationPageController::class, 'show'])
    ->where('slug', '[a-z0-9-]{3,40}')
    ->name('public.reservations.show');
