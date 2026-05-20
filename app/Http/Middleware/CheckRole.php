<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if (!$user->hasAnyRole($roles)) {
            return response()->json(['success' => false, 'message' => 'Insufficient permissions'], 403);
        }

        return $next($request);
    }
}
