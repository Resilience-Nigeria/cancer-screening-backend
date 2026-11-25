<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';
    protected $fillable = ['userId', 'category', 'message', 'device', 'syncLog', 'status', 'adminNote', 'resolvedById', 'resolvedAt'];
    protected $casts = ['device' => 'array', 'syncLog' => 'array', 'resolvedAt' => 'datetime'];
}
