<?php

namespace App\Models;

use App\Enums\PoolOs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Credential extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'private_key',
        'public_key',
    ];

    protected function casts(): array
    {
        return [
            'os' => PoolOs::class,
            'password' => 'encrypted',
            'private_key' => 'encrypted',
            'public_key' => 'encrypted',
        ];
    }

    public function imageBuilds(): HasMany
    {
        return $this->hasMany(ImageBuild::class);
    }

    public function runnerTemplates(): HasMany
    {
        return $this->hasMany(RunnerTemplate::class);
    }

    public function hasAuthenticationMaterial(): bool
    {
        return filled($this->password) || (filled($this->private_key) && filled($this->public_key));
    }

    public function username(?string $fallback = null): string
    {
        return (string) ($this->username ?: $fallback ?: 'runner');
    }
}
