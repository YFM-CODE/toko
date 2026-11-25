<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Handlers\UserHandler;
use App\Helpers\PaginateHelper;
use App\Http\Requests\UserRequest;
use App\Interfaces\UserInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private UserInterface $userInterface,
        private UserHandler $userHandler
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'role', 'is_active', 'sort_by', 'sort_direction', 'per_page']);
        $users = $this->userInterface->getAll($filters);

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'roles' => UserRole::values(),
            'pagination' => PaginateHelper::getPaginate($users),
            'filters' => $filters,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        try {
            $this->userHandler->createUser($request->validated());

            return redirect()->back()->with('success', 'Akun pengguna berhasil dibuat.');
        } catch (Throwable $e) {
            return redirect()->back()->withErrors(['user' => 'Gagal membuat akun: '.$e->getMessage()]);
        }
    }

    public function update(UserRequest $request, int $id): RedirectResponse
    {
        try {
            $this->userHandler->updateUser($id, $request->validated());

            return redirect()->back()->with('success', 'Akun pengguna berhasil diperbarui.');
        } catch (Throwable $e) {
            return redirect()->back()->withErrors(['user' => 'Gagal memperbarui akun: '.$e->getMessage()]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->userHandler->deleteUser($id);

            return redirect()->back()->with('success', 'Akun pengguna berhasil dihapus.');
        } catch (Throwable $e) {
            return redirect()->back()->withErrors(['user' => 'Gagal menghapus akun: '.$e->getMessage()]);
        }
    }
}
