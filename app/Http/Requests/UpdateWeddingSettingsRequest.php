<?php

namespace App\Http\Requests;

use App\Enums\WeddingTemplateKey;
use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeddingSettingsRequest extends FormRequest
{
    private const OPTIONAL_THEME_FIELDS = [
        'key',
        'primaryColor',
        'secondaryColor',
        'accentColor',
        'backgroundColor',
        'headingFont',
        'bodyFont',
    ];

    private const COLOR_FIELDS = [
        'primaryColor',
        'secondaryColor',
        'accentColor',
        'backgroundColor',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $colorRules = ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/'];

        return [
            'partnerOneName' => ['required', 'string', 'max:150'],
            'partnerTwoName' => ['required', 'string', 'max:150'],
            'weddingDate' => ['required', 'date_format:Y-m-d'],
            'rsvpDeadline' => ['nullable', 'date_format:Y-m-d', 'before:weddingDate'],
            'dressCode' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(Wedding::STATUSES)],
            'templateKey' => ['sometimes', 'required', Rule::enum(WeddingTemplateKey::class)],
            'theme' => ['sometimes', 'array'],
            'theme.key' => ['nullable', 'string', 'max:100'],
            'theme.primaryColor' => $colorRules,
            'theme.secondaryColor' => $colorRules,
            'theme.accentColor' => $colorRules,
            'theme.backgroundColor' => $colorRules,
            'theme.headingFont' => ['nullable', 'string', 'max:100'],
            'theme.bodyFont' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['partnerOneName', 'partnerTwoName'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        if ($this->exists('dressCode') && is_string($this->input('dressCode'))) {
            $normalized['dressCode'] = $this->nullableTrimmed($this->input('dressCode'));
        }

        if (is_array($this->input('theme'))) {
            $theme = $this->input('theme');

            foreach (self::OPTIONAL_THEME_FIELDS as $field) {
                if (array_key_exists($field, $theme) && is_string($theme[$field])) {
                    $theme[$field] = $this->nullableTrimmed($theme[$field]);
                }
            }

            foreach (self::COLOR_FIELDS as $field) {
                if (isset($theme[$field])) {
                    $theme[$field] = strtoupper($theme[$field]);
                }
            }

            $normalized['theme'] = $theme;
        }

        $this->merge($normalized);
    }

    private function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
