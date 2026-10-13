<?php

namespace App\Exceptions;

/** Schedule already has actual output, so it cannot be edited, deleted or shifted. */
class ScheduleLockedException extends ApiException
{
    protected int $status = 423;
    protected string $reason = 'locked';
}
