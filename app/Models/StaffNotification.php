<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffNotification extends Model
{
    protected $table = 'staff_notifications';
    protected $fillable = ['userId', 'type', 'title', 'body', 'route', 'data', 'broadcastId', 'readAt'];
    protected $casts = ['data' => 'array', 'readAt' => 'datetime'];

    public function toApp(): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'title'     => $this->title,
            'body'      => $this->body,
            'route'     => $this->route,
            'data'      => $this->data ?? (object) [],
            'read'      => $this->readAt !== null,
            'readAt'    => $this->readAt?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
