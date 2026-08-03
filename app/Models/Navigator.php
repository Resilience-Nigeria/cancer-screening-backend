<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Navigator extends Model
{
    use HasFactory;

    protected $table = 'navigators';
    protected $fillable = [
        'facilityId',
        'userId',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relationship with Users (if needed)
    public function users()
    {
        return $this->hasMany(User::class, 'userId');
    }

    // Relationship with Facilities (if needed)
    public function facilities()
    {
        return $this->hasMany(Facility::class, 'facilityId');
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}