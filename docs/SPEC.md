# FINAL MASTER PROMPT — Etowah Website: Careers + Backend + Admin Dashboard + Hostinger Package

You are a senior full-stack developer. The Etowah Construction Group marketing website already exists in this folder as a static site (plain HTML/CSS/vanilla JS). Turn it into a **small, fast, secure PHP + MySQL website with a private admin dashboard**, add a **Careers** section, and produce a **ready-to-upload package for Hostinger**. Complete everything by following the execution plan below. Do not ask questions. When something is unspecified, choose the simplest secure option and note it in the final summary.

The owner is not a developer. After you finish, he will only: upload one zip to Hostinger, open an install wizard in the browser, and fill in a form. Everything else must already work.

---

## How to work (execution plan; follow this exactly)

Do the work in **5 phases, in order**, inside this one conversation. Treat each phase as a checkpoint.

| Phase | Scope (sections of this prompt) | Commit message |
|---|---|---|
| 1 | Inventory + foundation + public pages to PHP + careers.php static parts + .htaccess + SEO (sections 0–4, 10, 11) | `Phase 1: foundation and PHP conversion` |
| 2 | Contact form + Careers jobs, apply form, uploads (sections 5, 6) | `Phase 2: forms and careers` |
| 3 | Admin dashboard + security (sections 7, 8) | `Phase 3: admin dashboard` |
| 4 | Install wizard + DEPLOY.md + zip (sections 9, 12, 13) | `Phase 4: installer and package` |
| 5 | Review as a strict reviewer: test, security, visual parity, fix (sections 14, 15) | `Phase 5: review and fixes` |

**Before Phase 1:** save this whole prompt to `docs/SPEC.md` (so it can be re-read section by section later) and create `docs/PROGRESS.md`.

**Token and quality rules:**
- Do not paste long file contents or long code into the chat. Write code to files. Keep chat messages to short status lines.
- In Phase 1, create `docs/INVENTORY.md` (every page's sections, data-bound copy, image slots with paths and alt text). In later phases, read INVENTORY.md and PROGRESS.md instead of re-reading all the original pages.
- Re-read only the SPEC sections and files you need for the current phase. Use search to find code instead of opening every file.
- Build shared helpers once (Phase 1–2) and reuse them. No duplicate code.
- Do not regenerate images that already exist.
- **End of each phase:** run that phase's checks (php -l on new/changed PHP files if PHP is available), append to `docs/PROGRESS.md` (done, files changed, decisions, open issues), then `git add .` and `git commit` with the message above. Do not push. Then continue directly with the next phase without asking.
- **Phase 5 is a real review**, not a summary: assume earlier phases contain bugs, go through section 8 and section 14 line by line, fix what you find, rebuild the zip.
- **If you are interrupted or the conversation runs long:** read `docs/PROGRESS.md`, find the last completed phase, and continue from the next one. Do not redo finished phases.

---

## 0. Read first
1. Every existing page: `index.html`, `site-development.html`, `underground-utilities.html`, `pump-stations.html`, `roadways-stormwater.html`, `markets.html`, `projects.html`, `about.html`, `contact.html`, plus `assets/css/style.css` and `assets/js/main.js`. Write down the exact copy, sections, and image paths **before changing anything**.
2. `reference/dashboard-idea.png`, if it exists. Use it as a **layout idea only** (dark admin UI, left icon sidebar, top bar with the user's email, theme toggle and Log out, a "Needs attention" panel, stat cards, list panels). Do not copy its brand name, logo, or colors.
3. `reference/capabilities-statement.docx` is the source of truth for company facts.

## 1. Non-negotiable rules
- **The public site must look and read exactly as it does now**, except for the additions in this prompt (the Careers page, the Careers menu item, and form behavior). Same HTML structure, CSS classes, copy, spacing, and animations. Compare before and after.
- **Do not invent content**: no fake jobs, testimonials, statistics, clients, certifications, or social links. Seed data comes only from the existing pages and the docx.
- Plain **PHP 8.1+** with **PDO + prepared statements**. No frameworks, no Composer, no Node build step. Vendor **PHPMailer** into `app/vendor/PHPMailer/` (official release files). If you cannot download it, use PHP `mail()` and say so in the summary.
- No third-party scripts, trackers, or analytics on the public site. Fonts stay as they are (Archivo + Inter).
- **Secrets never go in Git or in the zip.** The install wizard writes the config on the server.
- All public pages are **server-rendered HTML** (no client-side rendering of content), for speed and SEO.

---

## 2. Target architecture

```
/                         (document root = Hostinger public_html)
├── index.php
├── site-development.php  underground-utilities.php  pump-stations.php  roadways-stormwater.php
├── markets.php  projects.php  about.php  careers.php  contact.php
├── 404.php
├── sitemap.php           (served as /sitemap.xml)
├── robots.txt
├── submit-contact.php    submit-application.php
├── partials/             head.php  header.php  cta-footer.php  footer-only.php  project-card.php  icons.php
├── admin/                (dashboard, see section 7)
├── install/              (one-time wizard, see section 9)
├── app/                  bootstrap.php  db.php  auth.php  csrf.php  mail.php  helpers.php
│                         settings.php  images.php  ratelimit.php  render.php  schema.sql  seed.php
│                         vendor/PHPMailer/
├── uploads/              (public images uploaded from the dashboard; images only)
├── storage/              (private: resumes, logs, cache, install code; deny-all)
├── assets/               (existing css/js/images, unchanged except form JS)
├── .htaccess
└── DEPLOY.md
```

- Every page includes `app/bootstrap.php` (config, DB, settings, helpers, security headers). If `app/config.php` is missing, redirect to `/install/`. If the install lock exists and the config is missing, show a plain "Site configuration missing" page.
- Header, CTA band + footer, and head tags become shared partials. **Their markup must match the current markup exactly**, only filled from settings.
- **Clean URLs:** `/`, `/site-development`, `/underground-utilities`, `/pump-stations`, `/roadways-stormwater`, `/markets`, `/projects`, `/about`, `/careers`, `/careers/{slug}`, `/contact`. Add a **301 redirect from every old `.html` URL** to its clean URL. Update every internal link, including anchors (`/projects#slug`, `/markets#municipal`).
- Remove Cloudflare-only files from the package (`wrangler.jsonc`, `.assetsignore`) and leave them out of the zip.

---

## 3. Database (`app/schema.sql`, utf8mb4_unicode_ci, InnoDB)

- `users` (id, name, email UNIQUE, password_hash, last_login_at, created_at)
- `settings` (k PRIMARY KEY, v TEXT)
- `pages` (key PRIMARY KEY: home, site-development, underground-utilities, pump-stations, roadways-stormwater, markets, projects, about, careers, contact; meta_title, meta_description, updated_at)
- `page_images` (id, page_key, slot, path, alt, width, height, is_placeholder TINYINT, updated_at; UNIQUE page_key+slot)
- `projects` (id, title, slug UNIQUE, status ENUM('current','completed'), client_name NULL, location NULL, market ENUM('municipal','commercial','residential','roadway'), summary VARCHAR(300) (card text), description TEXT (full text on the Projects page), image_path, image_alt, image_width, image_height, is_placeholder_image TINYINT DEFAULT 1, home_position ENUM('none','card1','card2','banner') DEFAULT 'none', sort_order INT, published TINYINT DEFAULT 1, created_at, updated_at)
- `project_services` (project_id, service_key: site-development / underground-utilities / pump-stations / roadways-stormwater; PRIMARY KEY both)
- `jobs` (id, title, slug UNIQUE, location, employment_type, description TEXT, status ENUM('draft','open','closed') DEFAULT 'draft', posted_at NULL, created_at, updated_at)
- `applications` (id, job_id NULL, name, email, phone, message TEXT, resume_file, resume_original_name, resume_mime, resume_size, status ENUM('new','reviewing','interview','hired','rejected') DEFAULT 'new', notes TEXT, ip_hash CHAR(64), created_at, updated_at)
- `inquiries` (id, name, company, email, phone, project_type, project_location, message, status ENUM('new','contacted','quoted','won','lost','spam') DEFAULT 'new', notes TEXT, ip_hash CHAR(64), contacted_at NULL, created_at, updated_at)
- `login_attempts` (id, ip_hash, email, created_at), `rate_limits` (id, ip_hash, bucket, created_at), `activity_log` (id, user_id, action, entity, entity_id, created_at). Index the lookup columns.

### Seed data (`app/seed.php`, run by the installer)
- **Settings**, from the current site: company_name "Etowah Construction Group LLC", contact_name "Kenny Wallis", phone "678-763-8047", email "Kenny@etowahconstruction.com", city_state "Cartersville, Georgia", license_text "Licensed in the State of Georgia", license_number "UC002776", markets_line "Municipal • Commercial • Residential • Roadway", notify_emails "Kenny@etowahconstruction.com", google_site_verification "" (empty), and the four homepage stats (label + value): Licensed in Georgia / UC002776, Roadway Asphalt / 5.5 miles, Mass Earthwork / ~85,000 CY, Deep Excavation / Up to 35 ft. Stat icons stay fixed in code.
- **Pages**: the current title and meta description of every page, plus the Careers values in section 5.
- **Page images**: one row per image slot that currently exists (each page hero, each service page proof image, about-team, cta-bg, careers hero) with its current path, alt, and real dimensions. `is_placeholder = 1` for all of them.
- **Projects**: all 8 current projects with exactly the current titles, locations, statuses, client names (City of Chattanooga, Bartow County Water Department where the current copy names them), summaries (use the shorter card text currently shown on Home/service pages), full descriptions (the Projects page text), images, markets, services, and order. Set `home_position` so the homepage stays identical: Chattanooga rebuild = card1, Alpharetta = card2, Cobb County asphalt = banner. All `is_placeholder_image = 1`.
- **No jobs, applications, or inquiries.**

---

## 4. Public site, dynamic parts
Everything below must render **identically to the current design**.

- **Header, CTA band, footer:** phone, email, contact name, city, license, and markets line come from settings. Navigation (desktop, mobile menu, footer Menu column): **Services (dropdown) | Markets | Projects | About | Careers | Contact** + the "Discuss Your Project" button. Careers goes after About. Check the header does not wrap at 1024px and 1200px; if tight, reduce nav gaps, then switch to the hamburger below 1100px.
- **Home:** the stats bar comes from settings. Selected Projects shows card1, card2, and the banner from `home_position`. If a position is empty, hide that slot cleanly.
- **Service pages:** the "Experience" section lists published projects linked to that service (`project_services`), ordered by sort_order, max 3, using `summary`. Hide the section if there are none.
- **Markets page:** each market's "Related projects" links come from the projects in that market.
- **Projects page:** "Current" and "Completed" sections from the database, using `description`, with `id="{slug}"` anchors and `scroll-margin-top`. Service tags link to the service pages.
- **Images:** every image uses the stored path, alt, width, and height. Uploaded images output `<picture>` with WebP + JPG fallback. Lazy-load everything except the hero.
- **Meta:** title, meta description, canonical, Open Graph (image = page hero), `google-site-verification` meta only when the setting is filled.
- **JSON-LD** `GeneralContractor` on every page from settings (name, url, telephone, email, address locality Cartersville, region GA, country US, logo). Only stored facts.

---

## 5. Careers (new)

### `/careers`
- Title: "Careers | Etowah Construction Group". Meta: "Join Etowah Construction Group LLC, an underground utility construction and site development company in Cartersville, Georgia. View open positions and apply."
- **Hero** (inner-page style, same as Projects): image `assets/images/heroes/hero-careers.jpg`. If missing, generate it (16:9, JPG, under ~300 KB): photorealistic crew of three walking toward heavy equipment at the start of the workday on a north Georgia jobsite, pine tree line, morning light, white hard hats, yellow high-visibility vests, no visible brand names, logos, or text. Breadcrumb Home / Careers. H1 "Careers". Subtitle "Build your career with a team that builds from the ground up."
- **Intro** (white, narrow centered): eyebrow "JOIN OUR TEAM", H2 "Build with Etowah.", text: "Etowah Construction Group delivers site development, underground utility, roadway, and pump-station construction for municipal, commercial, and residential clients. When we have openings, you will find them here."
- **Open positions** (`--bg-alt`): eyebrow "OPEN POSITIONS", H2 "Current openings." Cards for each `open` job (title, meta line "LOCATION • EMPLOYMENT TYPE", ~160-character excerpt, "View & Apply →").
- **Empty state** (no open jobs): a card titled "There are no open positions right now." with "Interested in working with Etowah? Send us your details below." Then the **general application form** under the heading "Send us your details."
- CTA band headline: "Interested in joining our team?" (button "Get in Touch" → /contact).

### `/careers/{slug}`
- Breadcrumb Home / Careers / {title}, H1, meta line, description, then the application form for that job. Return 404 if the job is not open.
- Description is plain text: escape it; blank line = new paragraph; lines starting with "- " = bullet list. Never output raw HTML from the database.
- **JobPosting JSON-LD** from stored fields only (title, description, datePosted, hiringOrganization, jobLocation when present, employmentType when it maps). Skip anything you cannot fill.

### Application form → `submit-application.php`
- Fields: Full Name*, Email*, Phone, Message, Resume* (PDF/DOC/DOCX, max 5 MB), hidden job_id. Same styling as the contact form: visible labels, inline errors, focus states, success message "Thank you — your application has been received."
- Server: validate; check the upload (`UPLOAD_ERR_OK`, size, extension allowlist, `finfo` MIME); store in private storage with a random 32-hex name; save the original name for display only. Email the notify list: subject "New job application — {name} ({job or General})" with a link to the dashboard. **Never attach the resume.**

---

## 6. Contact form → `submit-contact.php`
- Keep the current form UI and validation. Submit with `fetch` and FormData. Show inline errors from the server response.
- Server: validate (name, valid email, message required; length limits), save to `inquiries`, email the notify list (subject "New website inquiry — {name}", all fields, link to the dashboard). `From` = configured sender, `Reply-To` = visitor.
- On a network or server error, show: "Something went wrong. Please call {phone} or email {email} directly." (from settings).
- Remove the old mailto fallback and the `FORM_ENDPOINT` constant.

### Abuse protection on both forms
Honeypot field, a signed time token (reject submits under 3 seconds), per-IP rate limit of 5 per hour per form (store `sha256(ip + app secret)`, never raw IPs), CSRF token, strict length limits, and a generic error message on rejection.

---

## 7. Admin dashboard (`/admin`)

### Look and feel
- Dark theme by default with a light toggle (remembered in `localStorage`, try/catch). Etowah palette (`--dark-900/800/700`, `--steel-600/300`), Archivo headings, Inter body, 2px radius. Etowah horizontal logo at the top of the sidebar.
- **Left sidebar** with icons: Overview, Inquiries, Projects, Careers (Jobs + Applications), Pages & SEO, Site Settings, Users. Top bar: user email, theme toggle, Log out.
- Responsive: the sidebar becomes a drawer under 900px, and tables become stacked cards under 640px. No horizontal scroll. Every page sends `noindex,nofollow`.
- Clear empty states, success toasts after saves, confirm dialogs (custom, not `alert()`) for deletes, and helpful field hints written for a non-technical user.

### Pages
1. **Overview**
   - "Needs attention" panel with links: new inquiries older than 48 hours; new applications older than 48 hours; **projects still using placeholder photos**; page images still marked placeholder.
   - Stat cards: Inquiries (7 days, vs previous 7), Applications (7 days, vs previous 7), Open positions, Awaiting reply.
   - Lists: 5 latest inquiries and 5 latest applications.
2. **Inquiries:** search, status filter, date sort, 20 per page, CSV export of the current filter. Detail view: all fields, mailto and tel links, status (setting `contacted` stamps `contacted_at` once), notes, Save, Delete.
3. **Projects:** list with thumbnail, status, market, home position, published toggle, and drag-or-arrow reordering.
   - Create/Edit fields: title, slug (auto-generated, editable, unique), status, client name, location, market, services (checkboxes), summary (with a live character counter, max 300), full description, image upload (with alt text), home position (choosing a position already in use asks to move the other project to "none"), published.
   - Delete with confirm. A "View on site" link.
4. **Careers:**
   - **Jobs:** list with status and application counts. Create/Edit: title, slug, location, employment type (Full-time, Part-time, Contract, Seasonal, Internship, Other), description (plain text with formatting hint), status (setting `open` the first time stamps `posted_at`). Delete only when the job has no applications; otherwise suggest Closed. "View on site" for open jobs.
   - **Applications:** filter by job and status, search, pagination, CSV export. Detail: contact info, job, message, **Download resume** (authenticated, via `admin/resume.php` by id, `Content-Disposition: attachment`, `nosniff`), status, notes, Delete (also deletes the file).
5. **Pages & SEO:** a list of the 10 pages. Per page: meta title (live counter, recommended ≤ 60 characters), meta description (live counter, ≤ 160), a Google-style search preview, and **every image slot on that page** with a thumbnail and "Replace image" (with alt text). The global CTA background image is also here. Page text is not editable here (say so in a small note).
6. **Site Settings:** contact name, phone, email, city/state, license text and number, markets line, the 4 homepage stats (label + value), notification emails (comma-separated, validated), Google Search Console verification code. A read-only **System** box: PHP version, GD WebP support, DB OK, storage writable, uploads writable, and the last 20 error-log lines.
7. **Users:** list, add a user (name, email, temporary password), remove a user (cannot remove the last user or yourself), change your own password (current password required, min 12 characters). Show the **activity log** (last 50 actions: who, what, when).

### Image uploads (projects and page images)
JPG/PNG/WebP only, max 10 MB, verified with `getimagesize` + `finfo`. Re-encode with GD (this also strips EXIF): resize to max 1920px wide for heroes and 1600px for others, then save **WebP (quality ~78) + JPG fallback (~80)** to `uploads/{type}/{random}.{ext}`. Store the dimensions. Clear the placeholder flag. Delete the old uploaded files when replaced. Never delete the original `assets/images/` files.

---

## 8. Security (implement all)
- **Login:** CSRF, generic error, 5 failed attempts per 15 minutes per IP + email → 15-minute lockout, `password_hash` with ARGON2ID (fall back to BCRYPT), `session_regenerate_id(true)` on login.
- **Sessions:** HttpOnly, Secure, SameSite=Lax, strict mode, 2-hour idle timeout. Logout destroys the session.
- Auth check on every admin endpoint. State changes only via POST with CSRF. IDs cast to int.
- Escape all output (`htmlspecialchars`, ENT_QUOTES|ENT_SUBSTITUTE, UTF-8). Reject CR/LF in any email header value.
- **Headers:** HSTS (1 year), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera, microphone and geolocation off). For `/admin`: `X-Frame-Options: DENY` and a strict CSP (self + Google Fonts).
- Errors are logged to `storage/logs/`, never displayed.
- **Private storage:** the installer prefers a folder **above the web root** (`dirname(public_html)/etowah-storage`) when writable. Otherwise it uses `/storage` protected by deny-all `.htaccess`.
- `uploads/`: images only, and PHP execution disabled there via `.htaccess`.

## 9. `/install/` wizard (one time)
1. Runs only if `app/config.php` does not exist and there is no install lock.
2. **Step 0, owner check:** generates a random 8-character code into `storage/install-code.txt` (or the private storage path) and asks for it. The owner reads it in hPanel File Manager. This stops strangers from installing first.
3. **Step 1, requirements check:** PHP ≥ 8.1, pdo_mysql, gd with WebP, fileinfo, mbstring, openssl, writable folders. Show a green/red checklist with plain-language fixes.
4. **Step 2, database:** host (default `localhost`), name, user, password. Test the connection with a clear error message.
5. **Step 3, email (SMTP):** host, port, encryption, username, password, from address (default `no-reply@etowahconstruction.com`). Add a "Send test email" button. Allow "Skip for now (use PHP mail)".
6. **Step 4, site and admin:** site URL (auto-detected, editable), admin name, email, password (min 12 + strength meter).
7. **Finish:** write `app/config.php` (chmod 600 where possible), create the tables, run the seed, create the admin, write the lock, generate an app secret. Show "Installation complete" with links to the site and `/admin`, and a red notice: **"Delete the /install folder now (hPanel → File Manager)."** Also make the installer refuse to run whenever the lock exists.

## 10. `.htaccess` (root)
- Force HTTPS. Use one canonical host (non-www by default, clearly marked to switch).
- Clean URL rewrites, the `/careers/{slug}` rewrite, `/sitemap.xml` → `sitemap.php`, and 301s from all old `.html` URLs. Custom 404 → `404.php` (same header and footer, a short message, a link home).
- Deny `app/`, `storage/`, `install/` once locked (the PHP check is enough, but also deny `*.sql`, `*.md`, `*.log`, `.git*`, `config*.php`, `composer.*`). `Options -Indexes`.
- Gzip/Brotli. 1-year cache for images, CSS, JS, and fonts (add a version query string on CSS/JS from the file modification time). Short cache for HTML.

## 11. SEO
- `robots.txt`: disallow `/admin/`, `/install/`, `/app/`, `/storage/`, and add a `Sitemap:` line built from the site URL. If robots.txt cannot be dynamic, serve it through a rewrite to a small PHP file.
- `sitemap.xml`: all public pages + `/careers/{slug}` for open jobs, with `lastmod`.
- One H1 per page, logical headings, descriptive alt text, and unique titles and descriptions (from the `pages` table).

## 12. Package for Hostinger
- Create `dist/etowah-hostinger.zip` that contains **only what goes into `public_html`**: everything above except `reference/`, `dist/`, `.git/`, `.gitignore`, `wrangler.jsonc`, `.assetsignore`, all `.md` files (including DEPLOY.md), and `app/config.php`. Include empty `uploads/` and `storage/` folders with their `.htaccess` files.
- Update `.gitignore`: `app/config.php`, `storage/*` (keep `.htaccess` and `.gitkeep`), `uploads/*` (keep `.htaccess`), `dist/`, `reference/`.

## 13. `DEPLOY.md` (plain language, numbered, for a non-developer)
1. hPanel: add the website or domain (a temporary domain is fine), set PHP 8.2+ and enable the extensions listed, and set the upload limits (`upload_max_filesize` 12M, `post_max_size` 14M).
2. Create a MySQL database + user and write down the 4 values.
3. Create the mailbox `no-reply@…` and note the SMTP details shown in hPanel.
4. File Manager → `public_html` → upload `etowah-hostinger.zip` → Extract → delete the zip.
5. Open `https://your-domain/install/`, read the code from `storage/install-code.txt` in File Manager, and follow the wizard.
6. Delete the `install` folder.
7. Test checklist: every page loads, the contact form sends and appears in the dashboard, adding a job shows it on /careers, an application with a PDF works, replacing a project photo works.
8. Free SSL, then connect the real domain (link to Hostinger's guide; do not invent DNS values).
9. Backups: weekly database export from phpMyAdmin plus a copy of `uploads/` and the private storage folder.
10. How to update the site later (upload changed files; never overwrite `app/config.php`, `uploads/`, or storage).

## 14. QA (before finishing)
1. If PHP is not installed locally, try to install PHP CLI (Windows: `winget install PHP.PHP.8.3`) and run `php -l` on every PHP file. If MySQL/MariaDB is available locally, run the installer end to end with `php -S localhost:8000`. If not, do a careful line-by-line review and list exactly what is untested.
2. **Visual parity:** compare every public page before and after at 375px, 768px, 1024px, and 1440px. Nothing may change except the Careers additions and the menu.
3. **Links:** every nav, footer, anchor, service, market, and project link works; old `.html` URLs redirect.
4. **Flows:** install → login → edit phone in Settings (shows on all pages) → replace a project photo (WebP + JPG created, placeholder warning gone) → move a project to the home banner → create, open, and close a job → apply with a PDF → download the resume → contact form → inquiry status changes → CSV exports.
5. **Attacks:** 6 wrong logins (lockout), a `.php` renamed `.jpg` upload, an `.exe` renamed `.pdf`, a missing CSRF token, direct requests to `/app/config.php`, `/storage/…`, `/uploads/x.php`, and `/admin/` while logged out. All must be blocked.
6. No console errors and no PHP warnings in the logs.

## 15. Final summary to the owner
List: what was built, the zip location, any untested items, and the **placeholder photos the client must replace from the dashboard**. Keep it short.
