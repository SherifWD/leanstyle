<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule as RuleContract;
use App\Models\User;
use App\Models\Customer;

class UniquePhoneAcrossAccounts implements RuleContract
{
    protected $ignoreUserId;
    protected $ignoreCustomerId;

    public function __construct($ignoreUserId = null, $ignoreCustomerId = null)
    {
        $this->ignoreUserId = $ignoreUserId;
        $this->ignoreCustomerId = $ignoreCustomerId;
    }

    public function passes($attribute, $value)
    {
        $userExists = User::where('phone', $value)
            ->when($this->ignoreUserId, fn($q) => $q->where('id','<>',$this->ignoreUserId))
            ->exists();

        $customerExists = class_exists(Customer::class)
            ? Customer::where('phone', $value)
                ->when($this->ignoreCustomerId, fn($q) => $q->where('id','<>',$this->ignoreCustomerId))
                ->exists()
            : false;

        return !$userExists && !$customerExists;
    }

    public function message()
    {
        return 'The phone has already been taken.';
    }
}
