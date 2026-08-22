<?php

namespace App\Guest;

use App\Models\GuestUsage;
use Illuminate\Http\Request;

final class GuestIdentityManager
{
    private const TOKEN_BYTES = 32;

    public function resolve(Request $request): GuestIdentity
    {
        $cookieName = (string) config('transcripts.anonymous.cookie_name');
        $token = $request->cookie($cookieName);
        $tokenToSet = null;

        if (! is_string($token) || preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) !== 1) {
            $token = $this->generateToken();
            $tokenToSet = $token;
        }

        $tokenHash = hash('sha256', $token);
        $usage = GuestUsage::query()
            ->where('token_hash', $tokenHash)
            ->first();

        return new GuestIdentity($tokenHash, $usage, $tokenToSet);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }
}
