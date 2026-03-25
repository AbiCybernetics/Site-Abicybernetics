<?php
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
    exit;
}

$adminEmail = 'office@abicybernetics.com';
$brandName = 'AbiCybernetics';
$brandEmail = 'office@abicybernetics.com';
$allowedHosts = [
    'abicybernetics.com',
    'www.abicybernetics.com',
    'abicybernetics.ro',
    'www.abicybernetics.ro',
];

function post_field(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function safe_html(string $value): string
{
    return nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function fail_request(int $status, string $message): void
{
    http_response_code($status);
    echo $message;
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if (!is_string($originHost) || !in_array(strtolower($originHost), $allowedHosts, true)) {
        fail_request(403, 'Invalid origin.');
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referer !== '') {
    $refererHost = parse_url($referer, PHP_URL_HOST);
    if (!is_string($refererHost) || !in_array(strtolower($refererHost), $allowedHosts, true)) {
        fail_request(403, 'Invalid referer.');
    }
}

$honeypot = post_field('company');
if ($honeypot !== '') {
    fail_request(400, 'Spam detected.');
}

$submittedAt = (int) post_field('submitted_at');
$currentTime = time();
if ($submittedAt <= 0 || $submittedAt > $currentTime || ($currentTime - $submittedAt) < 2) {
    fail_request(400, 'Form submission could not be validated.');
}

$lang = post_field('lang') === 'ro' ? 'ro' : 'en';
$name = strip_tags(post_field('name'));
$email = filter_var(post_field('email'), FILTER_SANITIZE_EMAIL);
$phone = trim(preg_replace('/\s+/', ' ', strip_tags(post_field('phone'))));
$message = trim(post_field('message'));

if ($name === '' || mb_strlen($name) > 120) {
    fail_request(400, $lang === 'ro' ? 'Numele este obligatoriu.' : 'Name is required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    fail_request(400, $lang === 'ro' ? 'Adresa de email nu este validă.' : 'A valid email address is required.');
}

if ($phone !== '' && mb_strlen($phone) > 40) {
    fail_request(400, $lang === 'ro' ? 'Numărul de telefon este prea lung.' : 'Phone number is too long.');
}

if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    fail_request(400, $lang === 'ro' ? 'Mesajul trebuie să aibă între 10 și 5000 de caractere.' : 'Message must be between 10 and 5000 characters.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = htmlspecialchars((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$timestamp = (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d H:i:s T');

$subjectAdmin = 'New Contact Form Message — ' . $name;
$subjectUser = $lang === 'ro'
    ? 'Am primit mesajul tău — AbiCybernetics'
    : 'We received your message — AbiCybernetics';

$adminName = safe_html($name);
$adminEmailSafe = safe_html($email);
$adminPhoneSafe = safe_html($phone !== '' ? $phone : ($lang === 'ro' ? 'Nefurnizat' : 'Not provided'));
$adminMessage = safe_html($message);
$adminIp = safe_html($ip);

$adminHtml = <<<HTML
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<body style="font-family:Arial,Helvetica,sans-serif;color:#111;background:#fff;">
  <h2>New Contact Form Submission</h2>
  <p><strong>Name:</strong> {$adminName}</p>
  <p><strong>Email:</strong> {$adminEmailSafe}</p>
  <p><strong>Phone:</strong> {$adminPhoneSafe}</p>
  <p><strong>Message:</strong><br>{$adminMessage}</p>
  <hr>
  <p style="font-size:12px;color:#555;">Submitted: {$timestamp}</p>
  <p style="font-size:12px;color:#555;">Client IP: {$adminIp}</p>
  <p style="font-size:12px;color:#555;">User-Agent: {$userAgent}</p>
  <p style="font-size:12px;color:#555;">Language: {$lang}</p>
</body>
</html>
HTML;

$userIntro = $lang === 'ro'
    ? 'Îți mulțumim pentru mesaj. Echipa AbiCybernetics îl va analiza și îți va răspunde cât mai curând.'
    : 'Thank you for your message. The AbiCybernetics team will review it and reply as soon as possible.';
$userFollowup = $lang === 'ro'
    ? 'Dacă ai nevoie de un răspuns rapid, ne poți suna la +40 728 044 104.'
    : 'If you need a quicker response, you can call us at +40 728 044 104.';
$userPhoneLabel = $lang === 'ro' ? 'Telefon' : 'Phone';

$userHtml = <<<HTML
<!doctype html>
<html lang="{$lang}">
<meta charset="utf-8">
<body style="font-family:Arial,Helvetica,sans-serif;color:#111;background:#fff;">
  <div style="max-width:640px;margin:0 auto;border:1px solid #dcdcdc;padding:24px;">
    <h1 style="margin:0 0 12px;font-size:24px;">{$brandName}</h1>
    <p style="margin:0 0 12px;line-height:1.6;">{$userIntro}</p>
    <p style="margin:0 0 12px;line-height:1.6;">{$userFollowup}</p>
    <p style="margin:0;line-height:1.6;"><strong>{$userPhoneLabel}:</strong> {$adminPhoneSafe}</p>
  </div>
</body>
</html>
HTML;

$headers = "MIME-Version: 1.0
";
$headers .= "Content-Type: text/html; charset=UTF-8
";
$headers .= "From: {$brandName} <{$brandEmail}>
";
$headers .= "Reply-To: {$name} <{$email}>
";
$headers .= 'X-Mailer: PHP/' . phpversion() . "
";

$autoHeaders = "MIME-Version: 1.0
";
$autoHeaders .= "Content-Type: text/html; charset=UTF-8
";
$autoHeaders .= "From: {$brandName} <{$brandEmail}>
";
$autoHeaders .= "Reply-To: {$brandName} <{$brandEmail}>
";
$autoHeaders .= 'X-Mailer: PHP/' . phpversion() . "
";

$envelopeSender = '-f ' . $brandEmail;
$adminOk = mail($adminEmail, $subjectAdmin, $adminHtml, $headers, $envelopeSender);
$userOk = $adminOk ? mail($email, $subjectUser, $userHtml, $autoHeaders, $envelopeSender) : false;

if (!$adminOk || !$userOk) {
    fail_request(500, $lang === 'ro' ? 'Mesajul nu a putut fi trimis acum.' : 'Your message could not be sent right now.');
}

$redirectTarget = $lang === 'ro' ? '/ro/thankyou.html' : '/en/thankyou.html';
header('Location: ' . $redirectTarget . '?name=' . urlencode($name), true, 303);
exit;
