<?php

namespace App\Http\Middleware;

use App\Guest\GuestIdentityManager;
use Closure;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureGuestIdentity
{
    public const ATTRIBUTE = 'guest_identity';

    public function __construct(
        private GuestIdentityManager $identityManager,
        private CookieJar $cookies,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $identity = $this->identityManager->resolve($request);
        $request->attributes->set(self::ATTRIBUTE, $identity);
        $response = $next($request);

        if ($identity->tokenToSet !== null) {
            $response->headers->setCookie($this->cookies->make(
                name: (string) config('transcripts.anonymous.cookie_name'),
                value: $identity->tokenToSet,
                minutes: (int) config('transcripts.anonymous.cookie_lifetime_minutes'),
                path: '/',
                domain: config('session.domain'),
                secure: (bool) config('transcripts.anonymous.cookie_secure'),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
        }

        return $response;
    }
}
