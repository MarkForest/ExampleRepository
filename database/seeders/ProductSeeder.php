<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Фіксований запис
        Product::query()->create([
            'name' => 'Laravel Mug',
            'price' => 199.00,
            'description' => 'Чашка з логотипом Laravel',
            'stock' => 50,
        ]);
    }
}
