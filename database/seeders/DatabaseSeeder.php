<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Akun Pengguna (Admin, Kasir, Owner) beserta UID Kartu RFID
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nama_lengkap' => 'Admin Toko',
                'rfid_uid' => 'RFID-ADMIN-001',
                'email' => 'admin@toko.com',
                'password_hash' => 'admin123',
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'kasir1'],
            [
                'nama_lengkap' => 'Kasir Satu',
                'rfid_uid' => 'RFID-KASIR-001',
                'email' => 'kasir@toko.com',
                'password_hash' => 'kasir123',
                'role' => UserRole::Kasir,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'owner'],
            [
                'nama_lengkap' => 'Pemilik Toko',
                'rfid_uid' => 'RFID-OWNER-001',
                'email' => 'owner@toko.com',
                'password_hash' => 'owner123',
                'role' => UserRole::Owner,
                'is_active' => true,
            ]
        );

        $memberUser = User::updateOrCreate(
            ['username' => 'member1'],
            [
                'nama_lengkap' => 'Andi Member',
                'rfid_uid' => 'RFID-PEL-001',
                'email' => 'andi@member.com',
                'password_hash' => 'member123',
                'role' => UserRole::Pelanggan,
                'is_active' => true,
            ]
        );

        // 2. Seed Kategori Produk
        $elektronik = Kategori::updateOrCreate(
            ['nama_kategori' => 'Elektronik'],
            ['deskripsi' => 'Perangkat elektronik dan aksesori']
        );

        $atk = Kategori::updateOrCreate(
            ['nama_kategori' => 'ATK'],
            ['deskripsi' => 'Alat tulis dan perlengkapan kantor']
        );

        $minuman = Kategori::updateOrCreate(
            ['nama_kategori' => 'Minuman'],
            ['deskripsi' => 'Minuman kemasan siap saji']
        );

        // 3. Seed Produk
        Produk::updateOrCreate(
            ['kode_produk' => 'PRD-001'],
            [
                'id_kategori' => $elektronik->id_kategori,
                'nama_produk' => 'Mouse Wireless',
                'harga' => 150000.00,
                'stok' => 25,
            ]
        );

        Produk::updateOrCreate(
            ['kode_produk' => 'PRD-002'],
            [
                'id_kategori' => $elektronik->id_kategori,
                'nama_produk' => 'Keyboard Mechanical',
                'harga' => 450000.00,
                'stok' => 10,
            ]
        );

        Produk::updateOrCreate(
            ['kode_produk' => 'PRD-003'],
            [
                'id_kategori' => $atk->id_kategori,
                'nama_produk' => 'Buku Catatan A5',
                'harga' => 25000.00,
                'stok' => 50,
            ]
        );

        Produk::updateOrCreate(
            ['kode_produk' => 'PRD-004'],
            [
                'id_kategori' => $minuman->id_kategori,
                'nama_produk' => 'Kopi Botol 250ml',
                'harga' => 12000.00,
                'stok' => 40,
            ]
        );

        // 4. Seed Pelanggan (Termasuk Default Walk-In & Kartu RFID Member + Poin, Tier, Saldo)
        $pelangganList = [
            ['nama_pelanggan' => 'Pelanggan Umum (Non-Member)', 'kota' => 'Umum', 'no_telepon' => '-', 'rfid_uid' => null, 'id_user' => null, 'tier' => 'Reguler', 'poin' => 0, 'saldo' => 0],
            ['nama_pelanggan' => 'Andi', 'kota' => 'Ponorogo', 'no_telepon' => '081234567801', 'rfid_uid' => 'RFID-PEL-001', 'id_user' => $memberUser->id_user, 'tier' => 'Gold', 'poin' => 120, 'saldo' => 250000],
            ['nama_pelanggan' => 'Budi', 'kota' => 'Madiun', 'no_telepon' => '081234567802', 'rfid_uid' => 'RFID-PEL-002', 'id_user' => null, 'tier' => 'Silver', 'poin' => 50, 'saldo' => 100000],
            ['nama_pelanggan' => 'Citra', 'kota' => 'Surabaya', 'no_telepon' => '081234567803', 'rfid_uid' => 'RFID-PEL-003', 'id_user' => null, 'tier' => 'Reguler', 'poin' => 25, 'saldo' => 50000],
            ['nama_pelanggan' => 'Dewi', 'kota' => 'Ponorogo', 'no_telepon' => '081234567804', 'rfid_uid' => 'RFID-PEL-004', 'id_user' => null, 'tier' => 'Reguler', 'poin' => 10, 'saldo' => 0],
        ];

        foreach ($pelangganList as $pelanggan) {
            Pelanggan::updateOrCreate(
                ['nama_pelanggan' => $pelanggan['nama_pelanggan'], 'kota' => $pelanggan['kota']],
                [
                    'id_user' => $pelanggan['id_user'],
                    'no_telepon' => $pelanggan['no_telepon'],
                    'rfid_uid' => $pelanggan['rfid_uid'],
                    'tier' => $pelanggan['tier'],
                    'poin' => $pelanggan['poin'],
                    'saldo' => $pelanggan['saldo'],
                ]
            );
        }
    }
}
