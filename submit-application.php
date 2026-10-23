<?php
require __DIR__ . '/app/bootstrap.php';
require APP . '/csrf.php';
require APP . '/ratelimit.php';
require APP . '/mail.php';

const RESUME_MAX_BYTES = 5 * 1024 * 1024;
// Allowed extension => MIME types that finfo may report for it.
const RESUME_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$jobId = (int) post_str('job_id');
$job = $jobId > 0 ? db_one("SELECT id, title, slug FROM jobs WHERE id = ? AND status = 'open'", [$jobId]) : null;
$backTo = $job ? '/careers/' . $job['slug'] : '/careers';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$respond = function (bool $ok, array $data = [], int $code = 200) use ($wantsJson, $backTo) {
    if ($wantsJson) {
        json_out(['ok' => $ok] + $data, $code);
    }
    redirect($backTo . '?' . ($ok ? 'applied=1' : 'error=1') . '#apply', 303);
};

// An empty POST usually means the upload was larger than the server's post_max_size.
if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $respond(false, ['errors' => ['resume' => 'That file is too large. The limit is 5 MB.']], 413);
}
if (!form_guard_passes('apply') || !rate_limit_allows('apply', 5, 3600)) {
    $respond(false, [], 400);
}
if ($jobId > 0 && !$job) {
    $respond(false, ['message' => 'This position is no longer accepting applications.'], 410);
}

$name = post_str('name');
$email = post_str('email');
$phone = post_str('phone');
$message = post_str('message');

$errors = [];
if ($name === '') {
    $errors['name'] = 'Please enter your full name.';
} elseif (str_length($name) > 120 || has_line_break($name)) {
    $errors['name'] = 'Please enter a shorter name (120 characters or fewer).';
}
if ($email === '') {
    $errors['email'] = 'Please enter your email address.';
} elseif (!valid_email($email)) {
    $errors['email'] = 'Please enter a valid email address, like name@company.com.';
}
if (str_length($phone) > 40) {
    $errors['phone'] = 'Please enter a shorter phone number.';
}
if (str_length($message) > 5000) {
    $errors['message'] = 'Please shorten your message to 5,000 characters or fewer.';
}

// Resume upload checks: upload status, size, extension allowlist, and real file type.
$file = $_FILES['resume'] ?? null;
$ext = '';
$mime = '';
if (!is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors['resume'] = 'Please attach your resume.';
} elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > RESUME_MAX_BYTES) {
    $errors['resume'] = 'That file is too large. The limit is 5 MB.';
} elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] <= 0) {
    $errors['resume'] = 'Your resume did not upload. Please try again.';
} else {
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(RESUME_TYPES[$ext]) || !in_array($mime, RESUME_TYPES[$ext], true) || !resume_content_matches($ext, $file['tmp_name'])) {
        $errors['resume'] = 'Please upload your resume as a PDF, DOC, or DOCX file.';
    }
}
if ($errors) {
    $respond(false, ['errors' => $errors], 422);
}

$dir = storage_path('resumes');
if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
    log_error('Resume folder is not writable: ' . $dir);
    $respond(false, [], 500);
}
$stored = bin2hex(random_bytes(16));
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
    log_error('Could not move uploaded resume.');
    $respond(false, [], 500);
}
@chmod($dir . '/' . $stored, 0600);

// Original name is kept for display only; strip paths and control characters.
$original = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '', basename(str_replace('\\', '/', (string) $file['name'])));
$original = mb_substr($original !== '' ? $original : 'resume.' . $ext, 0, 200, 'UTF-8');

try {
    db_run(
        'INSERT INTO applications (job_id, name, email, phone, message, resume_file, resume_original_name, resume_mime, resume_size, ip_hash, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [$job ? (int) $job['id'] : null, $name, $email, $phone, $message, $stored, $original, $mime, (int) $file['size'], ip_hash()]
    );
    $id = (int) db()->lastInsertId();
    rate_limit_record('apply');
} catch (Throwable $e) {
    @unlink($dir . '/' . $stored);
    log_error('Application save failed: ' . $e->getMessage());
    $respond(false, [], 500);
}

$position = $job ? $job['title'] : 'General';
$body = "A new job application was sent from the website.\n\n"
    . 'Position: ' . $position . "\n"
    . 'Full Name: ' . $name . "\n"
    . 'Email: ' . $email . "\n"
    . 'Phone: ' . ($phone !== '' ? $phone : 'Not provided') . "\n\n"
    . "Message:\n" . ($message !== '' ? $message : '(none)') . "\n\n"
    . "The resume is stored securely in the dashboard (it is not attached to this email).\n"
    . 'View the application: ' . site_url('/admin/?p=application&id=' . $id) . "\n";
mail_send(notify_emails_jobs(), 'New job application — ' . mail_header_safe($name) . ' (' . mail_header_safe($position) . ')', $body, $email, mail_header_safe($name));

$respond(true, ['message' => 'Thank you — your application has been received.']);

/** Extra sanity check on the file contents for each allowed type. */
function resume_content_matches(string $ext, string $path): bool
{
    $head = (string) file_get_contents($path, false, null, 0, 8);
    return match ($ext) {
        'pdf'  => strncmp($head, '%PDF-', 5) === 0,
        'doc'  => strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0,
        'docx' => strncmp($head, "PK\x03\x04", 4) === 0 && docx_has_document($path),
        default => false,
    };
}

function docx_has_document(string $path): bool
{
    if (!class_exists('ZipArchive')) {
        return true;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return false;
    }
    $ok = $zip->locateName('word/document.xml') !== false;
    $zip->close();
    return $ok;
}
