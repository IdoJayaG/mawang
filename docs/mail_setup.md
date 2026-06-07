# SMTP Setup (local Windows / XAMPP)

1) Set environment variables (recommended, do NOT commit credentials)

- Use the provided `scripts/setup_mail_env.bat` to set the `MAIL_*` variables for your user. Edit the file to change credentials before running.
- Or set Windows user environment variables via System Properties -> Advanced -> Environment Variables.

Variables used by the app (read from `config.php`):

- `MAIL_TRANSPORT` = `smtp` or `mail`
- `MAIL_HOST` = e.g. `smtp.gmail.com`
- `MAIL_PORT` = e.g. `587`
- `MAIL_USERNAME` = SMTP username (your email)
- `MAIL_PASSWORD` = SMTP password / app password
- `MAIL_ENCRYPTION` = `tls` or `ssl` or `none`
- `MAIL_FROM_ADDRESS` = from address
- `MAIL_FROM_NAME` = display name

2) Gmail special note

If you use Gmail, create an App Password (recommended) and use that instead of your account password. Do NOT enable "Less secure apps".

3) Scheduling reminders

- Queue scripts:
  - `scripts/queue_surat_tugas_email_reminders.php` — scans `surat_tugas` for H-1 and Hari H reminders.
  - `scripts/queue_maintenance_email_reminders.php` — scans maintenance schedules for H-1 and Hari H reminders.

- Sender script:
  - `scripts/send_scheduled_emails.php` — sends pending `email_reminder_jobs` using `app_send_email()`.

4) Windows Task Scheduler (example)

- Create three tasks:
  1. Every 1 hour run `php c:\xampp\htdocs\mawang\scripts\queue_surat_tugas_email_reminders.php`
  2. Every 1 hour run `php c:\xampp\htdocs\mawang\scripts\queue_maintenance_email_reminders.php`
  3. Every 5 minutes run `php c:\xampp\htdocs\mawang\scripts\send_scheduled_emails.php`

5) Security

- Never commit credentials to git. Keep them in environment variables or a secure secrets store.
