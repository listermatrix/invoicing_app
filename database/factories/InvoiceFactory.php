<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number' => 'INV-' . strtoupper(fake()->unique()->bothify('####??')),
            'issue_date' => $issueDate,
            'due_date' => fake()->dateTimeBetween($issueDate, '+60 days'),
            'status' => fake()->randomElement(['draft', 'sent', 'paid', 'overdue']),
            'notes' => fake()->optional()->sentence(),
            'total_amount' => 0,
        ];
    }
}
