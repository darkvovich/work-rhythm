<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

do_logout();
header('Location: ' . BASE_URL . '/login');
exit;
