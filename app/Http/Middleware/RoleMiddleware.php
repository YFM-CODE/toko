<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Akun Anda sedang dinonaktifkan. Hubungi Admin.',
            ]);
        }

        $userRole = $user->role->value;

        if (! in_array($userRole, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki hak akses ke halaman ini.');
        }

        return $next($request);
    }
}
