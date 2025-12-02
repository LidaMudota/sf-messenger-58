<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'body',
        'forwarded_from_message_id',
        'edited_at',
    ];

    public function chat()
    {
        return $this->belongsTo(\App\Models\Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
