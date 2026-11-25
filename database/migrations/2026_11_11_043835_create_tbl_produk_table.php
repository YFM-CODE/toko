<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_produk', function (Blueprint $table) {
            $table->integer('id_produk')->autoIncrement();
            $table->integer('id_kategori');
            $table->string('kode_produk', 30)->unique();
            $table->string('nama_produk', 150);
            $table->decimal('harga', 12, 2)->default(0.00);
            $table->integer('stok')->default(0);
            $table->string('gambar', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_kategori', 'fk_produk_kategori')
                ->references('id_kategori')
                ->on('tbl_kategori')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index('id_kategori', 'idx_produk_kategori');
            $table->index('nama_produk', 'idx_produk_nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_produk');
    }
};
