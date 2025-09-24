<?php
// Email configuration - UPDATE THESE
$fromEmail = 'hahich4@gmail.com'; // Email address that will be in the from field of the message
$fromName  = 'Trần Hùng';        // Name that will be in the from field of the message
$sendToEmail = 'kurorumi0x@gmail.com'; // Recipient email address (your Gmail)
$sendToName  = 'Hùng Trần';        // Recipient name

// Database configuration - point to your MySQL server (not XAMPP)
$dbHost = '127.0.0.1';   // hostname or IP of MySQL server
$dbPort = 3306;          // MySQL port
$dbUser = 'root';        // MySQL username
$dbPass = 'hunglaanh00';            // MySQL password
$dbName = 'contact_forms_db'; // Database name
$dbSocket = '';          // Optional Unix socket path (leave empty on Windows)

// SMTP settings (use this format)
$smtpUse = true;            // Set to true to enable SMTP authentication
$smtpHost = 'smtp.gmail.com';             // Enter SMTP host ie. smtp.gmail.com
$smtpUsername = 'kurorumi0x@gmail.com';   // SMTP username ie. gmail address
$smtpPassword = 'hduf zlxk dzra pyvh';      // Gmail App Password (16 chars), NOT your normal password
$smtpSecure = 'tls';        // Enable TLS or SSL encryption: 'tls' or 'ssl'
$smtpAutoTLS = true;        // Enable Auto TLS (STARTTLS)
$smtpPort = 587;            // TCP port to connect to: 587 for TLS, 465 for SSL

// Optional: force From email to match authenticated account (recommended for Gmail)
$smtpForceFrom = 'kurorumi0x@gmail.com';

// Align From with Gmail account for better deliverability
$fromEmail = 'kurorumi0x@gmail.com';
$fromName  = 'Kurorumi';
?>