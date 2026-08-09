<?php

namespace App\Enums;

enum RsvpQuestionKey: string
{
    case DietaryRequirements = 'dietaryRequirements';
    case AccessibilityNeeds = 'accessibilityNeeds';
    case ResponsePhone = 'responsePhone';
    case ResponseEmail = 'responseEmail';
    case MessageToCouple = 'messageToCouple';

    public function scope(): RsvpQuestionScope
    {
        return match ($this) {
            self::DietaryRequirements, self::AccessibilityNeeds => RsvpQuestionScope::Guest,
            self::ResponsePhone, self::ResponseEmail, self::MessageToCouple => RsvpQuestionScope::Household,
        };
    }
}
