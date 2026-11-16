<?php

namespace Tests\Feature;

use App\Domain\Identity\Livewire\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        Livewire::test(Login::class)
            ->set('email', 'missing@example.com')
            ->set('password', 'Password123')
            ->call('authenticate')
            ->assertHasErrors('email');

        Livewire::test(Login::class)
            ->set('email', 'cashier@example.com')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertSee('بيانات الدخول غير صحيحة.');
    }

    public function test_login_locks_after_five_failures(): void
    {
        $component = Livewire::test(Login::class)->set('email', 'cashier@example.com');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->set('password', 'wrong-password')->call('authenticate');
        }

        $component->call('authenticate')
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

        Livewire::test(Login::class)
            ->set('email', 'cashier@example.com')
            ->set('password', 'Password123')
            ->call('authenticate')
            ->assertRedirect(route('app.home'));

        $this->assertAuthenticatedAs($user);
        // Livewire's test runner does not terminate the HTTP kernel, so flush the database session handler explicitly.
        app('session')->driver()->save();

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

        Livewire::test(Login::class)
            ->set('email', 'cashier@example.com')
            ->set('password', 'Password123')
            ->call('authenticate')
            ->assertSee('هذا الحساب موقوف.');

        $this->assertGuest();
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
