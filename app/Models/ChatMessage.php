<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';
    protected $fillable = ['conversationId', 'senderId', 'body', 'clientId', 'clientMsgId', 'readAt'];
    protected $casts = ['readAt' => 'datetime'];
}
