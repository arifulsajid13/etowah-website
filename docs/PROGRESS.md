# Progress log

Spec: `docs/SPEC.md`. Inventory: `docs/INVENTORY.md`.

Local tooling: PHP 8.3 CLI installed via winget (`%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_*\php.exe`), run with a custom php.ini (pdo_mysql, gd, fileinfo, mbstring, openssl enabled). Headless Chrome is used for screenshots.

## Phase 1 — foundation and PHP conversion ✅

**Done**
- Saved the spec, wrote INVENTORY.md, and took baseline screenshots (9 pages × 375/768/1024/1440).
- Foundation in `app/`: bootstrap, db (PDO), helpers, settings, images, content, render, schema.sql, seed.php.
- Partials: head, header, cta-footer, footer-only (+ shared footer-grid), project-card, icons.
- All 9 pages converted to PHP with clean URLs. Dynamic parts: contact data, stats, home projects, service-page experience, markets related projects, projects page, image slots.
- Added careers.php (hero, intro, openings / empty state), 404.php, sitemap.php (/sitemap.xml), and robots.php (/robots.txt).
- Root `.htaccess` (HTTPS, canonical host, clean URLs, 301s from .html and .php, deny rules, compression, caching). Added `.htaccess` deny files for `app/`, `storage/`, and `uploads/` (images only, no scripts).
- Old `.html` files removed; the 301 redirects cover the old URLs.
- The form plumbing (csrf.php, ratelimit.php, mail.php, PHPMailer 6.9.3, both submit handlers, form JS) was written early in this phase while MariaDB downloaded. Phase 2 finishes and tests it.

**Checks**
- `php -l` passes on all PHP files.
- Local run: portable MariaDB 10.11 + `php -S` with a router that mirrors `.htaccess` (scratchpad only).
- Normalized HTML diff of every page against the originals: identical except the Careers nav/footer links, form wiring, and the two notes below.
- Screenshot diff at 4 widths: changes only in the header (Careers item) and the footer (one more Menu line, so the page is taller).

**Decisions**
- `pages.page_key` is used instead of `key`, because `key` is a reserved word in MySQL.
- Each service page shows different titles, text, and order for the same project, so these are stored per service in `project_services` (`sort_order`, `card_title`, `card_summary`). `projects.primary_service` sets the single tag shown on the Projects page.
- The Markets page images are page_images slots (`markets/{market}`).
- Header fit:
  - Nav gaps are now 20/18px for widths up to 1199px (this used to apply only up to 1079px).
  - The hamburger menu now appears below 1024px (was 960px); `main.js` mobileQuery was updated to match. Without these changes, the header ran out of room at about 960px and 1080px once a desktop scrollbar was present.
- `hero-careers.jpg` could not be AI-generated here (no image generation tool), so it is a copy of `hero-about.jpg`, marked as a placeholder.
- Accepted differences from the original pages:
  - On the utilities page, the Bartow card meta now reads "BARTOW COUNTY, GA • COMPLETED" (it had no status before).
  - Markets › Municipal related projects follow the global project order (rebuild, bartow, rehab).
- `picture { display: contents; }` was added so uploaded WebP images keep the existing layout.

## Phase 2 — forms and careers ✅

**Done**
- `/careers/{slug}`: breadcrumb, H1, meta line, plain-text description (paragraphs and "- " bullets, all escaped), and the application form. JobPosting JSON-LD is built from stored fields only. A job that isn't open returns 404.
- `/careers` empty state + general application form ("Send us your details.").
- `partials/apply-form.php` (shared). `submit-application.php` checks:
  - upload status, size (5 MB), and an extension allowlist;
  - the `finfo` MIME type;
  - magic bytes, and that a docx contains `word/document.xml`.

  Resumes are stored as random 32-hex names in private storage, the original name is kept for display only, and the notification email never attaches the resume.
- `submit-contact.php`: validation and length limits, saved to `inquiries`, notification email (From = configured sender, Reply-To = visitor).
- Abuse protection on both forms: honeypot, signed time token (≥3 s), CSRF (session), 5 per hour per IP-hash per form, and a generic rejection.
- `main.js`: the mailto fallback and `FORM_ENDPOINT` are removed. One `initForm()` handles both forms with fetch + FormData, inline server errors, and the settings-based "Something went wrong. Please call … or email … directly." fallback.
- No-JS fallback: the form posts normally and the page shows `?sent=1` / `?applied=1` / `?error=1`.

**Checks**
- curl tests passed:
  - too fast, missing CSRF, honeypot, and validation errors are all rejected;
  - an `.exe` renamed `.pdf` and a PHP file renamed `.docx` are rejected;
  - a closed or unknown job is rejected;
  - a valid PDF is stored, and the path traversal in its filename is stripped.
- Puppeteer (local Chrome): inline errors on an empty submit, contact success, application with a PDF success, mobile menu at 1000px, and zero console errors.

**Decisions**
- Generic failures return no message, so the JS shows the standard linked phone/email fallback.
- Local test mail uses a `log` transport that writes to `storage/logs/mail.log`. It is used for development only; the installer writes `smtp` or `mail`.

## Phase 3 — admin dashboard + security ✅

**Done**
- `app/auth.php`:
  - login throttling (5 failures per 15 min per IP-hash + email, plus 20 per IP-hash);
  - generic error and a dummy-hash timing guard;
  - ARGON2ID hashing (BCRYPT fallback), rehash on login, `session_regenerate_id(true)`;
  - 2-hour idle timeout, logout destroys the session, activity log.
- Sessions: HttpOnly, Secure (on HTTPS), SameSite=Lax, strict mode, private save path in storage, 2-hour gc lifetime.
- `/admin` structure: `index.php` router, `login.php`, `logout.php` (POST + CSRF), `export.php` (CSV), `resume.php`. Templates live in `admin/inc/` (deny `.htaccess` plus an `ETOWAH_ADMIN` guard).
- Screens:
  - Overview: needs attention, 4 stat cards with 7-day trend, latest 5 + 5.
  - Inquiries: list with search/status/sort/pagination/CSV; detail with mailto/tel, status (contacted stamps `contacted_at` once), notes, delete.
  - Projects: thumbnails, placeholder badge, publish switch, ↑/↓ order; editor with auto slug, counter, services with optional per-page card text, main service, photo + alt, home position with move confirm, delete.
  - Jobs: posted_at on first open; delete only with no applications.
  - Applications: filters, CSV, resume download, delete removes the file.
  - Pages & SEO: counters, live Google preview, every image slot plus site-wide CTA background.
  - Settings: contact details, stats, notify emails, GSC code, System box with the last 20 log lines.
  - Users: add, remove (not self, not last), change password (current required, min 12), last 50 activity entries.
- UI:
  - Dark theme by default with a light toggle (localStorage in try/catch; `theme.js` runs before paint).
  - The sidebar becomes a drawer under 900px, and tables become stacked cards under 640px.
  - Toasts, a custom `<dialog>` confirm, and plain-language hints.
- Strict CSP (self + Google Fonts): there is no inline script or style in the dashboard. X-Frame-Options DENY, X-Robots-Tag and meta noindex,nofollow, and `Cache-Control: no-store`.
- CSV export prefixes `= + - @` to stop spreadsheet formula injection, and adds a BOM for Excel.

**Checks (local MariaDB + php -S + Puppeteer)**
- All 16 dashboard routes return 200 with zero console or CSP errors and no horizontal overflow at 375, 768, and 1366.
- Flows passed:
  - phone change shows on all pages;
  - a .php renamed .jpg is rejected;
  - a photo upload creates JPG + WebP, clears the placeholder flag, and the homepage serves a `<picture>`;
  - the home banner move asks for confirmation and the other project goes to none;
  - job draft (404) → open (listed, in the sitemap, application received) → closed (404, delete hidden);
  - resume download works (attachment + nosniff) and gives 302 to login when logged out;
  - inquiry set to contacted stamps the date; both CSV exports work;
  - page hero replace serves `<picture>` with fetchpriority; SEO save and live preview work;
  - delete confirm cancel keeps the record; the theme is remembered.
- Attacks blocked:
  - 6th login refused even with the correct password;
  - admin POST without CSRF → 400, nothing deleted;
  - GET logout ignored;
  - `/app/*`, `/storage/*`, `/uploads/x.php`, `/docs/*.md`, `/.git`, and `/.htaccess` → 403;
  - logged-out admin endpoints → 302 to login.

**Decisions / notes**
- Reordering uses ↑/↓ buttons, which work without JS and with a keyboard. There is no drag and drop.
- The Contact page meta description mentions the phone number. It is SEO text kept in `pages`, so the Settings phone hint points to it.
- These deny rules were verified through the dev router, which mirrors `.htaccess`. Real Apache/LiteSpeed behaviour is untested locally.

## Phase 4 — installer and package ✅

**Done**
- `install/index.php` wizard:
  1. Owner code: 8 characters in `storage/install-code.txt`; 10 wrong tries create a new code.
  2. Requirements checklist with plain-language hPanel fixes. The upload limit is a "recommended" item.
  3. Database test with friendly messages for errors 1045/1049/1044/2002.
  4. SMTP step with "Send test email" (shows the PHPMailer error) and "Skip for now (use PHP mail)".
  5. Site URL (auto-detected) and admin account (min 12 characters + strength meter).
- Finish step:
  - runs the schema, then the idempotent seed (INSERT IGNORE for settings and pages);
  - creates or updates the admin user;
  - creates private storage, using `dirname(public_html)/etowah-storage` when writable and `/storage` otherwise;
  - writes `app/config.php` (chmod 600) with a 64-hex app secret, writes `app/install.lock`, and deletes the code file.
- The installer refuses to run when the config or the lock exists; `.htaccess` also returns 403 for /install once the lock exists.
- `DEPLOY.md`: a 10-step plain-language guide for the owner.
- `tools/build-package.php` → `dist/etowah-hostinger.zip`, containing only public_html content. It leaves out:
  - `.md` files, `docs/`, `tools/`, `reference/`, and `.git`;
  - `app/config.php` and the lock file.

  `storage/` and `uploads/` ship empty, with only their `.htaccess` files.
- `.gitignore` updated in Phase 1. No Cloudflare files exist in the repo (`wrangler.jsonc` and `.assetsignore` were never present).

**Checks**
- The full wizard ran in Puppeteer against a fresh MariaDB database:
  - wrong code, wrong DB password, and SMTP failure are rejected with clear messages; skipping mail works; a short password is rejected;
  - install completes; `/install/` then returns 403 and the site returns 200;
  - the owner can sign in (argon2id hash); 8 projects, 20 image slots, and 18 settings are seeded.
- Before installing, the site redirects to `/install/` while the config is missing.

**Notes**
- The local test created `D:\etowah-storage` (empty folders only). The automatic cleanup was blocked by a safety check, so the user can delete it by hand.

## Phase 5 — review and fixes ✅

**Review of §8 (security), line by line**
- Login: CSRF ✓; generic error ✓; 5 per 15 min per IP + email lockout ✓ (tested: the 6th try is refused even with the right password); ARGON2ID ✓; regenerate id ✓.
- Sessions: HttpOnly, Secure (on HTTPS), SameSite=Lax, strict mode, 2 h idle, destroy on logout ✓.
- Auth on every admin endpoint ✓ (index, export, resume; `admin/inc` denied plus a guard). State changes are POST + CSRF only ✓ (tested: a POST without a token → 400). IDs are cast to int ✓.
- Escaping: every dynamic output goes through `e()`; JSON-LD uses JSON_HEX_TAG; JS uses textContent ✓. Email headers are checked for CR/LF ✓.
- Headers: HSTS, nosniff, Referrer-Policy, Permissions-Policy ✓. Admin: DENY + strict CSP (zero CSP violations in Puppeteer) ✓.
- Errors go to the log and are never displayed ✓. Private storage sits above the web root when writable ✓. `uploads/` serves images only and runs no scripts ✓.

**Review of §14 (QA)**
1. PHP 8.3 CLI installed; `php -l` passes on all 61 PHP files. The installer ran end to end against portable MariaDB 10.11 with `php -S` and a router that mirrors `.htaccess`.
2. Visual parity at 375/768/1024/1440:
   - The normalized HTML diff of all 9 pages shows only intended changes (Careers link, form wiring, and the 2 accepted notes from Phase 1).
   - Screenshot diffs change only in the header and footer bands.
   - No horizontal overflow at 320–1440, and exactly one H1 on every page.
3. Links: every 301 (.html, .php, trailing slash) works, plus sitemap and robots; anchors use slug ids.
4. Flows: every step in the spec list passed (see Phase 3), plus rate limiting (6th contact in an hour refused) and replacing the CTA background (both footer variants).
5. Attacks: all blocked (see Phases 2 and 3).
6. No PHP warnings in the logs; no console errors on the public site or dashboard. The only console messages were the expected 404 status on `/nope`.

**Fixes made in this phase**
- Root `.htaccess` also denies `admin/inc` (defence in depth next to its own `.htaccess`).
- DEPLOY.md step 1 now checks that SSL is Active before installing, because the site forces https.
- Installer: the private-storage path uses forward slashes only.
- Earlier fixes from these reviews: slug `pattern` escaping, the drawer buttons showing on desktop, the sidebar background height, the stacked-table layout, the strict_types guard order, and the phone hint about the Contact SEO description.
- `dist/etowah-hostinger.zip` rebuilt (103 files, 6.0 MB). Verified that it contains no config, lock, .md files, docs, tools, uploads, or resumes.

**Untested (no Apache/LiteSpeed or real mail server locally)**
- Real `.htaccess` execution on Hostinger: HTTPS and www redirects, deny rules, Brotli/Gzip, cache headers. The logic was only exercised through the dev router.
- Real SMTP delivery and PHP `mail()`. The PHPMailer failure path was tested; successful sends were only tested with the local log transport.
- PHP 8.1/8.2 and MySQL 8 (tested on PHP 8.3 + MariaDB 10.11). File permissions (chmod 600) on Linux.
- Memory use when processing a full 10 MB photo on shared hosting (`memory_limit` is raised to 512M while processing, if the host allows it).

