<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\BuildShiftCashReport;
use App\Domain\Finance\Actions\BuildTaxReport;
use App\Domain\Finance\Actions\CreateExpense;
use App\Domain\Finance\Actions\CreateExpenseCategory;
use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Pos\Actions\CloseShift;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Pos\Actions\RecordCashMovement;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Feature;
use App\Models\Order;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceSprintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_expense_requires_date_and_positive_amount(): void
    {
        [$owner, $branch] = $this->seedFinance('alpha');
        $this->actingAs($owner);

        $category = app(CreateExpenseCategory::class)->handle([
            'name_ar' => 'إيجار',
            'name_en' => 'Rent',
        ]);

        try {
            app(CreateExpense::class)->handle([
                'branch_id' => $branch->id,
                'category_id' => $category->id,
                'amount' => '100',
                'incurred_on' => '',
            ]);
            $this->fail('Expense without date was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('incurred_on', $exception->errors());
        }

        try {
            app(CreateExpense::class)->handle([
                'branch_id' => $branch->id,
                'category_id' => $category->id,
                'amount' => '0',
                'incurred_on' => now()->toDateString(),
            ]);
            $this->fail('Expense without amount was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $expense = app(CreateExpense::class)->handle([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'amount' => '250.50',
            'tax_amount' => '10',
            'incurred_on' => now()->toDateString(),
            'note' => 'شهر نوفمبر',
        ]);

        $this->assertSame('250.50', (string) $expense->amount);
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_tax_report_uses_stored_totals_not_current_product_price(): void
    {
        [$owner, $branch, $register, $product] = $this->seedFinance('beta', withPos: true);
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $date = now('Africa/Cairo')->toDateString();
        $before = app(BuildTaxReport::class)->handle($branch, $date, $date);
        $this->assertSame('14.00', $before['tax_total']);
        $this->assertSame(1, $before['orders_count']);

        $product->forceFill(['base_price' => '500.00'])->save();

        $after = app(BuildTaxReport::class)->handle($branch, $date, $date);
        $this->assertSame($before['tax_total'], $after['tax_total']);
        $this->assertSame('14.00', $after['tax_total']);
        $this->assertSame('100.00', (string) Order::query()->value('subtotal'));
        $this->assertSame('14.00', (string) Order::query()->value('tax_total'));
    }

    public function test_shift_cash_report_matches_movements(): void
    {
        [$owner, $branch, $register, $product] = $this->seedFinance('gamma', withPos: true);
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '100.00');
        app(RecordCashMovement::class)->handle($shift, 'pay_in', '20.00', 'float');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);
        app(CloseShift::class)->handle($shift->fresh(), '234.00');

        $rows = app(BuildShiftCashReport::class)->handle($branch);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['matches_movements']);
        $this->assertSame('234.00', $rows[0]['expected_cash']);
        $this->assertSame('134.00', $rows[0]['movements_total']);
        $this->assertSame('0.00', $rows[0]['difference']);
    }

    public function test_csv_exports_are_audited(): void
    {
        [$owner, $branch, $register, $product] = $this->seedFinance('delta', withPos: true);
        $this->actingAs($owner);

        $category = app(CreateExpenseCategory::class)->handle([
            'name_ar' => 'مرافق',
            'name_en' => 'Utilities',
        ]);
        app(CreateExpense::class)->handle([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'amount' => '50.00',
            'incurred_on' => now()->toDateString(),
        ]);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $date = now('Africa/Cairo')->toDateString();

        $this->get(route('app.reports.export.orders', [
            'branch_id' => $branch->id,
            'from' => $date,
            'to' => $date,
        ]))->assertOk();

        $this->get(route('app.reports.export.expenses', [
            'branch_id' => $branch->id,
            'from' => $date,
            'to' => $date,
        ]))->assertOk();

        $this->assertTrue(
            AuditLog::query()->where('action', 'export.orders_csv')->exists()
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'export.expenses_csv')->exists()
        );
    }

    /**
     * @return array{0: User, 1: \App\Models\Branch, 2?: CashRegister, 3?: \App\Models\Product}
     */
    private function seedFinance(string $slug, bool $withPos = false): array
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة '.$slug,
            'display_name_ar' => 'مطعم '.$slug,
            'display_name_en' => 'Restaurant '.$slug,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'phone' => '+201000000000',
            'owner_name' => 'مالك '.$slug,
            'owner_email' => 'owner-'.$slug.'@example.com',
            'branch_name_ar' => 'الفرع الرئيسي',
            'branch_name_en' => 'Main branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع التحرير',
            'plan_code' => 'pro',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);
        app(FeatureGate::class)->flush();

        if ($withPos) {
            $featureId = Feature::query()->where('key', 'module.pos')->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
            app(FeatureGate::class)->flush();
        }

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();

        if (! $withPos) {
            return [$owner, $created['branch']];
        }

        $this->actingAs($owner);
        $tax = TaxRate::query()->create([
            'name' => 'VAT',
            'rate_percent' => '14.000',
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);
        $category = app(CreateCategory::class)->handle([
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
        ]);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'tax_rate_id' => $tax->id,
            'name_ar' => 'شاي',
            'name_en' => 'Tea',
            'base_price' => '100.00',
        ]);
        $register = CashRegister::query()->create([
            'branch_id' => $created['branch']->id,
            'name' => 'صندوق 1',
            'is_active' => true,
        ]);

        return [$owner, $created['branch'], $register, $product];
    }
}
