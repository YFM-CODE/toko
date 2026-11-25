<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_log', function (Blueprint $table) {
            $table->integer('id_log')->autoIncrement();
            $table->integer('id_user')->nullable();
            $table->string('nama_tabel', 50);
            $table->string('aktivitas', 50);
            $table->integer('id_referensi')->nullable();
            $table->text('data_lama')->nullable();
            $table->text('data_baru')->nullable();
            $table->timestamp('waktu')->useCurrent();

            $table->foreign('id_user', 'fk_log_user')
                ->references('id_user')
                ->on('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['nama_tabel', 'waktu'], 'idx_log_tabel_waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_log');
    }
};
