<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\MenuThemeCatalog;
use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuTheme;
use App\Models\OperatingHour;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BuildPublicMenu
{
    public function __construct(
        private FeatureGate $features,
        private EnsureTenantThemes $ensureTenantThemes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $slug, string $branchPublicId): array
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null || $tenant->status !== 'active') {
            abort(404);
        }

        app(TenantContext::class)->set($tenant->id);
        $this->features->flush();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        $branch = Branch::query()
            ->where('public_id', $branchPublicId)
            ->where('is_active', true)
            ->first();

        if ($branch === null) {
            abort(404);
        }

        $cached = Cache::remember(
            PublicMenuCache::key($branch->id),
            PublicMenuCache::TTL_SECONDS,
            fn (): array => $this->buildPayload($tenant, $branch),
        );

        return [
            'tenant' => $tenant,
            'branch' => $branch,
            ...$cached,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Tenant $tenant, Branch $branch): array
    {
        $this->ensureTenantThemes->handle();

        $theme = MenuTheme::query()->where('is_active', true)->first();
        $tokens = MenuThemeCatalog::sanitize($theme?->tokens ?? MenuThemeCatalog::all()['green']['tokens']);

        $now = Carbon::now($tenant->timezone);
        $day = $now->dayOfWeekIso - 1;
        $hours = OperatingHour::query()
            ->where('branch_id', $branch->id)
            ->where('day_of_week', $day)
            ->first();

        $closedToday = $hours === null || $hours->is_closed;
        $isOpen = false;

        if (! $closedToday && $hours->opens_at && $hours->closes_at) {
            $openAt = Carbon::parse($now->toDateString().' '.$hours->opens_at, $tenant->timezone);
            $closeAt = Carbon::parse($now->toDateString().' '.$hours->closes_at, $tenant->timezone);
            $isOpen = $now->betweenIncluded($openAt, $closeAt);
        }

        $products = Product::query()
            ->with(['branchSettings', 'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get()
            ->filter(fn (Product $product) => $product->isAvailableAt($branch))
            ->values();

        $categories = MenuCategory::query()
            ->visibleOnPublicMenu()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->roots()
            ->get();

        $byCategory = $products->groupBy('category_id');

        $payload = $categories->map(function (MenuCategory $category) use ($byCategory, $branch, $tenant): ?array {
            $items = ($byCategory->get($category->id) ?? collect())
                ->map(fn (Product $product) => $this->productPayload($product, $branch, $tenant))
                ->values();

            $children = $category->children->map(function (MenuCategory $child) use ($byCategory, $branch, $tenant): ?array {
                $childItems = ($byCategory->get($child->id) ?? collect())
                    ->map(fn (Product $product) => $this->productPayload($product, $branch, $tenant))
                    ->values();

                if ($childItems->isEmpty()) {
                    return null;
                }

                return [
                    'public_id' => $child->public_id,
                    'name_ar' => $child->name_ar,
                    'name_en' => $child->name_en,
                    'products' => $childItems->all(),
                ];
            })->filter()->values();

            if ($items->isEmpty() && $children->isEmpty()) {
                return null;
            }

            return [
                'public_id' => $category->public_id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'products' => $items->all(),
                'children' => $children->all(),
            ];
        })->filter()->values()->all();

        return [
            'theme' => $tokens,
            'categories' => $payload,
            'is_open' => $isOpen,
            'closed_today' => $closedToday,
            'opens_at' => $hours?->opens_at,
            'closes_at' => $hours?->closes_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, Branch $branch, Tenant $tenant): array
    {
        $hasImage = $product->images->first()?->path !== null;

        return [
            'public_id' => $product->public_id,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'description_ar' => $product->description_ar,
            'description_en' => $product->description_en,
            'price' => $product->priceAt($branch),
            'image_path' => $product->images->first()?->path,
            'image_url' => $hasImage
                ? route('public.menu.product-image', [
                    'slug' => $tenant->slug,
                    'productPublicId' => $product->public_id,
                ])
                : null,
        ];
    }
}
