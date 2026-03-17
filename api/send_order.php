<?php
// send_order.php (white background, English messages)
// Sends admin notification to office@abicybernetics.com and confirmation to user

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Invalid request.";
    exit;
}

$admin_email = "office@abicybernetics.com";
$brand_from_name = "AbiCybernetics";
$brand_from_email = "office@abicybernetics.com";

function field($key) {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : "";
}

$name  = strip_tags(field("name"));
$email = filter_var(field("email"), FILTER_SANITIZE_EMAIL);
$phone = strip_tags(field("phone"));
$notes = htmlspecialchars(field("notes"), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$errors = [];
if ($name === "")  { $errors[] = "Name is required."; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "A valid email is required."; }
if ($phone === "") { $errors[] = "Phone is required."; }

if (!empty($errors)) {
    http_response_code(400);
    echo implode(" ", $errors);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$subject_admin = "New Exoskeleton Order from {$name}";
$subject_user  = "We received your order — AbiCybernetics";

$now = (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d H:i:s T');

$admin_html = <<<HTML
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<body style="font-family: Arial, Helvetica, sans-serif; color:#0b0b0b;">
  <h2>New Order Submission</h2>
  <p><strong>Name:</strong> {$name}</p>
  <p><strong>Email:</strong> {$email}</p>
  <p><strong>Phone:</strong> {$phone}</p>
  <p><strong>Notes:</strong><br>{$notes}</p>
  <hr>
  <p style="font-size:12px;color:#555;">Submitted: {$now}</p>
  <p style="font-size:12px;color:#555;">Client IP: {$ip}</p>
  <p style="font-size:12px;color:#555;">User-Agent: {$ua}</p>
</body>
</html>
HTML;

$user_html = <<<HTML
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<body style="font-family: Arial, Helvetica, sans-serif; color:#111;">
  <div style="max-width:640px;margin:0 auto;border:1px solid #dcdcdc;padding:24px;border-radius:12px;">
    <div style="text-align:center;margin-bottom:16px;">
      <div style="display:inline-block;width:64px;height:64px;border-radius:50%;background:#fafafa;color:#e8c547;line-height:64px;font-size:36px;border:1px solid #dcdcdc;">✓</div>
    </div>
    <h1 style="margin:0 0 8px;font-size:22px;">Thank you, {$name}!</h1>
    <p style="margin:0 0 12px;line-height:1.6;">
      We’ve received your request and a member of the AbiCybernetics team will contact you shortly.
    </p>
    <p style="margin:0 0 12px;line-height:1.6;">
      If you have any questions in the meantime, reply to this email or call us at <strong>+40 728 044 104</strong>.
    </p>
    <p style="margin:12px 0 0;font-size:12px;color:#666;">This is an automated confirmation. Please keep it for your records.</p>
  </div>
</body>
</html>
HTML;

$common_headers  = "MIME-Version: 1.0\r\n";
$common_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$common_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$admin_headers  = $common_headers;
$admin_headers .= "From: {$brand_from_name} <{$brand_from_email}>\r\n";
$admin_headers .= "Reply-To: {$name} <{$email}>\r\n";

$user_headers   = $common_headers;
$user_headers  .= "From: {$brand_from_name} <{$brand_from_email}>\r\n";
$user_headers  .= "Reply-To: {$brand_from_name} <{$brand_from_email}>\r\n";

$envelope_sender = "-f {$brand_from_email}";

$admin_ok = @$__abi_mail_ok = mail($admin_email, $subject_admin, $admin_html, $admin_headers, $envelope_sender);

$__abi_name = isset($_POST['name']) ? $_POST['name'] : '';
if (isset($_POST['lang'])) {
  $__abi_lang = ($_POST['lang'] === 'ro') ? 'ro' : 'en';
} else {
  $__abi_ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
  $__abi_lang = (strpos($__abi_ref, '/ro/') !== false) ? 'ro' : 'en';
}
$__abi_target = ($__abi_lang === 'ro') ? '/ro/thankyou.html' : '/en/thankyou.html';
if (!headers_sent()) {
  header('Location: ' . $__abi_target . '?name=' . urlencode($__abi_name));
  exit;
}
if ($__abi_mail_ok) {
  if (!headers_sent()) {
    $__abi_name = isset($_POST['name']) ? $_POST['name'] : '';
    header('Location: /thankyou.html?name=' . urlencode($__abi_name));
    exit;
  }
}

$user_ok  = @mail($email, $subject_user, $user_html, $user_headers, $envelope_sender);

$success = $admin_ok && $user_ok;
http_response_code($success ? 200 : 500);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $success ? "Thank you!" : "Error"; ?> — AbiCybernetics</title>
<style>
  :root { --bg:#ffffff; --card:#ffffff; --line:#dcdcdc; --text:#111; --muted:#555; --gold:#e8c547; }
  html,body { margin:0; padding:0; background:var(--bg); color:var(--text); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
  .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:20px; padding:28px; width:100%; max-width:720px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
  .badge { width:72px; height:72px; border-radius:50%; background:#fafafa; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; border:1px solid var(--line); }
  .badge svg { width:40px; height:40px; stroke: var(--gold); }
  h1 { text-align:center; margin:8px 0 12px; font-size:28px; }
  p { margin:0 0 10px; color:var(--muted); line-height:1.65; font-size:16px; text-align:center; }
  .divider { height:1px; background:var(--line); margin:18px 0; border:none; }
  .cta { display:inline-block; padding:12px 18px; border-radius:12px; background:var(--gold); color:#111; font-weight:600; text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="badge" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </div>
    <h1><?php echo $success ? "Thank you — your request was sent." : "An error occurred"; ?></h1>
    <p><?php
      if ($success) {
        echo "We have sent you a confirmation email. Our team will contact you shortly.";
      } else {
        echo "Please try again or contact us directly at <a href='mailto:{$admin_email}'>".$admin_email."</a>.";
      }
    ?></p>
    <hr class="divider">
    <p><a class="cta" href="/" aria-label="Back to homepage">Back to homepage</a></p>
  </div>
</div>
</body>
</html>
