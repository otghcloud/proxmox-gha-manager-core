<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GitHubAccount extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    protected $table = 'github_accounts';

    protected $hidden = [
        'github_token',
        'github_webhook_secret',
        'linux_ssh_password',
        'windows_password',
    ];

    public function uniqueIds(): array
    {
        return ['webhook_id'];
    }

    protected function casts(): array
    {
        return [
            'github_token' => 'encrypted',
            'github_webhook_secret' => 'encrypted',
            'linux_ssh_password' => 'encrypted',
            'windows_password' => 'encrypted',
            'github_runner_group_id' => 'integer',
        ];
    }

    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class, 'github_account_id');
    }
}
