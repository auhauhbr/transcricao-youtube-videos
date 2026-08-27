<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateAccountProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user();

        return Inertia::render('Account/Show', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profileUrl' => route('account.profile', absolute: false),
            'passwordUrl' => route('account.password', absolute: false),
            'hasPassword' => auth()->user()?->password !== null,
        ]);
    }

    public function updateProfile(UpdateAccountProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $emailChanged = $data['email'] !== $user->email;

        $user->fill($data);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

        return back()->with('status', 'profile-updated');
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        return back()->with('status', 'password-updated');
    }
}
