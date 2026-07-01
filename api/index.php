<?php
/**
 * Vercel Serverless PHP Router
 * Crown Basketball Academy
 */

// Get the requested URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Clean the path
$path = ltrim($path, '/');

// If root is requested, load index.php
if (empty($path)) {
    $path = 'index.php';
}

// Security check: prevent directory traversal
if (strpos($path, '..') !== false) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// Target file in the parent folder (project root)
$file = dirname(__DIR__) . '/' . $path;

// Append .php if the exact file doesn't exist but the .php version does
if (!file_exists($file) && file_exists($file . '.php')) {
    $file .= '.php';
    $path .= '.php';
}

// If it's a valid PHP file in the root
if (file_exists($file) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    // Set the current working directory to project root so relative requires resolve correctly
    chdir(dirname(__DIR__));
    
    // Override server variables to mimic direct execution
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = '/' . $path;
    $_SERVER['PHP_SELF'] = '/' . $path;
    
    // Execute the file
    require $file;
    exit;
}

// If the file is not found, return 404
http_response_code(404);
echo "404 - Halaman tidak ditemukan.";
exit;
