<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void {}

    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($e);
        }
        return parent::render($request, $e);
    }

    private function handleApiException(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        [$status, $message] = match (true) {
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException       => [404, 'Resource not found'],
            $e instanceof AuthenticationException     => [401, 'Unauthenticated'],
            $e instanceof AccessDeniedHttpException   => [403, 'Forbidden'],
            $e instanceof \InvalidArgumentException   => [422, $e->getMessage()],
            default                                   => [
                method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
                $e->getMessage() ?: 'Server Error',
            ],
        };

        $payload = ['success' => false, 'message' => $message];

        if (config('app.debug') && $status === 500) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'message'   => $e->getMessage(),
            ];
        }

        return response()->json($payload, $status);
    }
}
