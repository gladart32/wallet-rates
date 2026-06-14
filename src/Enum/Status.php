<?php

namespace App\Enum;

enum Status: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Deleted = 'deleted';
}
