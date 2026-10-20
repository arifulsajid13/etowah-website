<?php
require __DIR__ . '/app/bootstrap.php';
require APP . '/csrf.php';
require APP . '/ratelimit.php';
require APP . '/mail.php';

const PROJECT_TYPES = ['Site Development', 'Underground Utilities', 'Pump Stations', 'Roadways & Stormwater', 'Other'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$respond = function (bool $ok, array $data = [], int $code = 200) use ($wantsJson) {
    if ($wantsJson) {
        json_out(['ok' => $ok] + $data, $code);
    }
    // No-JavaScript fallback: back to the contact page with a status flag.
    redirect('/contact?' . ($ok ? 'sent=1' : 'error=1') . '#contact-form', 303);
};

if (!form_guard_passes('contact') || !rate_limit_allows('contact', 5, 3600)) {
    $respond(false, [], 400);
}

$data = [
    'name'             => post_str('name'),
    'company'          => post_str('company'),
    'email'            => post_str('email'),
    'phone'            => post_str('phone'),
    'project_type'     => post_str('project_type'),
    'project_location' => post_str('location'),
    'message'          => post_str('message'),
];

$errors = [];
if ($data['name'] === '') {
    $errors['name'] = 'Please enter your full name.';
} elseif (str_length($data['name']) > 120 || has_line_break($data['name'])) {
    $errors['name'] = 'Please enter a shorter name (120 characters or fewer).';
}
if ($data['email'] === '') {
    $errors['email'] = 'Please enter your email address.';
} elseif (!valid_email($data['email'])) {
    $errors['email'] = 'Please enter a valid email address, like name@company.com.';
}
if ($data['message'] === '') {
    $errors['message'] = 'Please tell us about your project.';
} elseif (str_length($data['message']) > 5000) {
    $errors['message'] = 'Please shorten your message to 5,000 characters or fewer.';
}
if (str_length($data['company']) > 160 || str_length($data['phone']) > 40 || str_length($data['project_location']) > 160) {
    $errors['message'] ??= 'One of the fields is too long. Please shorten it and try again.';
}
if ($data['project_type'] !== '' && !in_array($data['project_type'], PROJECT_TYPES, true)) {
    $data['project_type'] = 'Other';
}
if ($errors) {
    $respond(false, ['errors' => $errors], 422);
}

try {
    db_run(
        'INSERT INTO inquiries (name, company, email, phone, project_type, project_location, message, ip_hash, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [$data['name'], $data['company'], $data['email'], $data['phone'], $data['project_type'], $data['project_location'], $data['message'], ip_hash()]
    );
    $id = (int) db()->lastInsertId();
    rate_limit_record('contact');
} catch (Throwable $e) {
    log_error('Inquiry save failed: ' . $e->getMessage());
    $respond(false, [], 500);
}

$or = fn(string $v) => $v !== '' ? $v : 'Not provided';
$body = "A new inquiry was sent from the website contact form.\n\n"
    . 'Full Name: ' . $data['name'] . "\n"
    . 'Company: ' . $or($data['company']) . "\n"
    . 'Email: ' . $data['email'] . "\n"
    . 'Phone: ' . $or($data['phone']) . "\n"
    . 'Project Type: ' . $or($data['project_type']) . "\n"
    . 'Project Location: ' . $or($data['project_location']) . "\n\n"
    . "Message:\n" . $data['message'] . "\n\n"
    . 'View in the dashboard: ' . site_url('/admin/?p=inquiry&id=' . $id) . "\n";
mail_send(notify_emails(), 'New website inquiry — ' . mail_header_safe($data['name']), $body, $data['email'], mail_header_safe($data['name']));

$firstName = strtok(setting('contact_name'), ' ') ?: 'We';
$respond(true, ['message' => 'Thank you — your message has been sent. ' . $firstName . ' will be in touch shortly.']);
