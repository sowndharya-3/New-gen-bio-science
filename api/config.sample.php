<?php
// Copy to config.php (or, better, to ../newgen-config.php ABOVE the web root)
// and fill in real values. Never commit or ship the real file.
return [
    'driver'     => 'mysql',            // 'mysql' in production
    'db_host'    => 'localhost',
    'db_name'    => 'CHANGE_ME',
    'db_user'    => 'CHANGE_ME',
    'db_pass'    => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',
    // Admin staff accounts live in the admin_users table (see tools/create-admin.php).

    // Online payments (Razorpay). Leave these out or as CHANGE_ME and the checkout offers
    // Cash on Delivery only. Keys exist ONLY in this server-side file.
    'razorpay_key_id'         => 'CHANGE_ME',   // e.g. rzp_live_xxxxxxxxxxxxxx (use rzp_test_... for testing)
    'razorpay_key_secret'     => 'CHANGE_ME',
    'razorpay_webhook_secret' => 'CHANGE_ME',   // the secret you set for the webhook in the Razorpay dashboard
];
