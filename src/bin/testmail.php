<?php
/**
 * Diagnostic helper for the WiRoc monitor SMTP setup.
 *
 * Sends one test email using exactly the same settings as
 * PasswordRecovery / DeleteAccount, but with the full SMTP conversation
 * printed, so you can see what the mail server actually answers.
 *
 * Usage:
 *   php bin/testmail.php you@example.com
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../public_html/PHPMailer/PHPMailer-master/src/Exception.php';
require __DIR__ . '/../public_html/PHPMailer/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../public_html/PHPMailer/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$to = $argv[1] ?? null;
if (!$to) {
    fwrite(STDERR, "Usage: php bin/testmail.php you@example.com\n");
    exit(2);
}

$ini = parse_ini_file(__DIR__ . '/../config/config.ini', true);
if (!isset($ini['smtp'])) {
    fwrite(STDERR, "No [smtp] section found in config/config.ini\n");
    exit(2);
}

echo "SMTP host: mailcluster.loopia.se:587 (tls)\n";
echo "SMTP user: {$ini['smtp']['username']}\n";
echo "From:      {$ini['smtp']['from']}\n";
echo "To:        {$to}\n\n";

$mail = new PHPMailer(true); // exceptions on, so we see why it fails
$mail->isSMTP();
// Prefer IPv4: Loopia publishes AAAA records for the mail host, and PHP would
// otherwise try IPv6 first and fail on hosts without a working IPv6 route.
$ipv4 = gethostbynamel('mailcluster.loopia.se');
$mail->Host = (is_array($ipv4) && count($ipv4) > 0)
    ? implode(';', $ipv4)
    : 'mailcluster.loopia.se';
if (is_array($ipv4) && count($ipv4) > 0) {
    $mail->SMTPOptions = ['ssl' => ['peer_name' => 'mailcluster.loopia.se']];
}
$mail->SMTPAuth = true;
$mail->Username = $ini['smtp']['username'];
$mail->Password = $ini['smtp']['password'];
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
$mail->Timeout = 15;
$mail->CharSet = 'UTF-8';

// Print the whole conversation with the mail server.
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
$mail->Debugoutput = 'echo';

$mail->From = $ini['smtp']['from'];
$mail->FromName = 'WiRoc Monitor';
$mail->addAddress($to);
$mail->addReplyTo($ini['smtp']['from'], 'WiRoc Monitor');
$mail->Subject = 'WiRoc monitor SMTP test';
$mail->Body = 'Test message from the WiRoc monitor SMTP test script.';
$mail->AltBody = 'Test message from the WiRoc monitor SMTP test script.';

try {
    $mail->send();
    echo "\n>>> RESULT: the mail server ACCEPTED the message (send() returned true).\n";
    echo ">>> If it does not arrive, the problem is downstream of this server:\n";
    echo ">>> look for the queue id in the server reply above, and check bounces sent to {$ini['smtp']['from']}.\n";
} catch (\Throwable $e) {
    echo "\n>>> RESULT: send() FAILED: " . $e->getMessage() . "\n";
}
