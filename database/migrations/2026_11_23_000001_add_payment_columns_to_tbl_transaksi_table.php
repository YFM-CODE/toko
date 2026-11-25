<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_transaksi', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_transaksi', 'metode_pembayaran')) {
                $table->string('metode_pembayaran', 30)->default('TUNAI')->after('total_belanja');
            }
            if (! Schema::hasColumn('tbl_transaksi', 'payment_channel')) {
                $table->string('payment_channel', 50)->default('CASH')->after('metode_pembayaran');
            }
            if (! Schema::hasColumn('tbl_transaksi', 'payment_token')) {
                $table->string('payment_token', 120)->nullable()->after('payment_channel');
            }
            if (! Schema::hasColumn('tbl_transaksi', 'payment_ref')) {
                $table->string('payment_ref', 80)->nullable()->after('payment_token');
            }
            if (! Schema::hasColumn('tbl_transaksi', 'nominal_bayar')) {
                $table->decimal('nominal_bayar', 12, 2)->default(0.00)->after('payment_ref');
            }
            if (! Schema::hasColumn('tbl_transaksi', 'kembalian')) {
                $table->decimal('kembalian', 12, 2)->default(0.00)->after('nominal_bayar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_transaksi', function (Blueprint $table) {
            $cols = array_filter([
                'metode_pembayaran',
                'payment_channel',
                'payment_token',
                'payment_ref',
                'nominal_bayar',
                'kembalian',
            ], fn (string $col): bool => Schema::hasColumn('tbl_transaksi', $col));

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
