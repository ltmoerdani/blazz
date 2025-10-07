<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Exception reporting registered here if needed
    }

    public function render($request, Throwable $exception)
    {
        if ($this->isHttpException($exception)) {
            /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception */
            $statusCode = $exception->getStatusCode();
            
            if ($statusCode == 404) {
                return response()->view('errors.404', [], 404);
            }
            if ($statusCode == 500) {
                return response()->view('errors.500', [], 500);
            }
        }
        return parent::render($request, $exception);
    }
}
