<?php

namespace App\Domain\Messaging\Actions;

use App\Mail\TemplatedOutboundMail;
use App\Models\NotificationTemplate;
use App\Models\OutboundMessage;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;

class QueueOutboundMessage
{
    public function reservationConfirmed(Reservation $reservation): ?OutboundMessage
    {
        $tenant = Tenant::query()->find(app(TenantContext::class)->id());
        $recipient = $tenant?->email;

        if ($recipient === null || $recipient === '') {
            return null;
        }

        $dedupe = 'reservation:'.$reservation->id.':confirmed:email';

        try {
            $message = OutboundMessage::query()->create([
                'tenant_id' => $tenant->id,
                'channel' => 'email',
                'recipient' => $recipient,
                'template_key' => 'reservation.confirmed',
                'payload' => [
                    'reservation_id' => $reservation->public_id,
                    'customer_name' => $reservation->customer_name,
                    'reserved_at' => $reservation->reserved_at->toIso8601String(),
                    'guest_count' => $reservation->guest_count,
                ],
                'status' => 'queued',
                'dedupe_key' => $dedupe,
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            return OutboundMessage::query()->where('dedupe_key', $dedupe)->first();
        }

        $this->attemptSend($message, $reservation);

        return $message->fresh();
    }

    public function attemptSend(OutboundMessage $message, ?Reservation $reservation = null): OutboundMessage
    {
        if ($message->status === 'sent') {
            return $message;
        }

        $template = NotificationTemplate::query()
            ->where('channel', $message->channel)
            ->where('key', $message->template_key)
            ->where(function ($query) use ($message): void {
                $query->where('tenant_id', $message->tenant_id)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->orderByRaw('tenant_id is null')
            ->first();

        $body = $template?->body_ar
            ?? 'تم تأكيد الحجز {{customer_name}} في {{reserved_at}} لـ {{guest_count}} أشخاص.';

        foreach ($message->payload as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        try {
            Mail::to($message->recipient)->send(new TemplatedOutboundMail(
                subjectLine: 'تأكيد حجز Restivo',
                bodyText: $body,
            ));

            $message->forceFill([
                'status' => 'sent',
                'provider' => 'smtp',
                'sent_at' => now(),
                'error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $message->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 255),
            ])->save();
        }

        return $message->fresh();
    }
}
