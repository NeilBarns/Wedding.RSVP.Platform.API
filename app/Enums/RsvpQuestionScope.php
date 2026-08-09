<?php

namespace App\Enums;

enum RsvpQuestionScope: string
{
    case Guest = 'guest';
    case Household = 'household';
}
