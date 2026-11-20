@extends('layouts.public')

@section('title', __('app.book_table').' · '.$branch->name())

@section('body')
    <main class="mx-auto w-full max-w-xl px-4 py-8">
        <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('app.book_table') }}</h1>
        <p class="mt-1 text-sm text-ink/70">{{ $branch->name() }}</p>

        <form id="reservation-form" class="ui-panel mt-6 space-y-4 px-4 py-5">
            <div>
                <x-ui.label for="date">{{ __('app.date') }}</x-ui.label>
                <x-ui.field id="date" type="date" required />
            </div>
            <div>
                <x-ui.label for="slot">{{ __('app.time_slot') }}</x-ui.label>
                <x-ui.select id="slot" required>
                    <option value="">{{ __('app.choose_slot') }}</option>
                </x-ui.select>
            </div>
            <div>
                <x-ui.label for="guest_count">{{ __('app.guest_count') }}</x-ui.label>
                <x-ui.field id="guest_count" type="number" min="1" max="30" value="2" required class="tabular-nums" />
            </div>
            <div>
                <x-ui.label for="customer_name">{{ __('app.customer_name') }}</x-ui.label>
                <x-ui.field id="customer_name" type="text" maxlength="120" required />
            </div>
            <div>
                <x-ui.label for="customer_phone">{{ __('app.customer_phone') }}</x-ui.label>
                <x-ui.field id="customer_phone" type="tel" placeholder="+2010..." required dir="ltr" />
            </div>
            <div>
                <x-ui.label for="notes">{{ __('app.notes') }}</x-ui.label>
                <x-ui.textarea id="notes" maxlength="500" rows="3"></x-ui.textarea>
            </div>
            <p id="form-error" class="hidden text-sm text-danger"></p>
            <p id="form-success" class="hidden text-sm text-brand"></p>
            <x-ui.button type="submit" variant="primary" class="w-full min-h-12">{{ __('app.submit_reservation') }}</x-ui.button>
        </form>

        <p class="mt-6 text-sm">
            <a href="{{ $whatsapp_url }}" target="_blank" rel="noopener" class="font-semibold text-brand underline">{{ __('app.whatsapp_guest') }}</a>
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
@endsection
