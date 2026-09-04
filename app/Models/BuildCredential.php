<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildCredential extends Model
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
            'password' => 'encrypted',
            'private_key' => 'encrypted',
            'public_key' => 'encrypted',
        ];
    }

    public function imageBuild(): BelongsTo
    {
        return $this->belongsTo(ImageBuild::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function hasAuthenticationMaterial(): bool
    {
        return filled($this->password) || (filled($this->private_key) && filled($this->public_key));
    }

    public function resolvedUsername(?string $fallback = null): string
    {
        return (string) ($this->username ?: $fallback ?: 'runner');
    }
}
