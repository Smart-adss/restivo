<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('app.book_table') }} · {{ $branch->name() }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <main class="mx-auto w-full max-w-xl px-4 py-8">
            <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ __('app.book_table') }}</h1>
            <p class="mt-1 text-sm text-ink/70">{{ $branch->name() }}</p>

            <form id="reservation-form" class="mt-6 space-y-4">
                <label class="block text-sm">{{ __('app.date') }}
                    <input id="date" type="date" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                </label>
                <label class="block text-sm">{{ __('app.time_slot') }}
                    <select id="slot" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                        <option value="">{{ __('app.choose_slot') }}</option>
                    </select>
                </label>
                <label class="block text-sm">{{ __('app.guest_count') }}
                    <input id="guest_count" type="number" min="1" max="30" value="2" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                </label>
                <label class="block text-sm">{{ __('app.customer_name') }}
                    <input id="customer_name" type="text" maxlength="120" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                </label>
                <label class="block text-sm">{{ __('app.customer_phone') }}
                    <input id="customer_phone" type="tel" placeholder="+2010..." required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3" dir="ltr">
                </label>
                <label class="block text-sm">{{ __('app.notes') }}
                    <textarea id="notes" maxlength="500" class="mt-1 w-full rounded-lg border border-line px-3 py-2" rows="3"></textarea>
                </label>
                <p id="form-error" class="hidden text-sm text-danger"></p>
                <p id="form-success" class="hidden text-sm text-brand"></p>
                <button type="submit" class="w-full min-h-11 rounded-lg bg-brand font-semibold text-brand-ink">{{ __('app.submit_reservation') }}</button>
            </form>

            <p class="mt-6 text-sm">
                <a href="{{ $whatsapp_url }}" target="_blank" rel="noopener" class="underline">{{ __('app.whatsapp_guest') }}</a>
            </p>
        </main>
        <script>
            (() => {
                const slotsUrl = @json($slots_url);
                const storeUrl = @json($store_url);
                const branchId = @json($branch->public_id);
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const dateEl = document.getElementById('date');
                const slotEl = document.getElementById('slot');
                const errorEl = document.getElementById('form-error');
                const successEl = document.getElementById('form-success');

                dateEl.value = new Date().toISOString().slice(0, 10);

                async function loadSlots() {
                    slotEl.innerHTML = `<option value="">{{ __('app.choose_slot') }}</option>`;
                    if (!dateEl.value) return;
                    try {
                        const { data } = await window.axios.get(slotsUrl, {
                            params: { date: dateEl.value },
                            headers: { Accept: 'application/json' },
                        });
                        (data.data.slots || []).forEach((slot) => {
                            const option = document.createElement('option');
                            option.value = slot;
                            option.textContent = slot.slice(11, 16);
                            slotEl.appendChild(option);
                        });
                    } catch (e) {
                        errorEl.textContent = 'Slots unavailable';
                        errorEl.classList.remove('hidden');
                    }
                }

                dateEl.addEventListener('change', loadSlots);
                loadSlots();

                document.getElementById('reservation-form').addEventListener('submit', async (event) => {
                    event.preventDefault();
                    errorEl.classList.add('hidden');
                    successEl.classList.add('hidden');
                    try {
                        const { data } = await window.axios.post(storeUrl, {
                            branch_id: branchId,
                            reserved_at: slotEl.value,
                            guest_count: Number(document.getElementById('guest_count').value),
                            customer_name: document.getElementById('customer_name').value,
                            customer_phone: document.getElementById('customer_phone').value,
                            notes: document.getElementById('notes').value || null,
                        }, {
                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        });
                        successEl.textContent = `{{ __('app.reservation_submitted') }} (${data.data.status})`;
                        successEl.classList.remove('hidden');
                    } catch (error) {
                        errorEl.textContent = error.response?.data?.message
                            || Object.values(error.response?.data?.errors || {}).flat()[0]
                            || 'Failed';
                        errorEl.classList.remove('hidden');
                    }
                });
            })();
        </script>
    </body>
</html>
