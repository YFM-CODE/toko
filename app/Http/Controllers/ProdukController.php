<?php

namespace App\Http\Controllers;

use App\Helpers\PaginateHelper;
use App\Http\Requests\ProdukRequest;
use App\Interfaces\KategoriInterface;
use App\Interfaces\ProdukInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProdukController extends Controller
{
    public function __construct(
        private ProdukInterface $produkInterface,
        private KategoriInterface $kategoriInterface
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'id_kategori', 'stok_menipis', 'sort_by', 'sort_direction', 'per_page']);
        $produk = $this->produkInterface->getAll($filters);
        $kategori = $this->kategoriInterface->getAllWithoutPagination();

        return Inertia::render('Admin/Produk', [
            'produk' => $produk,
            'kategori' => $kategori,
            'pagination' => PaginateHelper::getPaginate($produk),
            'filters' => $filters,
        ]);
    }

    public function store(ProdukRequest $request): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->safe()->except(['gambar', 'hapus_gambar']);

            if ($request->hasFile('gambar')) {
                $data['gambar'] = $this->uploadGambar($request->file('gambar'), (string) $data['kode_produk']);
            }

            $this->produkInterface->create($data);
            DB::commit();

            return redirect()->back()->with('success', 'Produk berhasil ditambahkan.');
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['produk' => 'Gagal menambah produk: '.$e->getMessage()]);
        }
    }

    public function update(ProdukRequest $request, int $id): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $existing = $this->produkInterface->find($id);
            $data = $request->safe()->except(['gambar', 'hapus_gambar']);

            if ($request->boolean('hapus_gambar') && $existing?->gambar) {
                $this->deleteGambarFile($existing->gambar);
                $data['gambar'] = null;
            }

            if ($request->hasFile('gambar')) {
                if ($existing?->gambar) {
                    $this->deleteGambarFile($existing->gambar);
                }
                $data['gambar'] = $this->uploadGambar($request->file('gambar'), (string) $data['kode_produk']);
            }

            $this->produkInterface->update($id, $data);
            DB::commit();

            return redirect()->back()->with('success', 'Produk berhasil diperbarui.');
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['produk' => 'Gagal memperbarui produk: '.$e->getMessage()]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $existing = $this->produkInterface->find($id);
            $this->produkInterface->delete($id);

            if ($existing?->gambar) {
                $this->deleteGambarFile($existing->gambar);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Produk berhasil dihapus.');
        } catch (Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['produk' => 'Produk tidak dapat dihapus karena sudah tercatat pada detail transaksi.']);
        }
    }

    private function uploadGambar(UploadedFile $file, string $kodeProduk): string
    {
        $directory = public_path('uploads/produk');
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::slug($kodeProduk).'-'.time().'-'.Str::lower(Str::random(4)).'.'.$extension;
        $file->move($directory, $filename);

        return $filename;
    }

    private function deleteGambarFile(string $filename): void
    {
        $path = public_path('uploads/produk/'.basename($filename));
        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
