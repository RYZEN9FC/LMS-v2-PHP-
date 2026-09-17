<?php

namespace App\Http\Middleware;

use App\Support\CurrentOutlet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOutletPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(app(CurrentOutlet::class)->allows($permission), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
