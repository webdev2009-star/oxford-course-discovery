<?php
// Check if WordPress is installed
if (file_exists('/var/www/html/wp-config.php') && 
    file_exists('/var/www/html/wp-includes/version.php')) {
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(503);
    echo 'Installing...';
}
