<?php

namespace App\Http\Requests;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Enums\CommunicationChannelEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'required_variables' => ['nullable', 'array'],
            'required_variables.*' => ['string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $slug = $this->input('slug');
            $channel = $this->input('channel');

            if ($slug === null || $channel === null) {
                return;
            }

            $exists = app(NotificationTemplateRepositoryInterface::class)
                ->existsBySlugForChannel((string) $slug, (string) $channel);

            if ($exists) {
                $validator->errors()->add('slug', 'Já existe um template com este slug para o canal informado.');
            }
        });
    }
}
