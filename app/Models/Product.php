<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'detail', 'price', 'image', 'stock', 'status', 'type', 'measure', 'brand'];
    public const TYPES = [
        'Flour',
        'Rice',
        'Milk',
        'Oil',
        'Tea',
        'Surf',
        'Pulses',
        'Spices'
    ];

    public static function getTypes(): array
    {
        return self::TYPES;
    }
}
