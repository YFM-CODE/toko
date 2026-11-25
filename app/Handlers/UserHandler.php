<?php

namespace App\Handlers;

use App\Interfaces\LogInterface;
use App\Interfaces\UserInterface;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserHandler
{
    public function __construct(
        private UserInterface $userInterface,
        private LogInterface $logInterface
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $payload = [
                'nama_lengkap' => $data['nama_lengkap'],
                'username' => $data['username'],
                'rfid_uid' => ! empty($data['rfid_uid']) ? $data['rfid_uid'] : null,
                'email' => $data['email'] ?? null,
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? true,
                'password_hash' => $data['password'],
            ];

            $user = $this->userInterface->create($payload);

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'INSERT USER',
                'id_referensi' => $user->id_user,
                'data_baru' => "username={$user->username}, role={$user->role->value}, rfid=".($user->rfid_uid ?? '-'),
            ]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data): User {
            $existing = $this->userInterface->find($id);

            if ($existing->id_user === Auth::id() && isset($data['is_active']) && ! $data['is_active']) {
                throw new Exception('Anda tidak dapat menonaktifkan akun Anda sendiri.');
            }

            $payload = [
                'nama_lengkap' => $data['nama_lengkap'],
                'username' => $data['username'],
                'rfid_uid' => array_key_exists('rfid_uid', $data)
                    ? (! empty($data['rfid_uid']) ? $data['rfid_uid'] : null)
                    : $existing->rfid_uid,
                'email' => $data['email'] ?? null,
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? $existing->is_active,
            ];

            if (! empty($data['password'])) {
                $payload['password_hash'] = $data['password'];
            }

            $oldSnapshot = "username={$existing->username}, role={$existing->role->value}, rfid=".($existing->rfid_uid ?? '-');
            $updated = $this->userInterface->update($id, $payload);
            $newSnapshot = "username={$updated->username}, role={$updated->role->value}, rfid=".($updated->rfid_uid ?? '-');

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'UPDATE USER',
                'id_referensi' => $updated->id_user,
                'data_lama' => $oldSnapshot,
                'data_baru' => $newSnapshot,
            ]);

            return $updated;
        });
    }

    public function deleteUser(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            if ($id === Auth::id()) {
                throw new Exception('Anda tidak dapat menghapus akun Anda sendiri.');
            }

            $existing = $this->userInterface->find($id);
            $deleted = $this->userInterface->delete($id);

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_users',
                'aktivitas' => 'DELETE USER',
                'id_referensi' => $id,
                'data_lama' => "username={$existing->username}, role={$existing->role->value}",
            ]);

            return $deleted;
        });
    }
}
