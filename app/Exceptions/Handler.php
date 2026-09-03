<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        /*
        |--------------------------------------------------------------------------
        | JSON / API Requests
        |--------------------------------------------------------------------------
        */

        if ($request->expectsJson()) {
            return parent::render($request, $exception);
        }

        /*
        |--------------------------------------------------------------------------
        | 403 Forbidden
        |--------------------------------------------------------------------------
        */

        if (
            $exception instanceof HttpExceptionInterface &&
            $exception->getStatusCode() === 403
        ) {
            return Inertia::render('Errors/403', [
                'message' => $exception->getMessage(),
            ])
                ->toResponse($request)
                ->setStatusCode(403);
        }

        return parent::render($request, $exception);
    }
}
