<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Interfaces\LogInterface;
use App\Interfaces\UserInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private UserInterface $userInterface,
        private LogInterface $logInterface
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Profile/Index', [
            'user' => $request->user(),
            'status' => session('status'),
        ]);
    }

    public function edit(Request $request): Response
    {
        return $this->index($request);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = $request->user();
            $oldName = $user->nama_lengkap;
            $validated = $request->validated();

            $this->userInterface->update($user->id_user, $validated);

            $this->logInterface->create([
                'id_user' => $user->id_user,
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'UPDATE PROFIL',
                'id_referensi' => $user->id_user,
                'data_lama' => "nama={$oldName}",
                'data_baru' => 'nama='.($validated['nama_lengkap'] ?? $oldName),
            ]);
        });

        return back()->with('success', 'Profil akun berhasil diperbarui.');
    }

    /**
     * @throws ValidationException
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        DB::transaction(function () use ($user, $validated): void {
            $this->userInterface->update($user->id_user, [
                'password_hash' => $validated['password'],
            ]);

            $this->logInterface->create([
                'id_user' => $user->id_user,
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'UBAH PASSWORD',
                'id_referensi' => $user->id_user,
                'data_baru' => "username={$user->username} memperbarui password",
            ]);
        });

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
