<?php

namespace App\Domain\Finance\Actions;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Refund;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuildEstimatedProfitReport
{
    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   net_sales: string,
     *   confirmed_sales: string,
     *   recipe_cogs: string,
     *   unlinked_purchases: string,
     *   expenses_total: string,
     *   estimated_profit: string,
     *   is_estimated: bool,
     *   missing_recipes: list<string>
     * }
     */
    public function handle(Branch $branch, string $from, string $to): array
    {
        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => __('app.report_date_range_required'),
            ]);
        }

        $timezone = $this->timezone();
        $start = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($to, $timezone)->endOfDay()->utc();

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('placed_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->with(['items'])
            ->get();

        $netSales = '0.00';
        $confirmedSales = '0.00';
        $recipeCogs = '0.00';
        $missing = [];
        $isEstimated = false;

        $productIds = $orders->flatMap(fn (Order $order) => $order->items->pluck('product_id'))->filter()->unique()->values();
        $recipes = Recipe::query()
            ->whereIn('product_id', $productIds)
            ->with(['lines.stockItem'])
            ->get()
            ->keyBy('product_id');
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($orders as $order) {
            $lineNet = Money::sub((string) $order->subtotal, (string) $order->discount_total);
            $netSales = Money::add($netSales, $lineNet);

            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $product = $products->get($item->product_id);
                $recipe = $recipes->get($item->product_id);
                $itemSales = Money::sub((string) $item->line_total, (string) $item->tax_amount);

                if ($recipe === null) {
                    $isEstimated = true;
                    if ($product !== null) {
                        $missing[$product->id] = $product->name();
                    }

                    continue;
                }

                $confirmedSales = Money::add($confirmedSales, $itemSales);

                $orderQty = number_format((float) $item->quantity, 3, '.', '');
                $yield = bccomp((string) $recipe->yield_qty, '0', 3) === 1
                    ? (string) $recipe->yield_qty
                    : '1.000';

                foreach ($recipe->lines as $line) {
                    $needed = bcdiv(bcmul((string) $line->quantity, $orderQty, 8), $yield, 8);
                    $needed = number_format((float) $needed, 3, '.', '');
                    $avg = BranchStock::query()
                        ->where('branch_id', $branch->id)
                        ->where('stock_item_id', $line->stock_item_id)
                        ->value('avg_cost');

                    if ($avg === null) {
                        $isEstimated = true;
                        $avg = '0.0000';
                    }

                    $lineCost = number_format((float) bcmul($needed, (string) $avg, 8), 2, '.', '');
                    $recipeCogs = Money::add($recipeCogs, $lineCost);
                }
            }
        }

        $orderIds = $orders->pluck('id');
        if ($orderIds->isNotEmpty()) {
            $refunds = Refund::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', 'completed')
                ->get();
            foreach ($refunds as $refund) {
                $netSales = Money::sub($netSales, (string) $refund->amount);
            }
        }

        $recipeStockItemIds = DB::table('recipe_lines')->pluck('stock_item_id')->unique()->all();

        $unlinkedPurchases = '0.00';
        $purchaseMovements = StockMovement::query()
            ->where('branch_id', $branch->id)
            ->where('kind', 'purchase')
            ->whereBetween('occurred_at', [$start, $end])
            ->get();

        foreach ($purchaseMovements as $movement) {
            if (in_array((int) $movement->stock_item_id, array_map('intval', $recipeStockItemIds), true)) {
                continue;
            }

            $qty = (string) $movement->quantity;
            $cost = $movement->unit_cost !== null ? (string) $movement->unit_cost : '0';
            $unlinkedPurchases = Money::add($unlinkedPurchases, number_format((float) bcmul($qty, $cost, 8), 2, '.', ''));
        }

        $expensesTotal = Money::of((string) Expense::query()
            ->where('branch_id', $branch->id)
            ->whereDate('incurred_on', '>=', $from)
            ->whereDate('incurred_on', '<=', $to)
            ->sum('amount'));

        $estimatedProfit = Money::sub(
            Money::sub(Money::sub($netSales, $recipeCogs), $unlinkedPurchases),
            $expensesTotal,
        );

        return [
            'from' => $from,
            'to' => $to,
            'net_sales' => $netSales,
            'confirmed_sales' => $confirmedSales,
            'recipe_cogs' => $recipeCogs,
            'unlinked_purchases' => $unlinkedPurchases,
            'expenses_total' => $expensesTotal,
            'estimated_profit' => $estimatedProfit,
            'is_estimated' => $isEstimated || $missing !== [],
            'missing_recipes' => array_values($missing),
        ];
    }

    private function timezone(): string
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return 'Africa/Cairo';
        }

        return (string) (Tenant::query()->whereKey($tenantId)->value('timezone') ?: 'Africa/Cairo');
    }
}
