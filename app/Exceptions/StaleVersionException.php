<?php

namespace App\Exceptions;

/** Optimistic locking: the client edited an outdated copy. */
class StaleVersionException extends ApiException
{
    protected int $status = 409;
    protected string $reason = 'stale';

    public function __construct(string $message = 'Data sudah diubah oleh proses lain. Muat ulang data lalu coba lagi.')
    {
        parent::__construct($message);
    }
}
