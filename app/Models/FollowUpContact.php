<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpContact extends Model
{
    protected $table = 'follow_up_contacts';
    protected $fillable = ['clientId', 'kind', 'refId', 'method', 'outcome', 'note', 'nextContactDate', 'userId'];
    protected $casts = ['nextContactDate' => 'date'];
}
