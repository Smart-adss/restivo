<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_defaults_to_arabic_rtl(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول');
    }

    public function test_english_locale_switches_direction_to_ltr(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Sign in');
    }

    public function test_failed_login_uses_a_generic_message(): void
    {
        User::factory()->create(['email' => 'cashier@example.com']);

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'missing@example.com',
                'password' => 'Password123',
            ])
            ->assertSee('بيانات الدخول غير صحيحة.');

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'cashier@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSee('بيانات الدخول غير صحيحة.');
    }

    public function test_login_locks_after_five_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login.store'), [
                'email' => 'cashier@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'cashier@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSee('محاولات كثيرة');
    }

    public function test_active_user_can_sign_in_and_session_is_stored(): void
    {
        config(['session.driver' => 'database']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');

        $user = User::factory()->create([
            'email' => 'cashier@example.com',
            'password' => 'Password123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'cashier@example.com',
            'password' => 'Password123',
        ])->assertRedirect(route('app.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_disabled_user_cannot_sign_in(): void
    {
        User::factory()->create([
            'email' => 'cashier@example.com',
            'password' => 'Password123',
            'status' => 'disabled',
        ]);

        $this->followingRedirects()
            ->post(route('login.store'), [
                'email' => 'cashier@example.com',
                'password' => 'Password123',
            ])
            ->assertSee('هذا الحساب موقوف.');
        $this->assertGuest();
    }

    public function test_platform_login_respects_the_totp_gate(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123',
            'is_platform_user' => true,
        ]);

        config(['restivo.require_platform_totp' => false]);

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ])->assertRedirect(route('platform.tenants.index'));

        $this->assertAuthenticated();

        $this->post(route('logout'));

        config(['restivo.require_platform_totp' => true]);

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ])->assertRedirect(route('platform.totp.setup'));
    }

    public function test_guests_cannot_open_the_shell(): void
    {
        $this->get(route('app.home'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_a_tenant_cannot_open_operations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.home'))
            ->assertForbidden()
            ->assertSee('التشغيل غير متاح');
    }
}
