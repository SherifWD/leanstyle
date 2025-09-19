<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Size, Brand, Category, Color, Store, Product};
use Illuminate\Http\Request;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class MetaController extends Controller
{
    use backendTraits, HelpersTrait;

    /**
     * GET /api/meta
     * Returns:
     *  - all_sizes
     *  - all_brands
     *  - categories (top-level)
     *  - all_colors
     *  - available_locations
     *  - types (mens, women, child) – taken from distinct Product.type if present; otherwise defaults
     */
    public function index(Request $request)
    {
        $sizes   = Size::query()->orderBy('name')->get(['id','name']);
        $brands  = Brand::query()->orderBy('name')->get(['id','name']);
        $cats    = Category::query()->whereNull('parent_id')->orderBy('name')->get(['id','name','image']);
        $colors  = Color::query()->orderBy('name')->get(['id','name','code']);

        // Available locations:
        // Prefer a dedicated 'city' column on stores; fallback to unique first token of address.
        $locations = Store::query()
            ->when(schema()->hasColumn('stores','city'), fn($q) => $q->select('city')->whereNotNull('city')->distinct(),
                fn($q) => $q->select('address')->whereNotNull('address')->distinct()
            )
            ->take(2000)->get()
            ->map(function ($row) {
                if (isset($row->city)) return trim($row->city);
                // crude fallback if only address exists (extract last comma token or first word)
                $addr = (string)$row->address;
                if (str_contains($addr, ',')) {
                    return trim(last(explode(',', $addr)));
                }
                return trim(str($addr)->explode(' ')->first());
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Types: if products table has 'type' column use distinct values; else default
        $types = [];
        try {
            if (schema()->hasColumn('products','type')) {
                $types = Product::query()->whereNotNull('type')
                    ->select('type')->distinct()->pluck('type')->map(fn($t) => (string)$t)->values()->all();
            }
        } catch (\Throwable $e) { /* ignore */ }
        if (empty($types)) {
            $types = ['mens', 'women', 'child'];
        }

        return $this->returnData('meta', [
            'all_sizes'          => $sizes,
            'all_brands'         => $brands,
            'categories'         => $cats,
            'all_colors'         => $colors,
            'available_locations'=> $locations,
            'types'              => $types,
        ], 'Meta data');
    }
}

/** tiny helper without adding a service provider */
if (!function_exists('schema')) {
    function schema() { return \Illuminate\Support\Facades\Schema::getFacadeRoot(); }
}
