<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Enums;

enum CircuitState: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';
    case HALF_OPEN = 'half_open';
}
