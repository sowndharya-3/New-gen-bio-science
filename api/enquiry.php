<?php
declare(strict_types=1);
// Public: contact-page enquiry form. Validates the fields, e-mails them to notify_email (server config)
// and answers ok only when the mail was handed to the mail system, so the page never shows a false success.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/mail-lib.php';
require_method('POST');

$in = read_json();

// Only the message may contain line breaks; every other field is one line, so it cannot fake extra "Label:" lines in the mail.
function field(mixed $v, int $min, int $max, bool $multiline = false): ?string {
    if (!is_string($v)) return null;
    $v = $multiline ? preg_replace('/[^\P{C}\n]+/u', ' ', str_replace("\r\n", "\n", $v))   // control chars except newlines
                    : preg_replace('/[\p{C}\s]+/u', ' ', $v);                                // one line
    $v = trim($v);
    $len = mb_strlen($v);
    return ($len >= $min && $len <= $max) ? $v : null;
}

$name       = field($in['name'] ?? null, 2, 100);
$email      = field($in['email'] ?? null, 5, 150);
$phone      = field($in['phone'] ?? null, 7, 20);
$country    = field($in['country'] ?? null, 2, 80);
$profession = field($in['profession'] ?? null, 2, 40);
$species    = field($in['species'] ?? null, 2, 40);
$message    = field($in['message'] ?? null, 2, 4000, true);
$product    = field($in['product'] ?? null, 1, 120);       // optional: product the enquiry is about
$page       = field($in['page'] ?? null, 1, 300);          // optional: page the form was sent from

$errors = [];
if (!$name) $errors[] = 'Please enter your full name.';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
if (!$phone || !preg_match('/^[0-9\s\-+()]{7,20}$/', $phone)) $errors[] = 'Please enter a valid phone number.';
if (!$country) $errors[] = 'Please enter your country.';
if (!$profession) $errors[] = 'Please select your profession.';
if (!$species) $errors[] = 'Please select a species.';
if (!$message) $errors[] = 'Please enter your message.';
if ($errors) respond(422, ['ok' => false, 'error' => $errors[0], 'errors' => $errors]);

rate_limit('enquiry', 5, 3600);

$body = "New enquiry from the NewGen Bioscience website\n\n"
      . mail_lines(['Name' => $name, 'Email' => $email, 'Phone' => $phone, 'Country' => $country,
                    'Profession' => $profession, 'Species' => $species, 'Product' => $product])
      . "\n\nMessage:\n" . $message . "\n\n"
      . mail_lines(['Submitted' => gmdate('Y-m-d H:i') . ' UTC', 'Page' => $page]) . "\n";

if (!send_notification('Website enquiry from ' . $name, $body, $email)) {
    respond(502, ['ok' => false, 'error' => 'Sorry, your message could not be sent right now. Please try again later or email us directly.']);
}
respond(200, ['ok' => true]);
