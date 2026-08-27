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
        return Inertia::render('Account/Show', [
            'profileUrl' => route('account.profile', absolute: false),
            'passwordUrl' => route('account.password', absolute: false),
            'hasPassword' => auth()->user()?->password !== null,
        ]);
    }

    public function updateProfile(UpdateAccountProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

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
