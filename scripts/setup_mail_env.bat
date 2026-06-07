@echo off
REM Setup MAIL_* environment variables for current user (Windows)
REM Run this from an elevated command prompt if needed.

echo Setting MAIL environment variables for current user...

REM Replace the values below with your SMTP credentials. Do NOT commit these values to source control.
setx MAIL_TRANSPORT "smtp"
setx MAIL_HOST "smtp.gmail.com"
setx MAIL_PORT "587"
setx MAIL_USERNAME "zxtynmqwopvb56@gmail.com"
setx MAIL_PASSWORD "wysxeeijdibhnsmv"
setx MAIL_ENCRYPTION "tls"
setx MAIL_FROM_ADDRESS "zxtynmqwopvb56@gmail.com"
setx MAIL_FROM_NAME "Sistem Randis"

echo Mail environment variables set. You may need to restart Apache / your shell session for changes to take effect.
echo Remember: do NOT commit credentials into version control.

pause
