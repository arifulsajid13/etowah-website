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

## Project SEO pages and demo-domain readiness ✅ (2026-10-21)

**Task 0 — forms**
- Contact and careers forms were re-tested in Chrome with autofill-style values: all succeed and show up in the dashboard.
- The PHP log showed two real rejections from the owner's own Chrome: "honeypot filled". Chrome autofilled the old spam-trap field `website`.
- The spam-trap field is now `etw_hp_x7`. It keeps `autocomplete="off"` and `tabindex="-1"`, has password-manager ignore flags, and sits off-screen inside an `aria-hidden` wrapper.
- Email failures were already only logged after the record was saved; no change was needed there.

**Task 1 — project pages**
- Schema v2:
  - `projects.seo_title` VARCHAR(70) and `seo_description` VARCHAR(170);
  - a new `project_images` table (gallery, max 12 per project, enforced in the dashboard).
- `app/migrate.php`:
  - `migrate_if_needed()` runs from bootstrap when `settings.schema_version` < 2, and is idempotent (checks columns, CREATE IF NOT EXISTS).
  - Fresh installs get v2 from `schema.sql` plus the seed, and the installer loads `migrate.php`.
- `/projects/{slug}` (`project.php`; rewrite in `.htaccess` and the dev router). Published projects only, 404 otherwise. The page has:
  - a hero with the main image, a breadcrumb, the H1, and a meta line;
  - a facts row: client, location, market (links to /markets#…), status, services (links);
  - the description, using the same plain-text rules as jobs;
  - a lazy WebP + JPG gallery with a `<dialog>` lightbox: Esc, ←/→ with wrap-around, built-in focus trap, focus returns to the thumbnail, and clicking the backdrop closes it;
  - up to 3 related projects in the same market, then the CTA band.
- Meta and structured data:
  - title is `seo_title`, or "Title | Location | Etowah Construction Group";
  - description is `seo_description`, or the summary;
  - canonical, og:image = main image, and BreadcrumbList JSON-LD.
- Every project card now links to `/projects/{slug}`: home cards and banner, service pages, and Markets related links.
- Projects page cards keep their `id` anchors and gain a "View project →" link.
- The sitemap includes every published project with its `updated_at`.
- Dashboard project editor:
  - SEO section: slug, SEO title (counter, 60), meta description (counter, 160), and a live Google preview that shows the automatic fallbacks.
  - Main photo alt text is required.
  - Gallery: multi-upload through `image_store_upload`, alt per photo, ↑/↓ reorder, and remove with a custom confirm (button-level `data-confirm`). Files are deleted when a photo or project is removed.
- Overview "Needs attention" lists projects missing alt text or a meta description, linking to the Projects list `?seo=missing` filter. The list shows an "SEO to finish" badge.

**Task 2 — demo domain**
- Site Settings › "Website address and search engines":
  - **Site URL** (validated as scheme + host [+ port] only) overrides `config.site_url` everywhere through `site_url()`: canonical, sitemap, robots, emails, JSON-LD, OG.
  - **Hide site from search engines** (seed default ON for new installs). When ON: `<meta name="robots" content="noindex, nofollow">` on every page, an `X-Robots-Tag: noindex, nofollow` header, `robots.txt` = `Disallow: /`, and a yellow "Hidden from search engines" label in the admin top bar. Toggling is logged.
- Existing installs upgraded by the migration get `hide_from_search = 0`, so an already-live site is not suddenly hidden.
- `.htaccess`: the HTTPS and www rules already used `%{HTTP_HOST}`, so they work on any host, including `*.hostingersite.com`. A comment now says so.
- DEPLOY.md: a new "Demo on a temporary domain" section, and step 8 rewritten as "Go live on the real domain" (connect the domain, SSL, Site URL, turn the toggle off, Search Console + sitemap). The installer's done page mentions the toggle.

**Checks**
- `php -l` passes on every changed PHP file and `node --check` on both JS files.
- On the local MariaDB:
  - the migration ran on the existing DB;
  - a fresh schema + seed gives v2 with the site hidden;
  - re-running the migration is harmless.
- Puppeteer, all with zero console errors:
  - project pages at 1366 and 375 (no overflow, one H1);
  - SEO save with the fallback preview;
  - alt-required error;
  - gallery upload (fake image rejected), reorder, edit, remove;
  - lightbox keyboard and focus;
  - Site URL plus the toggle on and off (header, meta, robots, canonical, sitemap, JSON-LD, top-bar label);
  - both forms.
- Zip rebuilt (105 files); it contains no tools, config, lock, .md, uploads, or resumes.

**Not tested**
- Real Apache/LiteSpeed rewrites for `/projects/{slug}`; only the dev router mirrored them.
- Google's handling of the noindex/robots signals.
- PHP 8.1/8.2 and MySQL 8.


## Project status wording removed + update package ✅ (2026-10-22)

Client request: show every project as finished work, with no "Current", "Completed", "In progress" or "Underway" wording used as a project status anywhere on the public site. No design changes.

- Projects page: one section, "OUR PROJECTS / Proven in the field.", with all published projects in admin sort order. It uses the existing 3-column `project-card` grid.
- The status label is removed from:
  - the home cards and banner;
  - the service-page "Experience" cards;
  - the project page hero meta and its "Status" fact;
  - the related-project cards.
- Copy changes:
  - About: "Our completed work includes" → "Our work includes", and "Completed … projects since launch" → "… projects delivered since launch".
  - Markets intro: "has completed" → "has delivered".
- Left as is:
  - "complete" meaning whole ("Complete civil scope", "complete sitework");
  - past-tense verbs inside project descriptions ("Completed 5.5 miles…").
- The admin still stores status, with a hint that it is internal only.
- Migration v3 updates seeded copy only if it was never edited:
  - the Projects meta description: "Current and completed …" → "Site development, underground utility, pump station, and roadway projects across Georgia and Tennessee.";
  - the Alpharetta description: "Completing…" → "Completed…".
  - The seed has the same text.
- `tools/build-package.php update` builds `dist/etowah-update.zip` for an installed site:
  - it holds code files only (php, js, css, sql, .htaccess);
  - it leaves out install/, uploads/, storage/, app/config.php, app/install.lock, images, docs, tools, .md files and the other build-only paths;
  - the migration runs from `app/bootstrap.php` on the first page load.
- DEPLOY.md step 10 now explains how to use the update zip.

**Checks**
- `php -l` passes on the changed files.
- Update path:
  - the last commit (v2) was extracted to a scratch folder and the update zip extracted over it;
  - the local DB was reset to v2 with the original texts;
  - the first page load moved it to v3 and updated both texts.
- Chrome (playwright-core), 375px and 1440px:
  - pages: Home, Projects, all 8 project pages, the 4 service pages, Markets and About;
  - all return 200, with one H1, no horizontal overflow and no console errors;
  - visible text, meta, JSON-LD and alt/aria text have no status wording;
  - every reveal element shows after scrolling, and all images load.
- Both zips rebuilt: update 70 files, hostinger 105 files.


## Homepage capability cards (client feedback) — 2026-10-22

**Commit:** 74edc8a

**Request:** Client wanted the 4 capability cards to stretch across the page so each fits on 1–2 lines, with shorter descriptions.

**Changes (index.php, assets/css/style.css only):**
- New `card-grid--caps` class on the homepage grid, so service and About pages are unchanged.
- Descriptions:
  - Site Development: "Clearing, Grading & Earthwork."
  - Underground Utilities: "Water, Sewer & Storm Pipe."
  - Pump Stations: "New Construction, Rehab & Bypass."
  - Roadways & Stormwater: "Asphalt, Curb & Underground Retention."
- 1200px and up: 4 equal-width cards filling the container. Card side padding 28px → 24px, title 20px → 18px, so "Roadways & Stormwater" stays on one line.
- 640–1199px: 2×2. Below 640px: 1 column.

**Decision:** At 1024px the cards show as 2×2 instead of 4 across. At 4 across, titles wrapped and the Roadways description ran to 3 lines.

**Not changed:** No database, seed, or migration changes. These texts live in index.php.

**Checked:** Headless browser at 375, 768, 1024, 1200, 1440px: titles on 1 line, descriptions on 1–2 lines, no horizontal scroll.

**Packages:** etowah-update.zip (70 files, no install/, no app/config.php) and etowah-hostinger.zip rebuilt.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Contact info from settings and optional contact name — 2026-10-23

**Commit:** "Contact info from settings and optional contact name"

**Request:** Client wanted no hardcoded contact details. Phone, email and contact name must come from Site Settings, and the contact name must be optional.

**Changes:**
- `app/settings.php` (`SETTING_DEFAULTS`, which `app/seed.php` writes on install): phone "(470) 982-4069", email and notification email "ECG@etowahconstruction.com", contact name empty. This was the old "Kenny Wallis" / 678-763-8047 / Kenny@etowahconstruction.com.
- `app/seed.php`: the Contact page meta description is built from the default phone instead of a hardcoded "Call 678-763-8047."
- Empty contact name hides the person icon and the name row in the CTA band (`partials/cta-footer.php`), the footer Contact column (`partials/footer-grid.php`) and the Contact page card (`contact.php`). The contact form's success message already said "We will be in touch shortly." when the name was empty. Notification emails never included the name.
- Site Settings: Contact name is marked "(optional)", has no `required` attribute, and can be saved empty. It is still limited to 120 characters. The Phone hint example is now "(470) 982-4069".
- `app/helpers.php`: the `tel_href()` comment example is updated. The function already builds `tel:+1` + digits from the phone setting. The forms' JS fallback does the same.

**Not changed:** No migration. Existing sites keep their stored settings until they are changed in the dashboard. The live Contact page meta description ("… Call 678-763-8047.") is stored in the database and must be edited in Pages & SEO.

**Checked:** Local site with a temporary admin user, which was removed afterwards; local settings were restored.
- Site Settings saved with the contact name empty, then "Test Person".
- Home, Contact and Careers at 1440 and 375px: the name row is hidden or shown in the CTA band, the footer and the Contact card. Every `tel:` link and the JS fallback give `tel:+14709824069`. There is no horizontal scroll and no console errors.
- The homepage capability cards are unchanged (`card-grid--caps`, titles on one line at 1200px and up).
- `php -l` passes on every changed PHP file.

**Packages:** etowah-update.zip (70 files, no install/, no app/config.php) and etowah-hostinger.zip (105 files) rebuilt. No code file in either zip contains the old contact values.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Jobs: experience field — 2026-10-23

**Commit:** "Jobs: experience field"

**Request:** Client wanted an optional "Experience" field on jobs, shown on the Careers page, the job page and in Google's job data.

**Changes:**
- Database: two nullable columns on `jobs`, `experience_range` (`entry`, `0-1`, `1-2`, `2-4`, `3-5`, `5+`, `10+`) and `experience_level` (`preferred`, `required`). NULL means "Not specified". `app/schema.sql` has them for new installs.
- Migration v4 (`app/migrate.php`, `SCHEMA_VERSION` 3 → 4): adds each column only if it is missing, so it runs once on the first request after upload and is safe to repeat. Existing jobs become "Not specified" and look the same as before.
- `app/helpers.php`: `EXPERIENCE_RANGES` (admin label, public label, minimum months) and `EXPERIENCE_LEVELS`.
- Add/Edit job: "Experience" (default Not specified) and "Requirement" (default Preferred) dropdowns in the row under Location / Employment type. Requirement is hidden and disabled for "Not specified" and "Entry level / no experience" (`initExperience()` in `admin/assets/admin.js`). The server ignores the posted level for those two options and stores NULL.
- Careers cards: the range is added to the meta line, e.g. "CARTERSVILLE, GEORGIA • FULL-TIME • 2–4 YEARS" (`job_meta_line()` / `job_experience_label()` in `app/content.php`).
- Job page: the hero meta line adds "EXPERIENCE: 2–4 YEARS PREFERRED" (or REQUIRED). Entry level shows "EXPERIENCE: ENTRY LEVEL" with no Preferred/Required.
- JobPosting JSON-LD (`app/render.php`): `experienceRequirements` = `OccupationalExperienceRequirements` with `monthsOfExperience` from the lower bound (0, 12, 24, 36, 60, 120). Left out for "Not specified" and "Entry level".
- Admin Jobs list: the range is added under the title, after location and employment type.

**Decision:** The 0–1 Years option gives `monthsOfExperience: 0`, as the lower bound is 0.

**Checked:** Local site with a temporary admin user; the test jobs and the user were removed afterwards.
- Migration: local DB rolled back to v3 with an existing open job, then one page request upgraded it to v4. The job kept NULL experience and its card was unchanged.
- One job created per option (Not specified, Entry level, and each range with Preferred or Required), then one job edited 2–4 Preferred → 10+ Required → Not specified → Entry level. Each time: the form reloads with the saved values, Requirement shows or hides correctly, the stored level is NULL where it should be, and the card meta, job page meta and JSON-LD match the list above.
- Admin job form, Jobs list, Careers and a job page at 375 and 1440px: no horizontal scroll, no console errors.
- `php -l` passes on every changed PHP file.

**Packages:** etowah-update.zip (70 files, no install/, no app/config.php) and etowah-hostinger.zip (105 files) rebuilt.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Separate notification email for job applications — 2026-10-23

**Commit:** "Separate notification email for job applications"

**Request:** Client wanted job application emails to go to different people than contact form inquiries.

**Changes:**
- Site Settings → Notifications: the existing field is now labelled "Send contact inquiries to" (still required). A new optional field below it, "Send job applications to", takes one or more comma-separated addresses with the same checks as the first field: every address must be valid, 10 at most, and duplicates are removed. The design is unchanged: one more field in the same panel, using the existing field, hint and "(optional)" styles.
- New setting `notify_emails_jobs` (`app/settings.php`), empty by default. `notify_emails_jobs()` returns that list, or the contact inquiry list when it is empty.
- `submit-contact.php` still sends to `notify_emails()`. `submit-application.php` now sends to `notify_emails_jobs()`.
- Migration v5 (`app/migrate.php`, `SCHEMA_VERSION` 4 → 5): `INSERT IGNORE` of `notify_emails_jobs` = '' for existing sites. Nothing changes until the field is filled in, and it is safe to repeat. New installs get the setting from `SETTING_DEFAULTS` through the seed.

**Checked:** Local site (mail transport `log`, i.e. storage/logs/mail.log) with a temporary admin user. The test inquiries, applications, resume files, activity rows, rate-limit rows, the user and the mail.log lines were removed afterwards, and local settings were restored.
- Migration: the first page request upgraded the local DB from v4 to v5 and added `notify_emails_jobs` = ''. When rolled back to v4 with a value already set, the re-run kept that value.
- Job list empty: a contact message and a general application both went to the contact list (fallback).
- Lists set in Site Settings (contact: two addresses, jobs: two addresses plus a duplicate): the contact message went only to the contact list, and the application only to the jobs list, with the duplicate dropped.
- Job list cleared again: the application went back to the contact list.
- Validation: an invalid address in the jobs field, an empty contact field, and 11 job addresses each show the right error under the right field, and nothing is saved.
- `php -l` passes on every changed PHP file.

**Packages:** etowah-update.zip (70 files, no install/, no app/config.php) and etowah-hostinger.zip (105 files) rebuilt.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Real project media, video gallery, project import — 2026-10-23

**Commit:** "Real project media, video gallery, project import"

**Request:** The client sent real photos and videos in six folders. Existing projects should be updated with them, new projects added, project galleries should support video, and everything should reach the live site through an import package. All existing projects stay.

**Originals:** The six folders were moved from `assets/images/projects/` to `client-media/`. That folder is in `.gitignore` and excluded from every zip in `tools/build-package.php`. No original file is published or packaged.

**Inspection (62 photos, 2 videos):**
- There were no HEIC files. Most photos had their EXIF stripped (`logo_…` exports), so dates come from file names only.
- GPS was used to confirm the place only, never published:
  - Alpharetta: 4 photos → Alpharetta, Fulton County.
  - Highway 75: 1 photo → near Cartersville, Bartow County.
  - Twigs video → unincorporated Cobb County, near Powder Springs.
  - Reliant Market District: none.
- Every photo was reviewed on contact sheets, with a sharpness score and 100% crops of the soft ones. Vehicles and people were checked at publish size:
  - No readable plates were found.
  - One Alpharetta shot with a clearly visible face was dropped, as was the 3-second Twigs clip.
  - Also dropped: blurry shots, near-duplicates, a finger over the lens, and a photo taken through glass.

**Matching:**
- **Alpharetta** → `alpharetta-commercial`.
- **Tiftonia** → `chattanooga-rebuild`. The photos show deep, fully shored excavation in Tiftonia (Chattanooga); the location becomes "Tiftonia, Chattanooga, TN".
- **Highway 75 Pump Station** → `bartow-pump-station`. The GPS is in Bartow County, and the photos show the station structure and piping.
- **New projects:**
  - `blue-ridge-pump-station`: Blue Ridge, GA, municipal, Pump Stations.
  - `twigs-farmers-market`: location empty, commercial, Roadways & Stormwater + Site Development, with 1 video.
  - `reliant-market-district`: location empty, commercial, Site Development + Roadways & Stormwater.
- Existing titles, summaries, descriptions and facts are kept. One sentence about the photos is appended to each updated description, and missing SEO titles, meta descriptions and alt text are filled in. All text is in `tools/project-import/manifest.php`; the client review is in `docs/PROJECT-CONTENT-REVIEW.md`.

**Media pipeline (`tools/build-project-import.php`):**
- **Photos:** EXIF orientation is applied, then crops; output has no metadata (GD re-encode, checked by the build). JPG q80 + WebP q78, the same settings as the dashboard upload. Gallery photos are at most 1600px on the long side; main photos are 1920px wide. Width and height are recorded, and names are stable hashes.
- **Video:** ffmpeg (installed with winget, Gyan.FFmpeg 9.0.2). H.264 High, at most 1080p (the source was 720p), CRF 23 capped at 2.5 Mbps (result 2.4 Mbps, 29 s, 8.5 MB). It uses faststart. Audio was removed (machine noise and possible crew talk). Metadata and data tracks were removed; the build fails if a location or creation tag survives. The poster is taken from the frame at 6 s.

**Video support:**
- Migration v6 (`SCHEMA_VERSION` 5 → 6) adds `media_type` (image|video, default image), `video_path`, `title`, `upload_date` and `duration` to `project_images`. Each column is added only if missing; `app/schema.sql` has them for new installs.
- **Project page:** a video tile shows its poster with a play badge. The lightbox shows `<video controls preload="none" playsinline>` with a poster and no autoplay. The source is set only when that item opens, and it is paused and unloaded on next/previous or close. Arrow keys seek in the player instead of changing items.
- **JSON-LD:** a VideoObject per video (`video_object_jsonld()`), with name, description, thumbnailUrl, contentUrl, uploadDate (the recorded date, or the day it was added) and duration.
- **Admin gallery:**
  - Videos appear with their poster, a "Video" badge, title, description, a "Watch video" link, up/down, and Remove (which deletes the poster, WebP and MP4).
  - The count reads "N of 12 photos · N of 3 videos".
  - Video upload accepts an MP4 only (checked by extension, finfo and `ftyp`) with an optional poster. When the poster is left empty, `admin.js` captures a frame in the browser. The admin CSP now allows `blob:` for media and images.
  - The size limit shown is min(64 MB, the server's upload limits). An upload over `post_max_size` gets a clear message instead of a "session expired" page.
- **Serving:** `uploads/.htaccess` allows `.mp4` (with `media-src 'self'`). The root `.htaccess` adds `AddType video/mp4` and a one-year cache. `etowah-update.zip` now includes `uploads/.htaccess`: the update build no longer skips the whole `uploads/` folder, and the runtime-folder rule still keeps only `.htaccess`.
- **Sitemap:** each project URL lists `image:image` entries for its main photo (unless it is a placeholder) and its gallery photos.

**Import (`admin/inc/import.php`, Admin → Projects → Import projects):**
- **Package location:** `storage_path('import')`, which is `etowah-storage/import/` next to `public_html`, or `public_html/storage/import/` on sites whose storage fell back inside the web root. The page shows the exact path and creates the folder. Only the zip needs to be uploaded: it is unpacked in place, writing only `projects.json` and `media/<hash>.(jpg|webp|mp4)`.
- **Preview:** new and updated projects, the photos and videos to add, fields filled versus kept, and homepage changes. **Import** asks for confirmation.
- **Run:** media is copied into `uploads/`, then the database is written in one transaction. On failure it rolls back, removes newly copied files and keeps the package. After success, the package files are deleted.
- **Safe to run twice:** projects match by slug and media by path; a second run shows "Already imported" and only deletes the package.
- **Client edits are never overwritten:**
  - A text field is filled only if it is empty or still holds the original seed text (both the "Completing" and the "Completed" Alpharetta variants count as original).
  - A main photo is replaced only while it is a placeholder.
  - Each homepage spot moves only while it holds its seeded project.
  - The gallery limits of 12 photos and 3 videos are respected.
- **Order:** new projects go right after a named project when there is room (Blue Ridge, Twigs and Reliant get 31–33 after Bartow's 30). That way the six projects with real photos lead the Projects page and the placeholder ones follow. On service pages, the new projects are appended at the end, so the existing cards stay.
- **Homepage:** card1 = Alpharetta, card2 = Chattanooga rebuild (Tiftonia), banner = Bartow Water Pump Station (the shotcrete-wall photo suits the wide banner best).

**Checked locally:** Portable MariaDB with a copy of the local data (`etowah_import_test`, kept for previewing) and a temporary admin user, which was removed afterwards. `app/config.php` was restored to `etowah_local`.
- **Migration:** the copy was rolled back to v5 with a legacy gallery row; one request upgraded it to v6 and the row became `image`.
- **Import:** the preview listed 3 new, 3 updated, 46 photos and 1 video, and the zip unpacked itself. The import gave the expected rows, files, sort orders, services and homepage spots, and the folder was emptied. The second run found nothing to add.
- **Edit protection:** on a separate copy with a custom Bartow description, a real Chattanooga photo, a custom Alpharetta SEO title and card 1 changed, the preview kept all four.
- **Pages:** Puppeteer with Chrome at 375 and 1440px covered Home, Projects, Markets, the 4 service pages, the 6 imported project pages and one unchanged project page. There were no console errors, failed requests or horizontal scroll. The only "broken image" reported was the lightbox's pre-existing empty placeholder `<img>`.
- **Lightbox and video:** a photo opens, and the video opens with poster and controls and plays (1280×720, 29 s). Next/previous stops and unloads it, and no MP4 is requested before Play.
- **Admin video:**
  - The upload auto-captured a poster (1280×720), then reorder and delete worked; delete removes all 3 files.
  - A 13 MB file (local limit 12 MB) and a 16 MB file (over `post_max_size`) both showed the size message.
  - The admin gallery and Import pages were checked at 1440 and 375px.
- **Sitemap:** valid XML with `image:image` entries; placeholder photos are excluded.
- `php -l` passes on every changed PHP file.

**Packages:**
- `etowah-update.zip`: 73 files, 0.2 MB. No install/, no app/config.php, no client-media/.
- `etowah-hostinger.zip`: 107 files, 6.0 MB.
- `etowah-projects-import.zip`: 53.3 MB. It holds `projects.json` + 95 media files: 47 JPG + 47 WebP + 1 MP4, with the poster included.

**Notes:** Photos at 1600px with the existing quality settings are heavy (median about 500 KB), because gravel and dirt textures compress poorly. A gallery of 12 lazy-loads about 6 MB. Smaller grid thumbnails (srcset) would be a good follow-up. The PHP dev server ignores Range requests; Apache and LiteSpeed on Hostinger support them.

**Open questions:** see `docs/PROJECT-CONTENT-REVIEW.md`. The main ones are the Twigs and Reliant locations, and confirmation of the Tiftonia and Highway 75 matches.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Enhance real photos and replace stock images — 2026-10-23

**Commit:** "Enhance real photos and replace stock images"

**Request:**
1. Give every real client photo (projects and pages) a light enhancement with free local tools, staying true to the work.
2. Replace the stock photos in the page image slots with real photos where a good match exists, delivered through Admin → Import. Only placeholders are replaced.

**Tools:**
- ImageMagick 7.1.2 (installed with winget, `ImageMagick.ImageMagick`). Python and Pillow are not on this machine.
- Real-ESRGAN was not needed: every crop is at least as large as its target, and the build fails if a photo would need upscaling.

**Enhancement (`tools/project-import/media.php`, used by the build):**
- **Pipeline:** EXIF orientation → optional straighten → crop → Lanczos resize (ImageMagick) → light enhancement → JPG q80 + WebP q78 by GD, so the dashboard settings and the no-metadata check are kept.
- **Levels:** black and white points from the 0.3% / 99.7% luminance percentiles, applied equally to R, G and B (no colour shift). At most 5% black and 8% white range is clipped.
- **Exposure:** a midtone gamma moving the mean 40% of the way to 0.45, limited to 0.93–1.2.
- **Contrast:** sigmoidal 1.5 at 50%.
- **Sharpening:** unsharp 0x0.7, amount 0.4, threshold 8%. The threshold keeps gravel and dirt texture, and file size, from being amplified.
- **Noise reduction:** `-enhance` before resizing, only for the 3 dim interior photos (Blue Ridge wet well and pumps, Bartow station piping).
- **Not done:**
  - Straightening: no photo was clearly tilted (wide-angle lens curvature was left alone).
  - Any generative or content edits.
  - Video and poster: left unchanged.
- **Bug found and fixed:** on Windows, PHP's `escapeshellarg()` turns `%` and `!` into spaces, which blew out the first test (`-level 5%,…` was read as absolute values). ImageMagick now runs through `proc_open()` with an argument array, with no shell in between.
- **Samples:** `php tools/build-project-import.php --samples` writes 3 side-by-side before/after JPGs to `docs/enhance-samples/`: Twigs mass excavation (overcast, flat light), Blue Ridge wet well (dim, with noise reduction), and the Reliant paving crop.

**Page photos (manifest `pages`):**
- 18 of 20 slots use real photos. Each source photo is used at most twice in total (project main or gallery + page slots); the build fails otherwise.
- **Heroes:**
  - Cropped 16:9 at 1920px. The CTA band is 1920 wide at 2.33:1, proof and team photos are 4:3 at 1600px, Markets photos 3:2 at 1600px.
  - Each hero was chosen with a contrast check: white text against the 95th-percentile brightness behind the heading, under the real overlay gradients (desktop band and mobile columns).
  - The chosen heroes measured 5.2:1 or better on desktop and 7.2:1 or better on mobile; the stock home hero measured 3.4:1 on desktop.
- **Avoided duplicates on the same page:** Roadways & Stormwater already shows the Twigs card with the stormwater photo, so its hero is the Reliant paving photo. The first Careers choice (Twigs crew) hid the crew under the overlay, so it is now Alpharetta #14 (crew member at a manhole, right of the text).
- **Kept as stock:**
  - `markets/residential`: no residential work in the photos.
  - `markets/roadway`: the only two roadway photos, Reliant paving and the Alpharetta street trench, are already used twice.
- Slot-by-slot list and alt text: `docs/PROJECT-CONTENT-REVIEW.md` → Page photos.

**Import changes (`admin/inc/import.php`, view):**
- **Page photos:** `pages` entries replace a slot only while `is_placeholder = 1`; a photo the client uploaded is shown as "Kept your photo". Files go to `uploads/pages/` (added to the allowed folders).
- **Newer versions:** the enhanced photos have revision-2 names, and each lists the revision-1 path it `replaces`. A site that already imported the first package gets the files swapped in place: same row, description and order, new path and size. The old JPG/WebP are deleted after the commit, and nothing is duplicated.
- **Preview:** shows page photos (replace / kept / already imported) and "N photos updated to a newer version".

**Checked locally** (temporary admin users and the extra database were removed afterwards; `app/config.php` restored to `etowah_local`):
- **Upgrade path** (`etowah_import_test`, which already held the first package):
  - 46 photos were swapped in place and 18 page photos replaced; gallery rows stayed at 41 with no duplicates.
  - 92 old files were deleted and 128 new ones added.
  - A second run: "Already imported" for all 24 items.
  - The Careers slot, reset to stock and re-imported, was replaced alone.
- **Fresh copy of the current local data**, with the Contact hero changed beforehand as if in the dashboard: 3 new and 3 updated projects, 46 photos, 1 video and 17 page photos added. Contact was kept, and 2 slots stay stock.
- **Pages:** Puppeteer with Chrome at 375 and 1440px covered Home, Projects, the 4 services, Markets, About, Careers, Contact and 3 project pages, all serving the new WebP heroes. There were no console errors, failed requests or overflow. Heroes, proof sections, Markets sections, the About team photo and the CTA band were reviewed in screenshots.
- `php -l` passes on all changed PHP files.

**Packages:**
- `etowah-update.zip`: 73 files, 0.2 MB.
- `etowah-hostinger.zip`: 107 files, 6.0 MB.
- `etowah-projects-import.zip`: 83.0 MB, 132 entries.

**Notes:**
- Enhanced photos are larger: gallery JPG median 635 KB against 536 KB before. Most of the growth comes from the sharper Lanczos resize and the contrast step; sharpening adds about 10%.
- Responsive thumbnails (srcset) for the gallery grid remain the best follow-up for page weight.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Copy review fixes — 2026-10-23

**Commit:** "Copy review fixes"

**Request:** Review every word on the live site for mistakes, fix what comes from code or seed/import data, and list what must be fixed in the live dashboard.

**Review:**
- All 20 URLs in the live sitemap.xml were read: 10 main pages, 8 project pages and 2 job pages.
- Checked: visible text, headings, buttons and links, form labels and messages (including the JS validation messages), footer, meta and Open Graph text, alt text, and JSON-LD.
- The full table is in `docs/COPY-REVIEW.md`: 19 issues.
- The "UtilitiContratores" misspelling is in the live Estimator job (title, web address, meta, JSON-LD). That is dashboard data, which is why the earlier search of the repo and the local databases found nothing.
- Flagged repeats such as "COMMERCIAL Commercial" and "Asphalt Asphalt" are two separate card elements (market label and title, or title and summary), not real repeats.

**Fixed in code or data:**
- `underground-utilities.php`:
  - "wet wells, and utility tie-ins" (series comma);
  - link text "Explore Pump Stations", matching the other "Explore …" links.
- `app/seed.php` (new installs):
  - Alpharetta: "sitework" instead of "site work" (the spelling used everywhere else);
  - grocery store: "storm, sewer, and water utilities".
- Import manifest:
  - The Alpharetta description fill now writes "sitework". The live text is still the original, so the import corrects it there. The corrected seed text was added to the list of originals.
  - Reliant Market District summary, description, SEO title and meta description: "Sitework".
- `docs/PROJECT-CONTENT-REVIEW.md` updated to match.

**Live dashboard (15 items, see `docs/COPY-REVIEW.md` rows 1–15):**
- Contact name capitalization.
- The Contact meta description's old phone number.
- The Estimator job: title, web address, location, and description grammar and series commas.
- The Superintendents job description.
- The grocery store description's series comma.

**Checked:**
- `php -l` passes on the changed PHP files.
- The local Underground Utilities page shows the new text.
- The update zip contains the fixes.
- The import zip contains no "Site work" values.

**Packages:**
- `etowah-update.zip`: 73 files, 0.2 MB.
- `etowah-projects-import.zip`: 83.0 MB, rebuilt for the text changes; photos unchanged.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]


## Import diagnostics and fixes — 2026-10-24

**Commit:** "Import diagnostics and fixes"

**Problem:** On the live site, Admin → Import projects said "No import package found", although the zip was in `…/snow-locust-324560.hostingersite.com/etowah-storage/import/`. Extracting it there did not help either.

**How the importer looked for a package (before):**
- **Folder:** only one, `storage_path('import')`. That is the absolute `storage_path` written into `app/config.php` at install time plus `/import`. The installer picks `<parent of public_html>/etowah-storage` when PHP can write the parent folder, and `public_html/storage` otherwise.
- **Lookup:** `projects.json` directly in that folder; otherwise `glob('*.zip')`, which had to find exactly one zip, unpacked with ZipArchive.
- **Cache:** none. Admin pages already sent `Cache-Control: no-store` (PHP's session start sends it too).

**Causes found (each one ends in "No import package found" with no error):**
1. **Single folder.** If config.php points somewhere else (installer fallback to `public_html/storage`, or a domain folder renamed after install), a package in `etowah-storage/import` is never seen.
2. **Exactly one zip required.** A second copy such as "etowah-projects-import (1).zip", or any other zip in the folder, made it silently ignore all of them.
3. **Extraction subfolder.** File Manager's Extract usually creates `import/etowah-projects-import/`, and `projects.json` one level down was not checked.
4. **`glob()`.** It is case-sensitive (`.ZIP` was missed), treats `[` `]` as patterns, and returns nothing in some open_basedir setups.
5. **Cleanup:** it deleted every `*.zip` in the folder, not just the package.

**Fixes (`admin/inc/import.php`, `admin/inc/views/import.php`, `app/helpers.php`):**
- **Folders:** `import_dirs()` searches the configured import folder, then `<parent>/etowah-storage/import`, then `public_html/storage/import`.
- **Locating (`import_locate()`):** in each folder, `projects.json`, then one subfolder down, then a zip.
  - Files are listed with `scandir` (no glob), and `.zip` is matched in any case.
  - With several zips, the newest `etowah-projects-import*.zip` wins.
  - A broken zip, or a zip without `projects.json`, now shows a clear error.
- **Cleanup (`import_clear()`):** deletes only `projects.json`, the package's own hash-named media files, the package zip (only `etowah-projects-import*.zip`, never an unrelated zip) and an empty extraction subfolder.
- **`storage_path()`:** if the configured folder does not exist, it falls back to `<parent>/etowah-storage`, then `public_html/storage`. This keeps resumes, logs, sessions and imports working after a domain folder rename.
- **Diagnostics box on the Import page:**
  - The box shows:
    - where the package was found;
    - the storage folder in use and the one in config.php (flagged "does not exist");
    - ZipArchive availability;
    - the PHP user;
    - open_basedir;
    - for each searched folder: exists, readable, writable, inside open_basedir, real path, and the files PHP sees, with sizes.
  - It is open when no package is found or there are problems, and collapsed otherwise.
- **Headers:** admin pages also send `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`, `Pragma: no-cache` and `X-LiteSpeed-Cache-Control: no-cache`. They are set in `send_security_headers(true)`; the duplicate line in `admin/inc/init.php` was removed.
- **DEPLOY.md** step 11 mentions the diagnostics.

**Checked locally** (`etowah_import_test`, which already held the import, so the previews said "Already imported"; temporary admin removed and config restored afterwards):
- **Headers:** `Cache-Control: no-store, no-cache, must-revalidate`, `Pragma: no-cache`, `X-LiteSpeed-Cache-Control: no-cache`.
- **Folders and zips:**
  - Zip only in `etowah-storage/import` while config points to `storage/`: found, and Finish removed it.
  - Extracted into a subfolder, with and without the zip kept: found, and the subfolder and zip were removed.
  - Two copies plus an unrelated `OTHER.ZIP`: the newest copy was used, and the next visit found the other. `OTHER.ZIP` was never touched.
  - Uppercase `.ZIP`: found.
  - Truncated zip: "could not be opened … upload it again".
  - Nothing anywhere: "No import package found" with the diagnostics open.
- **Missing storage folder:** with config.php's `storage_path` set to a missing folder, the site and login keep working on `etowah-storage`, and the diagnostics show "(does not exist)".
- **Layout:** the diagnostics box was checked at 1440 and 375px, with no overflow and no JS errors.
- `php -l` passes on the changed files.

**Deploy status:** [Pending / Uploaded to Hostinger on YYYY-MM-DD]
