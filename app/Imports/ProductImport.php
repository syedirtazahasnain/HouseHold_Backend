<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToModel, WithHeadingRow, WithChunkReading, ShouldQueue
{
    /**
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $item_name = trim($row['Item'] ?? $row['item'] ?? '');

        if (empty($item_name)) {
            Log::channel('product_import')->error("Missing Item name", ['row' => $row]);
            return null;
        }

        $product = Product::updateOrCreate(
            ['name' => $item_name],
            [
                'detail'  => $item_name,
                'stock'   => $row['Qty'] ?? $row['qty'] ?? 0,
                'measure' => $row['Measure'] ?? $row['measure'] ?? 'Unit',
                'price'   => $row['Amount'] ?? $row['amount'] ?? 0,
                'type'    => $row['Type'] ?? $row['type'] ?? 'default',
                'brand'   => $row['Brand'] ?? $row['brand'] ?? null,
                'status'  => 1,
                'image'   => null,
            ]
        );

        Log::channel('product_import')->info("Product Imported", [
            'name' => $product->name,
            'stock' => $product->stock,
            'price' => $product->price,
        ]);

        return $product;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
