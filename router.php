<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requested = __DIR__ . $path;

// Redirect .php to non-.php
if (preg_match('/\.php$/', $path)) {
    header("Location: $path", true, 301);
    exit;
}

// Serve PHP files without extension
if (file_exists($requested . '.php')) {
    require $requested . '.php';
} 
// Serve static files (CSS/JS/images)
elseif (file_exists($requested)) {
    return false;
}
// 404 handling
else {
    http_response_code(404);
    echo "404 - Page not found";
}
?>