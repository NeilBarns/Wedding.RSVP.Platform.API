<?php

namespace App\Http\Requests;

class UpdateInvitationRequest extends StoreInvitationRequest
{
    public function rules(): array
    {
        return $this->profileRules();
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->normalizeProfile($this->all()));
    }
}
