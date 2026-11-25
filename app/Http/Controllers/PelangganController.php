<?php

namespace App\Http\Controllers;

use App\Handlers\PelangganHandler;
use App\Helpers\PaginateHelper;
use App\Http\Requests\PelangganRequest;
use App\Http\Requests\TopUpSaldoRequest;
use App\Interfaces\PelangganInterface;
use App\Models\Pelanggan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PelangganController extends Controller
{
    public function __construct(
        private PelangganInterface $pelangganInterface,
        private PelangganHandler $pelangganHandler
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'kota', 'sort_by', 'sort_direction', 'per_page']);
        $pelanggan = $this->pelangganInterface->getAll($filters);
        $daftarKota = Pelanggan::query()->select('kota')->distinct()->orderBy('kota')->pluck('kota');

        return Inertia::render('Admin/Pelanggan', [
            'pelanggan' => $pelanggan,
            'daftarKota' => $daftarKota,
            'pagination' => PaginateHelper::getPaginate($pelanggan),
            'filters' => $filters,
        ]);
    }

    public function store(PelangganRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $pelanggan = $this->pelangganHandler->savePelanggan($request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Data member/pelanggan berhasil ditambahkan.',
                    'data' => $pelanggan,
                ], 201);
            }

            return redirect()->back()->with('success', 'Data member/pelanggan berhasil ditambahkan.');
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Gagal menambah pelanggan: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['pelanggan' => 'Gagal menambah pelanggan: '.$e->getMessage()]);
        }
    }

    public function update(PelangganRequest $request, int $id): RedirectResponse
    {
        try {
            $this->pelangganHandler->savePelanggan($request->validated(), $id);

            return redirect()->back()->with('success', 'Data member/pelanggan berhasil diperbarui.');
        } catch (Throwable $e) {
            return redirect()->back()->withErrors(['pelanggan' => 'Gagal memperbarui pelanggan: '.$e->getMessage()]);
        }
    }

    public function topUp(TopUpSaldoRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        try {
            $pelanggan = $this->pelangganHandler->topUpSaldo($id, (float) $validated['nominal']);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Top-up saldo member {$pelanggan->nama_pelanggan} berhasil.",
                    'data' => $pelanggan,
                ]);
            }

            return redirect()->back()->with(
                'success',
                'Top-up saldo member '.$pelanggan->nama_pelanggan.' sebesar Rp '.number_format((float) $validated['nominal'], 0, ',', '.').' berhasil.'
            );
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['pelanggan' => 'Gagal top-up saldo: '.$e->getMessage()]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->pelangganInterface->delete($id);

            return redirect()->back()->with('success', 'Data pelanggan berhasil dihapus.');
        } catch (Throwable) {
            return redirect()->back()->withErrors(['pelanggan' => 'Pelanggan tidak dapat dihapus karena memiliki riwayat transaksi.']);
        }
    }
}
