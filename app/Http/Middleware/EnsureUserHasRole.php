<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    /**
     * Usage in routes: ->middleware('role:admin,receptionist')
     *
     * AJAX callers get a 403 JSON response; a browser navigating to a page
     * they may not see is sent back to the dashboard.
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if ($user && in_array($user->role, $roles, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden. Insufficient role.'], 403);
        }

        return redirect()->route('dashboard');
    }
}
