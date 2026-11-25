<?php

namespace App\Http\Controllers;

use App\Helpers\PaginateHelper;
use App\Http\Requests\KategoriRequest;
use App\Interfaces\KategoriInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KategoriController extends Controller
{
    public function __construct(
        private KategoriInterface $kategoriInterface
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'sort_by', 'sort_direction', 'per_page']);
        $kategori = $this->kategoriInterface->getAll($filters);

        return Inertia::render('Admin/Kategori', [
            'kategori' => $kategori,
            'pagination' => PaginateHelper::getPaginate($kategori),
            'filters' => $filters,
        ]);
    }

    public function store(KategoriRequest $request): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $this->kategoriInterface->create($request->validated());
            DB::commit();

            return redirect()->back()->with('success', 'Kategori berhasil ditambahkan.');
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['kategori' => 'Gagal menambah kategori: '.$e->getMessage()]);
        }
    }

    public function update(KategoriRequest $request, int $id): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $this->kategoriInterface->update($id, $request->validated());
            DB::commit();

            return redirect()->back()->with('success', 'Kategori berhasil diperbarui.');
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['kategori' => 'Gagal memperbarui kategori: '.$e->getMessage()]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $this->kategoriInterface->delete($id);
            DB::commit();

            return redirect()->back()->with('success', 'Kategori berhasil dihapus.');
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['kategori' => 'Kategori tidak dapat dihapus karena masih digunakan oleh produk (ON DELETE RESTRICT).']);
        }
    }
}
