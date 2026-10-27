# Live database fixes — "utility contractor" wording — 2026-10-27

The text below is stored in the live database, not in the code. Uploading `etowah-update.zip` does not change it, and the installer never overwrites rows that already exist. Paste each new text into the dashboard field named in the heading.

The rule for this change is that no public text says "utility contractor". The new wording is "utility construction", and the license reads "Licensed in the State of Georgia" followed by "License No. UC002776".

## 1. Site Settings → License text

```
Licensed in the State of Georgia
```

The footer then reads "Licensed in the State of Georgia. License No. UC002776." on every page, and the Contact page reads "Licensed in the State of Georgia · License No. UC002776". Leave the License number field as `UC002776`.

## 2. Pages & SEO

### Home

Title:
```
Etowah Construction Group | Site Development & Underground Utilities in Georgia
```
Description:
```
Etowah Construction Group LLC, based in Cartersville, Georgia, delivers site development, underground utilities, pump stations, and roadway construction for municipal, commercial, and residential clients. License No. UC002776.
```

### Underground Utilities

Title (unchanged):
```
Underground Utilities | Etowah Construction Group
```
Description:
```
Water distribution, sanitary sewer, and storm drainage installation for municipal, commercial, and residential projects. Georgia License No. UC002776.
```

### About

Title (unchanged):
```
About | Etowah Construction Group
```
Description:
```
Etowah Construction Group LLC is a site development and underground utility construction company in Cartersville, Georgia, also delivering roadway and pump-station construction. License No. UC002776.
```

### Careers

Title (unchanged):
```
Careers | Etowah Construction Group
```
Description:
```
Join Etowah Construction Group LLC, an underground utility construction and site development company in Cartersville, Georgia. View open positions and apply.
```

## 3. Careers → Jobs → Estimator

The job title also feeds the H1, the breadcrumb, the meta title, the Careers card and the JobPosting JSON-LD. The description feeds the card excerpt and the meta description.

Job title:
```
Estimator – Underground Utility Construction & Site Development
```
Web address (slug):
```
estimator-underground-utility-construction-and-site-development
```
The old address `/careers/estimator-underground-utiliticontractores-and-site-development` stops working once the slug changes.

Description: replace only the first sentence and leave the rest of the description as it is.

Current:
```
Established site development and underground utility contractor based in Cartersville, Georgia is seeking an Estimator with 2–4 years of experience in sitework and underground utility construction.
```
New:
```
An established site development and underground utility construction company based in Cartersville, Georgia, is seeking an Estimator with 2–4 years of experience in sitework and underground utility construction.
```

The other Estimator fixes in `COPY-REVIEW.md` (rows 6–10) still apply if they have not been made yet.

## 4. Careers → Jobs → Civil & Underground Utility Construction Superintendents

Description: replace only the first paragraph.

Current:
```
Etowah Construction Group, a North Georgia based Underground Utilities & Site Development Contractor Seeking Experienced Civil/Underground Utility Superintendents for Commercial, Industrial and Municipal project Locations throughout North Georgia.
```
New:
```
Etowah Construction Group, a North Georgia-based Underground Utility Construction & Site Development company, is seeking experienced civil/underground utility superintendents for commercial, industrial, and municipal project locations throughout North Georgia.
```

The other Superintendents fixes in `COPY-REVIEW.md` (rows 12–14) still apply if they have not been made yet.

## 5. Check for anything left (phpMyAdmin, read-only)

Run this in phpMyAdmin → SQL on the live database. It only reads data. Each row it returns names the table, the record, the field and the full text.

- `utiliti[^e]` finds misspellings such as "UtilitiContractores" without matching every normal "utilities".
- "contract" also matches the `Contract` employment type and the word "subcontractor". Judge each row: only wording that calls the company a contractor needs to change.
- Expected after the fixes: no rows, unless a job uses the Contract employment type (that column is not searched) or a text mentions subcontractors.

```sql
SELECT 'settings' AS tbl, k AS record, 'v' AS field, v AS text FROM settings WHERE v REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'pages', page_key, 'meta_title', meta_title FROM pages WHERE meta_title REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'pages', page_key, 'meta_description', meta_description FROM pages WHERE meta_description REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'jobs', CONCAT(id, ' ', slug), 'title', title FROM jobs WHERE title REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'jobs', CONCAT(id, ' ', slug), 'slug', slug FROM jobs WHERE slug REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'jobs', CONCAT(id, ' ', slug), 'description', description FROM jobs WHERE description REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'projects', CONCAT(id, ' ', slug), 'title', title FROM projects WHERE title REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'projects', CONCAT(id, ' ', slug), 'summary', summary FROM projects WHERE summary REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'projects', CONCAT(id, ' ', slug), 'description', description FROM projects WHERE description REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'projects', CONCAT(id, ' ', slug), 'seo_title', seo_title FROM projects WHERE seo_title REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'projects', CONCAT(id, ' ', slug), 'seo_description', seo_description FROM projects WHERE seo_description REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'projects', CONCAT(id, ' ', slug), 'image_alt', image_alt FROM projects WHERE image_alt REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'project_services', CONCAT(project_id, ' ', service_key), 'card_title', card_title FROM project_services WHERE card_title REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'project_services', CONCAT(project_id, ' ', service_key), 'card_summary', card_summary FROM project_services WHERE card_summary REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'page_images', CONCAT(page_key, ' ', slot), 'label', label FROM page_images WHERE label REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'page_images', CONCAT(page_key, ' ', slot), 'alt', alt FROM page_images WHERE alt REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'project_images', CONCAT(project_id, ' #', id), 'title', title FROM project_images WHERE title REGEXP 'contract|contratore|utiliti[^e]'
UNION ALL SELECT 'project_images', CONCAT(project_id, ' #', id), 'alt', alt FROM project_images WHERE alt REGEXP 'contract|contratore|utiliti[^e]';
```

`project_images` (gallery photo and video titles and alt text) is included as well, because it also holds public text.
