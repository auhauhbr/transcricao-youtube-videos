<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function socialAccountsMigration(): object
{
    return require database_path('migrations/2026_08_26_000000_create_social_accounts_table.php');
}

test('social accounts migration rolls back when every user has a password', function () {
    User::factory()->create(['password' => 'password-seguro']);

    socialAccountsMigration()->down();

    expect(Schema::hasTable('social_accounts'))->toBeFalse();
    expect(fn () => User::query()->create(['name' => 'Sem senha', 'email' => 'sem-senha@example.com', 'password' => null]))
        ->toThrow(QueryException::class);
});

test('social accounts migration refuses rollback without removing social-only data', function () {
    $user = User::factory()->create(['password' => null]);
    $account = SocialAccount::query()->create(['user_id' => $user->getKey(), 'provider' => 'google', 'provider_user_id' => 'rollback-safe']);

    expect(fn () => socialAccountsMigration()->down())
        ->toThrow(RuntimeException::class, 'não pode ser revertida enquanto existirem usuários social-only');

    expect(Schema::hasTable('social_accounts'))->toBeTrue()
        ->and($user->fresh()->password)->toBeNull()
        ->and(SocialAccount::query()->find($account->getKey()))->not->toBeNull();
});
