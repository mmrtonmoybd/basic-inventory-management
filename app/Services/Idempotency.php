<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Makes form submissions safe to replay: a repeated idempotency key returns the original record.
 */
class Idempotency
{
    /**
     * Namespaces a client-supplied key by user so one user's key can never collide with another's.
     */
    public function scope(?string $key, User $user): ?string
    {
        return filled($key) ? $user->getKey().':'.$key : null;
    }

    /**
     * @template TResult
     *
     * @param  Closure(string): (TResult|null)  $lookup
     * @param  Closure(): TResult  $create
     * @return array{0: TResult, 1: bool} The record and whether it was newly created.
     */
    public function run(?string $key, Closure $lookup, Closure $create): array
    {
        if (filled($key) && ($existing = $lookup($key)) !== null) {
            return [$existing, false];
        }

        try {
            return [$create(), true];
        } catch (UniqueConstraintViolationException $exception) {
            if (filled($key) && ($existing = $lookup($key)) !== null) {
                return [$existing, false];
            }

            throw $exception;
        }
    }
}
