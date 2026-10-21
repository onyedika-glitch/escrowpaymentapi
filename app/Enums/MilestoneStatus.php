<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case Created = 'Created';
    case Funded = 'Funded';
    case Released = 'Released';
}
