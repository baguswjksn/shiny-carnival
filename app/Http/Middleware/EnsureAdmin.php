<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks every write endpoint for read-only (viewer) accounts. */
class EnsureAdmin
{
    private const MESSAGE = 'Akun read-only tidak boleh mengubah data.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => self::MESSAGE, 'reason' => 'forbidden'], 403);
            }
            abort(403, self::MESSAGE);
        }

        return $next($request);
    }
}
