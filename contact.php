<?php
/**
 * DerSalvador AI - Contact Form Handler
 * Sends form submissions via Brevo SMTP
 */

// ── CORS & Headers ──────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// ── Config (loaded from environment or .env file) ──────────────────────────
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        putenv(trim($line));
    }
}

$SMTP_HOST = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
$SMTP_PORT = (int)(getenv('SMTP_PORT') ?: 587);
$SMTP_USER = getenv('SMTP_USER');
$SMTP_PASS = getenv('SMTP_PASS');
$SMTP_FROM = getenv('SMTP_FROM');
$RECIPIENT = getenv('RECIPIENT');

// ── Only accept POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Read form data ──────────────────────────────────────────────────────────
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$company = trim($_POST['company'] ?? '—');
$team    = trim($_POST['team_size'] ?? '—');

if (empty($name) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name und E-Mail sind Pflichtfelder.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige E-Mail-Adresse.']);
    exit;
}

// ── Build email ─────────────────────────────────────────────────────────────
$date    = date('d.m.Y H:i');
$subject = "Neue Anfrage von {$name} – DerSalvador AI";

$htmlBody = "
<html><body style='font-family:Inter,Arial,sans-serif;color:#1E293B;max-width:600px;margin:0 auto;'>
  <div style='background:linear-gradient(135deg,#111827,#1F2937);padding:32px;border-radius:12px 12px 0 0;'>
    <h1 style='color:#10B981;margin:0;font-size:24px;'>DerSalvador AI</h1>
    <p style='color:rgba(255,255,255,0.7);margin:8px 0 0;font-size:14px;'>Neue Kontaktanfrage</p>
  </div>
  <div style='background:#fff;padding:32px;border:1px solid #E2E8F0;border-top:none;border-radius:0 0 12px 12px;'>
    <table style='width:100%;border-collapse:collapse;'>
      <tr><td style='padding:12px 0;color:#64748B;width:140px;'>Name</td><td style='padding:12px 0;font-weight:600;'>{$name}</td></tr>
      <tr><td style='padding:12px 0;color:#64748B;border-top:1px solid #E2E8F0;'>E-Mail</td><td style='padding:12px 0;font-weight:600;border-top:1px solid #E2E8F0;'><a href='mailto:{$email}' style='color:#10B981;'>{$email}</a></td></tr>
      <tr><td style='padding:12px 0;color:#64748B;border-top:1px solid #E2E8F0;'>Unternehmen</td><td style='padding:12px 0;font-weight:600;border-top:1px solid #E2E8F0;'>{$company}</td></tr>
      <tr><td style='padding:12px 0;color:#64748B;border-top:1px solid #E2E8F0;'>Teamgröße</td><td style='padding:12px 0;font-weight:600;border-top:1px solid #E2E8F0;'>{$team}</td></tr>
      <tr><td style='padding:12px 0;color:#64748B;border-top:1px solid #E2E8F0;'>Eingegangen</td><td style='padding:12px 0;font-weight:600;border-top:1px solid #E2E8F0;'>{$date}</td></tr>
    </table>
  </div>
</body></html>";

$textBody = "Neue Anfrage – DerSalvador AI\n\nName: {$name}\nE-Mail: {$email}\nUnternehmen: {$company}\nTeamgröße: {$team}\nDatum: {$date}";

// ── Send via SMTP ───────────────────────────────────────────────────────────
$boundary = md5(time());

$headers  = "From: DerSalvador AI <{$SMTP_FROM}>\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

$body  = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
$body .= $textBody . "\r\n";
$body .= "--{$boundary}\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$body .= $htmlBody . "\r\n";
$body .= "--{$boundary}--\r\n";

// Use SMTP via fsockopen for shared hosting compatibility
$success = sendSmtp($SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM, $RECIPIENT, $subject, $headers, $body);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Anfrage erfolgreich gesendet!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'E-Mail konnte nicht gesendet werden.']);
}

// ── SMTP sender (no external dependencies) ──────────────────────────────────
function sendSmtp($host, $port, $user, $pass, $from, $to, $subject, $headers, $body) {
    $smtp = @fsockopen("tcp://{$host}", $port, $errno, $errstr, 10);
    if (!$smtp) return false;

    $resp = fgets($smtp, 512);

    fputs($smtp, "EHLO dersalvador.ai\r\n");
    while ($line = fgets($smtp, 512)) {
        if (substr($line, 3, 1) === ' ') break;
    }

    fputs($smtp, "STARTTLS\r\n");
    fgets($smtp, 512);

    stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);

    fputs($smtp, "EHLO dersalvador.ai\r\n");
    while ($line = fgets($smtp, 512)) {
        if (substr($line, 3, 1) === ' ') break;
    }

    fputs($smtp, "AUTH LOGIN\r\n");
    fgets($smtp, 512);
    fputs($smtp, base64_encode($user) . "\r\n");
    fgets($smtp, 512);
    fputs($smtp, base64_encode($pass) . "\r\n");
    $authResp = fgets($smtp, 512);
    if (substr($authResp, 0, 3) !== '235') {
        fclose($smtp);
        return false;
    }

    fputs($smtp, "MAIL FROM:<{$from}>\r\n");
    fgets($smtp, 512);
    fputs($smtp, "RCPT TO:<{$to}>\r\n");
    fgets($smtp, 512);
    fputs($smtp, "DATA\r\n");
    fgets($smtp, 512);

    $message  = "Subject: {$subject}\r\n";
    $message .= "To: <{$to}>\r\n";
    $message .= $headers;
    $message .= "\r\n" . $body;

    fputs($smtp, $message . "\r\n.\r\n");
    $dataResp = fgets($smtp, 512);

    fputs($smtp, "QUIT\r\n");
    fclose($smtp);

    return (substr($dataResp, 0, 3) === '250');
}
?>
