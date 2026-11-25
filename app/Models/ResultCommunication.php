<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultCommunication extends Model
{
    protected $table = 'result_communications';
    protected $fillable = ['sampleId', 'clientId', 'method', 'communicatedAt', 'counselling', 'counselledBy', 'clientUnderstood', 'notes', 'userId'];
    protected $casts = ['communicatedAt' => 'datetime', 'counselling' => 'boolean', 'clientUnderstood' => 'boolean'];
}
