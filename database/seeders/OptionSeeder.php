<?php

namespace Database\Seeders;

use App\Models\Option;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class OptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            [
                'key' => 'max_order_amount',
                'value' => '25000',
                'status' => 1
            ],
            [
                'key' => 'last_order_date',
                'value' => '2025-04-18',
                'status' => 1
            ],
            [
                'key' => 'company_share',
                'value' => '10',
                'status' => 1
            ]
        ];

        foreach ($options as $option) {
            Option::updateOrCreate(
                ['key' => $option['key']],
                $option
            );
        }
    }
}
