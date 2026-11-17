<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        NotificationTemplate::query()->updateOrCreate(
            [
                'tenant_id' => null,
                'channel' => 'email',
                'key' => 'reservation.confirmed',
            ],
            [
                'body_ar' => 'تم تأكيد حجز {{customer_name}} في {{reserved_at}} لـ {{guest_count}} أشخاص. رقم الحجز {{reservation_id}}.',
                'body_en' => 'Reservation for {{customer_name}} at {{reserved_at}} for {{guest_count}} guests is confirmed. Ref {{reservation_id}}.',
                'is_active' => true,
            ],
        );
    }
}
