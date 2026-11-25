<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_pelanggan', function (Blueprint $table) {
            $table->integer('id_pelanggan')->autoIncrement();
            $table->integer('id_user')->nullable()->unique();
            $table->string('rfid_uid', 50)->nullable()->unique();
            $table->string('nama_pelanggan', 100);
            $table->string('kota', 50);
            $table->string('no_telepon', 20)->nullable();
            $table->string('tier', 20)->default('Reguler');
            $table->integer('poin')->default(0);
            $table->decimal('saldo', 12, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('id_user', 'fk_pelanggan_user')
                ->references('id_user')
                ->on('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('kota', 'idx_pelanggan_kota');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_pelanggan');
    }
};
