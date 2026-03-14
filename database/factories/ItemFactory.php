<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['inbox', 'next-action', 'project', 'waiting', 'someday', 'tickler', 'done']);

        return [
            'id' => Str::ulid(),
            'title' => fake()->sentence(fake()->numberBetween(3, 10)),
            'status' => $status,
            'context' => $status === 'next-action' ? fake()->randomElement(['@home', '@work', '@errands', '@computer', null]) : null,
            'waiting_for' => $status === 'waiting' ? fake()->name() : null,
            'waiting_date' => $status === 'waiting' ? fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d') : null,
            'tickler_date' => $status === 'tickler' ? fake()->dateTimeBetween('+1 day', '+90 days')->format('Y-m-d') : null,
            'notes' => fake()->optional(0.3)->paragraph(),
            'sort_order' => 0,
            'flagged' => fake()->boolean(10),
            'completed_at' => $status === 'done' ? fake()->dateTimeBetween('-30 days', 'now') : null,
            'original_status' => $status === 'done' ? fake()->randomElement(['inbox', 'next-action', 'project', 'waiting', 'someday']) : null,
        ];
    }
}
