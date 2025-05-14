<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Option extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['key', 'value', 'status'];

    static function getValueByKey($key){
        $option = static::where("key", $key)->first();
        if(is_object($option)){
            return $option->value;
        }
    }

    // $min_qty = Option::getValueByKey('MIN_QUANTITY_' . $customer->channel_id);
}
