# Setup Pengingat Email Terjadwal

Dokumen ini menjelaskan cara mengaktifkan pengingat email otomatis dari sistem Randis.

## 1) Jalankan migration tabel queue

```powershell
php scripts/run_migrations.php migrations/2026-04-23_create_email_reminder_jobs.sql
```

## 2) Konfigurasi email

Aplikasi membaca konfigurasi dari environment variable berikut (lihat `config.php`):

- `MAIL_TRANSPORT` : `smtp` atau `mail`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION` : `tls`, `ssl`, atau `none`

Catatan:
- Jika `MAIL_TRANSPORT=smtp`, gunakan PHPMailer.
- Install dependency:

```powershell
composer require phpmailer/phpmailer
```

## 3) Queue reminder otomatis (H-1 jadwal perawatan)

Script berikut membuat email reminder untuk jadwal perawatan besok, untuk role: `admin`, `pimpinan`, `driver`.

```powershell
php scripts/queue_maintenance_email_reminders.php
```

## 4) Kirim email yang sudah jatuh tempo

```powershell
php scripts/send_scheduled_emails.php
```

Opsional, batasi jumlah kirim per eksekusi:

```powershell
php scripts/send_scheduled_emails.php --limit=100
```

## 5) Jadwalkan otomatis (Windows Task Scheduler)

Disarankan membuat 2 task:

1. Queue task (misalnya setiap hari jam 06:00)

```powershell
php C:\xampp\htdocs\mawang\scripts\queue_maintenance_email_reminders.php
```

2. Sender task (misalnya setiap 5 menit)

```powershell
php C:\xampp\htdocs\mawang\scripts\send_scheduled_emails.php --limit=100
```

## Status email

Data disimpan di tabel `email_reminder_jobs` dengan status:

- `pending`: menunggu waktu kirim
- `sent`: terkirim
- `failed`: gagal setelah melewati `max_attempts`
- `cancelled`: dibatalkan manual
