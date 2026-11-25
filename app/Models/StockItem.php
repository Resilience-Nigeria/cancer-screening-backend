<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $table = 'stock_items';
    protected $fillable = ['code', 'name', 'unit', 'testTypes', 'perTest', 'defaultLowLevel', 'active'];
    protected $casts = ['testTypes' => 'array', 'perTest' => 'float', 'active' => 'boolean'];
}
