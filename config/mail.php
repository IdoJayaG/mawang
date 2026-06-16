<?php
// ============================================================
// SMTP CREDENTIALS — edit this file to configure email.
// For Gmail: enable 2FA → generate App Password at
//   https://myaccount.google.com/apppasswords
// Do NOT commit real credentials to version control.
// ============================================================

return [
    'transport'    => 'smtp',
    'host'         => 'smtp.gmail.com',
    'port'         => 587,
    'encryption'   => 'tls',               // tls | ssl | none
    'username'     => 'zxtynmqwopvb56@gmail.com',
    'password'     => 'wysxeeijdibhnsmv',  // Gmail App Password (16 chars, no spaces)
    'from_address' => 'zxtynmqwopvb56@gmail.com',
    'from_name'    => 'Sistem Randis',
];
