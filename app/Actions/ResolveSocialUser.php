<?php

namespace App\Actions;

use App\Exceptions\SocialLoginException;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResolveSocialUser
{
    public function handle(string $provider, string $providerUserId, string $email, ?string $name): User
    {
        return DB::transaction(function () use ($provider, $providerUserId, $email, $name): User {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($account !== null) {
                return $account->user;
            }

            if (User::query()->where('email', $email)->exists()) {
                throw new SocialLoginException('Já existe uma conta com este email. Entre pelo método usado anteriormente.');
            }

            $user = User::query()->create([
                'name' => Str::limit(trim($name ?? '') ?: 'Usuário', 255, ''),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => null,
            ]);

            SocialAccount::query()->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ]);

            return $user;
        });
    }
}
