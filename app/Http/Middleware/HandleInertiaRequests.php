<?php

namespace App\Http\Middleware;

use App\Guest\GuestExtractionQuota;
use App\Guest\GuestIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $guestIdentity = $request->attributes->get(EnsureGuestIdentity::ATTRIBUTE);
        $flashStatus = $request->session()->get('status');
        $flashMessage = $request->session()->get('message');
        $flashStatus = is_string($flashStatus) ? $flashStatus : null;
        $flashMessage = is_string($flashMessage) ? $flashMessage : null;

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->getAuthIdentifier(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'anonymousQuota' => $user !== null || ! $guestIdentity instanceof GuestIdentity
                ? null
                : app(GuestExtractionQuota::class)->summary($guestIdentity->usage),
            'flash' => [
                'id' => $flashStatus !== null || $flashMessage !== null ? (string) Str::ulid() : null,
                'status' => $flashStatus,
                'message' => $flashMessage,
            ],
        ];
    }
}
