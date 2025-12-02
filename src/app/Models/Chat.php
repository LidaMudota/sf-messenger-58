<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Message;
use App\Models\User;
use App\Models\ChatUser;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'owner_id',
        'muted_by_default',
    ];

    protected $casts = [
        'muted_by_default' => 'boolean',
    ];

    /**
     * Один чат имеет много сообщений.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Участники чата (pivot: chat_user).
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_user', 'chat_id', 'user_id')
            ->using(ChatUser::class)
            ->withPivot(['role', 'muted'])
            ->withTimestamps();
    }
}
