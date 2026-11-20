<?php
/**
 * Contact Form Handler — mrakibulislam.com
 * Upload this file alongside index.html in public_html/
 * Hostinger PHP mail() works out of the box on all shared plans.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://mrakibulislam.com');
header('Access-Control-Allow-Methods: POST');
header('X-Content-Type-Options: nosniff');

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Rate limiting via session (basic) ─────────────────────────
session_start();
$now = time();
if (isset($_SESSION['last_submit']) && ($now - $_SESSION['last_submit']) < 60) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Please wait 60 seconds before submitting again.']);
    exit;
}

// ── Honeypot check (bot trap) ─────────────────────────────────
if (!empty($_POST['website'])) {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// ── Sanitise & validate inputs ────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$firstName = clean($_POST['firstName'] ?? '');
$lastName  = clean($_POST['lastName']  ?? '');
$email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$company   = clean($_POST['company']   ?? '');
$service   = clean($_POST['service']   ?? '');
$budget    = clean($_POST['budget']    ?? '');
$message   = clean($_POST['message']   ?? '');

$errors = [];
if (empty($firstName))                          $errors[] = 'First name is required.';
if (empty($lastName))                           $errors[] = 'Last name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (strlen($message) < 20)                      $errors[] = 'Please describe your project in at least 20 characters.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Build the email hello@mrakibulislam.com───────────────────────────────────────────
$toEmail   = 'hello@mrakibulislam.com'; // ← CHANGE THIS to your actual email
$toName    = 'Rakibul Islam';
$subject   = "New enquiry from {$firstName} {$lastName}" . ($company ? " ({$company})" : '');

$body = "
==============================================
 NEW ENQUIRY — mrakibulislam.com
==============================================

Name:     {$firstName} {$lastName}
Email:    {$email}
Company:  {$company}
Service:  {$service}
Budget:   {$budget}

Message:
-----------------------------------------------
{$message}
-----------------------------------------------

Submitted: " . date('Y-m-d H:i:s T') . "
IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "
==============================================
";

$headers  = "From: hello@mrakibulislam.com\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// ── Send ──────────────────────────────────────────────────────
$sent = mail($toEmail, $subject, $body, $headers);

if ($sent) {
    $_SESSION['last_submit'] = $now;

    // Auto-reply to sender
    $replySubject = "Got your message — Rakibul Islam";
    $replyBody = "Hi {$firstName},

Thank you for reaching out. I've received your message and will get back to you within 24 hours.

Here's a copy of what you sent:

Service: {$service}
Message: {$message}

Talk soon,
Rakibul Islam
CRM Engineer · Automation Architect · eCommerce Expert
https://mrakibulislam.com
linkedin.com/in/rakibul
";
    $replyHeaders  = "From: hello@mrakibulislam.com\r\n";
    $replyHeaders .= "Reply-To: hello@mrakibulislam.com\r\n";
    $replyHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
    mail($email, $replySubject, $replyBody, $replyHeaders);

    echo json_encode(['success' => true, 'message' => 'Message sent! I\'ll reply within 24 hours.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mail server error. Please email hello@mrakibulislam.com directly.']);
}
