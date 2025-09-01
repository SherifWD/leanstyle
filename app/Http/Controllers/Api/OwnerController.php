<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Store, Product, Order, OrderStatusHistory};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

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

        $data = $request->validate([
            'name'      => ['required','string','max:190'],
            'slug'      => ['nullable','string','max:190','unique:stores,slug'],
            'address'   => ['nullable','string','max:500'],
            'city'      => ['nullable','string','max:120'],
            'logo_path' => ['nullable','string','max:500'],
            'is_active' => ['boolean'],
        ]);

        $store = new Store($data);
        $store->owner_id = $user->id;
        $store->is_active = (bool)($data['is_active'] ?? true);
        $store->save();

        return $this->returnData('store', $store, 'Store created');
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
            'store_id'       => ['nullable','exists:stores,id'],
            'name'           => ['required','string','max:190'],
            'description'    => ['nullable','string'],
            'category_id'    => ['nullable','exists:categories,id'],
            'brand_id'       => ['nullable','exists:brands,id'],
            'price'          => ['required','numeric','min:0'],
            'discount_price' => ['nullable','numeric','min:0'],
            'stock'          => ['nullable','integer','min:0'],
            'type'           => ['nullable','string','max:50'], // mens|women|child etc
            'is_active'      => ['boolean'],
        ]);

        $store = Store::where('id', $data['store_id'])->where('owner_id', $uid)->first();
        abort_if(!$store, 403, 'You do not own this store');

        $p = new Product($data);
        $p->is_active = (bool)($data['is_active'] ?? true);
        $p->save();

        return $this->returnData('product', [
            'id'   => $p->id,
            'name' => $p->name,
        ], 'Product created');
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
