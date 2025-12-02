<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $r)
    {
        return response()->json($r->user());
    }

    public function update(Request $r)
    {
        $data = $r->validate([
            'nickname'     => ['nullable','string','max:50','unique:users,nickname,' . $r->user()->id],
            'email_hidden' => ['nullable','boolean'],
            'avatar'       => ['nullable','image','max:2048'],
        ]);

        $u = $r->user();

        if ($r->hasFile('avatar')) {
            if ($u->avatar_path) {
                Storage::disk('public')->delete($u->avatar_path);
            }

            $path = $r->file('avatar')->store('avatars', 'public');
            $u->avatar_path = $path;
        }

        if (array_key_exists('nickname', $data)) {
            $u->nickname = $data['nickname'];
        }

        if (array_key_exists('email_hidden', $data)) {
            $u->email_hidden = (bool) $data['email_hidden'];
        }

        $u->save();

        return response()->json($u->fresh());
    }
}
