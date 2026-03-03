<?php
require __DIR__ . '/../../vendor/autoload.php';

use Rongie\QuickHire\Core\Session;
use Rongie\QuickHire\Services\AuthService;
use Rongie\QuickHire\Core\Database;

Session::start();
$config = require __DIR__ . '/../../config/config.php';
$db = new Database($config['db']);
$auth = new AuthService($db->pdo());

$auth->logout();
header('Location: /QuickHire/public/index.php');
exit;