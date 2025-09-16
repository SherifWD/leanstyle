<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{BusinessHour, Store, Product, Order, OrderStatusHistory, ProductVariant};
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class OwnerController extends Controller
{
    use backendTraits, HelpersTrait;

    /** GET /api/owner/shops */
    public function myShops(Request $request)
    {
$user = $request->user()                      // preferred (current guard)
         ?? Auth::guard('api')->user()            // explicit api guard
         ?? (JWTAuth::check() ? JWTAuth::user() : null); // fallback for JWTAuth
    if (!$user) {
        return $this->returnError(401, 'Unauthenticated. Provide a valid Bearer token.');
    }
    $uid = $user->id;
        $shops = Store::with(['categories','businessHours','products','brands'])->where('owner_id', $uid)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->returnData('shops', $shops, 'My shops');
    }

    /** POST /api/owner/shops */
   public function createShop(Request $request)
{
    // Resolve current user (api/customer/etc.)
    $user = $request->user()
         ?? Auth::guard('api')->user()
         ?? (JWTAuth::check() ? JWTAuth::user() : null);

    if (!$user) {
        return $this->returnError(401, 'Unauthenticated. Provide a valid Bearer token.');
    }

    // 1) Validate
    $data = $request->validate([
        'name'              => ['required','string','max:255'],
        'slug'              => [
            'nullable','string','max:255','regex:/^[a-z0-9-]+$/',
            Rule::unique('stores','slug')->whereNull('deleted_at'),
        ],
        // image max 5MB
        'logo_path'         => ['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],
        'brand_color'       => ['nullable','string','max:255'],
        'description'       => ['nullable','string'],
        'address'           => ['nullable','string','max:255'],
        'lat'               => ['nullable','numeric','between:-90,90'],
        'lng'               => ['nullable','numeric','between:-180,180'],
        'is_active'         => ['nullable','boolean'],
        'delivery_settings' => ['nullable'],
        'country'           => ['nullable','string','max:255'],
        'city'              => ['nullable','string','max:255'],

        'business_hours'                 => ['required','array','min:1'],
        'business_hours.*.weekday'       => ['required','integer','between:0,6'],
        'business_hours.*.open_at'       => ['nullable','date_format:H:i'],
        'business_hours.*.close_at'      => ['nullable','date_format:H:i'],
        'business_hours.*.is_closed'     => ['boolean'],
    ]);

    // 2) Validate open/close per row
    foreach ($data['business_hours'] as $i => $bh) {
        $closed = (bool)($bh['is_closed'] ?? false);
        if (!$closed && !empty($bh['open_at']) && !empty($bh['close_at'])) {
            if (strtotime($bh['close_at']) <= strtotime($bh['open_at'])) {
                return $this->returnError(422, "close_at must be after open_at for index $i");
            }
        }
    }

    // 3) Slug
    $slug = $data['slug'] ?? Str::slug($data['name']) ?: Str::random(8);
    $slug = $this->uniqueSlug($slug);

    // 4) Upload to public/store via "local" disk (rooted at public_path('/'))
    //     Ensure directory exists, generate stable filename
    $uploadedPath = null; // will be like "store/slug-XXXXXX.png"
    if ($request->hasFile('logo_path') && $request->file('logo_path')->isValid()) {
        $file = $request->file('logo_path');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = $slug . '-' . Str::random(8) . '.' . $ext;

        // make sure "store" dir exists under public/
        Storage::disk('local')->makeDirectory('store'); // local disk -> public root
        // save to public/store/<filename>
        $uploadedPath = Storage::disk('local')->putFileAs('store', $file, $filename);
        // $uploadedPath now equals "store/<filename>"
    }

    try {
        // 5) Create store + hours atomically
        $store = DB::transaction(function () use ($user, $data, $slug, $uploadedPath) {

            $store = new \App\Models\Store();
            $store->owner_id          = $user->id;
            $store->name              = $data['name'];
            $store->slug              = $slug;
            $store->logo_path         = $uploadedPath; // e.g., "store/xxx.jpg" in public/
            $store->brand_color       = $data['brand_color']      ?? null;
            $store->description       = $data['description']      ?? null;
            $store->address           = $data['address']          ?? null;
            $store->lat               = $data['lat']              ?? null;
            $store->lng               = $data['lng']              ?? null;
            $store->is_active         = array_key_exists('is_active',$data) ? (bool)$data['is_active'] : true;
            $store->delivery_settings = $data['delivery_settings']?? null;
            $store->country           = $data['country']          ?? null;
            $store->city              = $data['city']             ?? null;
            $store->save();

            // Bulk insert business hours
            $rows = [];
            $now  = now();
            foreach ($data['business_hours'] as $bh) {
                $rows[] = [
                    'store_id'  => $store->id,
                    'weekday'   => (int)$bh['weekday'],
                    'open_at'   => $bh['open_at']  ?? null,
                    'close_at'  => $bh['close_at'] ?? null,
                    'is_closed' => (int)($bh['is_closed'] ?? false),
                    'created_at'=> $now,
                    'updated_at'=> $now,
                ];
            }
            \App\Models\BusinessHour::insert($rows);

            return $store;
        });
    } catch (\Throwable $e) {
        // If DB failed after file saved, clean up file (from public/)
        if ($uploadedPath) {
            Storage::disk('local')->delete($uploadedPath);
        }
        throw $e;
    }

    // Eager load hours for response
    $hours = $store->businessHours()->orderBy('weekday')->get();

    return $this->returnData('store', [
        'id'                => $store->id,
        'owner_id'          => $store->owner_id,
        'name'              => $store->name,
        'slug'              => $store->slug,
        'logo_path'         => $store->logo_path, // "store/abc.jpg"
        'logo_url'          => $store->logo_path ? asset($store->logo_path) : null, // "/store/abc.jpg"
        'brand_color'       => $store->brand_color,
        'description'       => $store->description,
        'address'           => $store->address,
        'lat'               => $store->lat,
        'lng'               => $store->lng,
        'is_active'         => (bool)$store->is_active,
        'delivery_settings' => $store->delivery_settings,
        'country'           => $store->country,
        'city'              => $store->city,
        'created_at'        => $store->created_at,
        'updated_at'        => $store->updated_at,
        'business_hours'    => $hours,
    ], 'Store created');
}

/**
 * Generate a unique slug with ONE DB call by scanning existing siblings.
 * - Accepts soft-deleted rows too to avoid collisions when restored.
 */
private function uniqueSlug(string $base, ?int $ignoreId = null): string
{
    $base = Str::slug($base) ?: Str::random(8);

    // Fetch all slugs that start with $base or $base-<number>
    $existing = Store::withTrashed()
        ->when($ignoreId !== null, function($q) use ($ignoreId) {
            $q->where('id','<>',$ignoreId);
        })
        ->where(function($q) use ($base) {
            $q->where('slug', $base)
              ->orWhere('slug', 'like', $base.'-%');
        })
        ->pluck('slug')
        ->all();

    if (!in_array($base, $existing, true)) {
        return $base;
    }

    // Find the highest numeric suffix and increment
    $max = 1;
    foreach ($existing as $s) {
        if (preg_match('/^'.preg_quote($base, '/').'-(\d+)$/', $s, $m)) {
            $n = (int)$m[1];
            if ($n >= $max) $max = $n + 1;
        }
    }
    return $base.'-'.$max;
}

    /** GET /api/owner/orders */
    public function myOrders(Request $request)
    {
        $uid = $request->user('api')->id;
        $status = $request->get('status'); // optional

        $q = Order::query()
            ->whereHas('store', fn($s) => $s->where('owner_id', $uid))
            ->with(['store','customer','items.product','items.productVariant','assignment','driver'])
            ->latest('id');

        if ($status) $q->where('status', $status);

        $orders = $q->paginate($request->integer('per_page', 20));

        return $this->returnData('orders', $orders, 'Store orders');
    }
    public function noOrders(Request $request)
    {
        $uid = $request->user('api')->id;
        $statuses = ['rejected', 'cancelled', 'delivered']; // optional

        $q = Order::query()
            ->whereHas('store', fn($s) => $s->where('owner_id', $uid))
            ->whereIn('status',$statuses)
            ->with(['store','customer','items.product','items.productVariant','assignment','driver'])
            ->latest('id');

        
        $orders = $q->paginate($request->integer('per_page', 20));

        return $this->returnData('orders', $orders, 'Store orders');
    }
    public function notRejOrders(Request $request)
    {
        $uid = $request->user('api')->id;
        $statuses = ['rejected', 'cancelled', 'delivered']; // optional

        $q = Order::query()
            ->whereHas('store', fn($s) => $s->where('owner_id', $uid))
            ->whereNotIn('status',$statuses)
            ->with(['store','customer','items.product','items.productVariant','assignment','driver'])
            ->latest('id');

        
        $orders = $q->paginate($request->integer('per_page', 20));

        return $this->returnData('orders', $orders, 'Store orders');
    }
    
    /** POST /api/owner/products  (creates product in one of my shops) */
    public function createProduct(Request $request)
{
    $uid = $request->user('api')->id;

    // Normalize images: allow single file as "images" array
    if ($request->hasFile('images') && !is_array($request->file('images'))) {
        $request->files->set('images', [$request->file('images')]);
    }

    $data = $request->validate([
        'store_id'        => ['required','exists:stores,id'],
        'name'            => ['required','string','max:190'],
        'description'     => ['nullable','string'],
        'category_id'     => ['nullable','exists:categories,id'],
        'brand_id'        => ['nullable','exists:brands,id'],
        'price'           => ['required','numeric','min:0'],
        'discount_price'  => ['nullable','numeric','min:0','lte:price'],
        'stock'           => ['nullable','integer','min:0'],
        'type'            => ['nullable','string','max:50'], // mens|women|child etc
        'is_active'       => ['boolean'],
        'images'          => ['nullable','array'],
        'images.*'        => ['file','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],
        // NEW: base product weight (grams or your unit)
        'weight'          => ['nullable','numeric','min:0'],

        // NEW: quick single-variant fields (optional)
        'size_id'         => ['nullable','exists:sizes,id'],
        'color_id'        => ['nullable','exists:colors,id'],

        // NEW: or full variants array
        'variants'                 => ['nullable','array'],
        'variants.*.color_id'      => ['nullable','exists:colors,id'],
        'variants.*.size_id'       => ['nullable','exists:sizes,id'],
        'variants.*.sku'           => ['required_with:variants','string','max:255'],
        'variants.*.price'         => ['nullable','numeric','min:0'],
        'variants.*.discount_price'=> ['nullable','numeric','min:0'],
        'variants.*.stock'         => ['nullable','integer','min:0'],
        'variants.*.is_active'     => ['nullable','boolean'],
    ]);

    // Ownership check (no 404s)
    $store = Store::where('id', $data['store_id'])->where('owner_id', $uid)->first();
    if (!$store) {
        return $this->returnError(403, 'You do not own this store');
    }

    // Create product
    $p = new Product();
    $p->store_id       = $data['store_id'];
    $p->name           = $data['name'];
    $p->description    = $data['description']     ?? null;
    $p->category_id    = $data['category_id']     ?? null;
    $p->brand_id       = $data['brand_id']        ?? null;
    $p->price          = $data['price'];
    $p->discount_price = $data['discount_price']  ?? null;
    $p->stock          = $data['stock']           ?? 0;
    $p->type           = $data['type']            ?? null;
    $p->is_active      = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true;
    $p->weight         = $data['weight']          ?? null; // make sure the products table has a weight column
    $p->save();

    // Build variants
    $createdVariants = [];

    if (!empty($data['variants']) && is_array($data['variants'])) {
        foreach ($data['variants'] as $v) {
            // per-variant price falls back to base product price
            $vPrice  = Arr::get($v, 'price', $p->price);
            $vDisc   = Arr::get($v, 'discount_price', null);

            $createdVariants[] = ProductVariant::create([
                'product_id'      => $p->id,
                'color_id'        => $v['color_id'] ?? null,
                'size_id'         => $v['size_id']  ?? null,
                'price'           => $vPrice,
                'discount_price'  => $vDisc,
                'stock'           => (int)($v['stock'] ?? 0),
                'is_active'       => array_key_exists('is_active', $v) ? (bool)$v['is_active'] : true,
            ]);
        }
    } else {
        // If single-variant fields are provided, create one variant
        if (!empty($data['size_id']) || !empty($data['color_id'])) {
            $createdVariants[] = ProductVariant::create([
                'product_id'      => $p->id,
                'color_id'        => $data['color_id'] ?? null,
                'size_id'         => $data['size_id']  ?? null,
                'price'           => $p->price,
                'discount_price'  => $p->discount_price,
                'stock'           => (int)($data['stock'] ?? 0),
                'is_active'       => true,
            ]);
        }
    }
    foreach (Arr::get($data, 'images', []) as $image) {
        $p_image = new ProductImage();
        $imagePath = $image->store('products');
        $p_image->product_id = $p->id;
        $p_image->path = $imagePath;
        $p_image->save();
    }
    return $this->returnData('product', [
        'id'              => $p->id,
        'name'            => $p->name,
        'store_id'        => $p->store_id,
        'category_id'     => $p->category_id,
        'brand_id'        => $p->brand_id,
        'price'           => (float)$p->price,
        'discount_price'  => $p->discount_price ? (float)$p->discount_price : null,
        'stock'           => (int)$p->stock,
        'type'            => $p->type,
        'weight'          => $p->weight ? (float)$p->weight : null,
        'is_active'       => (bool)$p->is_active,
        'images'        => $p->images,
        'variants'        => collect($createdVariants)->map(function ($v) {
            return [
                'id'             => $v->id,
                'price'          => $v->price ? (float)$v->price : null,
                'discount_price' => $v->discount_price ? (float)$v->discount_price : null,
                'stock'          => (int)$v->stock,
                'is_active'      => (bool)$v->is_active,
                'color_id'       => $v->color_id,
                'size_id'        => $v->size_id,
            ];
        })->values(),
    ], 'Product created');
}



public function updateProduct(\App\Models\Product $product, Request $request)
{
    // --- Auth ---
    $user = $request->user()
        ?? Auth::guard('api')->user()
        ?? (JWTAuth::check() ? JWTAuth::user() : null);

    if (!$user) {
        return $this->returnError(401, 'Unauthenticated. Provide a valid Bearer token.');
    }

    // --- Ownership ---
    $store = \App\Models\Store::find($product->store_id);
    if (!$store || (int)$store->owner_id !== (int)$user->id) {
        return $this->returnError(403, 'You do not own this product/store');
    }

    // Normalize images: allow single file as "images"
    if ($request->hasFile('images') && !is_array($request->file('images'))) {
        $request->files->set('images', [$request->file('images')]);
    }

    // Optional: decode variants if sent as JSON string
    if ($request->filled('variants') && is_string($request->variants)) {
        $decoded = json_decode($request->variants, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $request->merge(['variants' => $decoded]);
        }
    }

    // Optional: decode image_ids if sent as JSON string
    if ($request->filled('image_ids') && is_string($request->image_ids)) {
        $decoded = json_decode($request->image_ids, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $request->merge(['image_ids' => $decoded]);
        }
    }

    // --- Validate ---
    $data = $request->validate([
        'store_id'        => ['sometimes','required','exists:stores,id'],
        'name'            => ['sometimes','required','string','max:190'],
        'description'     => ['sometimes','nullable','string'],
        'category_id'     => ['sometimes','nullable','exists:categories,id'],
        'brand_id'        => ['sometimes','nullable','exists:brands,id'],
        'price'           => ['sometimes','required','numeric','min:0'],
        'discount_price'  => ['sometimes','nullable','numeric','min:0','lte:price'],
        'stock'           => ['sometimes','nullable','integer','min:0'],
        'type'            => ['sometimes','nullable','string','max:50'],
        'is_active'       => ['sometimes','boolean'],
        'weight'          => ['sometimes','nullable','numeric','min:0'],

        // images reconciliation
        'images'          => ['sometimes','array'],
        'images.*'        => ['file','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],
        'image_ids'       => ['sometimes','array'],
        'image_ids.*'     => [
            'integer',
            Rule::exists('product_images','id')->where(function($q) use ($product) {
                $q->where('product_id', $product->id);
            })
        ],

        // quick single-variant fields
        'size_id'         => ['sometimes','nullable','exists:sizes,id'],
        'color_id'        => ['sometimes','nullable','exists:colors,id'],

        // full variants upsert
        'variants'                         => ['sometimes','array'],
        'variants.*.id'                    => ['sometimes','integer','exists:product_variants,id'],
        'variants.*._delete'               => ['sometimes','boolean'],
        'variants.*.color_id'              => ['sometimes','nullable','exists:colors,id'],
        'variants.*.size_id'               => ['sometimes','nullable','exists:sizes,id'],
        'variants.*.sku'                   => ['sometimes','nullable','string','max:255'],
        'variants.*.price'                 => ['sometimes','nullable','numeric','min:0'],
        'variants.*.discount_price'        => ['sometimes','nullable','numeric','min:0','lte:variants.*.price'],
        'variants.*.stock'                 => ['sometimes','nullable','integer','min:0'],
        'variants.*.is_active'             => ['sometimes','boolean'],
    ]);

    // If moving product, ensure target store belongs to user
    if (array_key_exists('store_id', $data)) {
        $target = \App\Models\Store::where('id', $data['store_id'])
            ->where('owner_id', $user->id)->first();
        if (!$target) return $this->returnError(403, 'You do not own the target store');
    }

    // Ensure provided variant ids (if any) belong to this product
    if (!empty($data['variants'])) {
        $ids = collect($data['variants'])->pluck('id')->filter()->values();
        if ($ids->isNotEmpty()) {
            $count = \App\Models\ProductVariant::where('product_id',$product->id)->whereIn('id',$ids)->count();
            if ($count !== $ids->count()) {
                return $this->returnError(422, 'One or more variants do not belong to this product');
            }
        }
    }

    DB::transaction(function () use ($product, $data, $request) {
        // Update product fields
        $product->fill([
            'store_id'       => $data['store_id']        ?? $product->store_id,
            'name'           => array_key_exists('name', $data) ? $data['name'] : $product->name,
            'description'    => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'category_id'    => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
            'brand_id'       => array_key_exists('brand_id', $data) ? $data['brand_id'] : $product->brand_id,
            'price'          => array_key_exists('price', $data) ? $data['price'] : $product->price,
            'discount_price' => array_key_exists('discount_price', $data) ? $data['discount_price'] : $product->discount_price,
            'stock'          => array_key_exists('stock', $data) ? $data['stock'] : $product->stock,
            'type'           => array_key_exists('type', $data) ? $data['type'] : $product->type,
            'is_active'      => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $product->is_active,
            'weight'         => array_key_exists('weight', $data) ? $data['weight'] : $product->weight,
        ])->save();

        // Variants sync (no blanket delete; respect _delete flags)
        if (array_key_exists('variants', $data)) {
            foreach ($data['variants'] as $v) {
                // delete existing
                if (!empty($v['_delete']) && !empty($v['id'])) {
                    \App\Models\ProductVariant::where('product_id',$product->id)->where('id',$v['id'])->delete();
                    continue;
                }
                // update existing
                if (!empty($v['id'])) {
                    $pv = \App\Models\ProductVariant::where('product_id',$product->id)->where('id',$v['id'])->first();
                    if ($pv) {
                        $pv->fill([
                            'color_id'       => array_key_exists('color_id',$v) ? $v['color_id'] : $pv->color_id,
                            'size_id'        => array_key_exists('size_id',$v) ? $v['size_id'] : $pv->size_id,
                            'price'          => array_key_exists('price',$v) ? $v['price'] : $pv->price,
                            'discount_price' => array_key_exists('discount_price',$v) ? $v['discount_price'] : $pv->discount_price,
                            'stock'          => array_key_exists('stock',$v) ? (int)$v['stock'] : $pv->stock,
                            'is_active'      => array_key_exists('is_active',$v) ? (bool)$v['is_active'] : $pv->is_active,
                            'sku'            => array_key_exists('sku',$v) ? $v['sku'] : $pv->sku,
                        ])->save();
                    }
                } else {
                    // create new
                    \App\Models\ProductVariant::create([
                        'product_id'      => $product->id,
                        'color_id'        => Arr::get($v,'color_id'),
                        'size_id'         => Arr::get($v,'size_id'),
                        'sku'             => Arr::get($v,'sku', \Illuminate\Support\Str::slug($product->name).'-'.\Illuminate\Support\Str::random(6)),
                        'price'           => Arr::get($v,'price', $product->price),
                        'discount_price'  => Arr::get($v,'discount_price'),
                        'stock'           => (int) Arr::get($v,'stock', 0),
                        'is_active'       => (bool) Arr::get($v,'is_active', true),
                    ]);
                }
            }
        }

        // Reconcile images if client provided image_ids (keep set) and/or new uploads
        $providedKeepIds = array_key_exists('image_ids', $data) ? collect($data['image_ids'])->map(fn($i)=>(int)$i)->all() : null;

        if ($providedKeepIds !== null) {
            // Delete images not in keep list
            $existing = $product->images()->get();
            foreach ($existing as $img) {
                if (!in_array((int)$img->id, $providedKeepIds, true)) {
                    if ($img->path) Storage::disk('local')->delete($img->path);
                    $img->delete();
                }
            }
        }

        // Append any newly uploaded files
        if ($request->hasFile('images')) {
            Storage::disk('local')->makeDirectory('products');
            foreach ($request->file('images') as $file) {
                if (!$file->isValid()) continue;
                $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension());
                $name = \Illuminate\Support\Str::slug($product->name) . '-' . \Illuminate\Support\Str::random(8) . '.' . $ext;
                $path = Storage::disk('local')->putFileAs('products', $file, $name);

                \App\Models\ProductImage::create([
                    'product_id' => $product->id,
                    'path'       => $path,
                ]);
            }
        }
    });

    // Reload for response
    $product->load('images','variants');

    return $this->returnData('product', [
        'id'              => $product->id,
        'store_id'        => $product->store_id,
        'name'            => $product->name,
        'description'     => $product->description,
        'category_id'     => $product->category_id,
        'brand_id'        => $product->brand_id,
        'price'           => (float)$product->price,
        'discount_price'  => $product->discount_price !== null ? (float)$product->discount_price : null,
        'stock'           => (int)$product->stock,
        'type'            => $product->type,
        'weight'          => $product->weight !== null ? (float)$product->weight : null,
        'is_active'       => (bool)$product->is_active,
        'images'          => $product->images->map(fn($img)=>[
            'id'=>$img->id,'path'=>$img->path,'url'=>asset($img->path)
        ]),
        'variants'        => $product->variants->map(fn($v)=>[
            'id'=>$v->id,
            'price'=> $v->price !== null ? (float)$v->price : null,
            'discount_price'=> $v->discount_price !== null ? (float)$v->discount_price : null,
            'stock'=> (int)$v->stock,
            'is_active'=> (bool)$v->is_active,
            'color_id'=> $v->color_id,
            'size_id'=> $v->size_id,
            'sku'=> $v->sku,
        ])->values(),
    ], 'Product updated');
}


public function updateShop(Store $store, Request $request)
{
    // Auth
    $user = $request->user() ?? Auth::guard('api')->user() ?? (JWTAuth::check() ? JWTAuth::user() : null);
    if (!$user) return $this->returnError(401, 'Unauthenticated. Provide a valid Bearer token.');

    // Ownership
    if (!$store || (int)$store->owner_id !== (int)$user->id) {
        return $this->returnError(403, 'You do not own this store');
    }

    // If business_hours is sent as JSON string, decode
    if ($request->filled('business_hours') && is_string($request->business_hours)) {
        $decoded = json_decode($request->business_hours, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->returnError(422, 'business_hours must be valid JSON');
        }
        $request->merge(['business_hours' => $decoded]);
    }

    // Validate
    $data = $request->validate([
        'name'              => ['sometimes','required','string','max:255'],
        'slug'              => [
            'sometimes','nullable','string','max:255','regex:/^[a-z0-9-]+$/',
            Rule::unique('stores','slug')->ignore($store->id)->whereNull('deleted_at'),
        ],
        // allow file upload like createShop
        'logo_path'         => ['sometimes','nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],
        'remove_logo'       => ['sometimes','boolean'],
        'brand_color'       => ['sometimes','nullable','string','max:255'],
        'description'       => ['sometimes','nullable','string'],
        'address'           => ['sometimes','nullable','string','max:255'],
        'lat'               => ['sometimes','nullable','numeric','between:-90,90'],
        'lng'               => ['sometimes','nullable','numeric','between:-180,180'],
        'is_active'         => ['sometimes','boolean'],
        'delivery_settings' => ['sometimes','nullable'], // JSON/array
        'country'           => ['sometimes','nullable','string','max:255'],
        'city'              => ['sometimes','nullable','string','max:255'],

        'business_hours'                 => ['sometimes','array','min:1'],
        'business_hours.*.weekday'       => ['required_with:business_hours','integer','between:0,6'],
        'business_hours.*.open_at'       => ['nullable','date_format:H:i'],
        'business_hours.*.close_at'      => ['nullable','date_format:H:i'],
        'business_hours.*.is_closed'     => ['boolean'],
    ]);

    // Hours sanity check
    if (array_key_exists('business_hours', $data)) {
        foreach ($data['business_hours'] as $i => $bh) {
            $closed = (bool)($bh['is_closed'] ?? false);
            if (!$closed && !empty($bh['open_at']) && !empty($bh['close_at'])) {
                if (strtotime($bh['close_at']) <= strtotime($bh['open_at'])) {
                    return $this->returnError(422, "close_at must be after open_at for index $i");
                }
            }
        }
    }

    // Prepare logo replacement/removal if provided
    $newLogoPath = null;
    $removeLogo = array_key_exists('remove_logo', $data) ? (bool)$data['remove_logo'] : false;
    unset($data['remove_logo']); // not a column
    $oldLogoPath = $store->logo_path; // for cleanup after commit
    if ($request->hasFile('logo_path') && $request->file('logo_path')->isValid()) {
        $file = $request->file('logo_path');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $base = $data['slug'] ?? $store->slug ?? Str::slug($data['name'] ?? $store->name) ?: Str::random(8);
        $filename = $base . '-' . Str::random(8) . '.' . $ext;

        Storage::disk('local')->makeDirectory('store'); // public/store
        $newLogoPath = Storage::disk('local')->putFileAs('store', $file, $filename); // "store/xxx.png"
    }

    // Persist atomically
    DB::transaction(function () use ($store, $data, $newLogoPath, $removeLogo) {
        // Slug regeneration if explicitly null/empty
        if (array_key_exists('slug',$data) && ($data['slug'] === null || $data['slug'] === '')) {
            $base = Str::slug($data['name'] ?? $store->name) ?: Str::random(8);
            if (method_exists($this,'uniqueSlug')) {
                $data['slug'] = $this->uniqueSlug($base, $store->id);
            } else {
                $slug = $base; $i = 1;
                while (Store::withTrashed()->where('slug',$slug)->where('id','<>',$store->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $data['slug'] = $slug;
            }
        }

        // Apply fields
        $store->fill($data);
        if ($newLogoPath) {
            $store->logo_path = $newLogoPath; // switch to new file
        } elseif ($removeLogo) {
            $store->logo_path = null; // explicit delete
        }
        $store->save();

        // Replace hours if provided
        if (array_key_exists('business_hours', $data)) {
            \App\Models\BusinessHour::where('store_id',$store->id)->delete();
            $rows = [];
            $now = now();
            foreach ($data['business_hours'] as $bh) {
                $rows[] = [
                    'store_id'  => $store->id,
                    'weekday'   => (int)$bh['weekday'],
                    'open_at'   => $bh['open_at']  ?? null,
                    'close_at'  => $bh['close_at'] ?? null,
                    'is_closed' => (int)($bh['is_closed'] ?? false),
                    'created_at'=> $now,
                    'updated_at'=> $now,
                ];
            }
            if ($rows) \App\Models\BusinessHour::insert($rows);
        }
    });

    // After commit: delete old file if it was replaced or removed
    if ($oldLogoPath) {
        if (($newLogoPath && $oldLogoPath !== $newLogoPath) || (!$newLogoPath && $removeLogo)) {
            Storage::disk('local')->delete($oldLogoPath);
        }
    }

    // Reload hours and return
    $hours = $store->businessHours()->orderBy('weekday')->get();

    return $this->returnData('store', [
        'id'                => $store->id,
        'owner_id'          => $store->owner_id,
        'name'              => $store->name,
        'slug'              => $store->slug,
        'logo_path'         => $store->logo_path,                 // "store/abc.png"
        'logo_url'          => $store->logo_path ? asset($store->logo_path) : null, // "/store/abc.png"
        'brand_color'       => $store->brand_color,
        'description'       => $store->description,
        'address'           => $store->address,
        'lat'               => $store->lat,
        'lng'               => $store->lng,
        'is_active'         => (bool)$store->is_active,
        'delivery_settings' => $store->delivery_settings,
        'country'           => $store->country,
        'city'              => $store->city,
        'created_at'        => $store->created_at,
        'updated_at'        => $store->updated_at,
        'business_hours'    => $hours,
    ], 'Store updated');
}

    /**
     * POST /api/owner/orders/{order}/state
     * Body: { state: "ready_to_delivery" | "delivered_to_delivery_boy" }
     */

    

public function updateOrderState(Order $order, Request $request)
{
    // Auth (same resilient pattern)
    $user = $request->user()
        ?? Auth::guard('api')->user()
        ?? (JWTAuth::check() ? JWTAuth::user() : null);

    if (!$user) {
        return $this->returnError(401, 'Unauthenticated. Provide a valid Bearer token.');
    }

    // Ensure the order belongs to a store owned by this user
    $order->loadMissing('store:id,owner_id');
    if (!$order->store || (int)$order->store->owner_id !== (int)$user->id) {
        return $this->returnError(403, 'You do not own this order/store');
    }

    // Accept ANY of your defined statuses
    $allStatuses = [
        'pending','preparing','ready','assigned','picked',
        'out_for_delivery','delivered','rejected','cancelled',
    ];

    $data = $request->validate([
        'to_status' => ['required', Rule::in($allStatuses)],
        'reason'    => ['sometimes','nullable','string','max:500'],
    ]);

    $from = $order->status;
    $to   = $data['to_status'];

    // Idempotent: nothing to do
    if ($from === $to) {
        return $this->returnData('order', [
            'order_id' => $order->id,
            'status'   => $order->status,
            'note'     => 'No change',
        ], 'Order state unchanged');
    }

    // Atomic update with row lock to avoid races
    DB::transaction(function () use ($order, $from, $to, $user, $data) {
        // Lock row & re-read current status
        $locked = Order::whereKey($order->id)->lockForUpdate()->first();
        $current = $locked->status;

        // If it changed during the request, surface a friendly error
        if ($current !== $from) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'to_status' => ["Order status changed concurrently ($from → $current). Please retry."],
            ]);
        }

        $locked->status = $to;
        $locked->save();

        OrderStatusHistory::create([
            'order_id'    => $locked->id,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $user->id,
            'reason'      => $data['reason']
                ?? "Status changed by shop owner ($from → $to)",
        ]);
    });

    $order->refresh();

    return $this->returnData('order', [
        'order_id' => $order->id,
        'status'   => $order->status,
    ], 'Order state updated');
}

}
