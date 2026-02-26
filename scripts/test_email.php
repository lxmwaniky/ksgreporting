<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

use KSG\Mailer;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

echo "\nEmail Configuration Test\n\n";

$enabled = filter_var($_ENV['MAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$enabled) {
    echo "Email is disabled in configuration\n";
    echo "Set MAIL_ENABLED=true to enable emails\n\n";
    exit(1);
}

echo "Configuration:\n";
echo "Host:       " . ($_ENV['MAIL_HOST'] ?? 'not set') . "\n";
echo "Port:       " . ($_ENV['MAIL_PORT'] ?? 'not set') . "\n";
echo "Username:   " . ($_ENV['MAIL_USERNAME'] ?? 'not set') . "\n";
echo "Encryption: " . ($_ENV['MAIL_ENCRYPTION'] ?? 'not set') . "\n";
echo "From:       " . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'not set') . "\n\n";

echo "Testing SMTP connection...\n";

try {
    $mailer = new Mailer();
    
    if ($mailer->testConnection()) {
        echo "SMTP connection successful\n\n";
    } else {
        echo "SMTP connection failed\n";
        echo "Check your SMTP settings\n\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "Send test email? (yes/no): ";
$sendTest = trim(fgets(STDIN));

if (strtolower($sendTest) === 'yes') {
    echo "Recipient email: ";
    $testEmail = trim(fgets(STDIN));
    
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email address\n";
        exit(1);
    }
    
    echo "\nSending test email...\n";
    
    try {
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port       = (int)$_ENV['MAIL_PORT'];
        
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($testEmail, 'Test Recipient');
        
        $mail->isHTML(true);
        $mail->Subject = 'KSG Reports System - Test Email';
        $mail->Body    = '<h2>Test Email</h2><p>This is a test email from the KSG Weekly Reports System.</p><p>If you received this, your email configuration is working correctly.</p>';
        $mail->AltBody = 'This is a test email from the KSG Weekly Reports System. If you received this, your email configuration is working correctly.';
        
        $mail->send();
        echo "Test email sent successfully to $testEmail\n\n";
        
    } catch (Exception $e) {
        echo "Failed to send test email: {$mail->ErrorInfo}\n\n";
        exit(1);
    }
}

echo "Email configuration test complete\n\n";
exit(0);