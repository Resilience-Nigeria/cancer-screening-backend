<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $table = 'chat_conversations';
    protected $fillable = ['userA', 'userB', 'lastMessageAt'];
    protected $casts = ['lastMessageAt' => 'datetime'];

    public function otherThan(int $me): int
    {
        return (int) ($this->userA === $me ? $this->userB : $this->userA);
    }
}
