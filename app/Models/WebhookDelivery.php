<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function githubAccount(): BelongsTo
    {
        return $this->belongsTo(GitHubAccount::class);
    }
}
