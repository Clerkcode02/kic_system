<?php

declare(strict_types=1);

namespace App\Domain\Business\Enums;

enum CanadianProvince: string
{
    case AB = 'AB';
    case BC = 'BC';
    case MB = 'MB';
    case NB = 'NB';
    case NL = 'NL';
    case NS = 'NS';
    case NT = 'NT';
    case NU = 'NU';
    case ON = 'ON';
    case PE = 'PE';
    case QC = 'QC';
    case SK = 'SK';
    case YT = 'YT';
}
