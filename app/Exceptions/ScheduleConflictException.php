<?php

namespace App\Exceptions;

/** Overlapping dates, or record still referenced by other data. */
class ScheduleConflictException extends ApiException
{
    protected int $status = 409;
    protected string $reason = 'conflict';
}
