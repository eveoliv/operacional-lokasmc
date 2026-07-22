<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
