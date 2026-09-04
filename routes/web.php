<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BuildController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\GitHubAccountController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\ProxmoxTargetController;
use App\Http\Controllers\RunnerController;
use App\Http\Controllers\RunnerTemplateController;
use App\Http\Controllers\Settings\CredentialController;
use App\Http\Controllers\Settings\DebugController;
use App\Http\Controllers\Settings\GeneralController;
use App\Http\Controllers\Settings\JobsController;
use App\Http\Controllers\Settings\RunnersController;
use App\Http\Controllers\Settings\TemplatesController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WorkflowJobController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', fn () => response()->json(['status' => 'ok']))->name('healthz');

// Authenticated by GitHub's HMAC signature, and reachable before setup completes.
Route::post('/webhook/{webhookId}', [WebhookController::class, 'handle'])->name('webhook');

Route::middleware('setup.incomplete')->group(function (): void {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
    Route::post('/setup/import', [SetupController::class, 'import'])->name('setup.import');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/active-runners', [DashboardController::class, 'activeRunners'])->name('dashboard.active-runners');
    Route::get('/dashboard/recent-runners', [DashboardController::class, 'recentRunners'])->name('dashboard.recent-runners');

    Route::prefix('config')->group(function (): void {
        Route::resource('environments', EnvironmentController::class);
        Route::resource('github-accounts', GitHubAccountController::class)->except(['show']);

        Route::prefix('nodes')->name('nodes.')->group(function (): void {
            Route::get('/', [ProxmoxTargetController::class, 'index'])->name('index');
            Route::get('/create', [ProxmoxTargetController::class, 'standaloneCreate'])->name('create');
            Route::post('/storage-options', [ProxmoxTargetController::class, 'storageOptions'])->name('storage-options');
            Route::post('/', [ProxmoxTargetController::class, 'standaloneStore'])->name('store');
            Route::get('/{target}/isos', [ProxmoxTargetController::class, 'isos'])->name('isos');
            Route::get('/{target}/edit', [ProxmoxTargetController::class, 'standaloneEdit'])->name('edit');
            Route::post('/{target}/test', [ProxmoxTargetController::class, 'test'])->name('test');
            Route::get('/{target}', [ProxmoxTargetController::class, 'show'])->name('show');
            Route::put('/{target}', [ProxmoxTargetController::class, 'standaloneUpdate'])->name('update');
            Route::delete('/{target}', [ProxmoxTargetController::class, 'standaloneDestroy'])->name('destroy');
        });

        Route::resource('environments.targets', ProxmoxTargetController::class)->except(['index', 'show']);
    });

    Route::prefix('images')->group(function (): void {
        Route::resource('templates', RunnerTemplateController::class)->parameters(['templates' => 'runnerTemplate']);
        Route::post('/templates/{runnerTemplate}/build/{target}', [RunnerTemplateController::class, 'build'])->name('templates.build');
        Route::post('/templates/{runnerTemplate}/rebuild', [RunnerTemplateController::class, 'build'])->name('templates.rebuild');
        Route::post('/templates/{runnerTemplate}/superseded/purge-all', [RunnerTemplateController::class, 'purgeAllSuperseded'])->name('templates.superseded.purge-all');
        Route::post('/templates/{runnerTemplate}/superseded/{retired}/purge', [RunnerTemplateController::class, 'purgeSuperseded'])->name('templates.superseded.purge');

        Route::resource('pools', PoolController::class);

        Route::get('/builds', [BuildController::class, 'index'])->name('builds.index');
        Route::get('/builds/{imageBuild}', [BuildController::class, 'show'])->name('builds.show');
        Route::get('/builds/{imageBuild}/log', [BuildController::class, 'log'])->name('builds.log');
        Route::post('/builds/{imageBuild}/cancel', [BuildController::class, 'cancel'])->name('builds.cancel');
        Route::delete('/builds/{imageBuild}', [BuildController::class, 'destroy'])->name('builds.destroy');
    });

    Route::prefix('workflows')->group(function (): void {
        Route::get('/runners', [RunnerController::class, 'index'])->name('runners.index');
        Route::get('/runners/{runner}', [RunnerController::class, 'show'])->name('runners.show');
        Route::delete('/runners/{runner}', [RunnerController::class, 'destroy'])->name('runners.destroy');

        Route::get('/jobs', [WorkflowJobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{job}', [WorkflowJobController::class, 'show'])->name('jobs.show');
        Route::get('/jobs/{job}/log', [WorkflowJobController::class, 'log'])->name('jobs.log');
        Route::delete('/jobs/{job}', [WorkflowJobController::class, 'destroy'])->name('jobs.destroy');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', fn () => redirect()->route('settings.overview'));

        Route::get('/overview', [GeneralController::class, 'overview'])->name('overview');
        Route::get('/application', [GeneralController::class, 'application'])->name('application');
        Route::put('/application', [GeneralController::class, 'updateApplication'])->name('application.update');

        Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
        Route::put('/jobs', [JobsController::class, 'update'])->name('jobs.update');

        Route::get('/runners', [RunnersController::class, 'index'])->name('runners.index');
        Route::put('/runners', [RunnersController::class, 'update'])->name('runners.update');

        Route::get('/templates/general', [TemplatesController::class, 'index'])->name('templates.index');
        Route::put('/templates/general', [TemplatesController::class, 'update'])->name('templates.update');
        Route::get('/templates/retention', [TemplatesController::class, 'retention'])->name('templates.retention');
        Route::put('/templates/retention', [TemplatesController::class, 'updateRetention'])->name('templates.retention.update');
        Route::post('/templates/check-updates', [TemplatesController::class, 'checkUpdates'])->name('templates.check-updates');
        Route::post('/templates/download-update', [TemplatesController::class, 'downloadUpdate'])->name('templates.download-update');
        Route::post('/templates/versions/{version}/activate', [TemplatesController::class, 'activateVersion'])->name('templates.activate-version');
        Route::resource('templates/credentials', CredentialController::class)->except(['show'])->names('templates.credentials');
        Route::post('/templates/credentials/default', [CredentialController::class, 'ensureDefault'])->name('templates.credentials.default');

        Route::resource('users', UserController::class)->except(['show']);

        Route::prefix('debug')->name('debug.')->group(function (): void {
            Route::get('/', [DebugController::class, 'index'])->name('index');
            Route::put('/toggle', [DebugController::class, 'toggle'])->name('toggle');
            Route::post('/reap-all', [DebugController::class, 'reapAll'])->name('reap-all');
            Route::post('/scheduler-cache', [DebugController::class, 'clearSchedulerCache'])->name('scheduler-cache');
            Route::delete('/runner-history', [DebugController::class, 'clearRunnerHistory'])->name('runner-history');
            Route::delete('/build-history', [DebugController::class, 'clearBuildHistory'])->name('build-history');
            Route::delete('/webhook-logs', [DebugController::class, 'purgeWebhookLogs'])->name('webhook-logs');
            Route::delete('/workflow-jobs', [DebugController::class, 'purgeWorkflowJobs'])->name('workflow-jobs');
            Route::get('/export-config', [DebugController::class, 'exportConfig'])->name('export-config');
        });
    });

    // Legacy redirects
    Route::redirect('/environments', '/config/environments');
    Route::redirect('/github-accounts', '/config/github-accounts');
    Route::redirect('/targets', '/config/nodes');
    Route::redirect('/builds', '/images/builds');
    Route::redirect('/pools', '/images/pools');
    Route::redirect('/templates', '/images/templates');
    Route::redirect('/jobs', '/workflows/jobs');
    Route::redirect('/runners', '/workflows/runners');

});
