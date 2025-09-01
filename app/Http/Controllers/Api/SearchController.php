<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Product, Store, Category, Brand, Color, Size};
use Illuminate\Http\Request;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class SearchController extends Controller
{
    use backendTraits, HelpersTrait;

    // GET /api/search/suggest?q=tee
    public function suggest(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return $this->returnData('suggestions', [
                'products' => [],
                'stores'   => []
            ], "No search term provided");
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where(function ($x) use ($q) {
                $x->where('name', 'like', "%$q%")
                  ->orWhere('description', 'like', "%$q%");
            })
            ->limit(8)
            ->pluck('name');

        $stores = Store::query()
            ->where('is_active', true)
            ->where('name', 'like', "%$q%")
            ->limit(8)
            ->pluck('name');

        return $this->returnData('suggestions', [
            'products' => $products,
            'stores'   => $stores
        ], "Search suggestions");
    }

    // GET /api/search?tab=products|stores&q=&category_id=&brand_id=&size_id=&color_id=&min_price=&max_price=&sort=
    public function search(Request $request)
    {
        $tab = $request->get('tab', 'products');

        // Store Search
        if ($tab === 'stores') {
            $q = Store::query()->where('is_active', true);
            $q->when($request->filled('q'), fn($x) => $x->where('name', 'like', '%'.$request->q.'%'));
            $q->when($request->filled('area'), fn($x) => $x->where('address', 'like', '%'.$request->area.'%'));

            $stores = $q->paginate($request->integer('per_page', 16));
            return $this->returnData('stores', $stores, "Stores search results");
        }

        // Product Search
        $q = Product::query()->where('is_active', true);

        $q->when($request->filled('q'), fn($x) =>
            $x->where('name', 'like', '%'.$request->q.'%')
              ->orWhere('description','like','%'.$request->q.'%')
        );
        $q->when($request->filled('category_id'), fn($x) => $x->where('category_id', $request->category_id));
        $q->when($request->filled('brand_id'),    fn($x) => $x->where('brand_id', $request->brand_id));
        $q->when($request->filled('store_id'),    fn($x) => $x->where('store_id', $request->store_id));
        $q->when($request->filled('min_price'),   fn($x) => $x->where('price', '>=', $request->min_price));
        $q->when($request->filled('max_price'),   fn($x) => $x->where('price', '<=', $request->max_price));

        // Join variants if color/size filters applied
        if ($request->filled('size_id') || $request->filled('color_id')) {
            $q->whereHas('variants', function ($v) use ($request) {
                $v->when($request->filled('size_id'),  fn($x) => $x->where('size_id',  $request->size_id));
                $v->when($request->filled('color_id'), fn($x) => $x->where('color_id', $request->color_id));
            });
        }

        match ($request->get('sort')) {
            'price_asc'  => $q->orderBy('price','asc'),
            'price_desc' => $q->orderBy('price','desc'),
            default      => $q->latest('id'),
        };

        $products = $q->with('images')
            ->paginate($request->integer('per_page', 16))
            ->through(fn($p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'image'          => optional($p->images->sortBy('sort')->first())->path,
                'price'          => (float)$p->price,
                'discount_price' => $p->discount_price ? (float)$p->discount_price : null,
                'final_price'    => (float)($p->discount_price ?? $p->price),
                'store_id'       => $p->store_id,
            ]);

        return $this->returnData('products', $products, "Products search results");
    }
}
