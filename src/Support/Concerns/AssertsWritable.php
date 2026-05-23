<?php

namespace MahmoudMhamed\DbLens\Support\Concerns;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Is DbLens allowed to mutate the database?" guard.
 *
 * Controllers: get an HttpException(403) that Laravel renders as the standard
 * 403 page.
 * Services:    get a RuntimeException, which the calling controller can catch
 * and turn into a flash error.
 *
 * The distinction is made by whether the consuming class extends a Laravel
 * controller — controllers set $this->writableFailsWith403 = true.
 */
trait AssertsWritable
{
    protected function assertWritable(): void
    {
        if (! config('dblens.read_only', false)) {
            return;
        }
        $message = 'DbLens is in read-only mode.';
        if (! empty($this->writableFailsWith403)) {
            throw new HttpException(403, $message);
        }
        throw new RuntimeException($message);
    }
}
