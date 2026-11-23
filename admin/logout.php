<?php
require __DIR__ . '/../app/bootstrap.php';
if (is_post()) {
    csrf_check();
    if ($a = current_admin()) audit('admin', (int) $a['id'], 'logout');
    logout_admin();
    flash('success', 'You are logged out.');
}
redirect('/admin/login');
