<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_detail_transaksi', function (Blueprint $table) {
            $table->integer('id_detail')->autoIncrement();
            $table->integer('id_transaksi');
            $table->integer('id_produk');
            $table->integer('jumlah');
            $table->decimal('harga', 12, 2);
            $table->decimal('subtotal_item', 12, 2);
            $table->timestamps();

            $table->foreign('id_transaksi', 'fk_detail_transaksi')
                ->references('id_transaksi')
                ->on('tbl_transaksi')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('id_produk', 'fk_detail_produk')
                ->references('id_produk')
                ->on('tbl_produk')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index(['id_transaksi', 'id_produk'], 'idx_detail_transaksi_produk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_detail_transaksi');
    }
};
