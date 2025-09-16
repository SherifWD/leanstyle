<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Product, Store};
use Illuminate\Http\Request;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductController extends Controller
{
    use backendTraits, HelpersTrait;

    
public function show(Product $product, Request $request)
{
    abort_if(!$product->is_active, 404);

    $product->load([
        'store',
        'images:id,product_id,product_variant_id,path,sort',
        'variants:id,product_id,price,discount_price,stock,color_id,size_id',
        'variants.color:id,name,code',
        'variants.size:id,name',
        'brand:id,name',
        'category:id,name',
    ]);

    // Try to resolve user, but DO NOT fail if absent
    $user = $request->user()
        ?? Auth::guard('api')->user();

    if (!$user && ($raw = $request->bearerToken())) {
        try { $user = JWTAuth::setToken($raw)->authenticate(); } catch (\Throwable $e) { /* ignore */ }
    }

    // Optional: record view if authenticated
    if ($user) {
        \App\Models\ProductView::create([
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'viewed_at'  => now(),
        ]);
    }

    $data = [
        'id'             => $product->id,
        'name'           => $product->name,
        'description'    => $product->description,
        'store'          => $product->store,
        'images'         => $product->images->sortBy('sort')->values(),
        'price'          => (float) $product->price,
        'discount_price' => $product->discount_price ? (float) $product->discount_price : null,
        'final_price'    => (float) ($product->discount_price ?? $product->price),
        'stock'          => (int) $product->stock,
        'variants'       => $product->variants->map(fn ($v) => [
            'id'             => $v->id,
            'price'          => (float) ($v->discount_price ?? $v->price ?? $product->price),
            'discount_price' => $v->discount_price ? (float) $v->discount_price : null,
            'stock'          => (int) $v->stock,
            'color'          => $v->color ? ['id' => $v->color->id, 'name' => $v->color->name, 'code' => $v->color->code] : null,
            'size'           => $v->size ? ['id' => $v->size->id, 'name' => $v->size->name] : null,
        ])->values(),
        'brand'          => $product->brand,
        'category'       => $product->category,
    ];

    return $this->returnData('product', $data, 'Product details');
}


    // GET /api/product/{product}/related
    public function related(Product $product)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->where('store_id', $product->store_id)
            ->where('id', '!=', $product->id)
            ->latest('id')
            ->with(['images','category','brand','variants'])
            ->take(12)
            ->get();

        return $this->returnData('related_products', $products, "Related products");
    }

    // GET /api/store/{store}/products?view=list|grid&sort=&page=
    public function storeProducts(Store $store, Request $request)
    {
        // $q = $store->products()->with(['images','category','brand','variants.size','variants.color','views'])->where('is_active', true);
$q = Product::where('store_id',$store->id)->with(['images','category','brand','variants.size','variants.color','views'])->where('is_active', true);
        match ($request->get('sort')) {
            'price_asc'  => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            default      => $q->latest('id'),
        };

        $products = $q->paginate($request->integer('per_page', 16));

        return $this->returnData('store_products', $products, "Store products");
    }
}
