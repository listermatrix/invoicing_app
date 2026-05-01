<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Create a new policy instance.
     */


    public function update(User $user, Customer $invoice): bool
    {
        return $user->isAdmin();
    }
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }
}
