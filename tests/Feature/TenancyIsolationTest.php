<?php

namespace Tests\Feature;

use App\Domain\Identity\PermissionCatalog;
use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_permission_catalog_matches_the_seed_and_cashier_cannot_refund(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(PermissionCatalog::all()),
            Permission::query()->pluck('key')->all(),
        );

        $cashier = Role::withoutGlobalScope('tenant')->whereNull('tenant_id')->where('key', 'cashier')->first();
        $refund = Permission::query()->where('key', 'pos.refund.create')->first();

        $this->assertFalse($cashier->permissions()->whereKey($refund->id)->exists());
    }

    public function test_editing_a_tenant_role_does_not_change_the_template(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        $copy = Role::withoutGlobalScope('tenant')
            ->where('tenant_id', $created['tenant']->id)
            ->where('key', 'cashier')
            ->first();

        $copy->name_ar = 'كاشير معدل';
        $copy->save();

        $template = Role::withoutGlobalScope('tenant')->whereNull('tenant_id')->where('key', 'cashier')->first();

        $this->assertSame('كاشير', $template->name_ar);
        $this->assertSame('كاشير معدل', $copy->fresh()->name_ar);
    }

    public function test_composite_foreign_key_rejects_a_branch_setting_from_another_tenant(): void
    {
        $first = app(CreateTenant::class)->handle($this->payload('alpha'));
        $second = app(CreateTenant::class)->handle($this->payload('beta'));

        try {
            DB::table('branch_settings')->insert([
                'tenant_id' => $second['tenant']->id,
                'branch_id' => $first['branch']->id,
                'key' => 'order.allow_outside_hours',
                'value' => json_encode(true),
            ]);
            $this->fail('A composite foreign key accepted a mismatched tenant.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('branch_settings', [
                'branch_id' => $first['branch']->id,
            ]);
        }
    }

    public function test_platform_admin_creates_a_tenant_and_a_cashier_only_sees_assigned_branches(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $response = $this->actingAs($admin)->post(route('platform.tenants.store'), $this->payload('alpha') + [
            'plan_code' => 'pro',
            'interval' => 'monthly',
        ]);
        $response->assertRedirect();
        $password = session('temporary_password');
        $this->assertNotEmpty($password);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($password);

        $owner = User::query()->where('email', 'owner-alpha@example.com')->first();
        app(TenantContext::class)->set($owner->tenant_id);
        $second = app(CreateBranch::class)->handle([
            'name_ar' => 'فرع المعادي',
            'name_en' => 'Maadi',
            'code' => 'MAADI',
            'city' => 'القاهرة',
            'address' => 'المعادي',
        ]);

        $this->actingAs($owner)->post(route('app.staff.store'), [
            'name' => 'كاشير الفرع',
            'email' => 'cashier-alpha@example.com',
            'password' => 'Password123',
            'role_key' => 'cashier',
            'branch_ids' => [Branch::query()->where('code', 'MAIN')->value('id')],
        ])->assertRedirect(route('app.staff.index'));

        $cashier = User::query()->where('email', 'cashier-alpha@example.com')->first();
        $main = Branch::withoutGlobalScope('tenant')->where('code', 'MAIN')->where('tenant_id', $owner->tenant_id)->first();

        $this->actingAs($cashier)
            ->get(route('app.home'))
            ->assertOk()
            ->assertSee('الفرع الرئيسي')
            ->assertDontSee('فرع المعادي');

        $this->actingAs($cashier)->get(route('app.branches.show', $main->public_id))->assertOk();
        $this->actingAs($cashier)->get(route('app.branches.show', $second->public_id))->assertNotFound();
        $this->actingAs($cashier)->get(route('platform.tenants.index'))->assertForbidden();
        $this->actingAs($cashier)->post(route('app.staff.store'), [])->assertForbidden();
        $this->actingAs($owner)->get(route('platform.tenants.index'))->assertForbidden();
    }

    public function test_a_request_tenant_id_cannot_move_a_branch_to_another_tenant(): void
    {
        $alpha = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'pro']);
        $beta = app(CreateTenant::class)->handle($this->payload('beta'));

        $this->actingAs($alpha['owner'])->post(route('app.branches.store'), [
            'tenant_id' => $beta['tenant']->id,
            'name_ar' => 'فرع ثالث',
            'name_en' => 'Third',
            'code' => 'THIRD',
            'city' => 'الجيزة',
            'address' => 'الهرم',
        ])->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'code' => 'THIRD',
            'tenant_id' => $alpha['tenant']->id,
        ]);
        $this->assertDatabaseMissing('branches', [
            'code' => 'THIRD',
            'tenant_id' => $beta['tenant']->id,
        ]);
    }

    public function test_a_branch_from_another_tenant_is_not_found(): void
    {
        $alpha = app(CreateTenant::class)->handle($this->payload('alpha'));
        $beta = app(CreateTenant::class)->handle($this->payload('beta'));

        $this->actingAs($alpha['owner'])
            ->get(route('app.branches.show', $beta['branch']->public_id))
            ->assertNotFound();
    }

    public function test_suspended_tenant_staff_cannot_operate(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->actingAs($admin)
            ->post(route('platform.tenants.suspend', $created['tenant']->public_id))
            ->assertRedirect();

        $this->assertSame('suspended', $created['tenant']->fresh()->status);

        $this->actingAsGuest();

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), [
                'email' => $created['owner']->email,
                'password' => $created['password'],
            ])
            ->assertSee('المنشأة معلقة');

        $this->assertGuest();

        $this->actingAs($created['owner'])
            ->get(route('app.home'))
            ->assertForbidden();
    }

    public function test_owner_can_update_profile(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));

        $this->actingAs($created['owner'])->put(route('app.profile.update'), [
            'name' => 'اسم جديد',
            'phone' => '01011111111',
            'locale' => 'en',
        ])->assertRedirect(route('app.profile'));

        $this->assertSame('en', $created['owner']->fresh()->locale);
        $this->assertSame('اسم جديد', $created['owner']->fresh()->name);
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $slug): array
    {
        return [
            'legal_name' => 'شركة '.$slug,
            'display_name_ar' => 'مطعم '.$slug,
            'display_name_en' => 'Restaurant '.$slug,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'phone' => '01000000000',
            'owner_name' => 'مالك '.$slug,
            'owner_email' => 'owner-'.$slug.'@example.com',
            'branch_name_ar' => 'الفرع الرئيسي',
            'branch_name_en' => 'Main branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع التحرير',
        ];
    }
}
