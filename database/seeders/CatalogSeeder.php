<?php

namespace Database\Seeders;

use App\Models\VariantAttribute;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Seeds the default warehouse and common attributes. Attributes stay fully editable in the UI.
     */
    public function run(): void
    {
        Warehouse::query()->firstOrCreate(['code' => 'MAIN'], ['name' => 'প্রধান গুদাম', 'is_default' => true, 'is_active' => true]);

        $attributes = [
            ['size', 'সাইজ', false, [['S', 'S', null], ['M', 'M', null], ['L', 'L', null], ['XL', 'XL', null], ['XXL', 'XXL', null]]],
            ['color', 'রং', true, [
                ['লাল', 'RED', '#dc2626'], ['সবুজ', 'GREEN', '#16a34a'], ['হলুদ', 'YELLOW', '#eab308'], ['সাদা', 'WHITE', '#f8fafc'],
                ['কালো', 'BLACK', '#0f172a'], ['নীল', 'BLUE', '#2563eb'], ['নেভি', 'NAVY', '#1e3a8a'],
            ]],
            ['fabric', 'কাপড়', false, [['সুতি', 'COT', null], ['পিকে', 'PIQ', null], ['পলিয়েস্টার', 'POLY', null]]],
        ];

        foreach ($attributes as $position => [$slug, $name, $isColor, $values]) {
            $attribute = VariantAttribute::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_color' => $isColor, 'sort_order' => $position],
            );

            foreach ($values as $order => [$value, $code, $swatch]) {
                $attribute->values()->firstOrCreate(['value' => $value], ['code' => $code, 'swatch' => $swatch, 'sort_order' => $order]);
            }
        }
    }
}
