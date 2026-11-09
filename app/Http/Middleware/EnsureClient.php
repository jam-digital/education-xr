<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Кабінет закладу освіти: лише активний акаунт школи, інакше на сторінку входу */
class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== Role::Client) {
            return redirect()->route('cabinet.login');
        }
        if ($user->status !== UserStatus::Active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('cabinet.login')->withErrors(['email' => 'Акаунт закладу призупинено. Напишіть нам, щоб відновити доступ.']);
        }

        return $next($request);
    }
}
