export function posSale(config) {
    return {
        products: config.products,
        shiftId: config.shiftId,
        saleUrl: config.saleUrl,
        locale: config.locale,
        csrf: config.csrf,
        labels: config.labels,
        cart: [],
        cashAmount: '',
        cardAmount: '',
        pending: false,
        error: '',
        success: '',
        receiptUrl: '',
        idempotencyKey: null,

        lineLabel(line) {
            const product = this.products.find((item) => item.id === line.product_id);
            if (!product) return '';
            return this.locale === 'ar' ? product.name_ar : product.name_en;
        },

        addProduct(product) {
            for (const group of product.option_groups || []) {
                if (group.is_required && (!group.options || group.options.length === 0)) {
                    this.error = this.labels.requiredOptions;
                    return;
                }
                if (group.is_required) {
                    const defaults = group.options.filter((option) => option);
                    if (group.selection === 'single') {
                        // Require cashier to confirm required groups via prompt-less auto-pick first option.
                        // If multiple required groups exist, pick first option of each.
                    }
                }
            }

            const optionIds = [];
            for (const group of product.option_groups || []) {
                if (group.is_required && group.options.length > 0) {
                    if (group.options.length > 1 && group.selection === 'single') {
                        // Keep cart blocked until a single required choice is explicit: use first for speed in MVP grid,
                        // but validate count against min_select.
                        optionIds.push(group.options[0].id);
                    } else {
                        for (let i = 0; i < Math.max(1, group.min_select || 1); i++) {
                            if (group.options[i]) {
                                optionIds.push(group.options[i].id);
                            }
                        }
                    }
                }
            }

            for (const group of product.option_groups || []) {
                if (!group.is_required) {
                    continue;
                }
                const selected = optionIds.filter((id) => group.options.some((option) => option.id === id)).length;
                if (selected < Math.max(1, group.min_select || 1)) {
                    this.error = this.labels.requiredOptions;
                    return;
                }
            }

            this.error = '';
            this.cart.push({
                product_id: product.id,
                quantity: 1,
                option_ids: optionIds,
                product,
            });
        },

        paymentsPayload() {
            const payments = [];
            const cash = Number(this.cashAmount || 0);
            const card = Number(this.cardAmount || 0);
            if (cash > 0) {
                payments.push({ method: 'cash', amount: cash.toFixed(2) });
            }
            if (card > 0) {
                payments.push({ method: 'card', amount: card.toFixed(2) });
            }
            return payments;
        },

        newKey() {
            const time = Date.now().toString(16).padStart(12, '0');
            const rand = Array.from(crypto.getRandomValues(new Uint8Array(10)))
                .map((b) => b.toString(16).padStart(2, '0'))
                .join('');
            return (time + rand).slice(0, 26).toUpperCase();
        },

        async checkout() {
            if (this.pending || this.cart.length === 0) {
                return;
            }

            for (const line of this.cart) {
                const product = line.product || this.products.find((item) => item.id === line.product_id);
                for (const group of product?.option_groups || []) {
                    if (!group.is_required) {
                        continue;
                    }
                    const selected = (line.option_ids || []).filter((id) => group.options.some((option) => option.id === id)).length;
                    if (selected < Math.max(1, group.min_select || 1)) {
                        this.error = this.labels.requiredOptions;
                        return;
                    }
                }
            }

            if (!this.idempotencyKey) {
                this.idempotencyKey = this.newKey();
            }

            this.pending = true;
            this.error = '';
            this.success = '';

            try {
                const { data } = await window.axios.post(this.saleUrl, {
                    shift_id: this.shiftId,
                    idempotency_key: this.idempotencyKey,
                    type: 'dine_in',
                    items: this.cart.map((line) => ({
                        product_id: line.product_id,
                        quantity: line.quantity,
                        option_ids: line.option_ids || [],
                    })),
                    payments: this.paymentsPayload(),
                }, {
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                });

                this.success = `${this.labels.saleOk} #${data.data.number}`;
                this.receiptUrl = data.data.receipt_url;
                this.cart = [];
                this.cashAmount = '';
                this.cardAmount = '';
                this.idempotencyKey = null;
            } catch (error) {
                if (!error.response) {
                    this.error = this.labels.networkError;
                    // Keep cart and idempotency key for retry.
                } else if (error.response.status === 422 && error.response.data?.error?.fields?.total) {
                    this.error = `${error.response.data.message}: ${error.response.data.error.fields.total}`;
                    this.idempotencyKey = null;
                } else {
                    this.error = error.response.data?.message
                        || Object.values(error.response.data?.errors || {}).flat()[0]
                        || 'Sale failed';
                    if (error.response.status !== 409) {
                        this.idempotencyKey = null;
                    }
                }
            } finally {
                this.pending = false;
            }
        },
    };
}
