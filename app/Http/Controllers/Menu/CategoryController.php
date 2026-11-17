<?php

namespace App\Http\Controllers\Menu;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Support\Features\FeatureGate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(FeatureGate $features): View
    {
        $this->authorizeMenu($features, 'menu.categories.manage');

        return view('app.menu.categories.index', [
            'categories' => MenuCategory::query()->roots()->with('children')->get(),
        ]);
    }

    public function create(FeatureGate $features): View
    {
        $this->authorizeMenu($features, 'menu.categories.manage');

        return view('app.menu.categories.create', [
            'parents' => MenuCategory::query()->roots()->get(),
        ]);
    }

    public function store(Request $request, CreateCategory $createCategory, FeatureGate $features): RedirectResponse
    {
        $this->authorizeMenu($features, 'menu.categories.manage');

        $data = $this->validated($request);

        try {
            $category = $createCategory->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.menu.categories.index')
            ->with('status', __('app.category_saved'));
    }

    public function edit(string $publicId, FeatureGate $features): View
    {
        $this->authorizeMenu($features, 'menu.categories.manage');
        $category = $this->findCategory($publicId);

        return view('app.menu.categories.edit', [
            'category' => $category,
            'parents' => MenuCategory::query()->roots()->whereKeyNot($category->id)->get(),
        ]);
    }

    public function update(Request $request, string $publicId, UpdateCategory $updateCategory, FeatureGate $features): RedirectResponse
    {
        $this->authorizeMenu($features, 'menu.categories.manage');
        $category = $this->findCategory($publicId);
        $data = $this->validated($request);

        try {
            $updateCategory->handle($category, $data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.menu.categories.index')
            ->with('status', __('app.category_saved'));
    }

    /**
     * @return array{name_ar: string, name_en: string, parent_id: ?int, sort_order: int, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name_ar' => __('app.name_ar'),
            'name_en' => __('app.name_en'),
            'parent_id' => __('app.parent_category'),
            'sort_order' => __('app.sort_order'),
        ]);

        return [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function findCategory(string $publicId): MenuCategory
    {
        $category = MenuCategory::query()->where('public_id', $publicId)->first();

        if ($category === null) {
            abort(404);
        }

        return $category;
    }

    private function authorizeMenu(FeatureGate $features, string $permission): void
    {
        abort_unless(auth()->user()->hasPermission($permission), 403);

        if (! $features->allows('module.digital_menu')) {
            abort(404);
        }
    }
}
