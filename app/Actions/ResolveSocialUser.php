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
                $user = $account->user;

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                return $user;
            }

            if (User::query()->where('email', $email)->exists()) {
                throw new SocialLoginException('Não foi possível concluir o login externo com esta conta. Entre pelo método usado anteriormente.');
            }

            $user = new User([
                'name' => Str::limit(trim($name ?? '') ?: 'Usuário', 255, ''),
                'email' => $email,
                'password' => null,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            SocialAccount::query()->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ]);

            return $user;
        });
    }
}
