<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';
    protected $fillable = ['facilityId', 'itemId', 'type', 'quantity', 'balanceAfter', 'sampleId', 'otherFacilityId', 'batchNo', 'expiryDate', 'note', 'userId'];
    protected $casts = ['quantity' => 'float', 'balanceAfter' => 'float', 'expiryDate' => 'date'];
}
