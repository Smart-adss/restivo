<?php

namespace Tests\Feature;

use App\Domain\Messaging\Actions\QueueOutboundMessage;
use App\Domain\Reservations\Actions\ConfirmReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\ListReservationSlots;
use App\Domain\Reservations\Actions\MarkReservationNoShow;
use App\Domain\Reservations\ReservationStatus;
use App\Domain\Reservations\Support\TableConflict;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\DiningTable;
use App\Models\Feature;
use App\Models\OutboundMessage;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use App\Support\WhatsApp;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00', 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_slots_exclude_times_outside_opening_hours_and_too_soon(): void
    {
        [$tenant, $branch] = $this->seedTenant('alpha');

        DB::table('operating_hours')
            ->where('branch_id', $branch->id)
            ->where('day_of_week', Carbon::now('Africa/Cairo')->dayOfWeekIso - 1)
            ->update([
                'opens_at' => '12:00:00',
                'closes_at' => '15:00:00',
                'is_closed' => false,
            ]);

        $slots = app(ListReservationSlots::class)->handle($branch, '2026-11-17');

        $this->assertNotContains('2026-11-17 11:00:00', $slots);
        $this->assertContains('2026-11-17 12:00:00', $slots);
        $this->assertContains('2026-11-17 13:30:00', $slots);
        $this->assertNotContains('2026-11-17 14:00:00', $slots);
    }

    public function test_public_reservation_is_pending_unless_auto_confirm_is_enabled(): void
    {
        [$tenant, $branch] = $this->seedTenant('alpha');

        $pending = $this->postJson(route('api.public.reservations.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'customer_name' => 'أحمد',
            'customer_phone' => '+201012345678',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 14:00:00',
        ])->assertCreated()->json('data');

        $this->assertSame(ReservationStatus::PENDING, $pending['status']);
        $this->assertDatabaseHas('reservations', [
            'public_id' => $pending['id'],
            'status' => ReservationStatus::PENDING,
        ]);

        DB::table('branch_settings')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'key' => 'reservations.auto_confirm',
            'value' => json_encode(true),
        ]);

        $confirmed = $this->postJson(route('api.public.reservations.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'customer_name' => 'سارة',
            'customer_phone' => '+201011111111',
            'guest_count' => 3,
            'reserved_at' => '2026-11-17 15:00:00',
        ])->assertCreated()->json('data');

        $this->assertSame(ReservationStatus::CONFIRMED, $confirmed['status']);
    }

    public function test_table_conflict_returns_409_and_smtp_failure_keeps_confirmation(): void
    {
        [$tenant, $branch] = $this->seedTenant('alpha');
        $owner = User::query()->where('email', 'owner-alpha@example.com')->firstOrFail();
        $this->actingAs($owner);

        $table = DiningTable::query()->create([
            'branch_id' => $branch->id,
            'name' => 'T1',
            'seats' => 4,
            'status' => 'available',
        ]);

        $first = app(CreateReservation::class)->handle([
            'branch_id' => $branch->id,
            'customer_name' => 'أحمد',
            'customer_phone' => '+201022222222',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 14:00:00',
            'source' => 'staff',
        ]);

        Mail::fake();
        app(ConfirmReservation::class)->handle($first, [$table->id]);
        $this->assertSame(ReservationStatus::CONFIRMED, $first->fresh()->status);
        $this->assertSame(1, OutboundMessage::query()->count());

        $second = app(CreateReservation::class)->handle([
            'branch_id' => $branch->id,
            'customer_name' => 'منى',
            'customer_phone' => '+201033333333',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 14:30:00',
            'source' => 'staff',
        ]);

        try {
            app(ConfirmReservation::class)->handle($second, [$table->id]);
            $this->fail('Overlapping table assignment was accepted.');
        } catch (TableConflict $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(ReservationStatus::CONFIRMED, $second->fresh()->status);
        $this->assertSame(0, $second->fresh()->tables()->count());
    }

    public function test_outbound_dedupe_key_prevents_duplicate_sends(): void
    {
        [$tenant, $branch] = $this->seedTenant('alpha');
        $owner = User::query()->where('email', 'owner-alpha@example.com')->firstOrFail();
        $this->actingAs($owner);
        Mail::fake();

        $reservation = app(CreateReservation::class)->handle([
            'branch_id' => $branch->id,
            'customer_name' => 'أحمد',
            'customer_phone' => '+201044444444',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 16:00:00',
            'source' => 'staff',
        ]);

        app(QueueOutboundMessage::class)->reservationConfirmed($reservation);
        app(QueueOutboundMessage::class)->reservationConfirmed($reservation);

        $this->assertSame(1, OutboundMessage::query()->count());
        Mail::assertSentCount(1);
    }

    public function test_whatsapp_link_is_generated_without_storing_a_code(): void
    {
        $url = WhatsApp::meUrl('+201012345678', 'مرحبا');

        $this->assertStringStartsWith('https://wa.me/201012345678?text=', $url);
        $this->assertDatabaseCount('outbound_messages', 0);
    }

    public function test_no_show_has_no_financial_side_effects_and_keeps_the_row(): void
    {
        [$tenant, $branch] = $this->seedTenant('alpha');
        $owner = User::query()->where('email', 'owner-alpha@example.com')->firstOrFail();
        $this->actingAs($owner);

        $reservation = app(CreateReservation::class)->handle([
            'branch_id' => $branch->id,
            'customer_name' => 'أحمد',
            'customer_phone' => '+201055555555',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 12:00:00',
            'source' => 'staff',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-17 13:00:00', 'Africa/Cairo'));
        app(MarkReservationNoShow::class)->handle($reservation);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::NO_SHOW,
        ]);
        $this->assertSame(1, Reservation::query()->count());
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manager_day_calendar_lists_reservations(): void
    {
        [$tenant, $branch] = $this->seedTenant('alpha');
        $owner = User::query()->where('email', 'owner-alpha@example.com')->firstOrFail();

        app(CreateReservation::class)->handle([
            'branch_id' => $branch->id,
            'customer_name' => 'أحمد',
            'customer_phone' => '+201066666666',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 17:00:00',
            'source' => 'staff',
        ]);

        $this->actingAs($owner)
            ->get(route('app.reservations.index', [
                'branch_id' => $branch->id,
                'date' => '2026-11-17',
            ]))
            ->assertOk()
            ->assertSee('أحمد')
            ->assertSee('wa.me', false);
    }

    /**
     * @return array{0: \App\Models\Tenant, 1: \App\Models\Branch}
     */
    private function seedTenant(string $slug): array
    {
        $created = app(CreateTenant::class)->handle($this->payload($slug));
        app(TenantContext::class)->set($created['tenant']->id);

        $featureId = Feature::query()->where('key', 'module.reservations')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '1']);
        app(FeatureGate::class)->flush();

        return [$created['tenant']->fresh(), $created['branch']];
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
            'phone' => '+201000000000',
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
