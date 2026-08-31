<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAppConfigured
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->isInstalled()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
