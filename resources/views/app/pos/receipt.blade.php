<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('app.receipt') }} #{{ $order->number }}</title>
        <style>
            body { font-family: "IBM Plex Sans Arabic", "IBM Plex Sans", sans-serif; margin: 0; background: #f3f4f6; }
            .ticket { width: {{ (int) $template->paper_width }}mm; margin: 1rem auto; background: #fff; padding: 12px; font-size: 12px; }
            .center { text-align: center; }
            .row { display: flex; justify-content: space-between; gap: 8px; margin: 4px 0; }
            .muted { color: #555; }
            hr { border: 0; border-top: 1px dashed #999; margin: 8px 0; }
            @media print { body { background: #fff; } .ticket { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="ticket">
            <div class="center">
                <strong>{{ $template->header_text }}</strong>
                <div class="muted">{{ $order->branch?->name() }}</div>
            </div>
            <hr>
            <div class="row"><span>{{ __('app.invoice_number') }}</span><strong>#{{ $order->number }}</strong></div>
            <div class="row"><span>{{ __('app.date') }}</span><span>{{ $order->placed_at?->format('Y-m-d H:i') }}</span></div>
            <hr>
            @foreach ($order->items as $item)
                <div class="row">
                    <span>{{ app()->getLocale() === 'ar' ? $item->name_ar : $item->name_en }} × {{ $item->quantity }}</span>
                    <span>{{ $item->line_total }}</span>
                </div>
            @endforeach
            <hr>
            <div class="row"><span>{{ __('app.subtotal') }}</span><span>{{ $order->subtotal }}</span></div>
            @if ($template->show_tax)
                <div class="row"><span>{{ __('app.tax_total') }}</span><span>{{ $order->tax_total }}</span></div>
            @endif
            <div class="row"><strong>{{ __('app.total') }}</strong><strong>{{ $order->total }} {{ $order->currency_code }}</strong></div>
            <hr>
            @foreach ($order->payments as $payment)
                <div class="row"><span>{{ __('app.payment_'.$payment->method) }}</span><span>{{ $payment->amount }}</span></div>
            @endforeach
            <hr>
            <div class="center muted">{{ $template->footer_text }}</div>
        </div>
        <script>window.print();</script>
    </body>
</html>
