<?php
require_once __DIR__ . '/config.php';

function getDbConnection(): mysqli {
	$host = $GLOBALS['dbHost'] ?? '127.0.0.1';
	$port = (int)($GLOBALS['dbPort'] ?? 3306);
	$user = $GLOBALS['dbUser'] ?? 'root';
	$pass = $GLOBALS['dbPass'] ?? '';
	$name = $GLOBALS['dbName'] ?? '';
	$socket = $GLOBALS['dbSocket'] ?? null;

	$connection = @mysqli_init();
	if ($socket) {
		$ok = @$connection->real_connect($host, $user, $pass, $name, null, $socket);
	} else {
		$ok = @$connection->real_connect($host, $user, $pass, $name, $port);
	}
	if (!$ok || $connection->connect_error) {
		throw new Exception('Database connection failed: ' . ($connection->connect_error ?: 'unknown'));
	}
	if ($connection->connect_error) {
		throw new Exception('Database connection failed: ' . $connection->connect_error);
	}
	$connection->set_charset('utf8mb4');
	return $connection;
}
?>


