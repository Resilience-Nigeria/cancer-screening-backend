<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportRecipient extends Model
{
    protected $table = 'report_recipients';

    protected $fillable = ['reportKey', 'type', 'roleName', 'userId', 'email', 'name', 'scope', 'facilityIds', 'isExcluded', 'active', 'createdBy'];

    protected $casts = [
        'facilityIds' => 'array',
        'isExcluded'  => 'boolean',
        'active'      => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}
