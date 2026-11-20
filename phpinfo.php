<?php

echo "<h1>PHP 7 / PHP 8 Environment Test!</h1>";

echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "MySQLi: " . (extension_loaded('mysqli') ? 'Enabled' : 'Disabled') . "\n";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled') . "\n";
echo "</pre>";

phpinfo();

