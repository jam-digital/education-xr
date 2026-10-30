<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Пускає лише персонал адмінки; з параметром — лише вказані ролі (через кому) */
class EnsureStaff
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && $user->role->isStaff(), 403);
        if ($roles) {
            abort_unless(in_array($user->role->value, $roles, true), 403);
        }

        return $next($request);
    }
}
