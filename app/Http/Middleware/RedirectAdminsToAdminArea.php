<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminsToAdminArea
{
    /**
     * Keep the customer area for customers: admins are sent to the admin dashboard.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
