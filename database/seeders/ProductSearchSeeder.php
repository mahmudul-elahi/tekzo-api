<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Product\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSearchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::query()
            ->where('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->whereHas('stockVariant', fn($q) => $q->where('status', '1'))
            ->whereHas('brand', fn($q) => $q->where('status', true))
            ->with(['category', 'brand']) // ← eager load, নাহলে toSearchableArray() তে N+1
            ->chunk(500, function ($products) {
                $products->searchable(); // ← collection এ একবারে call, loop না
            });

        $this->command->info('Products imported to Meilisearch successfully!');
    }
}
