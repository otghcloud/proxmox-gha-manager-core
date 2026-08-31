<?php

namespace App\Http\Controllers;

use App\Http\Requests\GitHubAccountRequest;
use App\Models\GitHubAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GitHubAccountController extends Controller
{
    public function index(): View
    {
        return view('pages.github-accounts.index', ['accounts' => GitHubAccount::withCount('environments')->orderBy('login')->get()]);
    }

    public function create(): View
    {
        return view('pages.github-accounts.create', ['account' => new GitHubAccount(['github_api_url' => 'https://api.github.com', 'github_runner_group_id' => 1, 'github_work_folder' => '_work', 'linux_ssh_username' => 'runner'])]);
    }

    public function store(GitHubAccountRequest $request): RedirectResponse
    {
        $account = GitHubAccount::create($request->validated());

        return redirect()->route('github-accounts.index')->with('success', "GitHub account {$account->login} created.");
    }

    public function edit(GitHubAccount $githubAccount): View
    {
        return view('pages.github-accounts.edit', ['account' => $githubAccount]);
    }

    public function update(GitHubAccountRequest $request, GitHubAccount $githubAccount): RedirectResponse
    {
        $data = $request->validated();
        foreach (['github_token', 'github_webhook_secret', 'linux_ssh_password', 'windows_password'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        $githubAccount->update($data);

        return redirect()->route('github-accounts.index')->with('success', 'GitHub account updated.');
    }

    public function destroy(GitHubAccount $githubAccount): RedirectResponse
    {
        $githubAccount->delete();

        return redirect()->route('github-accounts.index')->with('success', 'GitHub account deleted.');
    }
}
