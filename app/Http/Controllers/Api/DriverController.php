<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{
    Order, OrderAssignment, OrderStatusHistory,
    DriverAvailability, DriverCashLedger
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class DriverController extends Controller
{
    use backendTraits, HelpersTrait;

    /** Basic profile + availability + balance */
    public function me(Request $request)
    {
        $u = $request->user('api');
        $u['balance'] = $this->driverBalance($u->id);
        return $this->returnData('data', $u, "Driver Profile");
    }

    /** Toggle availability */
    public function setAvailability(Request $request)
    {
        $data = $request->validate(['is_available' => ['required','boolean']]);

        $u = $request->user('api');
        $u->is_available = $data['is_available'];
        $u->save();

        DriverAvailability::create([
            'driver_id'   => $u->id,
            'is_available'=> $data['is_available'],
            'changed_at'  => now(),
        ]);

        return $this->returnData('data', ['is_available' => $u->is_available], "Availability Updated");
    }

    /** List driver orders */
    public function orders(Request $request)
    {
        $driverId = $request->user('api')->id;
        $status   = strtolower((string) $request->get('status', 'active'));

        // Map common aliases
        $aliases = [
            'current'   => 'active',
            'assigned'  => 'pending',
            'inprogress'=> 'active',
            'in_progress'=> 'active',
            'done'      => 'delivered',
            'completed' => 'delivered',
            'canceled'  => 'cancelled',
        ];
        $status = $aliases[$status] ?? $status;

        $q = Order::query()
            ->with(['store:id,name,logo_path,address,lat,lng', 'customer:id,name,phone'])
            ->whereHas('assignment', fn($a) => $a->where('driver_id', $driverId));

        if ($status === 'pending') {
            // Assigned but not accepted/rejected yet
            $q->whereHas('assignment', fn($a) => $a->whereNull('accepted_at')->whereNull('rejected_at'))
              ->whereNotIn('status', ['delivered','cancelled','rejected']);
        } elseif ($status === 'ready') {
            // Orders ready for pickup (assigned or already picked)
            $q->whereIn('status', ['assigned','picked']);
        } elseif ($status === 'active') {
            // Accepted, not completed/cancelled/rejected
            $q->whereNotIn('status', ['delivered','cancelled','rejected'])
              ->whereHas('assignment', fn($a) => $a->whereNotNull('accepted_at')->whereNull('completed_at'));
        } elseif ($status === 'history') {
            // Delivered OR Cancelled OR Rejected
            $q->whereIn('status', ['delivered','cancelled','rejected']);
        } elseif (in_array($status, ['delivered','cancelled','rejected'], true)) {
            $q->where('status', $status);
        } else {
            // Fallback to latest assigned orders
            $q->latest('id');
        }

        $orders = $q->latest('id')->paginate($request->integer('per_page', 15))
            ->through(function (Order $o) {
                return [
                    'id'          => $o->id,
                    'order_code'  => $o->order_code,
                    'status'      => $o->status,
                    'grand_total' => (float)$o->grand_total,
                    'store'       => $o->store?->only('id','name','logo_path'),
                    'customer'    => [
                        'id'    => $o->customer_id,
                        'name'  => $o->customer?->name,
                        'phone' => $o->customer?->phone,
                    ],
                    'ship_from'   => [
                        'address' => $o->store?->address,
                        'lat'     => $o->store?->lat,
                        'lng'     => $o->store?->lng,
                    ],
                    'ship_to'     => [
                        'address' => $o->ship_address,
                        'lat'     => $o->ship_lat,
                        'lng'     => $o->ship_lng,
                    ],
                    'assigned_at' => $o->assignment?->assigned_at?->toIso8601String(),
                ];
            });

        return $this->returnData('orders', $orders, "Orders List");
    }

    /** Accept assignment */
    public function accept(Order $order, Request $request)
    {
        $driver = $request->user('api');
        $assignment = $this->ownedAssignmentOrAbort($order, $driver->id);

        abort_if($assignment->accepted_at, 422, 'Order already accepted');
        abort_if($assignment->rejected_at, 422, 'Order already rejected');

        DB::transaction(function () use ($order, $assignment, $driver) {
            $assignment->accepted_at = now();
            $assignment->save();
            $this->transition($order, 'assigned', $driver->id, 'Driver accepted assignment');
        });

        return $this->returnData('order', ['order_id' => $order->id], "Accepted");
    }

    /** Reject assignment */
    public function reject(Order $order, Request $request)
    {
        $driver = $request->user('api');
        $assignment = $this->ownedAssignmentOrAbort($order, $driver->id);

        abort_if($assignment->accepted_at, 422, 'Already accepted');
        abort_if($assignment->rejected_at, 422, 'Already rejected');

        DB::transaction(function () use ($order, $assignment, $driver) {
            $assignment->rejected_at = now();
            $assignment->save();
            $this->transition($order, 'rejected', $driver->id, 'Driver rejected assignment');
        });

        return $this->returnData('order', ['order_id' => $order->id], "Rejected");
    }

    /** Update status */
    public function updateStatus(Order $order, Request $request)
    {
        $data = $request->validate([
            'to_status' => ['required','in:started,picked,out_for_delivery,delivered'],
        ]);

        $driver = $request->user('api');
        $assignment = $this->ownedAssignmentOrAbort($order, $driver->id);
        // abort_if(!$assignment->accepted_at, 422, 'Accept the order first');

        $from = $order->status;
        $allowed = [
            'assigned'        => ['started','picked'],
            'started'         => ['picked'],
            'picked'          => ['out_for_delivery'],
            'out_for_delivery'=> ['delivered'],
            'delivered'=> ['delivered'],
        ];

        if (! isset($allowed[$from]) || ! in_array($data['to_status'], $allowed[$from], true)) {
            return response()->json(['result'=>false,'msg'=>"Invalid transition $from → {$data['to_status']}"], 422);
        }

        DB::transaction(function () use ($order, $assignment, $driver, $data) {
            $now = now();
            match ($data['to_status']) {
                'started'          => $assignment->started_at = $now,
                'picked'           => $assignment->picked_at = $now,
                'out_for_delivery' => $assignment->out_for_delivery_at = $now,
                'delivered'        => $assignment->completed_at = $now,
            };
            $assignment->save();
            $this->transition($order, $data['to_status'], $driver->id, 'Driver status update');

            if ($data['to_status'] === 'delivered' && $order->payment_method === 'cod') {
                DriverCashLedger::firstOrCreate([
                    'driver_id' => $driver->id,
                    'order_id'  => $order->id,
                    'type'      => 'collect',
                ], [
                    'amount'    => $order->grand_total,
                    'note'      => 'Auto collect on delivery',
                    'effective_at' => now(),
                ]);
            }
        });

        return $this->returnData('order', ['order_id' => $order->id, 'status' => $order->status], "Updated");
    }

    /** Cash summary */
    public function cashSummary(Request $request)
    {
        $driverId = $request->user('api')->id;

        $collect = (float) DriverCashLedger::where('driver_id',$driverId)->where('type','collect')->sum('amount');
        $remit   = (float) DriverCashLedger::where('driver_id',$driverId)->where('type','remit')->sum('amount');
        $adj     = (float) DriverCashLedger::where('driver_id',$driverId)->where('type','adjustment')->sum('amount');

        return $this->returnData('cash', [
            'collected_total' => $collect,
            'remitted_total'  => $remit,
            'adjustments'     => $adj,
            'balance'         => $collect - $remit + $adj,
        ], "Cash Summary");
    }

    /** Collect cash */
    public function collectCash(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required','exists:orders,id'],
            'amount'   => ['required','numeric','min:0'],
            'note'     => ['nullable','string','max:500'],
        ]);

        $driverId = $request->user('api')->id;
        $order = Order::findOrFail($data['order_id']);
        $this->ownedAssignmentOrAbort($order, $driverId);

        $entry = DriverCashLedger::create([
            'driver_id'   => $driverId,
            'order_id'    => $order->id,
            'type'        => 'collect',
            'amount'      => $data['amount'],
            'note'        => $data['note'] ?? null,
            'effective_at'=> now(),
        ]);

        return $this->returnData('entry', ['entry_id' => $entry->id], "Cash Collected");
    }

    /** Remit cash */
    public function remitCash(Request $request)
    {
        $data = $request->validate([
            'amount'    => ['required','numeric','min:0.01'],
            'reference' => ['nullable','string','max:120'],
            'note'      => ['nullable','string','max:500'],
        ]);

        $driverId = $request->user('api')->id;

        $entry = DriverCashLedger::create([
            'driver_id'   => $driverId,
            'type'        => 'remit',
            'amount'      => $data['amount'],
            'note'        => $data['note'] ?? $data['reference'] ?? null,
            'effective_at'=> now(),
        ]);

        return $this->returnData('entry', ['entry_id' => $entry->id], "Remittance Logged");
    }

    /** ---------- helpers ---------- */

    private function ownedAssignmentOrAbort(Order $order, int $driverId): OrderAssignment
    {
        $assignment = OrderAssignment::where('order_id', $order->id)
            ->where('driver_id', $driverId)
            ->first();

        abort_if(!$assignment, 403, 'Not your assignment');

        return $assignment;
    }

    private function transition(Order $order, string $to, int $byUserId, ?string $reason = null): void
    {
        $from = $order->status;
        $order->update(['status' => $to]);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'from_status'=> $from,
            'to_status'  => $to,
            'changed_by' => $byUserId,
            'reason'     => $reason,
        ]);
    }

    private function driverBalance(int $driverId): float
    {
        $collect = (float) DriverCashLedger::where('driver_id',$driverId)->where('type','collect')->sum('amount');
        $remit   = (float) DriverCashLedger::where('driver_id',$driverId)->where('type','remit')->sum('amount');
        $adj     = (float) DriverCashLedger::where('driver_id',$driverId)->where('type','adjustment')->sum('amount');
        return $collect - $remit + $adj;
    }
}
