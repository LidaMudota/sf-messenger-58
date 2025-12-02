<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'nickname' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(User::class, 'nickname')->ignore($this->user()->id),
            ],
            'email_hidden' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }
    
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $nicknameFromRequest = $this->input('nickname', $this->user()->nickname);
            $shouldHide = $this->boolean('email_hidden', $this->user()->email_hidden);

            if ($shouldHide && !$nicknameFromRequest) {
                $v->errors()->add(
                    'email_hidden',
                    'Чтобы скрыть email, сначала задайте уникальный nickname.'
                );
            }
        });
    }
}
