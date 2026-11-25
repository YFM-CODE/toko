<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RfidLoginRequest;
use App\Interfaces\LogInterface;
use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private LogInterface $logInterface
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user) {
            $this->logInterface->create([
                'id_user' => $user->id_user,
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'LOGIN',
                'id_referensi' => $user->id_user,
                'data_baru' => "username={$user->username}, role={$user->role->value}",
            ]);
        }

        return redirect()->intended($this->redirectPathByRole($user?->role?->value));
    }

    /**
     * Login cepat menggunakan Tap Kartu RFID Kasir / Staf.
     *
     * @throws ValidationException
     */
    public function storeRfid(RfidLoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $uid = trim($validated['rfid_uid']);
        $user = User::where('rfid_uid', $uid)->first();

        if (! $user) {
            $pelanggan = Pelanggan::where('rfid_uid', $uid)->first();
            if ($pelanggan) {
                if ($pelanggan->id_user) {
                    $user = User::find($pelanggan->id_user);
                } else {
                    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $pelanggan->nama_pelanggan));
                    $username = ($slug ?: 'member').'_'.$pelanggan->id_pelanggan;
                    $user = User::create([
                        'nama_lengkap' => $pelanggan->nama_pelanggan,
                        'username' => $username,
                        'rfid_uid' => $uid,
                        'password_hash' => 'member123',
                        'role' => UserRole::Pelanggan,
                        'is_active' => true,
                    ]);
                    $pelanggan->update(['id_user' => $user->id_user]);
                }
            }
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'rfid_uid' => "Kartu RFID ({$uid}) tidak terdaftar pada sistem.",
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'rfid_uid' => 'Akun pemilik kartu RFID ini sedang dinonaktifkan. Hubungi Admin.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $this->logInterface->create([
            'id_user' => $user->id_user,
            'nama_tabel' => 'tbl_users',
            'aktivitas' => 'LOGIN RFID',
            'id_referensi' => $user->id_user,
            'data_baru' => "username={$user->username}, rfid={$uid}, role={$user->role->value}",
        ]);

        return redirect()->intended($this->redirectPathByRole($user->role->value));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->logInterface->create([
                'id_user' => $user->id_user,
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'LOGOUT',
                'id_referensi' => $user->id_user,
                'data_baru' => "username={$user->username}",
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectPathByRole(?string $role): string
    {
        return match ($role) {
            'kasir' => route('pos.index', absolute: false),
            'admin' => route('admin.dashboard', absolute: false),
            'owner' => route('laporan.index', absolute: false),
            'pelanggan' => route('member.dashboard', absolute: false),
            default => route('dashboard', absolute: false),
        };
    }
}
