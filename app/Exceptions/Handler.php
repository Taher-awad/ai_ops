<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use PDOException;

class Handler
{
    public static function getErrorCategory(?Throwable $exception, float $latencyMs): string
    {
        if ($latencyMs > 4000) {
            return 'TIMEOUT_ERROR';
        }

        if (!$exception) {
            return 'NONE';
        }

        if ($exception instanceof ValidationException) {
            return 'VALIDATION_ERROR';
        }

        if ($exception instanceof QueryException || $exception instanceof PDOException) {
            return 'DATABASE_ERROR';
        }

        if ($exception instanceof \RuntimeException || $exception instanceof \Exception) {
            return 'SYSTEM_ERROR';
        }

        return 'UNKNOWN';
    }
}
