<?php

namespace App\Domain\Identity\Livewire;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $remember = $this->remember;
        $existing = User::query()->where('email', $this->email)->first();

        if ($existing?->is_platform_user) {
            $remember = false;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $remember)) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('auth.disabled'),
            ]);
        }

        RateLimiter::clear($key);

        if ($user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if (! $tenant || $tenant->status !== 'active') {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => __('auth.suspended'),
                ]);
            }
        }

        session()->regenerate();
        $requestLocale = session('locale');
        session(['locale' => $user->locale ?: 'ar']);

        if (is_string($requestLocale) && in_array($requestLocale, ['ar', 'en'], true)) {
            session(['locale' => $requestLocale]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $destination = $user->is_platform_user
            ? route('platform.tenants.index')
            : route('app.home');

        $this->redirect($destination, navigate: false);
    }

    public function render()
    {
        return view('identity.login');
    }

    private function throttleKey(): string
    {
        return mb_strtolower($this->email).'|'.request()->ip();
    }
}
