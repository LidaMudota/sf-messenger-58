<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $r)
    {
        $contacts = Contact::where('user_id', $r->user()->id)
            ->with('contactUser')
            ->get();

        return response()->json($contacts);
    }

    // поиск по nickname/email и добавление
    public function store(Request $r)
    {
        $data = $r->validate([
            'query' => ['required', 'string'],
        ]);

        $me = $r->user();
        $q = $data['query'];

        // Ищем:
        // 1) по точному nickname
        // 2) по email, если email_hidden = false
        $user = User::query()
            ->where('nickname', $q)
            ->orWhere(function($sub) use ($q) {
                $sub->where('email_hidden', false)
                    ->where('email', $q);
            })
            ->firstOrFail();

        // Нельзя добавить самого себя
        abort_if($user->id === $me->id, 422, 'self not allowed');

        Contact::firstOrCreate([
            'user_id'         => $me->id,
            'contact_user_id' => $user->id,
        ]);

        return response()->json([
            'ok' => true,
            'contact_user_id' => $user->id
        ], 201);
    }
}
