<?php

namespace App\Http\Requests;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Enums\CommunicationChannelEnum;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNotificationTemplateRequest extends FormRequest
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
        /** @var NotificationTemplate $template */
        $template = $this->route('notification_template');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'channel' => ['sometimes', 'required', 'string', Rule::in(CommunicationChannelEnum::values())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var NotificationTemplate $template */
            $template = $this->route('notification_template');

            $slug = $this->input('slug', $template->slug);
            $channel = $this->input('channel', $template->channel->value);

            $exists = app(NotificationTemplateRepositoryInterface::class)
                ->existsBySlugForChannel((string) $slug, (string) $channel, excludingId: $template->id);

            if ($exists) {
                $validator->errors()->add('slug', 'Já existe outro template com este slug para o canal informado.');
            }

            if ($channel === CommunicationChannelEnum::Email->value
                && $this->exists('subject')
                && blank($this->input('subject'))) {
                $validator->errors()->add('subject', 'Templates de e-mail exigem um assunto.');
            }
        });
    }
}
