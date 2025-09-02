<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Product, Store};
use Illuminate\Http\Request;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class ProductController extends Controller
{
    use backendTraits, HelpersTrait;

    // GET /api/product/{product}
    public function show(Product $product, Request $request)
    {
        abort_if(!$product->is_active, 404);

        $product->load([
            'store:id,name,slug,logo_path,address',
            'images:id,product_id,product_variant_id,path,sort',
            'variants.id', 'variants.color:id,name,code', 'variants.size:id,name',
            'brand:id,name', 'category:id,name',
        ]);

        // Optional: record view
        if ($u = $request->user('api')) {
            \App\Models\ProductView::create([
                'user_id'    => $u->id,
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
            'price'          => (float)$product->price,
            'discount_price' => $product->discount_price ? (float)$product->discount_price : null,
            'final_price'    => (float)($product->discount_price ?? $product->price),
            'stock'          => (int)$product->stock,
            'variants'       => $product->variants->map(fn($v) => [
                'id'             => $v->id,
                'sku'            => $v->sku,
                'price'          => (float)($v->discount_price ?? $v->price ?? $product->price),
                'discount_price' => $v->discount_price ? (float)$v->discount_price : null,
                'stock'          => (int)$v->stock,
                'color'          => $v->color ? ['id' => $v->color->id, 'name' => $v->color->name, 'code' => $v->color->code] : null,
                'size'           => $v->size ? ['id' => $v->size->id, 'name' => $v->size->name] : null,
            ])->values(),
            'brand'          => $product->brand,
            'category'       => $product->category,
        ];

        return $this->returnData('product', $data, "Product details");
    }

    // GET /api/product/{product}/related
    public function related(Product $product)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->where('store_id', $product->store_id)
            ->where('id', '!=', $product->id)
            ->latest('id')
            ->with('images')
            ->take(12)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'image'       => optional($p->images->sortBy('sort')->first())->path,
                'final_price' => (float)($p->discount_price ?? $p->price),
            ]);

        return $this->returnData('related_products', $products, "Related products");
    }

    // GET /api/store/{store}/products?view=list|grid&sort=&page=
    public function storeProducts(Store $store, Request $request)
    {
        $q = $store->products()->with(['images','category','brand','variants.size','variants.color','views'])->where('is_active', true);

        match ($request->get('sort')) {
            'price_asc'  => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            default      => $q->latest('id'),
        };

        $products = $q->paginate($request->integer('per_page', 16))
            ->through(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'image'       => optional($p->images->sortBy('sort')->first())->path,
                'final_price' => (float)($p->discount_price ?? $p->price),
            ]);

        return $this->returnData('store_products', $products, "Store products");
    }
}
