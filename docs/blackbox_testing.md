# Blackbox Testing Plan — SI-KENDI (mawang)

Dokumen ini berisi skenario pengujian blackbox untuk fitur yang baru/telah diubah: eksport (XLSX/CSV/PDF), pengingat email H-1, dan notifikasi popup persetujuan surat tugas.

## Tabel Hasil Pengujian (berdasarkan role dan modul)

### Role Admin

| Modul | Laman | Skenario Pengujian | Test Case | Hasil yang Diharapkan | Hasil Pengujian |
| --- | --- | --- | --- | --- | --- |
| Auth | [login.php](login.php) | Login sebagai admin | Email admin; Password admin | Bisa login dan diarahkan ke halaman utama | Sesuai |
| Auth | [logout.php](logout.php) | Logout dari aplikasi | Klik tombol logout | Sesi berakhir dan diarahkan ke halaman login | Sesuai |
| Auth | [pages/403.php](pages/403.php) | Akses halaman tanpa hak | Akses URL tanpa role yang sesuai | Halaman 403 tampil | Sesuai |
| Dashboard | [index.php](index.php) | Membuka halaman utama | Login lalu akses beranda | Dashboard sesuai role tampil | Sesuai |
| Dashboard | [pages/dashboard_admin.php](pages/dashboard_admin.php) | Membuka dashboard admin | Akses menu dashboard admin | Halaman tampil sesuai role | Sesuai |
| Dashboard | [pages/dashboard_operator.php](pages/dashboard_operator.php) | Membuka dashboard operator (opsional) | Akses menu dashboard operator | Halaman tampil sesuai role | Sesuai |
| Home | [pages/home.php](pages/home.php) | Membuka halaman home | Akses menu home | Halaman tampil | Sesuai |
| Home | [pages/home.back.php](pages/home.back.php) | Membuka halaman home (backup) | Akses URL langsung | Halaman tampil atau redirect sesuai konfigurasi | Sesuai |
| Kendaraan | [pages/list_kendaraan.php](pages/list_kendaraan.php) | Melihat list kendaraan | Akses menu list kendaraan | Daftar tampil | Sesuai |
| Kendaraan | [pages/list_kendaraan.back.php](pages/list_kendaraan.back.php) | Melihat list kendaraan (backup) | Akses URL langsung | Halaman tampil atau redirect sesuai konfigurasi | Sesuai |
| Kendaraan | [pages/kendaraan.php](pages/kendaraan.php) | Melihat daftar kendaraan | Akses menu kendaraan | Daftar kendaraan tampil | Sesuai |
| Kendaraan | [pages/kendaraan_detail.php](pages/kendaraan_detail.php) | Melihat detail kendaraan | Pilih salah satu kendaraan | Detail kendaraan tampil | Sesuai |
| Kendaraan | [pages/kendaraan_view.php](pages/kendaraan_view.php) | Melihat detail kendaraan (view) | Akses menu view kendaraan | Detail tampil | Sesuai |
| Kendaraan | [pages/pengguna_kendaraan.php](pages/pengguna_kendaraan.php) | Melihat pengguna kendaraan | Akses menu pengguna kendaraan | Daftar tampil | Sesuai |
| Dokumen | [pages/dokumen_kendaraan.php](pages/dokumen_kendaraan.php) | Melihat daftar dokumen kendaraan | Akses menu dokumen | Daftar dokumen tampil | Sesuai |
| Dokumen | [pages/dokumen_kendaraan_edit.php](pages/dokumen_kendaraan_edit.php) | Mengubah dokumen kendaraan | Buka form edit dan simpan data valid | Data tersimpan dan tampil kembali | Sesuai |
| Peminjaman | [pages/form_peminjaman.php](pages/form_peminjaman.php) | Membuat peminjaman kendaraan | Isi form peminjaman dengan data valid | Peminjaman tersimpan | Sesuai |
| Peminjaman | [pages/monitoring_peminjaman.php](pages/monitoring_peminjaman.php) | Melihat monitoring peminjaman | Akses menu monitoring peminjaman | Data peminjaman tampil | Sesuai |
| Peminjaman | [pages/peminjaman_terjadwal.php](pages/peminjaman_terjadwal.php) | Melihat peminjaman terjadwal | Akses menu peminjaman terjadwal | Daftar tampil | Sesuai |
| Peminjaman | [pages/persetujuan_peminjaman.php](pages/persetujuan_peminjaman.php) | Menyetujui peminjaman | Akses menu persetujuan | Daftar pengajuan tampil | Sesuai |
| Peminjaman | [pages/pinjam_pakai.php](pages/pinjam_pakai.php) | Melihat pinjam pakai | Akses menu pinjam pakai | Data pinjam pakai tampil | Sesuai |
| Penugasan | [pages/penugasan_admin.php](pages/penugasan_admin.php) | Mengelola penugasan (admin) | Akses menu penugasan admin | Halaman tampil dan data muncul | Sesuai |
| Penugasan | [pages/penugasan.php](pages/penugasan.php) | Melihat penugasan | Akses menu penugasan | Daftar penugasan tampil | Sesuai |
| Perawatan | [pages/jadwal_perawatan.php](pages/jadwal_perawatan.php) | Melihat jadwal perawatan | Akses menu jadwal perawatan | Jadwal tampil | Sesuai |
| Perawatan | [pages/Jadwal_perawatan.back.php](pages/Jadwal_perawatan.back.php) | Melihat jadwal perawatan (backup) | Akses URL langsung | Halaman tampil atau redirect sesuai konfigurasi | Sesuai |
| Perawatan | [pages/riwayat_perawatan.php](pages/riwayat_perawatan.php) | Melihat riwayat perawatan | Akses menu riwayat perawatan | Riwayat tampil | Sesuai |
| Perawatan | [pages/riwayat_perawatan_kendaraan.php](pages/riwayat_perawatan_kendaraan.php) | Melihat riwayat perawatan kendaraan | Akses menu riwayat perawatan kendaraan | Riwayat tampil | Sesuai |
| Perawatan | [pages/riwayat_perawatan_new.php](pages/riwayat_perawatan_new.php) | Melihat riwayat perawatan (baru) | Akses menu riwayat perawatan (baru) | Riwayat tampil | Sesuai |
| Perawatan | [pages/riwayat_perbaikan.php](pages/riwayat_perbaikan.php) | Melihat riwayat perbaikan | Akses menu riwayat perbaikan | Riwayat tampil | Sesuai |
| Perawatan | [pages/riwayat_perbaikan_detail.php](pages/riwayat_perbaikan_detail.php) | Melihat detail riwayat perbaikan | Pilih salah satu riwayat | Detail tampil | Sesuai |
| Riwayat | [pages/riwayat.php](pages/riwayat.php) | Melihat riwayat | Akses menu riwayat | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_kendaraan.php](pages/riwayat_kendaraan.php) | Melihat riwayat kendaraan | Akses menu riwayat kendaraan | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_pemakaian.php](pages/riwayat_pemakaian.php) | Melihat riwayat pemakaian | Akses menu riwayat pemakaian | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_peminjaman.php](pages/riwayat_peminjaman.php) | Melihat riwayat peminjaman | Akses menu riwayat peminjaman | Riwayat tampil | Sesuai |
| Log | [pages/log_aktivitas.php](pages/log_aktivitas.php) | Melihat log aktivitas | Akses menu log aktivitas | Log tampil | Sesuai |
| Log | [pages/log_aktivitas_detail.php](pages/log_aktivitas_detail.php) | Melihat detail log aktivitas | Pilih salah satu log | Detail log tampil | Sesuai |
| Log | [pages/log_bahan_bakar.php](pages/log_bahan_bakar.php) | Melihat log bahan bakar | Akses menu log bahan bakar | Log tampil | Sesuai |
| Log | [pages/log_bahan_bakar_detail.php](pages/log_bahan_bakar_detail.php) | Melihat detail log bahan bakar | Pilih salah satu log | Detail log tampil | Sesuai |
| Log | [pages/log_bbm_kendaraan.php](pages/log_bbm_kendaraan.php) | Melihat log BBM kendaraan | Akses menu log BBM | Log tampil | Sesuai |
| Manajemen | [pages/manajemen_user.php](pages/manajemen_user.php) | Mengelola pengguna | Akses menu manajemen user | Data pengguna tampil | Sesuai |
| Surat Tugas | [pages/surat_tugas.php](pages/surat_tugas.php) | Mengelola surat tugas | Akses menu surat tugas | Daftar surat tugas tampil | Sesuai |
| Laporan | [pages/laporan_perjalanan.php](pages/laporan_perjalanan.php) | Melihat laporan perjalanan | Akses menu laporan perjalanan | Laporan tampil | Sesuai |
| Peta | [pages/map_kendaraan.php](pages/map_kendaraan.php) | Melihat peta kendaraan | Akses menu peta kendaraan | Peta dan marker tampil | Sesuai |
| Profil | [pages/profil.php](pages/profil.php) | Melihat profil pengguna | Akses menu profil | Profil tampil | Sesuai |

### Role Pimpinan

| Modul | Laman | Skenario Pengujian | Test Case | Hasil yang Diharapkan | Hasil Pengujian |
| --- | --- | --- | --- | --- | --- |
| Auth | [login.php](login.php) | Login sebagai pimpinan | Email pimpinan; Password pimpinan | Bisa login dan diarahkan ke halaman utama | Sesuai |
| Auth | [logout.php](logout.php) | Logout dari aplikasi | Klik tombol logout | Sesi berakhir dan diarahkan ke halaman login | Sesuai |
| Auth | [pages/403.php](pages/403.php) | Akses halaman tanpa hak | Akses URL tanpa role yang sesuai | Halaman 403 tampil | Sesuai |
| Dashboard | [index.php](index.php) | Membuka halaman utama | Login lalu akses beranda | Dashboard sesuai role tampil | Sesuai |
| Dashboard | [pages/dashboard_pimpinan.php](pages/dashboard_pimpinan.php) | Membuka dashboard pimpinan | Akses menu dashboard pimpinan | Halaman tampil sesuai role | Sesuai |
| Peminjaman | [pages/monitoring_peminjaman.php](pages/monitoring_peminjaman.php) | Melihat monitoring peminjaman | Akses menu monitoring peminjaman | Data peminjaman tampil | Sesuai |
| Peminjaman | [pages/persetujuan_peminjaman.php](pages/persetujuan_peminjaman.php) | Menyetujui peminjaman | Akses menu persetujuan | Daftar pengajuan tampil | Sesuai |
| Surat Tugas | [pages/surat_tugas.php](pages/surat_tugas.php) | Melihat surat tugas | Akses menu surat tugas | Daftar surat tugas tampil | Sesuai |
| Laporan | [pages/laporan_perjalanan.php](pages/laporan_perjalanan.php) | Melihat laporan perjalanan | Akses menu laporan perjalanan | Laporan tampil | Sesuai |
| Peta | [pages/map_kendaraan.php](pages/map_kendaraan.php) | Melihat peta kendaraan | Akses menu peta kendaraan | Peta dan marker tampil | Sesuai |
| Penugasan | [pages/penugasan.php](pages/penugasan.php) | Melihat penugasan | Akses menu penugasan | Daftar penugasan tampil | Sesuai |
| Riwayat | [pages/riwayat.php](pages/riwayat.php) | Melihat riwayat | Akses menu riwayat | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_peminjaman.php](pages/riwayat_peminjaman.php) | Melihat riwayat peminjaman | Akses menu riwayat peminjaman | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_perawatan.php](pages/riwayat_perawatan.php) | Melihat riwayat perawatan | Akses menu riwayat perawatan | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_perbaikan.php](pages/riwayat_perbaikan.php) | Melihat riwayat perbaikan | Akses menu riwayat perbaikan | Riwayat tampil | Sesuai |
| Profil | [pages/profil.php](pages/profil.php) | Melihat profil pengguna | Akses menu profil | Profil tampil | Sesuai |

### Role Driver

| Modul | Laman | Skenario Pengujian | Test Case | Hasil yang Diharapkan | Hasil Pengujian |
| --- | --- | --- | --- | --- | --- |
| Auth | [login.php](login.php) | Login sebagai driver | Email driver; Password driver | Bisa login dan diarahkan ke halaman utama | Sesuai |
| Auth | [logout.php](logout.php) | Logout dari aplikasi | Klik tombol logout | Sesi berakhir dan diarahkan ke halaman login | Sesuai |
| Auth | [pages/403.php](pages/403.php) | Akses halaman tanpa hak | Akses URL tanpa role yang sesuai | Halaman 403 tampil | Sesuai |
| Dashboard | [index.php](index.php) | Membuka halaman utama | Login lalu akses beranda | Dashboard sesuai role tampil | Sesuai |
| Dashboard | [pages/dashboard_driver.php](pages/dashboard_driver.php) | Membuka dashboard driver | Akses menu dashboard driver | Halaman tampil sesuai role | Sesuai |
| Kendaraan | [pages/kendaraan_saya.php](pages/kendaraan_saya.php) | Melihat kendaraan saya | Akses menu kendaraan saya | Daftar kendaraan terkait tampil | Sesuai |
| Kendaraan | [pages/kendaraan_detail_user.php](pages/kendaraan_detail_user.php) | Melihat detail kendaraan | Pilih salah satu kendaraan | Detail tampil | Sesuai |
| Surat Tugas | [pages/surat_tugas_user.php](pages/surat_tugas_user.php) | Melihat surat tugas | Akses menu surat tugas | Daftar surat tugas tampil | Sesuai |
| Peta | [pages/map_kendaraan.php](pages/map_kendaraan.php) | Melihat peta kendaraan | Akses menu peta kendaraan | Peta dan marker tampil | Sesuai |
| Laporan | [pages/laporan_perjalanan.php](pages/laporan_perjalanan.php) | Melihat laporan perjalanan | Akses menu laporan perjalanan | Laporan tampil | Sesuai |
| Log | [pages/log_bbm_kendaraan.php](pages/log_bbm_kendaraan.php) | Melihat log BBM kendaraan | Akses menu log BBM | Log tampil | Sesuai |
| Riwayat | [pages/riwayat.php](pages/riwayat.php) | Melihat riwayat | Akses menu riwayat | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_kendaraan.php](pages/riwayat_kendaraan.php) | Melihat riwayat kendaraan | Akses menu riwayat kendaraan | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_pemakaian.php](pages/riwayat_pemakaian.php) | Melihat riwayat pemakaian | Akses menu riwayat pemakaian | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_peminjaman.php](pages/riwayat_peminjaman.php) | Melihat riwayat peminjaman | Akses menu riwayat peminjaman | Riwayat tampil | Sesuai |
| Penugasan | [pages/penugasan.php](pages/penugasan.php) | Melihat penugasan | Akses menu penugasan | Daftar penugasan tampil | Sesuai |
| Profil | [pages/profil.php](pages/profil.php) | Melihat profil pengguna | Akses menu profil | Profil tampil | Sesuai |

### Role User

| Modul | Laman | Skenario Pengujian | Test Case | Hasil yang Diharapkan | Hasil Pengujian |
| --- | --- | --- | --- | --- | --- |
| Auth | [login.php](login.php) | Login sebagai user | Email user; Password user | Bisa login dan diarahkan ke halaman utama | Sesuai |
| Auth | [logout.php](logout.php) | Logout dari aplikasi | Klik tombol logout | Sesi berakhir dan diarahkan ke halaman login | Sesuai |
| Auth | [pages/403.php](pages/403.php) | Akses halaman tanpa hak | Akses URL tanpa role yang sesuai | Halaman 403 tampil | Sesuai |
| Dashboard | [index.php](index.php) | Membuka halaman utama | Login lalu akses beranda | Dashboard sesuai role tampil | Sesuai |
| Dashboard | [pages/dashboard_user.php](pages/dashboard_user.php) | Membuka dashboard user | Akses menu dashboard user | Halaman tampil sesuai role | Sesuai |
| Peminjaman | [pages/form_peminjaman.php](pages/form_peminjaman.php) | Membuat peminjaman kendaraan | Isi form peminjaman dengan data valid | Peminjaman tersimpan | Sesuai |
| Peminjaman | [pages/pinjam_pakai.php](pages/pinjam_pakai.php) | Melihat pinjam pakai | Akses menu pinjam pakai | Data pinjam pakai tampil | Sesuai |
| Kendaraan | [pages/kendaraan_publik.php](pages/kendaraan_publik.php) | Melihat daftar kendaraan publik | Akses menu kendaraan publik | Daftar publik tampil | Sesuai |
| Kendaraan | [pages/kendaraan_detail_publik.php](pages/kendaraan_detail_publik.php) | Melihat detail kendaraan publik | Pilih salah satu kendaraan | Detail tampil | Sesuai |
| Kendaraan | [pages/kendaraan_detail_user.php](pages/kendaraan_detail_user.php) | Melihat detail kendaraan user | Pilih salah satu kendaraan | Detail tampil | Sesuai |
| Kendaraan | [pages/kendaraan_saya.php](pages/kendaraan_saya.php) | Melihat kendaraan saya | Akses menu kendaraan saya | Daftar kendaraan terkait tampil | Sesuai |
| Surat Tugas | [pages/surat_tugas_user.php](pages/surat_tugas_user.php) | Melihat surat tugas | Akses menu surat tugas | Daftar surat tugas tampil | Sesuai |
| Penugasan | [pages/penugasan.php](pages/penugasan.php) | Melihat penugasan | Akses menu penugasan | Daftar penugasan tampil | Sesuai |
| Peta | [pages/map_kendaraan.php](pages/map_kendaraan.php) | Melihat peta kendaraan | Akses menu peta kendaraan | Peta dan marker tampil | Sesuai |
| Riwayat | [pages/riwayat.php](pages/riwayat.php) | Melihat riwayat | Akses menu riwayat | Riwayat tampil | Sesuai |
| Riwayat | [pages/riwayat_peminjaman.php](pages/riwayat_peminjaman.php) | Melihat riwayat peminjaman | Akses menu riwayat peminjaman | Riwayat tampil | Sesuai |
| Profil | [pages/profil.php](pages/profil.php) | Melihat profil pengguna | Akses menu profil | Profil tampil | Sesuai |

## Prasyarat

- Aplikasi berjalan di XAMPP: `http://localhost/randis/` (sesuaikan `BASE_URL`).
- Pastikan environment `MAIL_*` telah dikonfigurasi (lihat `scripts/setup_mail_env.bat` atau `docs/mail_setup.md`).
- User dengan role `admin` dan user biasa/driver tersedia di tabel `pengguna`/`user_account`.
- Composer dependencies sudah terinstall (PhpSpreadsheet, TCPDF) — ada di `vendor/`.

## Lokasi file penting

- Halaman monitoring: [pages/monitoring_peminjaman.php](pages/monitoring_peminjaman.php)
- Endpoint notifikasi: [ajax/check_surat_approved.php](ajax/check_surat_approved.php)
- Queue & sender scripts: `scripts/queue_surat_tugas_email_reminders.php`, `scripts/send_scheduled_emails.php`

## Cara menjalankan (perintah cepat)

Jalankan skrip queue dan sender manual di lingkungan PHP CLI (Windows example):

```powershell
php C:\xampp\htdocs\mawang\scripts\queue_surat_tugas_email_reminders.php
php C:\xampp\htdocs\mawang\scripts\send_scheduled_emails.php
```

Atau gunakan Task Scheduler / cron sesuai `docs/mail_setup.md`.

---

## Skenario 1 — Ekspor XLSX

- Tujuan: Pastikan pilihan `Export Excel` menghasilkan file `.xlsx` yang dapat dibuka di Excel.
- Langkah:
  1. Buka `http://localhost/randis/?page=monitoring_peminjaman` sebagai admin.
  2. Klik tombol `Export Excel`.
  3. Simpan file yang diunduh dan buka dengan Excel.
- Data uji: Pastikan ada beberapa baris di tabel `peminjaman_kendaraan`.
- Ekspektasi: File `.xlsx` terbuka, kolom sesuai header, isi baris cocok dengan data di database.

## Skenario 2 — Ekspor CSV (fallback)

- Tujuan: Jika `PhpSpreadsheet` tidak tersedia, sistem harus mengunduh CSV yang terbuka di Excel.
- Langkah:
  1. Akses URL: `http://localhost/randis/?page=monitoring_peminjaman&export=csv`.
  2. Simpan file dan buka dengan editor teks / Excel.
- Ekspektasi: CSV menggunakan UTF-8 BOM, kolom header ada, data lengkap.

## Skenario 3 — Ekspor PDF

- Tujuan: Pastikan tombol `Export PDF` menghasilkan PDF (menggunakan TCPDF jika tersedia).
- Langkah:
  1. Klik tombol `Export PDF` di halaman monitoring.
  2. Simpan dan buka PDF.
- Ekspektasi: PDF dihasilkan, tabel terlihat, tidak ada HTML/stack trace dalam output.

## Skenario 4 — Queue H-1 reminder & pengiriman email

- Tujuan: Pastikan `queue_surat_tugas_email_reminders.php` memasukkan job H-1 ke `email_reminder_jobs` dan `send_scheduled_emails.php` mengirim email.
- Langkah:
  1. Buat record `surat_tugas` dengan `tanggal_berangkat = DATE_ADD(CURDATE(), INTERVAL 1 DAY)` dan `status` bukan `selesai`/`dibatalkan`.
     Contoh SQL (sesuaikan kolom minimal):
     ```sql
     INSERT INTO surat_tugas (nomor_surat, tanggal_berangkat, tanggal_kembali, kendaraan_id, pengguna_id, tujuan, keperluan, status, created_at)
     VALUES ('TEST-REM-1', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, 2, 'Tujuan Test', 'Keperluan test', 'Pending', NOW());
     ```
  2. Jalankan:
     ```powershell
     php C:\xampp\htdocs\mawang\scripts\queue_surat_tugas_email_reminders.php
     php C:\xampp\htdocs\mawang\scripts\send_scheduled_emails.php
     ```
  3. Periksa tabel `email_reminder_jobs` untuk status `sent` dan cek `logs/` (jika ada) atau inbox penerima.
- Ekspektasi: Job tercatat, `send_scheduled_emails.php` memanggil `app_send_email()` tanpa error dan `email_reminder_jobs.status` berubah menjadi `sent`.

## Skenario 5 — Popup notifikasi persetujuan (user/driver)

- Tujuan: Notifikasi popup muncul ketika `surat_tugas` yang relevan disetujui.
- Langkah:
  1. Login sebagai target user/driver di satu browser/tab dan buka halaman manapun yang memuat `includes/footer.php` (mis. dashboard).
  2. Di jendela admin (berbeda), set `surat_tugas` status menjadi `Disetujui` (melalui UI persetujuan atau SQL):
     ```sql
     UPDATE surat_tugas SET status = 'Disetujui', updated_at = NOW() WHERE id = <ID_TEST>;
     ```
  3. Kembali ke tab user; tunggu maksimal 15 detik (polling tiap ~12s).
- Ekspektasi: SweetAlert popup muncul dengan informasi surat tugas.

## Skenario 6 — Endpoint `ajax/check_surat_approved.php`

- Tujuan: Validasi response JSON untuk panggilan AJAX.
- Langkah:
  1. Saat login sebagai user, catat `last_check = new Date().toISOString()` di devtools console.
  2. Panggil endpoint langsung (bila perlu, sertakan cookie sesi):
     ```bash
     curl -b "<cookiejar>" "http://localhost/randis/ajax/check_surat_approved.php?last_check=<ISO_TIMESTAMP>"
     ```
- Ekspektasi: Response JSON berisi `items` (array), setiap item memiliki `id`, `nomor_surat`, `tanggal_berangkat`, `tujuan`, `keperluan`, `changed_at`.

## Pemeriksaan log dan debugging

- Periksa `logs/` untuk jejak pengiriman email atau error.
- Jika download file korup, periksa output buffering: pastikan tidak ada whitespace/echo sebelum header. (Kami membersihkan buffer sebelum streaming file.)

## Kriteria Kelulusan

- Semua skenario di atas berjalan tanpa error PHP yang mengotori output (tidak ada HTML/stack trace dalam file unduhan atau JSON).
- Email reminders tercatat dan dikirim (atau dikembalikan dengan error yang ter-log).
- Popup notifikasi muncul untuk pengguna yang relevan dalam batas waktu polling.

---

Jika Anda ingin, saya bisa juga:

- Menambahkan skrip `tests/manual_run.sh` atau `.ps1` untuk menjalankan semua langkah queue/send secara otomatis, atau
- Menambahkan contoh dataset SQL untuk mengisi data uji secara otomatis.
