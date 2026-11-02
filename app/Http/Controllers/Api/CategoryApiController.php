<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\DeliveryZoneService;
use App\Enums\Category\CategorySubCategoryFilterEnum;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

#[Group('Categories')]
class CategoryApiController extends Controller
{
    /**
     * Get categories base api.
     * If slug is not provided, returns root categories.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]
    #[QueryParameter('slug', description: 'Category slug to filter by', type: 'string', example: 'apple')]
    #[QueryParameter('search', description: 'Search term to filter categories', type: 'string', example: 'electronics')]
    #[QueryParameter('home', description: 'When true, returns only root categories marked as home categories, ordered by sort_order', type: 'boolean', example: true)]
    #[QueryParameter('include_no_product', description: 'When true, also return categories without product', type: 'boolean', example: 'false')]
    public function index(Request $request): JsonResponse
    {
        // Validate inputs
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'slug' => 'sometimes|string',
            'search' => 'sometimes|string',
            'home' => 'nullable',
        ]);

        $perPage = (int)$request->input('per_page', 15);
        $includeNoProduct = filter_var($request->input('include_no_product', false), FILTER_VALIDATE_BOOLEAN);

        // Base query: either children of slug or root categories
        $query = Category::query()->with(['parent', 'media'])->where('status', CategoryStatusEnum::ACTIVE());
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }
        $homeOnly = filter_var($request->input('home', false), FILTER_VALIDATE_BOOLEAN);

        if ($homeOnly) {
            // Root categories the admin picked for the home screen. If the admin has not picked any
            // yet, show every active root category so a new catalogue is never empty.
            $query->whereNull('parent_id');
            $hasHomePicks = Category::whereNull('parent_id')
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->where('is_home_category', true)
                ->exists();
            if ($hasHomePicks) {
                $query->where('is_home_category', true);
            }
        } elseif ($request->has('slug')) {
            $parentCategory = Category::where('slug', $request->input('slug'))
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->first();
            if (!$parentCategory) {
                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage));
            }
            $query->where('parent_id', $parentCategory->id);
        } else {
            $query->whereNull('parent_id');
        }

        // Zone context
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );

        // Admin-defined order first (sort_order), then title – new categories appear automatically
        $allCategories = $query->orderBy('sort_order')->orderBy('title')->get();

        // Visible-product counts incl. every level of sub-categories (2 queries total, no N+1)
        $processed = $this->attachCounts($allCategories, $useZoneFilter, $storeIds);

        if (!$includeNoProduct) {
            $processed = $this->filterNonZeroProducts($processed);
        }

        // Paginate and respond
        $paginator = $this->paginateCollection($processed, (int)$request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);
        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Get sub-categories.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('filter', description: 'Filter enum: random | top_category', type: 'string', example: 'random')]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]
    public function subCategories(Request $request): JsonResponse
    {
        // Validate inputs
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'filter' => 'nullable|string',
        ]);

        $perPage = (int)$request->input('per_page', 15);
//        $filter = is_null($request->input('filter')) ? CategorySubCategoryFilterEnum::RANDOM() : $request->input('filter');
        $filter = $request->input('filter');

        $active = CategoryStatusEnum::ACTIVE();
        $query = Category::query()
            ->with(['parent', 'media'])
            ->where('categories.status', $active);

        if ($filter === CategorySubCategoryFilterEnum::TOP_CATEGORY() && !$request->filled('slug')) {
            // Home "All" tab = every browsable leaf: sub-categories (of active parents) PLUS top-level
            // categories that have no sub-categories – so a flat catalogue is never shown as empty.
            $query->where(function ($q) use ($active) {
                $q->where(fn($w) => $w->whereNotNull('categories.parent_id')
                    ->whereHas('parent', fn($p) => $p->where('status', $active)))
                  ->orWhere(fn($w) => $w->whereNull('categories.parent_id')
                    ->whereDoesntHave('children', fn($c) => $c->where('status', $active)));
            });
        } else {
            $query->whereNotNull('categories.parent_id')
                // never show a sub-category whose parent the admin switched off
                ->whereHas('parent', fn($q) => $q->where('status', $active));
        }

        if ($request->filled('slug')) {
            // Sub-categories of one category
            $parent = Category::where('slug', $request->input('slug'))->where('status', CategoryStatusEnum::ACTIVE())->first();
            if (!$parent) {
                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, ['filter' => $filter]));
            }
            $query->where('categories.parent_id', $parent->id);
        }

        // Zone context
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );

        // Ordering. "top_category" (the home "All" tab) = sub-categories of EVERY active top-level
        // category, grouped in the admin's category order, so newly added categories show up too.
        if ($filter === CategorySubCategoryFilterEnum::RANDOM()) {
            $query->inRandomOrder();
        } else {
            $query->leftJoin('categories as sb_parent', 'sb_parent.id', '=', 'categories.parent_id')
                ->select('categories.*')
                // group under the top-level category (a leaf top-level category is its own group)
                ->orderByRaw('COALESCE(sb_parent.sort_order, categories.sort_order)')
                ->orderByRaw('COALESCE(sb_parent.title, categories.title)')
                ->orderByRaw('CASE WHEN categories.parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('categories.sort_order')
                ->orderBy('categories.title');
        }

        $allCategories = $query->get();

        $processed = $this->attachCounts($allCategories, $useZoneFilter, $storeIds);

        $filtered = $this->filterNonZeroProducts($processed);

        $paginator = $this->paginateCollection($filtered, (int)$request->input('page', 1), $perPage, $request);
        $response = array_merge(ApiResponseType::responseFromPaginator($paginator), ['filter' => $filter]);
        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Get categories for sidebar filter.
     */
    #[QueryParameter('ids', description: 'Comma separated list of category IDs or array of IDs', type: 'string', example: '1,2,3')]
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('latitude', description: 'Latitude of the user location for zone-wise product counts', type: 'float', example: 23.11684540)]
    #[QueryParameter('longitude', description: 'Longitude of the user location for zone-wise product counts', type: 'float', example: 70.02805670)]
    public function getCategories(Request $request): JsonResponse
    {
        // Normalize ids: accept CSV string or array
        $idsInput = $request->input('ids');
        if (is_string($idsInput)) {
            $ids = array_filter(array_map('trim', explode(',', $idsInput)), fn($v) => $v !== '');
            $request->merge(['ids' => $ids]);
        }

        // Validate inputs
        $validated = $request->validate([
            'ids' => 'sometimes|array',
            'ids.*' => 'integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Prepare IDs (optional)
        $ids = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));

        $perPage = (int)$request->input('per_page', 15);

        $query = Category::query()
            ->with(['parent', 'media'])
            ->where('status', CategoryStatusEnum::ACTIVE());
        // If IDs were provided, limit to those
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        // Zone context
        [$useZoneFilter, $storeIds] = $this->zoneContext(
            $request->input('latitude'),
            $request->input('longitude')
        );

        // Fetch all. If IDs provided, keep their order; otherwise order by title
        if (!empty($ids)) {
            $allCategories = $query->get()->sortBy(function (Category $cat) use ($ids) {
                return array_search($cat->id, $ids);
            })->values();
        } else {
            $allCategories = $query->orderBy('title')->get();
        }

        $processed = $this->attachCounts($allCategories, $useZoneFilter, $storeIds);

        $filtered = $this->filterNonZeroProducts($processed);

        // Paginate and respond
        $paginator = $this->paginateCollection($filtered, (int)$request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);
        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Build an empty paginated-like response.
     *
     */
    private function emptyResponse(int $perPage, array $extra = []): array
    {
        return array_merge([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'data' => [],
        ], $extra);
    }

    /**
     * Determine zone context and store ids.
     * @return array{0: bool, 1: array}
     */
    private function zoneContext($latitude, $longitude): array
    {
        $useZoneFilter = !is_null($latitude) && !is_null($longitude);
        if (!$useZoneFilter) {
            return [false, []];
        }
        $zoneInfo = DeliveryZoneService::getZonesAtPoint((float)$latitude, (float)$longitude);
        $storeIds = Product::getStoreIdsInZone($zoneInfo);
        return [true, $storeIds ?? []];
    }

    /**
     * Sets `products_count` (products a customer can actually see, in this category AND all of its
     * sub-categories at any depth) and `children_count` (active sub-categories) on every category.
     *
     * Uses the same visibility rules as the product listing API (approved + active, and – when a
     * location is given – sold by a store delivering there). Runs two grouped queries regardless of
     * how many categories exist, so it scales with a growing catalogue.
     *
     * @param Collection<int,Category> $categories
     */
    private function attachCounts(Collection $categories, bool $useZoneFilter, array $storeIds): Collection
    {
        if ($categories->isEmpty()) {
            return $categories;
        }

        // 1) direct visible-product counts per category
        $direct = [];
        if (!($useZoneFilter && empty($storeIds))) {
            $q = Product::query()
                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                ->where('status', ProductStatusEnum::ACTIVE());
            if ($useZoneFilter) {
                $q->whereHas('variants.storeProductVariants', fn($sq) => $sq->whereIn('store_id', $storeIds));
            }
            $direct = $q->toBase()
                ->selectRaw('category_id, COUNT(*) as aggregate')
                ->groupBy('category_id')
                ->get()
                ->mapWithKeys(fn($r) => [(int)$r->category_id => (int)$r->aggregate])
                ->all();
        }

        // 2) the active category tree (id → children)
        $children = [];
        Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->toBase()
            ->get(['id', 'parent_id'])
            ->each(function ($row) use (&$children) {
                if ($row->parent_id !== null) {
                    $children[(int)$row->parent_id][] = (int)$row->id;
                }
            });

        $memo = [];
        $total = function (int $id, array $seen = []) use (&$total, &$memo, $children, $direct): int {
            if (isset($memo[$id])) return $memo[$id];
            if (isset($seen[$id])) return 0; // defensive: broken parent loop
            $seen[$id] = true;
            $sum = $direct[$id] ?? 0;
            foreach ($children[$id] ?? [] as $childId) {
                $sum += $total($childId, $seen);
            }
            return $memo[$id] = $sum;
        };

        return $categories->map(function (Category $cat) use ($total, $children) {
            $cat->products_count = $total((int)$cat->id);
            $cat->children_count = count($children[(int)$cat->id] ?? []);
            return $cat;
        });
    }

    /**
     * Keep categories with products_count > 0.
     * @param Collection<int,Category> $categories
     */
    private function filterNonZeroProducts(Collection $categories): Collection
    {
        return $categories->filter(function ($cat) {
            return ($cat->products_count ?? 0) > 0;
        })->values();
    }

    /**
     * Paginate an in-memory collection and wrap items in CategoryResource.
     */
    private function paginateCollection(Collection $categories, int $page, int $perPage, Request $request): LengthAwarePaginator
    {
        $total = $categories->count();
        $items = $categories->slice(($page - 1) * $perPage, $perPage)->values();
        $resourceItems = $items->map(fn($cat) => new CategoryResource($cat));

        $paginator = new LengthAwarePaginator(
            $resourceItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $paginator->appends($request->query());
        return $paginator;
    }
}
