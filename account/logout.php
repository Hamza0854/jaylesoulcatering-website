<?php
require __DIR__ . '/../app/bootstrap.php';
if (is_post()) {
    csrf_check();
    logout_user();
    flash('success', 'You are logged out.');
}
redirect('/account/login');
