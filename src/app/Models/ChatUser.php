<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatUser extends Pivot
{
    protected $table = 'chat_user';

    public $timestamps = true;

    protected $fillable = [
        'chat_id',
        'user_id',
        'role',
        'muted',
    ];

    protected $casts = [
        'muted' => 'boolean',
    ];
}
