<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Cart, CartItem, Product, ProductVariant, Customer, Order, OrderItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class CartController extends Controller
{
    use backendTraits, HelpersTrait;

    private function myCart(Request $request): Cart
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $request->user('customer')->id,
            'status'  => 'active',
        ]);
        $cart->load('items');
        return $cart;
    }

    // GET /api/cart
    public function show(Request $request)
    {
        $cart = $this->myCart($request);
        return $this->returnData('cart', $this->payload($cart), 'Cart fetched');
    }

    // POST /api/cart/add {product_id, product_variant_id?, qty}
    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id'         => ['required','exists:products,id'],
            'product_variant_id' => ['nullable','exists:product_variants,id'],
            'qty'                => ['nullable','integer','min:1'],
        ]);
        $qty = (int)($data['qty'] ?? 1);

        $cart    = $this->myCart($request);
        $product = Product::with('images')->findOrFail($data['product_id']);

        $variant = null;
        if (!empty($data['product_variant_id'])) {
            $variant = ProductVariant::findOrFail($data['product_variant_id']);
            abort_if($variant->product_id !== $product->id, 422, 'Variant mismatch');
            abort_if($variant->stock < $qty, 422, 'Insufficient stock for variant');
        } else {
            abort_if($product->stock < $qty && $product->variants()->count() === 0, 422, 'Insufficient stock');
        }

        // merge line if same product+variant
        $line = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        $unit = (float)($variant->discount_price ?? $variant->price ?? $product->discount_price ?? $product->price);

        if ($line) {
            $newQty = $line->qty + $qty;
            abort_if($variant?->stock !== null && $newQty > $variant->stock, 422, 'Insufficient stock');
            $line->qty        = $newQty;
            $line->unit_price = $unit;
            $line->line_total = $unit * $newQty - (float)$line->discount;
            $line->save();
        } else {
            $cart->items()->create([
                'product_id'         => $product->id,
                'product_variant_id' => $variant?->id,
                'name'               => $product->name,
                'options'            => $variant ? [
                    'color' => $variant->color?->name,
                    'size'  => $variant->size?->name,
                ] : null,
                'qty'        => $qty,
                'unit_price' => $unit,
                'discount'   => 0,
                'line_total' => $unit * $qty,
            ]);
        }

        $cart->refresh();
        $this->recalculate($cart);

        return $this->returnData('cart', $this->payload($cart), 'Item added to cart');
    }

    // POST /api/cart/update {item_id, qty}
    public function updateItem(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required','exists:cart_items,id'],
            'qty'     => ['required','integer','min:1'],
        ]);

        $cart = $this->myCart($request);
        $item = $cart->items()->findOrFail($data['item_id']);

        // stock checks
        if ($item->product_variant_id) {
            $variantStock = ProductVariant::find($item->product_variant_id)?->stock ?? 0;
            abort_if($data['qty'] > $variantStock, 422, 'Insufficient stock');
        } else {
            $productStock = Product::find($item->product_id)?->stock ?? 0;
            abort_if($data['qty'] > $productStock, 422, 'Insufficient stock');
        }

        $item->qty        = $data['qty'];
        $item->line_total = ($item->unit_price - $item->discount) * $item->qty;
        $item->save();

        $this->recalculate($cart->refresh());
        return $this->returnData('cart', $this->payload($cart), 'Cart item updated');
    }

    // POST /api/cart/remove {item_id}
    public function remove(Request $request)
    {
        $data = $request->validate([
            'item_id' => ['required','exists:cart_items,id'],
        ]);

        $cart = $this->myCart($request);
        $cart->items()->where('id', $data['item_id'])->delete();
        $this->recalculate($cart->refresh());

        return $this->returnData('cart', $this->payload($cart), 'Item removed from cart');
    }

    // POST /api/cart/clear
    public function clear(Request $request)
    {
        $cart = $this->myCart($request);
        $cart->items()->delete();
        $this->recalculate($cart->refresh());

        return $this->returnData('cart', $this->payload($cart), 'Cart cleared');
    }

    // POST /api/cart/apply-coupon {code}
    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => ['required','string']]);

        $cart = $this->myCart($request);
        // TODO: integrate Coupon & CouponRedemption rules here
        // (currently a no-op)

        return $this->returnData('cart', $this->payload($cart), 'Coupon applied');
    }

    // POST /api/cart/select-address {address_id}
    public function selectAddress(Request $request)
{
    $customer = $request->user('customer');

    $data = $request->validate([
        'address_id' => ['required','integer'],
    ]);

    $addr = \App\Models\CustomerAddress::where('customer_id', $customer->id)
        ->where('id', $data['address_id'])
        ->firstOrFail();

    abort_if(!$addr->is_verified, 422, 'Address must be verified before use.');

    $cart = $this->myCart($request);
    $cart->address_id = $addr->id;
    $cart->save();

    return $this->returnData('cart', $this->payload($cart), 'Address selected');
}
private function defaultVerifiedAddress(\App\Models\Customer $customer)
{
    return $customer->addresses()
        ->where('is_default', true)
        ->where('is_verified', true)
        ->first();
}
    // POST /api/cart/select-payment {payment_method: cod|card}
    public function selectPayment(Request $request)
    {
        $data = $request->validate(['payment_method' => ['required','in:cod,card']]);

        $cart = $this->myCart($request);
        $cart->payment_method = $data['payment_method'];
        $cart->save();

        return $this->returnData('cart', $this->payload($cart), 'Payment method selected');
    }

    // POST /api/cart/checkout
    public function checkout(Request $request)
{
    // FIX: use the customer guard (cart belongs to customers)
    $customer = $request->user('customer');
    $cart = $this->myCart($request)->load('items');

    abort_if($cart->items->isEmpty(), 422, 'Cart is empty');
    abort_if(!$cart->payment_method, 422, 'Select payment method');

    // Resolve address: selected → validated OR default verified
    $addr = null;

    if ($cart->address_id) {
        $addr = \App\Models\CustomerAddress::where('customer_id', $customer->id)
            ->where('id', $cart->address_id)
            ->first();
        abort_if(!$addr, 422, 'Selected address not found.');
        abort_if(!$addr->is_verified, 422, 'Selected address is not verified.');
    } else {
        $addr = $this->defaultVerifiedAddress($customer);
        abort_if(!$addr, 422, 'No default verified address found. Please verify or select an address.');
        // Persist the choice on cart for transparency (optional)
        $cart->address_id = $addr->id;
        $cart->save();
    }

    $result = DB::transaction(function () use ($cart, $customer, $addr) {
        // Create order
        $order = new Order();
        $order->store_id             = $cart->items->first()->product->store_id ?? $cart->store_id;
        $order->customer_id          = $customer->id;
        $order->address_id  = $addr->id;              // <-- keep the FK
        $order->status               = 'pending';
        $order->order_code           = strtoupper(Str::random(10));
        $order->subtotal             = $cart->subtotal;
        $order->discount_total       = $cart->discount_total;
        $order->tax_total            = $cart->tax_total;
        $order->delivery_fee         = $cart->delivery_fee;
        $order->grand_total          = $cart->grand_total;
        $order->payment_method       = $cart->payment_method;
        // denormalized shipping snapshot
        $order->ship_address         = $addr->address_line;
        $order->ship_lat             = $addr->lat;
        $order->ship_lng             = $addr->lng;
        $order->notes                = $cart->notes;
        $order->save();

        // Items + stock decrement
        foreach ($cart->items as $ci) {
            $product = Product::lockForUpdate()->find($ci->product_id);
            $variant = $ci->product_variant_id ? ProductVariant::lockForUpdate()->find($ci->product_variant_id) : null;

            if ($variant) {
                abort_if($variant->stock < $ci->qty, 422, 'Insufficient stock');
                $variant->decrement('stock', $ci->qty);
            } else {
                // If product has NO variants, decrement product stock
                if ($product && !$product->variants()->exists()) {
                    abort_if($product->stock < $ci->qty, 422, 'Insufficient stock');
                    $product->decrement('stock', $ci->qty);
                }
            }

            OrderItem::create([
                'order_id'           => $order->id,
                'product_id'         => $ci->product_id,
                'product_variant_id' => $ci->product_variant_id,
                'name'               => $ci->name,
                'options'            => $ci->options,
                'qty'                => $ci->qty,
                'unit_price'         => $ci->unit_price,
                'discount'           => $ci->discount,
                'line_total'         => $ci->line_total,
            ]);
        }

        // Status history (changed_by = customer)
        $order->statusHistories()->create([
            'from_status' => null,
            'to_status'   => 'pending',
            'changed_by'  => $customer->id,    // if this field is reserved for admins, set to null or add a "changed_by_type"
            'reason'      => 'Order placed via app',
        ]);

        // Close cart
        $cart->update(['status' => 'converted']);

        return [
            'order_id'    => $order->id,
            'order_code'  => $order->order_code,
            'grand_total' => (float) $order->grand_total,
        ];
    });

    return $this->returnData('order', $result, 'Order placed');
}

    /** ---------- helpers ---------- */

    private function recalculate(Cart $cart): void
    {
        $subtotal = (float) $cart->items->sum('line_total');
        $discount = 0.0; // apply coupons here if integrated
        $taxRate  = (float) (\App\Models\Setting::firstWhere('key','tax_rate')->value ?? 0);
        $delivery = (float) (\App\Models\Setting::firstWhere('key','default_delivery_fee')->value ?? 0);

        $tax   = round(($subtotal - $discount) * ($taxRate / 100), 2);
        $grand = max(0, $subtotal - $discount + $tax + $delivery);

        $cart->update([
            'subtotal'       => $subtotal,
            'discount_total' => $discount,
            'tax_total'      => $tax,
            'delivery_fee'   => $delivery,
            'grand_total'    => $grand,
        ]);
        $cart->load('items');
    }

    private function payload(Cart $cart)
    {
        return [
            'id'    => $cart->id,
            'items' => $cart->items->map(fn($i) => [
                'id'                 => $i->id,
                'product_id'         => $i->product_id,
                'product_variant_id' => $i->product_variant_id,
                'name'               => $i->name,
                'options'            => $i->options,
                'qty'                => (int)$i->qty,
                'unit_price'         => (float)$i->unit_price,
                'discount'           => (float)$i->discount,
                'line_total'         => (float)$i->line_total,
            ]),
            'totals' => [
                'subtotal'        => (float)$cart->subtotal,
                'discount_total'  => (float)$cart->discount_total,
                'tax_total'       => (float)$cart->tax_total,
                'delivery_fee'    => (float)$cart->delivery_fee,
                'grand_total'     => (float)$cart->grand_total,
            ],
            'selected_address_id' => $cart->address_id,
            'payment_method'      => $cart->payment_method,
        ];
    }
}
