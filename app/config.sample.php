<?php
/*
 * Copy this file to  app/config.php  and fill in the values.
 * app/config.php holds passwords: it is git-ignored and blocked from the web.
 */
return [
    // Full site address, no trailing slash. Used in emails (verify / reset links).
    'site_url' => 'https://jaylesoulcatering.com',

    // 'production' hides PHP errors from visitors. Use 'local' on your PC only.
    'env' => 'production',

    // Hostinger → hPanel → Databases → MySQL Databases
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u000000000_jaylesoul',
        'user' => 'u000000000_jaylesoul',
        'pass' => 'CHANGE-ME',
    ],

    'mail' => [
        // smtp (recommended) | mail (PHP mail()) | log (writes to storage/logs/mail.log — for local testing)
        'driver'     => 'smtp',
        'from_email' => 'no-reply@jaylesoulcatering.com',
        'from_name'  => 'Chef Jay LeSoul Catering',
        // Gets an email whenever a new testimonial is waiting for approval ('' = off)
        'admin_notify' => 'chef@jaylesoulcatering.com',
        // Hostinger → hPanel → Emails: create no-reply@jaylesoulcatering.com
        'smtp_host' => 'smtp.hostinger.com',
        'smtp_port' => 465,              // 465 = SSL, 587 = STARTTLS
        'smtp_user' => 'no-reply@jaylesoulcatering.com',
        'smtp_pass' => 'CHANGE-ME',
    ],

    // Free bot protection: https://dash.cloudflare.com → Turnstile → Add site.
    // Leave both empty to switch it off (only do that on your PC).
    'turnstile' => [
        'site_key'   => '',
        'secret_key' => '',
    ],

    // Set true ONLY if the site sits behind Cloudflare's proxy (orange cloud).
    'trust_cloudflare_ip' => false,

    // Admins must set up an authenticator app (Google Authenticator, Authy…) before using the panel.
    'admin_require_2fa' => true,

    // One-time key for /install. Put a long random string here, run the installer,
    // then set it back to '' (the installer also locks itself after the first run).
    'install_key' => '',

    // Upload limits for customer testimonials (admins get the larger video limit)
    'uploads' => [
        'max_photos'           => 3,
        'photo_max_mb'         => 5,
        'video_max_mb'         => 20,
        'admin_video_max_mb'   => 60,
    ],
];
