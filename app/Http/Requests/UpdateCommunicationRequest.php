<?php

namespace App\Http\Requests;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Enums\CommunicationChannelEnum;
use App\Models\Communication;
use App\Models\NotificationTemplate;
use App\Support\ValidatesTemplateVariables;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCommunicationRequest extends FormRequest
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
            'recipient' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string'],
            'origin_system' => ['sometimes', 'required', 'string', 'max:120'],
            'template_id' => ['sometimes', 'nullable', 'integer', 'exists:notification_templates,id'],
            'template_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'variables.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Communication $communication */
            $communication = $this->route('communication');
            $channel = $communication->channel->value;

            $recipient = $this->input('recipient', $communication->recipient);
            $subject = $this->has('subject') ? $this->input('subject') : $communication->subject;
            $message = $this->has('message') ? $this->input('message') : $communication->message;

            $hasTemplateRef = $this->filled('template_id') || $this->filled('template_slug');
            $clearsTemplate = $this->wantsToClearTemplate();
            $resolvedTemplate = $this->resolveTemplate($communication);
            $template = $clearsTemplate ? null : ($resolvedTemplate ?? $communication->template);

            if ($template === null && blank($message)) {
                $validator->errors()->add('message', 'O campo message é obrigatório quando não há template.');
            }

            if ($channel === CommunicationChannelEnum::Email->value
                && $template === null
                && blank($subject)) {
                $validator->errors()->add('subject', 'O campo subject é obrigatório para e-mails sem template.');
            }

            if ($channel === CommunicationChannelEnum::Email->value
                && filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                $validator->errors()->add('recipient', 'O destinatário deve ser um endereço de e-mail válido para o canal email.');
            }

            if ($hasTemplateRef && $resolvedTemplate === null) {
                $validator->errors()->add('template_slug', 'Template não encontrado para o canal da comunicação.');

                return;
            }

            if ($resolvedTemplate !== null) {
                if (! $resolvedTemplate->is_active) {
                    $validator->errors()->add('template_slug', 'O template informado está inativo.');
                }

                if ($resolvedTemplate->channel->value !== $channel) {
                    $validator->errors()->add('template_slug', 'O template não pertence ao canal da comunicação.');
                }
            }

            if ($template !== null) {
                $variables = $this->has('variables')
                    ? $this->input('variables')
                    : ($communication->variables ?? []);

                $this->validateRequiredTemplateVariables($validator, $template, is_array($variables) ? $variables : []);
            }
        });
    }

    public function resolveTemplate(Communication $communication): ?NotificationTemplate
    {
        $channel = $communication->channel->value;
        $repository = app(NotificationTemplateRepositoryInterface::class);

        if ($this->filled('template_id')) {
            return $repository->findByIdAndChannel($this->integer('template_id'), $channel);
        }

        if ($this->filled('template_slug')) {
            return $repository->findBySlugAndChannel((string) $this->input('template_slug'), $channel);
        }

        return null;
    }

    public function wantsToClearTemplate(): bool
    {
        return ($this->exists('template_id') && blank($this->input('template_id')))
            || ($this->exists('template_slug') && blank($this->input('template_slug')));
    }
}
