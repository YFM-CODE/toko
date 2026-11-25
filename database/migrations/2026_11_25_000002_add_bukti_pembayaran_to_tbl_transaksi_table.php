<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE tbl_transaksi MODIFY COLUMN status ENUM('pending', 'selesai', 'batal') NOT NULL DEFAULT 'selesai'");
        }

        Schema::table('tbl_transaksi', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_transaksi', 'bukti_pembayaran')) {
                $table->string('bukti_pembayaran', 255)->nullable()->after('payment_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_transaksi', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_transaksi', 'bukti_pembayaran')) {
                $table->dropColumn('bukti_pembayaran');
            }
        });
    }
};
