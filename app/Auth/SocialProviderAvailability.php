<?php

namespace App\Auth;

final class SocialProviderAvailability
{
    public function isConfigured(string $provider): bool
    {
        if (! in_array($provider, ['google', 'microsoft'], true)) {
            return false;
        }

        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            $value = config("services.{$provider}.{$key}");

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
