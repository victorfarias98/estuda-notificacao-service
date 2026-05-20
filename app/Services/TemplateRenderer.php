<?php

namespace App\Services;

use App\Exceptions\MissingTemplateVariableException;
use App\Models\NotificationTemplate;

class TemplateRenderer
{
    /**
     * @param  array<string, mixed>|null  $variables
     * @return array{subject: ?string, body: string}
     *
     * @throws MissingTemplateVariableException
     */
    public function render(NotificationTemplate $template, ?array $variables): array
    {
        $variables ??= [];

        return [
            'subject' => $template->subject !== null
                ? $this->replace($template->subject, $variables)
                : null,
            'body' => $this->replace($template->body, $variables),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     *
     * @throws MissingTemplateVariableException
     */
    private function replace(string $content, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];

                if (! array_key_exists($key, $variables)) {
                    throw new MissingTemplateVariableException(
                        "Variável obrigatória ausente para o template: {$key}"
                    );
                }

                $value = $variables[$key];

                return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            },
            $content
        ) ?? $content;
    }
}
