<?php

namespace Database\Seeders;

use App\Models\Context;
use Illuminate\Database\Seeder;

class ContextSeeder extends Seeder
{
    public function run(): void
    {
        $contexts = [
            ['name' => '🏠 @house', 'built_in' => true, 'sort_order' => 0],
            ['name' => '💼 @work', 'built_in' => true, 'sort_order' => 1],
        ];

        foreach ($contexts as $context) {
            Context::firstOrCreate(['name' => $context['name']], $context);
        }
    }
}
