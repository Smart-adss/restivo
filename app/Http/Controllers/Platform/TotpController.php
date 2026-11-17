<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TotpController extends Controller
{
    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_platform_user, 403);

        if ($user->totp_confirmed_at) {
            return redirect()->route('platform.tenants.index');
        }

        if (! $request->session()->has('totp_setup_secret')) {
            $request->session()->put('totp_setup_secret', Totp::generateSecret());
        }

        $secret = (string) $request->session()->get('totp_setup_secret');

        return view('platform.totp.setup', [
            'secret' => $secret,
            'uri' => Totp::otpAuthUri($secret, $user->email, config('app.name', 'Restivo')),
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_platform_user, 403);

        $secret = (string) $request->session()->get('totp_setup_secret');
        abort_if($secret === '', 400);

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! Totp::verify($secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => __('auth.totp_invalid'),
            ]);
        }

        $user->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('totp_setup_secret');
        $request->session()->put('platform_totp_ok', true);

        return redirect()->route('platform.tenants.index')
            ->with('status', __('auth.totp_enabled'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_platform_user, 403);

        if (! $user->totp_confirmed_at) {
            return redirect()->route('platform.totp.setup');
        }

        if ($request->session()->get('platform_totp_ok')) {
            return redirect()->route('platform.tenants.index');
        }

        return view('platform.totp.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_platform_user && $user->totp_secret, 403);

        $key = 'platform-totp|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! Totp::verify((string) $user->totp_secret, $data['code'])) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages([
                'code' => __('auth.totp_invalid'),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->put('platform_totp_ok', true);

        return redirect()->intended(route('platform.tenants.index'));
    }
}
