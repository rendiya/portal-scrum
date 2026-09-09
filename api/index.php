<?php
// api/index.php - Vercel Serverless Function Router for PHP

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// Mapping routes to PHP files in the root directory
$routes = [
    ''            => 'index.php',
    'index'       => 'index.php',
    'index.php'   => 'index.php',
    'login'       => 'login.php',
    'login.php'   => 'login.php',
    'logout'      => 'logout.php',
    'logout.php'  => 'logout.php',
    'board'       => 'board.php',
    'board.php'   => 'board.php',
    'prd'         => 'prd.php',
    'prd.php'     => 'prd.php',
    'logbook'     => 'logbook.php',
    'logbook.php' => 'logbook.php',
    'lms'         => 'lms.php',
    'lms.php'     => 'lms.php',
    'reports'     => 'reports.php',
    'reports.php' => 'reports.php',
    'admin'       => 'admin.php',
    'admin.php'   => 'admin.php',
    'invite'      => 'invite.php',
    'invite.php'  => 'invite.php',
    'api'         => 'api.php',
    'api.php'     => 'api.php',
];

$file = $routes[$path] ?? null;

if (!$file) {
    $potentialFile = __DIR__ . '/../' . $path;
    if (file_exists($potentialFile) && pathinfo($potentialFile, PATHINFO_EXTENSION) === 'php') {
        $file = $path;
    } else {
        $file = 'index.php';
    }
}

chdir(__DIR__ . '/..');
require __DIR__ . '/../' . $file;
