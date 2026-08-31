<?php

namespace App\Services;

use App\Models\GitHubAccount;

class WebhookSignature
{
    private const HEADER = 'X-Hub-Signature-256';

    private const PREFIX = 'sha256=';

    /**
     * Verify GitHub's HMAC-SHA256 signature over the raw request body.
     *
     * The comparison is constant time, and the caller must run this before the payload
     * is parsed or trusted in any way.
     */
    public function verify(GitHubAccount $account, string $payload, ?string $signature): bool
    {
        if ($signature === null || ! str_starts_with($signature, self::PREFIX)) {
            return false;
        }

        $secret = (string) $account->github_webhook_secret;

        if ($secret === '') {
            return false;
        }

        $expected = self::PREFIX.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public static function header(): string
    {
        return self::HEADER;
    }
}
