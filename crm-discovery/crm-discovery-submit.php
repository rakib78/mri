<?php
/**
 * crm-discovery-submit.php
 * mrakibulislam.com — CRM Discovery Form handler
 *
 * Receives the JSON payload from crm-discovery.html, emails a formatted
 * summary to the agency inbox, and sends a short confirmation to the client.
 *
 * Deployment: drop this file in the same folder as crm-discovery.html,
 * alongside the PHPMailer/ folder (already included next to this file).
 */

// ============================================================
// CONFIG — edit the values in this block only
// ============================================================
const SMTP_HOST       = 'smtp.hostinger.com';   // e.g. smtp.hostinger.com
const SMTP_PORT       = 465;                     // 465 for SSL, 587 for TLS
const SMTP_SECURE     = 'ssl';                   // 'ssl' or 'tls' — must match the port above
const SMTP_USERNAME   = 'forms@mrakibulislam.com'; // the mailbox that sends the email
const SMTP_PASSWORD   = '********';

const FROM_EMAIL      = 'forms@mrakibulislam.com'; // usually same as SMTP_USERNAME
const FROM_NAME       = 'Md. Rakibul Islam';

const NOTIFY_TO_EMAIL = 'hello@mrakibulislam.com'; // where YOU receive submissions for this site
const NOTIFY_TO_NAME  = 'Rakib';

const SITE_NAME       = 'Md. Rakibul Islam';
const SUBJECT_PREFIX  = '[CRM Discovery Form]';
// ============================================================
// END CONFIG
// ============================================================

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid submission data']);
    exit;
}

// Basic honeypot spam check (form includes a hidden field named "website_hp")
if (!empty($data['website_hp'])) {
    // Silently accept to not tip off bots, but don't send email
    echo json_encode(['success' => true]);
    exit;
}

// Required fields
$companyName  = trim($data['company_name'] ?? '');
$contactInfo  = trim($data['contact_info'] ?? '');
$clientEmail  = trim($data['client_email'] ?? '');

if ($companyName === '' || $contactInfo === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Company name and contact info are required.']);
    exit;
}

// Extract a usable email from contact_info or client_email for the auto-reply
function extract_email($text) {
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $m)) {
        return $m[0];
    }
    return null;
}
$replyToEmail = $clientEmail !== '' ? $clientEmail : extract_email($contactInfo);

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Build the formatted HTML summary of all answers
$sections = $data['sections'] ?? [];
$bodyHtml = '<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:680px;margin:0 auto;">';
$bodyHtml .= '<div style="background:#10141c;padding:20px 24px;border-radius:8px 8px 0 0;">';
$bodyHtml .= '<span style="color:#c9a84c;font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:600;">' . h(SITE_NAME) . '</span>';
$bodyHtml .= '<h2 style="color:#fff;margin:6px 0 0;font-size:20px;">New CRM Discovery Form Submission</h2>';
$bodyHtml .= '</div>';
$bodyHtml .= '<div style="border:1px solid #e3e6eb;border-top:none;padding:24px;border-radius:0 0 8px 8px;">';

foreach ($sections as $section) {
    $secName = h($section['name'] ?? '');
    $bodyHtml .= '<h3 style="color:#C9A84C;font-size:15px;border-bottom:2px solid #C9A84C;padding-bottom:6px;margin:24px 0 12px;">' . $secName . '</h3>';
    foreach (($section['questions'] ?? []) as $q) {
        $label = h($q['label'] ?? '');
        $value = $q['value'] ?? '';

        $bodyHtml .= '<div style="margin-bottom:14px;">';
        $bodyHtml .= '<div style="font-weight:600;font-size:13.5px;color:#1f2937;margin-bottom:3px;">' . $label . '</div>';

        if (is_array($value)) {
            // pills (array of strings) or table (array of rows)
            if (isset($value[0]) && is_array($value[0])) {
                // table: array of [col1, col2]
                $bodyHtml .= '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                foreach ($value as $row) {
                    $c0 = h($row[0] ?? '');
                    $c1 = h($row[1] ?? '');
                    if ($c0 === '' && $c1 === '') continue;
                    $bodyHtml .= '<tr><td style="border:1px solid #e3e6eb;padding:5px 8px;color:#374151;">' . $c0 . '</td><td style="border:1px solid #e3e6eb;padding:5px 8px;color:#374151;">' . $c1 . '</td></tr>';
                }
                $bodyHtml .= '</table>';
            } else {
                $clean = array_filter($value, fn($v) => trim((string)$v) !== '');
                $bodyHtml .= '<div style="font-size:13.5px;color:#374151;">' . (count($clean) ? h(implode(', ', $clean)) : '<span style="color:#9ca3af;">(none selected)</span>') . '</div>';
            }
        } else {
            $valTrim = trim((string)$value);
            $bodyHtml .= '<div style="font-size:13.5px;color:#374151;white-space:pre-wrap;">' . ($valTrim !== '' ? nl2br(h($valTrim)) : '<span style="color:#9ca3af;">(blank)</span>') . '</div>';
        }
        $bodyHtml .= '</div>';
    }
}

$bodyHtml .= '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e3e6eb;font-size:12px;color:#9ca3af;">Submitted ' . date('d M Y, H:i') . ' via ' . h(SITE_NAME) . '</div>';
$bodyHtml .= '</div></div>';

// ---- Send notification email to agency inbox ----
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress(NOTIFY_TO_EMAIL, NOTIFY_TO_NAME);
    if ($replyToEmail) {
        $mail->addReplyTo($replyToEmail, $companyName);
    }

    $mail->isHTML(true);
    $mail->Subject = SUBJECT_PREFIX . ' ' . $companyName;
    $mail->Body    = $bodyHtml;
    $mail->AltBody = 'New CRM discovery form submission from ' . $companyName . '. View in an HTML-capable email client for full details.';

    $mail->send();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send notification email.']);
    exit;
}

// ---- Send confirmation email to the client (best-effort, don't fail the request if this fails) ----
if ($replyToEmail) {
    try {
        $confirm = new PHPMailer(true);
        $confirm->isSMTP();
        $confirm->Host       = SMTP_HOST;
        $confirm->SMTPAuth   = true;
        $confirm->Username   = SMTP_USERNAME;
        $confirm->Password   = SMTP_PASSWORD;
        $confirm->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $confirm->Port       = SMTP_PORT;

        $confirm->setFrom(FROM_EMAIL, FROM_NAME);
        $confirm->addAddress($replyToEmail, $companyName);

        $confirm->isHTML(true);
        $confirm->Subject = 'We received your CRM discovery form';
        $confirm->Body = '
            <div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:520px;margin:0 auto;padding:24px;border:1px solid #e3e6eb;border-radius:8px;">
                <p>Hi ' . h($companyName) . ',</p>
                <p>Thanks for filling out the CRM discovery form. We have received your answers and will review them shortly.</p>
                <p>If anything needs clarifying, we will follow up to schedule a short call. Otherwise, expect to hear from us with next steps and a proposed build plan soon.</p>
                <p style="margin-top:24px;">Best,<br>' . h(FROM_NAME) . '</p>
            </div>';
        $confirm->AltBody = 'Thanks for filling out the CRM discovery form. We have received your answers and will follow up soon.';

        $confirm->send();
    } catch (Exception $e) {
        // Non-fatal: notification already sent, so we don't block on this
    }
}

echo json_encode(['success' => true]);
