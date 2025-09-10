<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Banner, Category, Store, Product, ProductImage, OrderItem, ProductView};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class HomeController extends Controller
{
    use backendTraits, HelpersTrait;

    /**
     * GET /api/home
     */
    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 10);

        // Top-level categories
        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->take(12)
            ->get(['id','name','slug']);

        // Stores (filter by area if provided)
        $stores = Store::query()
            ->when($request->filled('area'), fn($q) => $q->where('address', 'like', '%'.$request->get('area').'%'))
            ->where('is_active', true)
            ->latest()
            ->take(10)
            ->get(['id','name','slug','logo_path','address']);

        // Latest products
        $latest = Product::query()
            ->where('is_active', true)
            ->latest('id')
            ->with('images')
            ->take($limit)
            ->get()
            ->map(fn($p) => $this->productCard($p));

        // Best sellers (last 30 days)
        $bestSellersIds = OrderItem::query()
            ->select('product_id', DB::raw('SUM(qty) as total_qty'))
            ->whereHas('order', fn($q) => $q->where('status', 'delivered'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take($limit)
            ->pluck('product_id');

        $bestSellers = Product::query()
            ->whereIn('id', $bestSellersIds)
            ->with('images')
            ->get()
            ->map(fn($p) => $this->productCard($p));

        // Recently viewed (if logged in)
        $recentlyViewed = collect();
        if ($request->user('api')) {
            $recentIds = ProductView::query()
                ->where('user_id', $request->user('api')->id)
                ->latest('viewed_at')
                ->take($limit)
                ->pluck('product_id');
            $recentlyViewed = Product::whereIn('id', $recentIds)
                ->with('images')
                ->get()
                ->map(fn($p) => $this->productCard($p));
        }

        return $this->returnData('data', [
            'categories' => $categories,
            'stores'     => $stores,
            'sections'   => [
                'latest'           => $latest,
                'best_sellers_30d' => $bestSellers,
                'recently_viewed'  => $recentlyViewed,
            ],
        ], "Home Page Data");
    }

    /**
     * GET /api/products
     */
    public function products(Request $request)
    {
        $q = Product::query()->where('is_active', true);

        $q->when($request->filled('q'), fn($x) =>
            $x->where(function ($inner) use ($request) {
                $inner->where('name', 'like', '%'.$request->q.'%')
                      ->orWhere('description', 'like', '%'.$request->q.'%');
            })
        );
        $q->when($request->filled('category_id'), fn($x) => $x->where('category_id', $request->category_id));
        $q->when($request->filled('store_id'), fn($x) => $x->where('store_id', $request->store_id));
        $q->when($request->filled('brand_id'), fn($x) => $x->where('brand_id', $request->brand_id));
        $q->when($request->filled('min_price'), fn($x) => $x->where('price', '>=', $request->min_price));
        $q->when($request->filled('max_price'), fn($x) => $x->where('price', '<=', $request->max_price));
        $q->when($request->filled('color_id'), fn($x) => $x->where('color_id', $request->color_id));
        $q->when($request->filled('size_id'), fn($x) => $x->where('size_id', $request->size_id));

        match ($request->get('sort')) {
            'price_asc'  => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            default      => $q->latest('id'),
        };

        $products = $q->with('images')
            ->paginate($request->integer('per_page', 16))
            ->through(fn($p) => $this->productCard($p));

        return $this->returnData('products', $products, "Products List");
    }

    /**
     * GET /api/stores
     */
    public function stores(Request $request)
    {
        $q = Store::query()->where('is_active', true);

        $q->when($request->filled('q'), fn($x) => $x->where('name', 'like', '%'.$request->q.'%'));
        $q->when($request->filled('area'), fn($x) => $x->where('address', 'like', '%'.$request->area.'%'));

        $stores = $q->paginate($request->integer('per_page', 12));

        return $this->returnData('stores', $stores, "Stores List");
    }

    /**
     * Helper: normalize product card payload
     */
    private function productCard(Product $p): array
    {
        $firstImage = optional($p->images->sortBy('sort')->first())->path;
        $price = (float) ($p->discount_price ?? $p->price);
        $hasDiscount = !is_null($p->discount_price) && (float)$p->discount_price < (float)$p->price;

        return [
            'id'             => $p->id,
            'name'           => $p->name,
            'store_id'       => $p->store_id,
            'category_id'    => $p->category_id,
            'brand_id'       => $p->brand_id,
            'image'          => $firstImage,
            'price'          => (float) $p->price,
            'discount_price' => $p->discount_price ? (float)$p->discount_price : null,
            'final_price'    => $price,
            'has_discount'   => $hasDiscount,
            'is_active'      => (bool)$p->is_active,
        ];
    }
}
