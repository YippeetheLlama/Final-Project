<?php
require_once __DIR__ . "/security.php";

block_current_ip("admin-dashboard honeypot");
http_response_code(200);
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #111;
        }

        iframe {
            width: min(960px, 92vw);
            aspect-ratio: 16 / 9;
            border: 0;
        }
    </style>
</head>
<body>
    <iframe
        src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1"
        title="Admin Dashboard"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen>
    </iframe>
</body>
</html>
