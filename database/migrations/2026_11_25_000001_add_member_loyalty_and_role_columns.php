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
            DB::statement("ALTER TABLE tbl_users MODIFY COLUMN role ENUM('admin', 'kasir', 'owner', 'pelanggan') NOT NULL DEFAULT 'kasir'");
            DB::statement("ALTER TABLE tbl_transaksi MODIFY COLUMN status ENUM('pending', 'selesai', 'batal') NOT NULL DEFAULT 'selesai'");
        }

        Schema::table('tbl_pelanggan', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_pelanggan', 'id_user')) {
                $table->integer('id_user')->nullable()->unique()->after('id_pelanggan');
                if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->foreign('id_user', 'fk_pelanggan_user')
                        ->references('id_user')
                        ->on('tbl_users')
                        ->nullOnDelete()
                        ->cascadeOnUpdate();
                }
            }

            if (! Schema::hasColumn('tbl_pelanggan', 'tier')) {
                $table->string('tier', 20)->default('Reguler')->after('no_telepon');
            }

            if (! Schema::hasColumn('tbl_pelanggan', 'poin')) {
                $table->integer('poin')->default(0)->after('tier');
            }

            if (! Schema::hasColumn('tbl_pelanggan', 'saldo')) {
                $table->decimal('saldo', 12, 2)->default(0.00)->after('poin');
            }
        });

        Schema::table('tbl_transaksi', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_transaksi', 'poin_didapat')) {
                $table->integer('poin_didapat')->default(0)->after('kembalian');
            }

            if (! Schema::hasColumn('tbl_transaksi', 'poin_ditukar')) {
                $table->integer('poin_ditukar')->default(0)->after('poin_didapat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_transaksi', function (Blueprint $table) {
            $cols = array_filter(
                ['poin_didapat', 'poin_ditukar'],
                fn (string $col): bool => Schema::hasColumn('tbl_transaksi', $col)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('tbl_pelanggan', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_pelanggan', 'id_user')) {
                try {
                    $table->dropForeign('fk_pelanggan_user');
                } catch (Throwable) {
                    // Ignore on SQLite
                }
            }

            $cols = array_filter(
                ['id_user', 'tier', 'poin', 'saldo'],
                fn (string $col): bool => Schema::hasColumn('tbl_pelanggan', $col)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
