<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ZipArchive;

class SetupController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function show(): View
    {
        return view('pages.setup.index', [
            'requirements' => $this->requirements(),
            'databaseDriver' => DB::connection()->getDriverName(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_url' => ['required', 'url'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if (collect($this->requirements())->contains(fn (array $check): bool => ! $check['passed'])) {
            return back()->with('error', 'Resolve the outstanding requirements before continuing.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $this->settings->setMany([
            'app_url' => rtrim($validated['app_url'], '/'),
            'timezone' => $validated['timezone'],
            'installed_at' => now()->toIso8601String(),
        ]);

        Auth::login($user);

        return redirect()
            ->route('github-accounts.create')
            ->with('success', 'Setup complete. Add your first GitHub account to get started.');
    }

    public function import(Request $request): RedirectResponse
    {
        $file = $request->file('config_file');

        if (! $file || ! $file->isValid()) {
            return back()->with('error', 'Please upload a valid backup zip file.');
        }

        $zip = new ZipArchive;
        $res = $zip->open($file->getRealPath());

        if ($res !== true) {
            return back()->with('error', 'Failed to open the uploaded zip file.');
        }

        $hasEnv = $zip->locateName('.env') !== false;
        $hasDb = $zip->locateName('database.sqlite') !== false;

        if (! $hasEnv && ! $hasDb) {
            $zip->close();

            return back()->with('error', 'The uploaded archive does not contain a .env or database.sqlite file.');
        }

        if ($hasEnv && ! app()->environment('testing')) {
            $envContent = $zip->getFromName('.env');
            if ($envContent !== false) {
                $targetEnvPath = base_path('.env');
                if (is_link($targetEnvPath)) {
                    $targetEnvPath = readlink($targetEnvPath) ?: $targetEnvPath;
                }
                file_put_contents($targetEnvPath, $envContent);
            }
        }

        if ($hasDb && ! app()->environment('testing')) {
            $dbContent = $zip->getFromName('database.sqlite');
            if ($dbContent !== false) {
                $targetDbPath = config('database.connections.sqlite.database');
                if (is_string($targetDbPath) && $targetDbPath !== ':memory:') {
                    if (is_link($targetDbPath)) {
                        $targetDbPath = readlink($targetDbPath) ?: $targetDbPath;
                    }

                    $dir = dirname($targetDbPath);
                    if (! is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    DB::disconnect();
                    file_put_contents($targetDbPath, $dbContent);
                }
            }
        }

        $zip->close();

        if (! app()->environment('testing')) {
            DB::purge();
        }

        $this->settings->flush();

        try {
            $user = User::first();
            if ($user) {
                Auth::login($user);

                return redirect()->route('dashboard')->with('success', 'Configuration imported successfully.');
            }
        } catch (\Throwable) {
            // Schemas or user state may differ
        }

        return redirect()->route('login')->with('success', 'Configuration imported successfully. Please log in.');
    }

    /**
     * @return array<int, array{label: string, passed: bool, detail: string}>
     */
    private function requirements(): array
    {
        $storageWritable = is_writable(storage_path());
        $databasePath = config('database.connections.sqlite.database');
        $usingSqlite = DB::connection()->getDriverName() === 'sqlite';

        return [
            [
                'label' => 'PHP 8.3 or newer',
                'passed' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'detail' => PHP_VERSION,
            ],
            [
                'label' => 'Application key set',
                'passed' => (string) config('app.key') !== '',
                'detail' => config('app.key') ? 'Present' : 'Missing - run php artisan key:generate',
            ],
            [
                'label' => 'Storage directory writable',
                'passed' => $storageWritable,
                'detail' => storage_path(),
            ],
            [
                'label' => 'Database reachable',
                'passed' => $this->databaseReachable(),
                'detail' => $usingSqlite && is_string($databasePath) ? $databasePath : DB::connection()->getDriverName(),
            ],
        ];
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
