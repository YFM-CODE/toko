<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_transaksi', function (Blueprint $table) {
            $table->integer('id_transaksi')->autoIncrement();
            $table->string('kode_transaksi', 30)->unique();
            $table->date('tanggal');
            $table->integer('id_pelanggan');
            $table->integer('id_user')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('diskon', 12, 2)->default(0.00);
            $table->decimal('total_belanja', 12, 2)->default(0.00);
            $table->enum('status', ['pending', 'selesai', 'batal'])->default('selesai');
            $table->timestamps();

            $table->foreign('id_pelanggan', 'fk_transaksi_pelanggan')
                ->references('id_pelanggan')
                ->on('tbl_pelanggan')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_user', 'fk_transaksi_user')
                ->references('id_user')
                ->on('tbl_users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index('tanggal', 'idx_transaksi_tanggal');
            $table->index(['id_pelanggan', 'tanggal'], 'idx_transaksi_pelanggan_tgl');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_transaksi');
    }
};
