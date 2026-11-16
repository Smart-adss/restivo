<?php

use App\Domain\Identity\Livewire\Login;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
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
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant:public_id}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('/tenants/{tenant:public_id}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{tenant:public_id}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
    });

    Route::middleware('tenant.operational')->prefix('app')->group(function () {
        Route::get('/', HomeController::class)->name('app.home');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('app.profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('app.profile.update');
        Route::get('/branches', [BranchController::class, 'index'])->name('app.branches.index');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('app.branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('app.branches.store');
        Route::get('/branches/{publicId}', [BranchController::class, 'show'])->name('app.branches.show');
        Route::get('/staff', [StaffController::class, 'index'])->name('app.staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('app.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('app.staff.store');
    });
});
