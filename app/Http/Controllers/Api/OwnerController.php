<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Store, Product, Order, OrderStatusHistory, ProductVariant};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Support\Arr;

class OwnerController extends Controller
{
    use backendTraits, HelpersTrait;

    /** GET /api/owner/shops */
    public function myShops(Request $request)
    {
        $uid = $request->user('api')->id;

        $shops = Store::where('owner_id', $uid)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->returnData('shops', $shops, 'My shops');
    }

    /** POST /api/owner/shops */
    public function createShop(Request $request)
{
    $user = $request->user('api');

    // 1) Validate shop fields
    $data = $request->validate([
        'name'             => ['required','string','max:255'],
        'slug'             => [
            'nullable','string','max:255','regex:/^[a-z0-9-]+$/',
            Rule::unique('stores', 'slug')->whereNull('deleted_at'),
        ],
        'logo_path'        => ['nullable','string','max:255'],
        'brand_color'      => ['nullable','string','max:255'],
        'description'      => ['nullable','string'],
        'address'          => ['nullable','string','max:255'],
        'lat'              => ['nullable','numeric','between:-90,90'],
        'lng'              => ['nullable','numeric','between:-180,180'],
        'is_active'        => ['nullable','boolean'],
        'delivery_settings'=> ['nullable'],
        'country'          => ['nullable','string','max:255'],
        'city'             => ['nullable','string','max:255'],

        // Business hours array
        'business_hours'               => ['required','array','min:1'],
        'business_hours.*.weekday'     => ['required','integer','between:0,6'],
        'business_hours.*.open_at'     => ['nullable','date_format:H:i'],
        'business_hours.*.close_at'    => ['nullable','date_format:H:i','after:business_hours.*.open_at'],
        'business_hours.*.is_closed'   => ['boolean'],
    ]);

    // Normalize delivery_settings
    if (array_key_exists('delivery_settings', $data)) {
        if (is_string($data['delivery_settings']) && $data['delivery_settings'] !== '') {
            $decoded = json_decode($data['delivery_settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->returnError(422, 'delivery_settings must be valid JSON');
            }
            $data['delivery_settings'] = $decoded;
        } elseif (!is_array($data['delivery_settings']) && !is_null($data['delivery_settings'])) {
            return $this->returnError(422, 'delivery_settings must be an object/array or JSON string');
        }
    }

    // Slug auto-generate if missing
    if (empty($data['slug'])) {
        $base = Str::slug($data['name']);
        $slug = $base ?: Str::random(8);
        $i = 1;
        while (Store::withTrashed()->where('slug',$slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $data['slug'] = $slug;
    }

    // Create the store
    $store = new Store();
    $store->owner_id         = $user->id;
    $store->name             = $data['name'];
    $store->slug             = $data['slug'];
    $store->logo_path        = $data['logo_path']        ?? null;
    $store->brand_color      = $data['brand_color']      ?? null;
    $store->description      = $data['description']      ?? null;
    $store->address          = $data['address']          ?? null;
    $store->lat              = $data['lat']              ?? null;
    $store->lng              = $data['lng']              ?? null;
    $store->is_active        = array_key_exists('is_active',$data) ? (bool)$data['is_active'] : true;
    $store->delivery_settings= $data['delivery_settings']?? null;
    $store->country          = $data['country']          ?? null;
    $store->city             = $data['city']             ?? null;
    $store->save();

    // Insert business hours
    foreach ($data['business_hours'] as $bh) {
        BusinessHour::create([
            'store_id'  => $store->id,
            'weekday'   => $bh['weekday'],
            'open_at'   => $bh['open_at']  ?? null,
            'close_at'  => $bh['close_at'] ?? null,
            'is_closed' => $bh['is_closed'] ?? false,
        ]);
    }

    // Return
    return $this->returnData('store', [
        'id'                => $store->id,
        'owner_id'          => $store->owner_id,
        'name'              => $store->name,
        'slug'              => $store->slug,
        'logo_path'         => $store->logo_path,
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
        'business_hours'    => $store->businessHours()->get(),
    ], 'Store created');
}

    /** GET /api/owner/orders */
    public function myOrders(Request $request)
    {
        $uid = $request->user('api')->id;
        $status = $request->get('status'); // optional

        $q = Order::query()
            ->whereHas('store', fn($s) => $s->where('owner_id', $uid))
            ->with(['store:id,name','customer:id,name,phone'])
            ->latest('id');

        if ($status) $q->where('status', $status);

        $orders = $q->paginate($request->integer('per_page', 20))
            ->through(function (Order $o) {
                return [
                    'id'          => $o->id,
                    'order_code'  => $o->order_code,
                    'status'      => $o->status,
                    'grand_total' => (float)$o->grand_total,
                    'store'       => $o->store?->only('id','name'),
                    'customer'    => [
                        'id'    => $o->customer_id,
                        'name'  => $o->customer?->name,
                        'phone' => $o->customer?->phone,
                    ],
                    'created_at'  => $o->created_at?->toIso8601String(),
                ];
            });

        return $this->returnData('orders', $orders, 'Store orders');
    }

    /** POST /api/owner/products  (creates product in one of my shops) */
    public function createProduct(Request $request)
{
    $uid = $request->user('api')->id;

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

        // NEW: base product weight (grams or your unit)
        'weight'          => ['nullable','numeric','min:0'],

        // NEW: quick single-variant fields (optional)
        'size_id'         => ['nullable','exists:sizes,id'],
        'color_id'        => ['nullable','exists:colors,id'],
        'sku'             => ['nullable','string','max:255'],

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
                'sku'             => $p->name.'-'.rand(1,1000000),
                'price'           => $vPrice,
                'discount_price'  => $vDisc,
                'stock'           => (int)($v['stock'] ?? 0),
                'is_active'       => array_key_exists('is_active', $v) ? (bool)$v['is_active'] : true,
            ]);
        }
    } else {
        // If single-variant fields are provided, create one variant
        if (!empty($data['sku']) || !empty($data['size_id']) || !empty($data['color_id'])) {
            $createdVariants[] = ProductVariant::create([
                'product_id'      => $p->id,
                'color_id'        => $data['color_id'] ?? null,
                'size_id'         => $data['size_id']  ?? null,
                'sku'             => $data['sku']      ?? 'SKU-'.strtoupper(Str::random(6)),
                'price'           => $p->price,
                'discount_price'  => $p->discount_price,
                'stock'           => (int)($data['stock'] ?? 0),
                'is_active'       => true,
            ]);
        }
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
        'variants'        => collect($createdVariants)->map(function ($v) {
            return [
                'id'             => $v->id,
                'sku'            => $v->sku,
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
public function updateShop(Store $store, Request $request)
{
    $user = $request->user('api');

    // Ownership check without throwing 404
    if (!$store || $store->owner_id !== $user->id) {
        return $this->returnError(403, 'You do not own this store');
    }

    $data = $request->validate([
        'name'             => ['sometimes','required','string','max:255'],
        'slug'             => [
            'sometimes','nullable','string','max:255','regex:/^[a-z0-9-]+$/',
            Rule::unique('stores','slug')->ignore($store->id)->whereNull('deleted_at'),
        ],
        'logo_path'        => ['sometimes','nullable','string','max:255'],
        'brand_color'      => ['sometimes','nullable','string','max:255'],
        'description'      => ['sometimes','nullable','string'],
        'address'          => ['sometimes','nullable','string','max:255'],
        'lat'              => ['sometimes','nullable','numeric','between:-90,90'],
        'lng'              => ['sometimes','nullable','numeric','between:-180,180'],
        'is_active'        => ['sometimes','boolean'],
        'delivery_settings'=> ['sometimes','nullable'], // JSON string or array
        'country'          => ['sometimes','nullable','string','max:255'],
        'city'             => ['sometimes','nullable','string','max:255'],
    ]);

    // Normalize delivery_settings (accept JSON string or array)
    if (array_key_exists('delivery_settings', $data)) {
        $value = $data['delivery_settings'];
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->returnError(422, 'delivery_settings must be valid JSON');
            }
            $data['delivery_settings'] = $decoded;
        } elseif (!is_array($value) && !is_null($value)) {
            return $this->returnError(422, 'delivery_settings must be an object/array or JSON string');
        }
    }

    // If slug provided empty, regenerate from name (or current name)
    if (array_key_exists('slug', $data) && empty($data['slug'])) {
        $base = Str::slug($data['name'] ?? $store->name);
        $slug = $base ?: Str::random(8);
        $i = 1;
        while (Store::withTrashed()->where('slug',$slug)->where('id','<>',$store->id)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $data['slug'] = $slug;
    }

    $store->fill($data);
    $store->save();

    return $this->returnData('store', [
        'id'                => $store->id,
        'owner_id'          => $store->owner_id,
        'name'              => $store->name,
        'slug'              => $store->slug,
        'logo_path'         => $store->logo_path,
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
    ], 'Store updated');
}
    /**
     * POST /api/owner/orders/{order}/state
     * Body: { state: "ready_to_delivery" | "delivered_to_delivery_boy" }
     */

    public function updateOrderState(Order $order, Request $request)
    {
        $uid = $request->user('api')->id;

        // Ensure I own the store this order belongs to
        abort_if(!$order->store || $order->store->owner_id !== $uid, 403);

        $data = $request->validate([
            'state' => ['required', Rule::in(['ready_to_delivery','delivered_to_delivery_boy'])],
        ]);

        $map = [
            'ready_to_delivery'         => 'ready_to_delivery',
            'delivered_to_delivery_boy' => 'assigned', // when it leaves store to driver, order becomes 'assigned'
        ];
        $to = $map[$data['state']];

        DB::transaction(function () use ($order, $to, $uid, $data) {
            $from = $order->status;
            $order->update(['status' => $to]);

            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => $from,
                'to_status'   => $to,
                'changed_by'  => $uid,
                'reason'      => $data['state'] === 'ready_to_delivery'
                    ? 'Shop marked Ready to Delivery'
                    : 'Shop handed order to delivery boy',
            ]);
        });

        return $this->returnData('order', [
            'order_id' => $order->id,
            'status'   => $order->status,
        ], 'Order state updated');
    }
}
