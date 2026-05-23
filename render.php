<?php
// Configure session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Security headers with strict CSP
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; form-action 'none'; base-uri 'none'; frame-ancestors 'self'");

// Verify the token matches and exists
if (! isset($_GET['token']) || 
    ! isset($_SESSION['render_token']) || 
    $_GET['token'] !== $_SESSION['render_token'] ||
    ! isset($_GET['output'])) {
    http_response_code(403);
    die('Access denied');
}

// Clear the token to prevent reuse
unset($_SESSION['render_token']);

session_write_close();

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            padding: 14px 16px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            color: #1a2230;
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }
    </style>
</head>
<body>
<?php 
// Safe to render unescaped because:
// 1. CSP blocks scripts and dangerous content
// 2. Token system prevents unauthorized access
// 3. Frame sandbox adds additional protection
echo base64_decode($_GET['output']); 
?>
</body>
</html>
