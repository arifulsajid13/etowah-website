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

