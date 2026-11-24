<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'id';
        if ($request->routeIs('public.*')) {
            $locale = in_array($s = $request->session()->get('locale'), ['id', 'en'], true) ? $s : 'id';
        }
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
