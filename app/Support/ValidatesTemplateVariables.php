<?php

namespace App\Support;

use App\Models\NotificationTemplate;
use Illuminate\Validation\Validator;

trait ValidatesTemplateVariables
{
    /**
     * @param  array<string, mixed>|null  $variables
     */
    protected function validateRequiredTemplateVariables(
        Validator $validator,
        ?NotificationTemplate $template,
        ?array $variables = null,
    ): void {
        if ($template === null) {
            return;
        }

        $required = $template->required_variables ?? [];

        if ($required === []) {
            return;
        }

        $variables ??= $this->input('variables', []);
        $providedKeys = is_array($variables) ? array_keys($variables) : [];
        $missing = array_values(array_diff($required, $providedKeys));

        if ($missing !== []) {
            $validator->errors()->add(
                'variables',
                'Variáveis obrigatórias ausentes: '.implode(', ', $missing).'.',
            );
        }
    }
}
