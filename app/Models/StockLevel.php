<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $table = 'stock_levels';
    protected $fillable = ['facilityId', 'itemId', 'quantity', 'lowLevel', 'lastAlertAt'];
    protected $casts = ['quantity' => 'float', 'lastAlertAt' => 'datetime'];
}
