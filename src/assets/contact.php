<?php
// Basic secure contact handler: validates inputs, saves to MySQL, sends email, then redirects

// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Helper for redirect back to index with status
	function redirectWithStatus(string $status): void {
	$location = '../../index.php?status=' . urlencode($status);
	header('Location: ' . $location);
	exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(false, 'Invalid method');
}

// Collect and sanitize inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$storeUrl = trim($_POST['store_url'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
$errors = [];
if ($name === '') { $errors[] = 'Name is required'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required'; }
if ($storeUrl === '' || !filter_var($storeUrl, FILTER_VALIDATE_URL)) { $errors[] = 'Valid store URL is required'; }

if (!empty($errors)) {
    respond_json(false, implode('; ', $errors));
}

// Save to database
try {
	$conn = getDbConnection();
	$stmt = $conn->prepare('INSERT INTO contact_submissions (name, email, store_url, notes) VALUES (?, ?, ?, ?)');
	if (!$stmt) { throw new Exception('Prepare failed: ' . $conn->error); }
	$stmt->bind_param('ssss', $name, $email, $storeUrl, $notes);
	if (!$stmt->execute()) { throw new Exception('Execute failed: ' . $stmt->error); }
	$stmt->close();
	$conn->close();
} catch (Throwable $e) {
    respond_json(false, 'Database error');
}

// Send email
$subject = 'New Contact Submission';
$message = "You have a new submission:\n\n" .
	"Name: {$name}\n" .
	"Email: {$email}\n" .
	"Store URL: {$storeUrl}\n\n" .
	"Notes:\n{$notes}\n";

// Build common headers
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/plain; charset=utf-8';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers[] = 'X-Mailer: PHP/' . phpversion();

// Optional: force From to authenticated account if configured
$effectiveFromEmail = isset($smtpForceFrom) && $smtpForceFrom ? $smtpForceFrom : $fromEmail;
$headers[] = 'From: ' . $fromName . ' <' . $effectiveFromEmail . '>';

// If SMTP is enabled, send via SMTP; otherwise use mail()
$sendOk = false;
if (isset($smtpUse) && $smtpUse === true) {
	$sendOk = smtp_send(
		$smtpHost ?? 'smtp.gmail.com',
		(int)($smtpPort ?? 587),
		$smtpSecure ?? 'tls',
		$smtpUsername ?? '',
		$smtpPassword ?? '',
		$effectiveFromEmail,
		$fromName,
		$sendToEmail,
		$sendToName,
		$subject,
		implode("\r\n", $headers) . "\r\n\r\n" . $message,
		isset($smtpAutoTLS) ? (bool)$smtpAutoTLS : false
	);
} else {
	$sendOk = mail($sendToEmail, $subject, $message, implode("\r\n", $headers));
}

if (!$sendOk) {
    $error = error_get_last();
    if ($error) { error_log('Mail send failed: ' . ($error['message'] ?? 'unknown error')); }
    respond_json(false, 'Email send failed');
}

respond_json(true, 'OK');



/**
 * Minimal SMTP client for authenticated sending.
 * Supports SSL (port 465) and STARTTLS (port 587).
 */
function smtp_send(
	string $host,
	int $port,
	string $secure, // 'ssl' or 'tls'
	string $username,
	string $password,
	string $fromEmail,
	string $fromName,
	string $toEmail,
	string $toName,
	string $subject,
	string $rawBody,
	bool $autoTls = false
): bool {
	$timeout = 30;
	$transport = ($secure === 'ssl') ? 'ssl://' . $host : $host;
	$context = stream_context_create([
		'ssl' => [
			'verify_peer' => true,
			'verify_peer_name' => true,
			'allow_self_signed' => false
		]
	]);
	$fp = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
	if (!$fp) { error_log("SMTP connect failed: $errstr ($errno) host=$host port=$port secure=$secure"); return false; }
	stream_set_timeout($fp, $timeout);

	$read = function() use ($fp): string {
		$resp = '';
		while (!feof($fp)) {
			$line = fgets($fp, 515);
			$resp .= $line;
			if (isset($line[3]) && $line[3] === ' ') break; // end of response
		}
		return $resp;
	};

	$write = function(string $cmd) use ($fp) {
		fwrite($fp, $cmd . "\r\n");
	};

	$expect = function(string $step, string $resp, array $okCodes) use ($host, $port, $secure): bool {
		$code = (int)substr($resp, 0, 3);
		$ok = in_array($code, $okCodes, true);
		if (!$ok) {
			error_log("SMTP step '$step' failed (code=$code) host=$host port=$port secure=$secure; resp=" . trim($resp));
		}
		return $ok;
	};

	if (!$expect('greeting', $read(), [220])) { fclose($fp); return false; }
	$write('EHLO localhost');
	if (!$expect('ehlo', $read(), [250])) { fclose($fp); return false; }

	if ($secure === 'tls') {
		$write('STARTTLS');
		if (!$expect('starttls', $read(), [220])) { fclose($fp); return false; }
		if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
		$write('EHLO localhost');
		if (!$expect('ehlo-tls', $read(), [250])) { fclose($fp); return false; }
	}

	if ($username !== '' && $password !== '') {
		$write('AUTH LOGIN');
		if (!$expect('auth-login', $read(), [334])) { fclose($fp); return false; }
		$write(base64_encode($username));
		if (!$expect('auth-username', $read(), [334])) { fclose($fp); return false; }
		$write(base64_encode($password));
		if (!$expect('auth-password', $read(), [235])) { fclose($fp); return false; }
	}

	$write('MAIL FROM: <' . $fromEmail . '>');
	if (!$expect('mail-from', $read(), [250])) { fclose($fp); return false; }
	$write('RCPT TO: <' . $toEmail . '>');
	if (!$expect('rcpt-to', $read(), [250, 251])) { fclose($fp); return false; }
	$write('DATA');
	if (!$expect('data-cmd', $read(), [354])) { fclose($fp); return false; }

	// Build message with headers; ensure Subject and To headers present
	$headers = [];
	$headers[] = 'To: ' . sprintf('%s <%s>', mb_encode_mimeheader($toName, 'UTF-8'), $toEmail);
	$headers[] = 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8');
	$headers[] = 'Date: ' . date('r');
	$headers[] = 'Message-ID: <' . uniqid('', true) . '@localhost>';

	$data = implode("\r\n", $headers) . "\r\n" . $rawBody;

	// Escape lines starting with a dot per SMTP (. -> ..)
	$data = preg_replace('/\r?\n\./', "\r\n..", $data);

	$write($data . "\r\n.");
	if (!$expect('data-send', $read(), [250])) { fclose($fp); return false; }
	$write('QUIT');
	$read();
	fclose($fp);
	return true;
}

function respond_json(bool $ok, string $message='') : void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($ok ? 200 : 400);
    echo json_encode(['ok'=>$ok, 'message'=>$message]);
    exit;
}
