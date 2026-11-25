<?php

namespace App\Handlers;

use App\Enums\UserRole;
use App\Interfaces\LogInterface;
use App\Interfaces\PelangganInterface;
use App\Interfaces\UserInterface;
use App\Models\Pelanggan;
use App\Models\Transaksi;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PelangganHandler
{
    public function __construct(
        private PelangganInterface $pelangganInterface,
        private UserInterface $userInterface,
        private LogInterface $logInterface
    ) {}

    /**
     * Registrasi mandiri akun Member / Pelanggan baru (membuat akun login tbl_users + profil member tbl_pelanggan).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerMemberAccount(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $rfidUid = ! empty($data['rfid_uid']) ? trim((string) $data['rfid_uid']) : null;

            $user = $this->userInterface->create([
                'nama_lengkap' => $data['nama_lengkap'],
                'username' => $data['username'],
                'rfid_uid' => $rfidUid,
                'email' => ! empty($data['email']) ? $data['email'] : null,
                'password_hash' => $data['password'],
                'role' => UserRole::Pelanggan,
                'is_active' => true,
            ]);

            $existingPelanggan = $rfidUid
                ? Pelanggan::where('rfid_uid', $rfidUid)->whereNull('id_user')->first()
                : null;

            if ($existingPelanggan) {
                $pelanggan = $this->pelangganInterface->update($existingPelanggan->id_pelanggan, [
                    'id_user' => $user->id_user,
                    'nama_pelanggan' => $data['nama_lengkap'],
                    'kota' => $data['kota'],
                    'no_telepon' => $data['no_telepon'] ?? $existingPelanggan->no_telepon,
                ]);
            } else {
                $pelanggan = $this->pelangganInterface->create([
                    'id_user' => $user->id_user,
                    'rfid_uid' => $rfidUid,
                    'nama_pelanggan' => $data['nama_lengkap'],
                    'kota' => $data['kota'],
                    'no_telepon' => $data['no_telepon'] ?? null,
                    'tier' => 'Reguler',
                    'poin' => 10,
                    'saldo' => 0.00,
                ]);
            }

            $this->logInterface->create([
                'id_user' => $user->id_user,
                'nama_tabel' => 'tbl_pelanggan',
                'aktivitas' => 'REGISTER MEMBER',
                'id_referensi' => $pelanggan->id_pelanggan,
                'data_baru' => "username={$user->username}, nama={$pelanggan->nama_pelanggan}, kota={$pelanggan->kota}",
            ]);

            return $user->fresh(['pelanggan']);
        });
    }

    /**
     * Simpan atau perbarui data Member/Pelanggan dari Admin/Kasir (mendukung pembuatan akun login Member opsional).
     *
     * @param  array<string, mixed>  $data
     */
    public function savePelanggan(array $data, ?int $idPelanggan = null): Pelanggan
    {
        return DB::transaction(function () use ($data, $idPelanggan): Pelanggan {
            $rfidUid = ! empty($data['rfid_uid']) ? trim((string) $data['rfid_uid']) : null;
            $existing = $idPelanggan ? $this->pelangganInterface->find($idPelanggan) : null;
            $idUser = $existing?->id_user;

            if (! empty($data['username'])) {
                if ($idUser) {
                    $userUpdate = [
                        'nama_lengkap' => $data['nama_pelanggan'],
                        'username' => $data['username'],
                        'rfid_uid' => $rfidUid,
                        'email' => $data['email'] ?? null,
                    ];
                    if (! empty($data['password'])) {
                        $userUpdate['password_hash'] = $data['password'];
                    }
                    $this->userInterface->update($idUser, $userUpdate);
                } else {
                    $newUser = $this->userInterface->create([
                        'nama_lengkap' => $data['nama_pelanggan'],
                        'username' => $data['username'],
                        'rfid_uid' => $rfidUid,
                        'email' => $data['email'] ?? null,
                        'password_hash' => ! empty($data['password']) ? $data['password'] : 'member123',
                        'role' => UserRole::Pelanggan,
                        'is_active' => true,
                    ]);
                    $idUser = $newUser->id_user;
                }
            } elseif ($idUser && $rfidUid !== $existing?->rfid_uid) {
                User::where('id_user', $idUser)->update(['rfid_uid' => $rfidUid]);
            }

            $payload = [
                'id_user' => $idUser,
                'rfid_uid' => $rfidUid,
                'nama_pelanggan' => $data['nama_pelanggan'],
                'kota' => $data['kota'],
                'no_telepon' => $data['no_telepon'] ?? null,
                'tier' => $data['tier'] ?? ($existing?->tier ?? 'Reguler'),
                'poin' => isset($data['poin']) ? (int) $data['poin'] : ($existing?->poin ?? 0),
                'saldo' => isset($data['saldo']) ? (float) $data['saldo'] : ($existing?->saldo ?? 0.0),
            ];

            if ($idPelanggan) {
                $pelanggan = $this->pelangganInterface->update($idPelanggan, $payload);
                $aktivitas = 'UPDATE PELANGGAN';
            } else {
                $pelanggan = $this->pelangganInterface->create($payload);
                $aktivitas = 'INSERT PELANGGAN';
            }

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_pelanggan',
                'aktivitas' => $aktivitas,
                'id_referensi' => $pelanggan->id_pelanggan,
                'data_baru' => "nama={$pelanggan->nama_pelanggan}, tier={$pelanggan->tier}, rfid=".($pelanggan->rfid_uid ?? '-'),
            ]);

            return $pelanggan->fresh(['user']);
        });
    }

    /**
     * Top-Up Saldo Deposit Kartu RFID Member.
     */
    public function topUpSaldo(int $idPelanggan, float $nominal): Pelanggan
    {
        return DB::transaction(function () use ($idPelanggan, $nominal): Pelanggan {
            if ($nominal <= 0) {
                throw new Exception('Nominal top-up saldo minimal Rp 1.000.');
            }

            $pelanggan = Pelanggan::lockForUpdate()->findOrFail($idPelanggan);
            $saldoLama = (float) $pelanggan->saldo;
            $saldoBaru = $saldoLama + $nominal;

            $updated = $this->pelangganInterface->update($idPelanggan, [
                'saldo' => $saldoBaru,
            ]);

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_pelanggan',
                'aktivitas' => 'TOPUP SALDO MEMBER',
                'id_referensi' => $idPelanggan,
                'data_lama' => "saldo={$saldoLama}",
                'data_baru' => "saldo={$saldoBaru}, topup={$nominal}",
            ]);

            return $updated;
        });
    }

    /**
     * Rekalkulasi Tier Member berdasarkan akumulasi total belanja yang selesai.
     */
    public function syncMemberTier(Pelanggan $pelanggan): string
    {
        if ($pelanggan->is_walk_in) {
            return 'Reguler';
        }

        $totalAkumulasi = (float) Transaksi::where('id_pelanggan', $pelanggan->id_pelanggan)
            ->where('status', 'selesai')
            ->sum('total_belanja');

        $tierBaru = 'Reguler';
        if ($totalAkumulasi >= 1000000) {
            $tierBaru = 'Gold';
        } elseif ($totalAkumulasi >= 500000) {
            $tierBaru = 'Silver';
        }

        $rank = ['Reguler' => 1, 'Silver' => 2, 'Gold' => 3, 'VIP' => 4];
        $currentRank = $rank[$pelanggan->tier ?? 'Reguler'] ?? 1;
        $newRank = $rank[$tierBaru] ?? 1;

        if ($newRank > $currentRank) {
            $pelanggan->update(['tier' => $tierBaru]);

            return $tierBaru;
        }

        return (string) ($pelanggan->tier ?: 'Reguler');
    }
}
