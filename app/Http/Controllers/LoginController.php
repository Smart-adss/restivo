<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('identity.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = mb_strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $existing = User::query()->where('email', $data['email'])->first();
        $remember = $request->boolean('remember') && ! $existing?->is_platform_user;

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $remember)) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $request->user();

        if ($user->status !== 'active') {
            return $this->reject($request, __('auth.disabled'));
        }

        if ($user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if (! $tenant || $tenant->status !== 'active') {
                return $this->reject($request, __('auth.suspended'));
            }
        }

        RateLimiter::clear($key);
        $requestLocale = $request->session()->get('locale');
        $request->session()->regenerate();
        $request->session()->put('locale', $user->locale ?: 'ar');

        if (is_string($requestLocale) && in_array($requestLocale, ['ar', 'en'], true)) {
            $request->session()->put('locale', $requestLocale);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->is_platform_user && config('restivo.require_platform_totp')) {
            $request->session()->forget('platform_totp_ok');

            $destination = $user->totp_confirmed_at
                ? route('platform.totp.challenge')
                : route('platform.totp.setup');
        } elseif ($user->is_platform_user) {
            $destination = route('platform.tenants.index');
        } else {
            $destination = route('app.home');
        }

        return redirect()->to($destination);
    }

    private function reject(Request $request, string $message): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
