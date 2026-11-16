<?php

use App\Domain\Identity\Livewire\Login;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Platform\AuditController;
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
        Route::get('/staff', [StaffController::class, 'index'])->name('app.staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('app.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->middleware('subscription.writable')->name('app.staff.store');
        Route::get('/subscription', [SubscriptionController::class, 'show'])->name('app.subscription');
        Route::get('/support', [SupportTicketController::class, 'index'])->name('app.support.index');
        Route::post('/support', [SupportTicketController::class, 'store'])->name('app.support.store');
        Route::get('/pos', [PosController::class, 'show'])->name('app.pos');
        Route::post('/pos', [PosController::class, 'store'])->middleware('subscription.writable')->name('app.pos.store');
    });
});
