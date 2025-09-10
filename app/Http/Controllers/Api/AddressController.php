<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Customer, CustomerAddress};
use Illuminate\Http\Request;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;

class AddressController extends Controller
{
    use backendTraits, HelpersTrait;

    /** Map the authenticated user to a Customer row (create if missing). */
    private function meCustomer(Request $request): Customer
    {
        $u = $request->user('customer');

        // Prefer phone as a stable key; fall back to email, then name.
        $query = [];
        if (!empty($u->phone)) $query['phone'] = $u->phone;
        if (!empty($u->email)) $query['email'] = $u->email;

        if (empty($query)) {
            // Last resort: create a standalone customer with just the name.
            return Customer::firstOrCreate(['name' => $u->name ?: "User {$u->id}"]);
        }

        return Customer::firstOrCreate(
            $query,
            ['name' => $u->name]
        );
    }

    /** GET /api/addresses */
    public function index(Request $request)
    {
        $customer = $this->meCustomer($request);

        $addresses = $customer->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return $this->returnData('addresses', $addresses, 'Addresses List');
    }

    /** POST /api/addresses */
    public function store(Request $request)
    {
        $customer = $this->meCustomer($request);

        $data = $request->validate([
            'name' => ['nullable','string'],
            'phone' => ['nullable'],
            'label'        => ['nullable','string','max:60'],
            'address_line' => ['required','string','max:500'],
            'lat'          => ['nullable','numeric'],
            'lng'          => ['nullable','numeric'],
            'is_default'   => ['boolean'],
        ]);

        $address = $customer->addresses()->create($data);

        if (!empty($data['is_default'])) {
            $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        return $this->returnData('address', $address, 'Address Created');
    }
    public function sendOTP(){
        return $this->returnData('otp','111111',200);
    }
    public function updateIsVerified(Request $request){
        if($request->otp == "111111" && $request->address_id){
            $address = CustomerAddress::find($request->address_id)->update(['is_verified'=>1]);
        }
        return $this->returnSuccessMessage('Address Verified');
    }
    /** PUT /api/addresses/{address} */
    public function update(CustomerAddress $address, Request $request)
    {
        $customer = $this->meCustomer($request);
        abort_if($address->customer_id !== $customer->id, 403);

        $data = $request->validate([
            'name' => ['nullable','string'],
            'phone' => ['nullable'],
            'label'        => ['nullable','string','max:60'],
            'address_line' => ['required','string','max:500'],
            'lat'          => ['nullable','numeric'],
            'lng'          => ['nullable','numeric'],
            'is_default'   => ['boolean'],
        ]);

        $address->update($data);

        if (!empty($data['is_default'])) {
            $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        return $this->returnData('address', $address->fresh(), 'Address Updated');
    }
    

    /** DELETE /api/addresses/{address} */
    public function destroy(CustomerAddress $address, Request $request)
    {
        $customer = $this->meCustomer($request);
        abort_if($address->customer_id !== $customer->id, 403);

        $address->delete();

        return $this->returnData('deleted', true, 'Address Deleted');
    }

    /** POST /api/addresses/{address}/make-default */
    public function makeDefault(CustomerAddress $address, Request $request)
    {
        $customer = $this->meCustomer($request);
        abort_if($address->customer_id !== $customer->id, 403);

        $customer->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $this->returnData('address', $address->fresh(), 'Default Address Set');
    }
}
