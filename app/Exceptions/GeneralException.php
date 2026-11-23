<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GeneralException extends Exception
{
    public function __construct(string $message = '', int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function report()
    {
        //
    }

    public function render(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            return responseMessage($this->getMessage(), $this->getCode());
        }

        return responseMessage('Bad Request', 400);
    }
}
