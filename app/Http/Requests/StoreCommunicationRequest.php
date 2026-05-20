<?php

namespace App\Http\Requests;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Enums\CommunicationChannelEnum;
use App\Models\NotificationTemplate;
use App\Support\ValidatesTemplateVariables;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCommunicationRequest extends FormRequest
{
    use ValidatesTemplateVariables;

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
            'recipient' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', Rule::in(CommunicationChannelEnum::values())],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'origin_system' => ['required', 'string', 'max:120'],
            'template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'template_slug' => ['nullable', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $channel = $this->input('channel');
            $hasTemplateRef = $this->filled('template_id') || $this->filled('template_slug');

            if (! $hasTemplateRef && blank($this->input('message'))) {
                $validator->errors()->add('message', 'O campo message é obrigatório quando não é informado um template.');
            }

            if ($channel === CommunicationChannelEnum::Email->value
                && ! $hasTemplateRef
                && blank($this->input('subject'))) {
                $validator->errors()->add('subject', 'O campo subject é obrigatório para envios de e-mail sem template.');
            }

            if ($channel === CommunicationChannelEnum::Email->value
                && filter_var($this->input('recipient'), FILTER_VALIDATE_EMAIL) === false) {
                $validator->errors()->add('recipient', 'O destinatário deve ser um endereço de e-mail válido para o canal email.');
            }

            $template = $this->resolveTemplate();

            if ($hasTemplateRef && $template === null) {
                $validator->errors()->add('template_slug', 'Template não encontrado para o canal informado.');

                return;
            }

            if ($template !== null) {
                if (! $template->is_active) {
                    $validator->errors()->add('template_slug', 'O template informado está inativo.');
                }

                if ($channel !== null && $template->channel->value !== $channel) {
                    $validator->errors()->add('template_slug', 'O template não pertence ao canal informado.');
                }

                $this->validateRequiredTemplateVariables($validator, $template);
            }
        });
    }

    public function resolveTemplate(): ?NotificationTemplate
    {
        $channel = $this->input('channel');
        $repository = app(NotificationTemplateRepositoryInterface::class);

        if ($this->filled('template_id')) {
            return $repository->findByIdAndChannel($this->integer('template_id'), $channel);
        }

        if ($this->filled('template_slug')) {
            return $repository->findBySlugAndChannel((string) $this->input('template_slug'), $channel);
        }

        return null;
    }
}
