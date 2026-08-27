<?php

namespace App\Http\Controllers;

use App\Actions\ClaimGuestExtractions;
use App\Auth\SocialProviderAvailability;
use App\Guest\GuestIdentity;
use App\Http\Middleware\EnsureGuestIdentity;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(SocialProviderAvailability $socialProviders): Response
    {
        return Inertia::render('Auth/Login', [
            'loginUrl' => route('login', absolute: false),
            'registerUrl' => route('register', absolute: false),
            'socialProviders' => [
                'google' => $socialProviders->isConfigured('google'),
                'microsoft' => $socialProviders->isConfigured('microsoft'),
            ],
        ]);
    }

    public function store(LoginRequest $request, ClaimGuestExtractions $claimExtractions): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas são inválidas.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $guestIdentity = $request->attributes->get(EnsureGuestIdentity::ATTRIBUTE);

        if ($user !== null) {
            $claimExtractions->handle(
                $user,
                $guestIdentity instanceof GuestIdentity ? $guestIdentity->usage : null,
            );
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
