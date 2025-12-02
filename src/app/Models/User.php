<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'nickname',
        'avatar_path',
        'email_hidden',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'email_hidden'      => 'boolean',
    ];

    /**
     * Сообщения пользователя.
     *
     * @return HasMany<Message>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Чаты, в которых состоит пользователь.
     *
     * pivot: chat_user(id, chat_id, user_id, role, muted, timestamps)
     *
     * @return BelongsToMany<Chat>
     */
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class, 'chat_user', 'user_id', 'chat_id')
            ->using(ChatUser::class)
            ->withPivot(['role', 'muted'])
            ->withTimestamps();
    }

    /**
     * Контакты пользователя.
     *
     * @return HasMany<Contact>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
