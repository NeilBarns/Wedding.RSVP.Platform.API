<?php

namespace App\Http\Requests\Concerns;

trait NormalizesContentInput
{
    protected function normalizeContentFields(array $optional, array $required = []): void
    {
        $values = [];
        foreach ([...$optional, ...$required] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $value = trim($this->input($field));
                $values[$field] = in_array($field, $optional, true) && $value === '' ? null : $value;
            }
        } $this->merge($values);
    }
}
