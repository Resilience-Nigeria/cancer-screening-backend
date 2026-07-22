<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuVisibilityRule extends Model
{
    protected $primaryKey = 'ruleId';

    protected $fillable = [
        'menuKey', 'menuLabel', 'allowedRoles',
    ];

    protected $casts = [
        'allowedRoles' => 'array',
    ];
}
