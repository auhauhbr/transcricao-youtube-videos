<?php

namespace App\Http\Controllers;

use App\Actions\ClaimGuestExtractions;
use App\Actions\ResolveSocialUser;
use App\Exceptions\SocialLoginException;
use App\Guest\GuestIdentity;
use App\Http\Middleware\EnsureGuestIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class SocialAuthenticationController extends Controller
{
    public function redirectGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function redirectMicrosoft(): RedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function callbackGoogle(Request $request, ResolveSocialUser $resolveUser, ClaimGuestExtractions $claimExtractions): RedirectResponse
    {
        return $this->callback($request, 'google', $resolveUser, $claimExtractions);
    }

    public function callbackMicrosoft(Request $request, ResolveSocialUser $resolveUser, ClaimGuestExtractions $claimExtractions): RedirectResponse
    {
        return $this->callback($request, 'microsoft', $resolveUser, $claimExtractions);
    }

    private function callback(Request $request, string $provider, ResolveSocialUser $resolveUser, ClaimGuestExtractions $claimExtractions): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            $id = (string) $socialUser->getId();
            $email = mb_strtolower(trim((string) $socialUser->getEmail()));

            if ($id === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new SocialLoginException('Não foi possível obter um email utilizável desta conta.');
            }

            $user = $resolveUser->handle($provider, $id, $email, $socialUser->getName());
        } catch (SocialLoginException $exception) {
            return redirect()->route('login')->withErrors(['social' => $exception->getMessage()]);
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['social' => 'Não foi possível concluir o login externo. Tente novamente.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $guestIdentity = $request->attributes->get(EnsureGuestIdentity::ATTRIBUTE);
        $claimExtractions->handle($user, $guestIdentity instanceof GuestIdentity ? $guestIdentity->usage : null);

        return redirect()->intended(route('library.index'));
    }
}
