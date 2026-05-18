<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MeasureResponseTime
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        $response->headers->set('X-Response-Time', $duration . ' ms');

        return $response;
    }
}