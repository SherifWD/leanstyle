<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Customer, Order};
use Illuminate\Http\Request;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class OrderController extends Controller
{
    use backendTraits, HelpersTrait;

    private function meCustomer(Request $request): Customer
    {
        return Customer::firstOrCreate(
            ['email' => $request->user('api')->email, 'phone' => $request->user('api')->phone],
            ['name'  => $request->user('api')->name]
        );
    }

    // GET /api/orders?status=&per_page=
    public function index(Request $request)
    {
        $customer = $this->meCustomer($request);

        $q = Order::query()->where('customer_id', $customer->id)->latest('id');

        $q->when($request->filled('status'), fn($x) => $x->where('status', $request->status));

        $orders = $q->paginate($request->integer('per_page', 15))
            ->through(fn($o) => [
                'id'          => $o->id,
                'order_code'  => $o->order_code,
                'status'      => $o->status,
                'grand_total' => (float)$o->grand_total,
                'created_at'  => $o->created_at?->toIso8601String(),
            ]);

        return $this->returnData('orders', $orders, "Orders list");
    }

    // GET /api/orders/{order}
    public function show(Order $order, Request $request)
    {
        $customer = $this->meCustomer($request);
        abort_if($order->customer_id !== $customer->id, 403);

        $order->load(['items','store:id,name,logo_path,address','assignment.driver:id,name,phone']);

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
            ]),
            'store'  => $order->store,
            'driver' => $order->assignment?->driver,
        ];

        return $this->returnData('order', $data, "Order details");
    }

    // GET /api/orders/{order}/timeline
    public function timeline(Order $order, Request $request)
    {
        $customer = $this->meCustomer($request);
        abort_if($order->customer_id !== $customer->id, 403);

        $events = $order->statusHistories()
            ->orderBy('created_at')
            ->get(['from_status','to_status','reason','created_at'])
            ->map(fn($h) => [
                'from'   => $h->from_status,
                'to'     => $h->to_status,
                'reason' => $h->reason,
                'at'     => $h->created_at?->toIso8601String(),
            ]);

        return $this->returnData('timeline', [
            'order_id' => $order->id,
            'events'   => $events
        ], "Order timeline");
    }
}
