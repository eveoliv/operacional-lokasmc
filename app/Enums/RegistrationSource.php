<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case SelfService = 'self_service';
    case Operator = 'operator';
    case Import = 'import';
}
