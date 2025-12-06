<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;

class UserLookupController extends Controller
{
    public function __invoke(Request $r)
    {
        $data = $r->validate([
            'query' => ['required', 'string'],
        ]);

        $needle = trim($data['query']);
        $caller = $r->user();

        $contactIds = Contact::query()
            ->where('user_id', $caller->id)
            ->pluck('contact_user_id')
            ->all();

        $matches = User::query()
            ->where('id', '!=', $caller->id)
            ->where(function ($scope) use ($needle) {
                $scope->where('nickname', 'like', "%{$needle}%")
                    ->orWhere(function ($emailScope) use ($needle) {
                        $emailScope->where('email_hidden', false)
                            ->where('email', 'like', "%{$needle}%");
                    });
            })
            ->orderBy('nickname')
            ->limit(10)
            ->get(['id', 'nickname', 'email', 'email_hidden', 'avatar_path']);

        $payload = $matches->map(function (User $user) use ($contactIds) {
            $visibleEmail = $user->email_hidden ? null : $user->email;

            return [
                'id'           => $user->id,
                'nickname'     => $user->nickname ?? $user->name,
                'email'        => $visibleEmail,
                'email_hidden' => (bool) $user->email_hidden,
                'avatar'       => $user->avatar_path ? "/storage/{$user->avatar_path}" : null,
                'in_contacts'  => in_array($user->id, $contactIds, true),
            ];
        });

        return response()->json($payload);
    }
}