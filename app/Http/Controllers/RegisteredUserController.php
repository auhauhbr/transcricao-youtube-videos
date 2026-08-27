<?php

namespace App\Http\Controllers;

use App\Actions\ClaimGuestExtractions;
use App\Guest\GuestIdentity;
use App\Http\Middleware\EnsureGuestIdentity;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'registerUrl' => route('register', absolute: false),
            'loginUrl' => route('login', absolute: false),
        ]);
    }

    public function store(RegisterRequest $request, ClaimGuestExtractions $claimExtractions): RedirectResponse
    {
        $user = User::query()->create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        $guestIdentity = $request->attributes->get(EnsureGuestIdentity::ATTRIBUTE);
        $claimExtractions->handle(
            $user,
            $guestIdentity instanceof GuestIdentity ? $guestIdentity->usage : null,
        );

        return redirect()->route('home');
    }
}
