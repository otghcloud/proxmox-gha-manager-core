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
    ];

    public function uniqueIds(): array
    {
        return ['webhook_id'];
    }

    public function getBreadcrumbLabel(): string
    {
        return (string) ($this->login ?: $this->getKey());
    }

    protected function casts(): array
    {
        return [
            'github_token' => 'encrypted',
            'github_webhook_secret' => 'encrypted',
            'github_runner_group_id' => 'integer',
        ];
    }

    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class, 'github_account_id');
    }
}
