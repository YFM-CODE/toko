<?php

namespace App\Http\Controllers\Auth;

use App\Handlers\PelangganHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterMemberRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private PelangganHandler $pelangganHandler
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterMemberRequest $request): RedirectResponse
    {
        $user = $this->pelangganHandler->registerMemberAccount($request->validated());

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('member.dashboard')
            ->with('success', 'Selamat datang! Akun Member Anda berhasil dibuat (+10 Poin Bonus Member Baru).');
    }
}
