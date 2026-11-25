<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dropMysqlObjects();

            // 1. Stored Functions
            DB::unprepared('
                CREATE FUNCTION HitungTotal(
                    p_harga DECIMAL(12,2),
                    p_jumlah INT
                )
                RETURNS DECIMAL(12,2)
                DETERMINISTIC
                NO SQL
                BEGIN
                    RETURN p_harga * p_jumlah;
                END
            ');

            DB::unprepared('
                CREATE FUNCTION HitungDiskon(
                    p_subtotal DECIMAL(12,2),
                    p_persen_diskon DECIMAL(5,2)
                )
                RETURNS DECIMAL(12,2)
                DETERMINISTIC
                NO SQL
                BEGIN
                    IF p_persen_diskon <= 0 THEN
                        RETURN 0.00;
                    ELSEIF p_persen_diskon > 100 THEN
                        RETURN p_subtotal;
                    END IF;
                    RETURN p_subtotal * (p_persen_diskon / 100);
                END
            ');

            DB::unprepared("
                CREATE FUNCTION KategoriBelanja(
                    p_total DECIMAL(12,2)
                )
                RETURNS VARCHAR(30)
                DETERMINISTIC
                NO SQL
                BEGIN
                    RETURN CASE
                        WHEN p_total >= 1000000 THEN 'Pelanggan Gold'
                        WHEN p_total >= 500000 THEN 'Pelanggan Silver'
                        ELSE 'Pelanggan Reguler'
                    END;
                END
            ");

            // 2. Database Triggers
            DB::unprepared("
                CREATE TRIGGER trg_before_insert_transaksi
                BEFORE INSERT ON tbl_transaksi
                FOR EACH ROW
                BEGIN
                    IF NEW.total_belanja < 0 OR NEW.subtotal < 0 OR NEW.diskon < 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Total belanja, subtotal, atau diskon tidak boleh negatif';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_before_insert_detail
                BEFORE INSERT ON tbl_detail_transaksi
                FOR EACH ROW
                BEGIN
                    DECLARE v_stok INT;

                    IF NEW.jumlah <= 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Jumlah pembelian harus lebih dari 0';
                    END IF;

                    SELECT stok INTO v_stok
                    FROM tbl_produk
                    WHERE id_produk = NEW.id_produk;

                    IF v_stok < NEW.jumlah THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Stok produk tidak mencukupi untuk transaksi ini';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_after_insert_detail
                AFTER INSERT ON tbl_detail_transaksi
                FOR EACH ROW
                BEGIN
                    UPDATE tbl_produk
                    SET stok = stok - NEW.jumlah
                    WHERE id_produk = NEW.id_produk;

                    INSERT INTO tbl_log (nama_tabel, aktivitas, id_referensi, data_baru)
                    VALUES (
                        'tbl_detail_transaksi',
                        'POTONG STOK OTOMATIS',
                        NEW.id_produk,
                        CONCAT('id_transaksi=', NEW.id_transaksi, ', qty_keluar=', NEW.jumlah)
                    );
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_after_insert_pelanggan
                AFTER INSERT ON tbl_pelanggan
                FOR EACH ROW
                BEGIN
                    INSERT INTO tbl_log (nama_tabel, aktivitas, id_referensi, data_baru)
                    VALUES (
                        'tbl_pelanggan',
                        'INSERT PELANGGAN',
                        NEW.id_pelanggan,
                        CONCAT('nama=', NEW.nama_pelanggan, ', kota=', NEW.kota)
                    );
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_after_update_pelanggan
                AFTER UPDATE ON tbl_pelanggan
                FOR EACH ROW
                BEGIN
                    INSERT INTO tbl_log (nama_tabel, aktivitas, id_referensi, data_lama, data_baru)
                    VALUES (
                        'tbl_pelanggan',
                        'UPDATE PELANGGAN',
                        OLD.id_pelanggan,
                        CONCAT('nama=', OLD.nama_pelanggan, ', kota=', OLD.kota),
                        CONCAT('nama=', NEW.nama_pelanggan, ', kota=', NEW.kota)
                    );
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_after_update_produk
                AFTER UPDATE ON tbl_produk
                FOR EACH ROW
                BEGIN
                    IF OLD.harga <> NEW.harga OR OLD.stok <> NEW.stok THEN
                        INSERT INTO tbl_log (nama_tabel, aktivitas, id_referensi, data_lama, data_baru)
                        VALUES (
                            'tbl_produk',
                            'UPDATE PRODUK',
                            OLD.id_produk,
                            CONCAT('harga=', OLD.harga, ', stok=', OLD.stok),
                            CONCAT('harga=', NEW.harga, ', stok=', NEW.stok)
                        );
                    END IF;
                END
            ");

            // 3. Stored Procedure + TCL (COMMIT & ROLLBACK)
            DB::unprepared("
                CREATE PROCEDURE sp_tambah_transaksi(
                    IN p_kode_transaksi VARCHAR(30),
                    IN p_tanggal DATE,
                    IN p_id_pelanggan INT,
                    IN p_id_user INT,
                    IN p_id_produk INT,
                    IN p_jumlah INT,
                    IN p_persen_diskon DECIMAL(5,2),
                    OUT p_id_transaksi_baru INT
                )
                BEGIN
                    DECLARE v_harga DECIMAL(12,2);
                    DECLARE v_subtotal DECIMAL(12,2);
                    DECLARE v_diskon DECIMAL(12,2);
                    DECLARE v_total DECIMAL(12,2);

                    DECLARE EXIT HANDLER FOR SQLEXCEPTION
                    BEGIN
                        ROLLBACK;
                        RESIGNAL;
                    END;

                    START TRANSACTION;

                    SELECT harga INTO v_harga
                    FROM tbl_produk
                    WHERE id_produk = p_id_produk
                    FOR UPDATE;

                    SET v_subtotal = HitungTotal(v_harga, p_jumlah);
                    SET v_diskon   = HitungDiskon(v_subtotal, p_persen_diskon);
                    SET v_total    = v_subtotal - v_diskon;

                    INSERT INTO tbl_transaksi (
                        kode_transaksi, tanggal, id_pelanggan, id_user,
                        subtotal, diskon, total_belanja, status, created_at, updated_at
                    ) VALUES (
                        p_kode_transaksi, p_tanggal, p_id_pelanggan, p_id_user,
                        v_subtotal, v_diskon, v_total, 'selesai', NOW(), NOW()
                    );

                    SET p_id_transaksi_baru = LAST_INSERT_ID();

                    INSERT INTO tbl_detail_transaksi (
                        id_transaksi, id_produk, jumlah, harga, subtotal_item, created_at, updated_at
                    ) VALUES (
                        p_id_transaksi_baru, p_id_produk, p_jumlah, v_harga, v_subtotal, NOW(), NOW()
                    );

                    INSERT INTO tbl_log (id_user, nama_tabel, aktivitas, id_referensi, data_baru)
                    VALUES (
                        p_id_user,
                        'tbl_transaksi',
                        'INSERT TRANSAKSI',
                        p_id_transaksi_baru,
                        CONCAT('kode=', p_kode_transaksi, ', total=', v_total)
                    );

                    COMMIT;
                END
            ");

            // 4. Database Views
            DB::unprepared("
                CREATE OR REPLACE VIEW view_laporan_penjualan AS
                SELECT
                    t.id_transaksi,
                    t.kode_transaksi,
                    t.tanggal,
                    p.nama_pelanggan,
                    p.kota,
                    COALESCE(u.nama_lengkap, 'System/SQL') AS kasir,
                    t.subtotal,
                    t.diskon,
                    t.total_belanja,
                    t.status
                FROM tbl_transaksi t
                JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
                LEFT JOIN tbl_users u ON t.id_user = u.id_user
            ");

            DB::unprepared('
                CREATE OR REPLACE VIEW view_total_belanja_pelanggan AS
                SELECT
                    p.id_pelanggan,
                    p.nama_pelanggan,
                    p.kota,
                    COUNT(t.id_transaksi) AS total_transaksi,
                    COALESCE(SUM(t.total_belanja), 0.00) AS total_belanja,
                    KategoriBelanja(COALESCE(SUM(t.total_belanja), 0.00)) AS kategori_pelanggan
                FROM tbl_pelanggan p
                LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
                GROUP BY p.id_pelanggan, p.nama_pelanggan, p.kota
            ');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP VIEW IF EXISTS view_laporan_penjualan');
            DB::unprepared('DROP VIEW IF EXISTS view_total_belanja_pelanggan');

            DB::unprepared("
                CREATE VIEW view_laporan_penjualan AS
                SELECT
                    t.id_transaksi,
                    t.kode_transaksi,
                    t.tanggal,
                    p.nama_pelanggan,
                    p.kota,
                    COALESCE(u.nama_lengkap, 'System/SQL') AS kasir,
                    t.subtotal,
                    t.diskon,
                    t.total_belanja,
                    t.status
                FROM tbl_transaksi t
                JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
                LEFT JOIN tbl_users u ON t.id_user = u.id_user
            ");

            DB::unprepared("
                CREATE VIEW view_total_belanja_pelanggan AS
                SELECT
                    p.id_pelanggan,
                    p.nama_pelanggan,
                    p.kota,
                    COUNT(t.id_transaksi) AS total_transaksi,
                    COALESCE(SUM(t.total_belanja), 0.00) AS total_belanja,
                    CASE
                        WHEN COALESCE(SUM(t.total_belanja), 0.00) >= 1000000 THEN 'Pelanggan Gold'
                        WHEN COALESCE(SUM(t.total_belanja), 0.00) >= 500000 THEN 'Pelanggan Silver'
                        ELSE 'Pelanggan Reguler'
                    END AS kategori_pelanggan
                FROM tbl_pelanggan p
                LEFT JOIN tbl_transaksi t ON p.id_pelanggan = t.id_pelanggan
                GROUP BY p.id_pelanggan, p.nama_pelanggan, p.kota
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dropMysqlObjects();
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP VIEW IF EXISTS view_laporan_penjualan');
            DB::unprepared('DROP VIEW IF EXISTS view_total_belanja_pelanggan');
        }
    }

    private function dropMysqlObjects(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS view_laporan_penjualan');
        DB::unprepared('DROP VIEW IF EXISTS view_total_belanja_pelanggan');
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_transaksi');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_before_insert_transaksi');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_before_insert_detail');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_insert_detail');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_insert_pelanggan');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_update_pelanggan');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_after_update_produk');
        DB::unprepared('DROP FUNCTION IF EXISTS HitungTotal');
        DB::unprepared('DROP FUNCTION IF EXISTS HitungDiskon');
        DB::unprepared('DROP FUNCTION IF EXISTS KategoriBelanja');
    }
};
