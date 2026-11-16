<?php
declare(strict_types=1);
// Server-side notification e-mails (enquiries, new orders). The recipient and sender live only in the
// server config ('notify_email', 'mail_from'); nothing mail-related is ever sent to the browser.
// Uses PHP's mail() (sendmail on the host). Returns false, and logs why, when the mail could not be handed over.

function notify_email(): ?string {
    $to = config()['notify_email'] ?? null;
    return (is_string($to) && filter_var($to, FILTER_VALIDATE_EMAIL) && !str_starts_with($to, 'CHANGE_ME')) ? $to : null;
}

function send_notification(string $subject, string $body, ?string $replyTo = null): bool {
    $to = notify_email();
    if (!$to) { error_log('newgen mail: notify_email is not configured'); return false; }
    $cfg  = config();
    $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    $from = $cfg['mail_from'] ?? '';
    if (!is_string($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'no-reply@' . preg_replace('/^www\./', '', $host);
    $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));
    $headers = ['From: NewGen Website <' . $from . '>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'X-Mailer: newgen-site'];
    if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
    $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers));
    if (!$ok) error_log('newgen mail: mail() refused "' . $subject . '" to ' . $to);
    return $ok;
}

// "Label: value" lines for a plain-text mail; blank values are skipped.
function mail_lines(array $fields): string {
    $out = [];
    foreach ($fields as $label => $value) {
        $value = trim((string)$value);
        if ($value !== '') $out[] = $label . ': ' . $value;
    }
    return implode("\n", $out);
}
