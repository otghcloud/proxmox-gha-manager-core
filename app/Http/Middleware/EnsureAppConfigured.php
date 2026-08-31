<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppConfigured
{
    /**
     * Routes that must stay reachable before the install has been completed.
     *
     * @var array<int, string>
     */
    private const ALLOWED = ['setup', 'setup/*', 'webhook/*', 'healthz', 'up'];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(self::ALLOWED) || $this->settings->isInstalled()) {
            return $next($request);
        }

        return redirect()->route('setup.show');
    }
}
