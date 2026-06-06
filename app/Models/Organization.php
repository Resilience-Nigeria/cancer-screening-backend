<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizationName',
        'organizationCode',
        'description',
        'status',
        'contactEmail',
        'contactPhone',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relationship with Users (if needed)
    public function users()
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}