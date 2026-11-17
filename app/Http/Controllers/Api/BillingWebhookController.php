<?php

namespace App\Http\Controllers\Api;

use App\Domain\Subscription\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Support\Features\DuplicatePayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingWebhookController extends Controller
{
    public function __invoke(Request $request, RecordPayment $payments): JsonResponse
    {
        $secret = (string) config('services.billing.webhook_secret', '');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook disabled.'], 403);
        }

        $signature = (string) $request->header('X-Restivo-Signature', '');
        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $data = $request->validate([
            'invoice_public_id' => ['required', 'string'],
            'provider_ref' => ['required', 'string', 'max:191'],
            'method' => ['nullable', 'string', 'max:32'],
        ]);

        $invoice = SubscriptionInvoice::withoutGlobalScope('tenant')
            ->where('public_id', $data['invoice_public_id'])
            ->first();

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        app(TenantContext::class)->set((int) $invoice->tenant_id);

        try {
            $payments->handle(
                $invoice,
                $data['method'] ?? 'gateway',
                $data['provider_ref'],
                'webhook',
            );
        } catch (DuplicatePayment) {
            return response()->json(['message' => 'Already recorded.'], 200);
        }

        return response()->json(['message' => 'Recorded.'], 200);
    }
}
