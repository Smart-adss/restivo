<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Actions\SeedDefaultTaxRate;
use App\Models\AuditLog;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaxRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_a_new_tenant_starts_with_an_exclusive_fourteen_percent_rate(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);

        $rate = TaxRate::query()->first();

        $this->assertSame(SeedDefaultTaxRate::NAME, $rate->name);
        $this->assertSame('14.000', (string) $rate->rate_percent);
        $this->assertFalse($rate->is_inclusive);
        $this->assertTrue($rate->is_default);
        $this->assertFalse($this->pricesIncludeTax($created['tenant']->id));
    }

    public function test_the_owner_can_switch_the_default_rate_and_a_cashier_cannot(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);
        $rate = TaxRate::query()->first();

        $this->actingAs($created['owner'])->post(route('app.staff.store'), [
            'name' => 'كاشير',
            'email' => 'cashier-alpha@example.com',
            'password' => 'Password123',
            'role_key' => 'cashier',
            'branch_ids' => [$created['branch']->id],
        ])->assertRedirect();

        $cashier = User::query()->where('email', 'cashier-alpha@example.com')->first();

        $this->actingAs($cashier)->get(route('app.taxes.index'))->assertForbidden();
        $this->actingAs($cashier)->put(route('app.taxes.update', $rate->public_id), [])->assertForbidden();

        $this->actingAs($created['owner'])
            ->get(route('app.taxes.index'))
            ->assertOk()
            ->assertSee(SeedDefaultTaxRate::NAME);

        $this->actingAs($created['owner'])
            ->put(route('app.taxes.update', $rate->public_id), [
                'name' => 'ضريبة القيمة المضافة',
                'rate_percent' => '10',
                'is_inclusive' => '1',
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('app.taxes.index'))
            ->assertSessionHas('status', 'تم حفظ معدل الضريبة.');

        $rate->refresh();
        $this->assertSame('10.000', (string) $rate->rate_percent);
        $this->assertTrue($rate->is_inclusive);
        $this->assertTrue($this->pricesIncludeTax($created['tenant']->id));
        $this->assertTrue(
            AuditLog::query()->where('action', 'tax_rate.saved')->where('auditable_id', $rate->id)->exists(),
        );

        $this->actingAs($created['owner'])
            ->from(route('app.taxes.index'))
            ->put(route('app.taxes.update', $rate->public_id), [
                'name' => 'ضريبة القيمة المضافة',
                'rate_percent' => '10',
                'is_inclusive' => '1',
                'is_default' => '0',
                'is_active' => '0',
            ])
            ->assertRedirect(route('app.taxes.index'))
            ->assertSessionHasErrors('is_default');

        $this->actingAs($created['owner'])
            ->post(route('app.taxes.store'), [
                'name' => 'معفاة',
                'rate_percent' => '0',
                'is_inclusive' => '0',
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('app.taxes.index'));

        $this->assertFalse($rate->fresh()->is_default);
        $exempt = TaxRate::query()->where('name', 'معفاة')->first();
        $this->assertTrue($exempt->is_default);
        $this->assertFalse($this->pricesIncludeTax($created['tenant']->id));

        $other = app(CreateTenant::class)->handle($this->payload('beta'));

        $this->actingAs($other['owner'])
            ->put(route('app.taxes.update', $rate->public_id), [
                'name' => 'دخيلة',
                'rate_percent' => '5',
                'is_active' => '1',
            ])
            ->assertNotFound();
    }

    private function pricesIncludeTax(int $tenantId): bool
    {
        $value = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', 'tax.prices_include_tax')
            ->value('value');

        return filter_var(json_decode((string) $value, true), FILTER_VALIDATE_BOOLEAN);
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
