<?php

namespace Database\Factories;

use App\Models\Barang;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockOut>
 */
class StockOutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barang_id' => Barang::inRandomOrder()->first()?->id ?? Barang::factory(),
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'selling_price' => fake()->numberBetween(5000, 500000),
            'date' => fake()->dateTimeBetween('-2 months', 'now'),
            'purpose' => fake()->randomElement(['sale', 'internal_use', 'damaged']),
            'note' => fake()->optional()->sentence(),
        ];
    }
}