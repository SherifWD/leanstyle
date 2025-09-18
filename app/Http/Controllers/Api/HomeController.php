<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Banner, Category, Store, Product, ProductImage, OrderItem, ProductView,Order};
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
        if ($request->user('customer')) {
            $recentIds = ProductView::query()
                ->where('user_id', $request->user('customer')->id)
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
     * GET /api/banners
     * Query: category_id?, position?
     */
    public function banners(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['sometimes','nullable','exists:categories,id'],
            'position'    => ['sometimes','nullable','string','max:60'],
            'limit'       => ['sometimes','integer','between:1,50'],
        ]);

        $now = now();
        $q = \App\Models\Banner::query()
            ->where('is_active', true)
            ->when(array_key_exists('category_id',$data), fn($x) =>
                $x->where('category_id', $data['category_id']))
            ->when(!empty($data['position']), fn($x) => $x->where('position', $data['position']))
            ->where(function($x) use ($now) {
                $x->whereNull('starts_at')->orWhere('starts_at','<=',$now);
            })
            ->where(function($x) use ($now) {
                $x->whereNull('ends_at')->orWhere('ends_at','>=',$now);
            })
            ->orderBy('sort')
            ->orderBy('id');

        $banners = $q->take($request->integer('limit', 20))->get();

        return $this->returnData('banners', $banners, 'Banners');
    }

    /**
     * GET /api/offers
     * Query: category_id?, per_page?
     */
    public function offers(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['sometimes','nullable','exists:categories,id'],
            'per_page'    => ['sometimes','integer','between:1,100'],
        ]);

        $q = Product::query()
            ->where('is_active', true)
            ->whereNotNull('discount_price')
            ->where('discount_price','>',0)
            ->when(array_key_exists('category_id',$data), fn($x) => $x->where('category_id', $data['category_id']))
            ->with('images')
            ->latest('id');

        $products = $q->paginate($request->integer('per_page', 16))
            ->through(fn($p) => $this->productCard($p));

        return $this->returnData('products', $products, 'Offers');
    }

    /**
     * GET /api/products
     */
    
public function showP(Order $order, Request $request)
    {


        $order->load(['customer','items.product','items.productVariant.color','items.productVariant.size','items.productVariant.images','store:id,name,logo_path,address','assignment.driver:id,name,phone']);

        $data = [
            'id'             => $order->id,
            'order_code'     => $order->order_code,
            'status'         => $order->status,
            'payment_method' => $order->payment_method,
            'totals' => [
                'subtotal'       => (float)$order->subtotal,
                'discount_total' => (float)$order->discount_total,
                'tax_total'      => (float)$order->tax_total,
                'delivery_fee'   => (float)$order->delivery_fee,
                'grand_total'    => (float)$order->grand_total,
            ],
            'shipping' => [
                'address' => $order->ship_address,
                'lat'     => $order->ship_lat,
                'lng'     => $order->ship_lng,
            ],
            'items'  => $order->items->map(fn($i) => [
                'name'       => $i->name,
                'qty'        => (int)$i->qty,
                'unit_price' => (float)$i->unit_price,
                'line_total' => (float)$i->line_total,
                'options'    => $i->options,
                'product' => ['variant' => $i->productVariant]
                
            ]),
            'store'  => $order->store,
            'driver' => $order->assignment?->driver,
            'customer' => $order->customer,
            'address' => $order->address
        ];

        return $this->returnData('order', $data, "Order details");
    }
 
public function products(Request $request)
{
    $q = Product::query()->where('is_active', true);

    // Effective price uses discounted_price when not null, else price
    $effectivePriceSql = 'COALESCE(discount_price, price)';

    $q->when($request->filled('q'), fn($x) =>
        $x->where(function ($inner) use ($request) {
            $inner->where('name', 'like', '%'.$request->q.'%')
                  ->orWhere('description', 'like', '%'.$request->q.'%');
        })
    );

    $q->when($request->filled('category_id'), fn($x) => $x->where('category_id', $request->category_id));
    $q->when($request->filled('store_id'),    fn($x) => $x->where('store_id', $request->store_id));
    $q->when($request->filled('brand_id'),    fn($x) => $x->where('brand_id', $request->brand_id));

    // Use effective price for range filtering
    $q->when($request->filled('min_price'), fn($x) => 
        $x->whereRaw("$effectivePriceSql >= ?", [$request->min_price])
    );
    $q->when($request->filled('max_price'), fn($x) => 
        $x->whereRaw("$effectivePriceSql <= ?", [$request->max_price])
    );

    $q->when($request->filled('color_id'), fn($x) => $x->where('color_id', $request->color_id));
    $q->when($request->filled('size_id'),  fn($x) => $x->where('size_id', $request->size_id));
    $q->when($request->filled('type'),     fn($x) => $x->where('type', $request->type));
    $q->when($request->filled('text'), function ($x) use ($request) {
    return $x->where('name', 'like', '%' . $request->text . '%');
});
if($request->filled('offer') && $request->offer == 1){
    $q->when($request->filled('offer'), function ($x) use ($request) {
    return $x->whereNotNull('discount_price')->where('discount_price','>',0);
});
}


    // Optional but recommended: sort by effective price too
    match ($request->get('sort')) {
        'price_asc'  => $q->orderByRaw("$effectivePriceSql asc"),
        'price_desc' => $q->orderByRaw("$effectivePriceSql desc"),
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

        $q->when($request->filled('text'), fn($x) => $x->where('name', 'like', '%'.$request->text.'%'));
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
    public function getStore(Request $request)
{
    $request->validate([
        'store_id' => ['required','integer','exists:stores,id'],
    ]);

    $store = Store::with([
        'owner',        // the user that owns the store
        'users',        // linked users/drivers
        'businessHours',
        'products.variants',
        'categories',
        'brands',
        'orders',
    ])->find($request->store_id);

    if (!$store) {
        return $this->returnError(404, 'Store not found');
    }

    return $this->returnData('data', $store, 200);
}

}
