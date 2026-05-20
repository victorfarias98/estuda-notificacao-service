<?php

namespace App\Http\Requests;

use App\Enums\CommunicationChannelEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'channel' => ['required', 'string', Rule::in(CommunicationChannelEnum::values())],
            'subject' => ['nullable', 'string', 'max:255', Rule::requiredIf(
                fn (): bool => $this->input('channel') === CommunicationChannelEnum::Email->value
            )],
            'body' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $slug = $this->input('slug');
            $channel = $this->input('channel');

            if ($slug === null || $channel === null) {
                return;
            }

            $exists = \App\Models\NotificationTemplate::query()
                ->where('slug', $slug)
                ->where('channel', $channel)
                ->exists();

            if ($exists) {
                $validator->errors()->add('slug', 'Já existe um template com este slug para o canal informado.');
            }
        });
    }
}
