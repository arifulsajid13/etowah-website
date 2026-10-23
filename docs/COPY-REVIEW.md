# Live site copy review — 2026-10-23

**Scope:** all 20 pages in https://etowahconstruction.com/sitemap.xml:
- 10 main pages,
- 8 project pages,
- 2 job pages.

**What was checked:** visible text, headings, buttons and link text, form labels and messages (including the JavaScript form messages), footer, meta title and description (plus Open Graph), image alt text, and JSON-LD.

**Checks:**
- US English spelling, grammar and typos;
- repeated words and punctuation;
- capitalization, and consistent wording (preferred phrase: "Underground Utility Construction & Site Development");
- phone and email format;
- en dashes in ranges.

Industry terms and names were treated as correct.

**Status:** 19 issues.
- **Fix in the live dashboard:** 15 issues (rows 1–15).
- **Fixed in code or import data:** 4 issues (rows 16–18 and 20). Rows 18 and 20 reach the live site through the import package.
- Row 19 is the seed side of row 15, and row 21 needs no change.

Where an issue is in the live database, the "Source" column says exactly which dashboard field to change.

**Not issues (checked and left as they are):**
- "COMMERCIAL Commercial Storage Facility" and "Roadway Asphalt Asphalt work…" are two separate elements on the card (the market label and the title, or the title and the summary), not repeated words.
- No real repeated words, double punctuation, straight apostrophes, or hyphens in number ranges were found. All ranges use an en dash ("2–4 years").
- Email is always ECG@etowahconstruction.com.
- The phone is always (470) 982-4069, except in the Contact meta description (row 2).
- Hyphenated compounds such as "pump-station construction" and "public-roadway tie-ins" are correct as compound modifiers and were left alone.
- "Roadways & Finishes" (About) and "ROADWAY & STORMWATER" (section label) are the client's wording.

## To fix in the live dashboard

| # | Page | Where | Current text | Suggested fix | Source |
|---|---|---|---|---|---|
| 1 | All 20 pages (footer, "Let's discuss" band) and Contact | Contact name next to the person icon | Etowah construction group | Etowah Construction Group | Site Settings → Contact name (or leave it empty to hide the row; it is meant for a person's name) |
| 2 | Contact | Meta description and sharing description | Discuss your site development, underground utility, pump station, or roadway project with Etowah Construction Group. Call 678-763-8047. | Discuss your site development, underground utility, pump station, or roadway project with Etowah Construction Group. Call (470) 982-4069. | Pages & SEO → Contact → Description (old phone number and format) |
| 3 | Estimator job (H1, About-the-role heading, breadcrumb, meta title, JSON-LD) and Careers card | Job title | Estimator – Underground UtilitiContractores & Site Development | Estimator – Underground Utility Construction & Site Development | Careers → Jobs → Estimator → Job title |
| 4 | Estimator job | Web address (URL) | /careers/estimator-underground-utiliticontractores-and-site-development | /careers/estimator-underground-utility-construction-and-site-development | Careers → Jobs → Estimator → Web address. The old link stops working, which is fine for a job posted today. |
| 5 | Estimator job (text, card excerpt, meta description) | Description, first sentence | Established site development and underground utility contractor based in Cartersville, Georgia is seeking an Estimator with 2–4 years of experience in sitework and underground utility construction. | An established site development and underground utility contractor based in Cartersville, Georgia, is seeking an Estimator with 2–4 years of experience in sitework and underground utility construction. | Careers → Jobs → Estimator → Description |
| 6 | Estimator job | Description, list heading | Typical Projects Include: | Typical projects include: | Careers → Jobs → Estimator → Description |
| 7 | Estimator job | Description, list item | Sidewalks and curbs and gutters | Sidewalks, curbs, and gutters | Careers → Jobs → Estimator → Description |
| 8 | Estimator job | Description, list heading | Preferred Qualifications: | Preferred qualifications: | Careers → Jobs → Estimator → Description |
| 9 | Estimator job | Description, list items (series commas, as used on the rest of the site) | underground utilities, heavy civil or site development construction / water, sanitary sewer and storm drainage construction / excavation, grading and sitework / construction equipment, crew production and material requirements | underground utilities, heavy civil, or site development construction / water, sanitary sewer, and storm drainage construction / excavation, grading, and sitework / construction equipment, crew production, and material requirements | Careers → Jobs → Estimator → Description |
| 10 | Estimator job and Careers card | Location (meta line) | Cartersville, Georgia | Cartersville, GA (the same as the Superintendents job) | Careers → Jobs → Estimator → Location |
| 11 | Superintendents job (text, card excerpt, meta description, JSON-LD) | Description, first paragraph: incomplete sentence, random capitals, preferred phrase | Etowah Construction Group, a North Georgia based Underground Utilities & Site Development Contractor Seeking Experienced Civil/Underground Utility Superintendents for Commercial, Industrial and Municipal project Locations throughout North Georgia. | Etowah Construction Group, a North Georgia-based Underground Utility Construction & Site Development contractor, is seeking experienced civil/underground utility superintendents for commercial, industrial, and municipal project locations throughout North Georgia. | Careers → Jobs → Civil & Underground Utility Construction Superintendents → Description |
| 12 | Superintendents job | Description, first bullet | erosion controls installation & maintenance | erosion control installation & maintenance | same field |
| 13 | Superintendents job | Description, fourth bullet | Leading and mentoring a dedicated field crews | Leading and mentoring dedicated field crews | same field |
| 14 | Superintendents job | Description, last bullet | Experienced required with all types of gravity-flow pipe systems | Experience required with all types of gravity-flow pipe systems | same field |
| 15 | Projects and Family-Owned Grocery Store | Full description (series comma) | Full sitework: clearing, grading, storm, sewer and water utilities, … | Full sitework: clearing, grading, storm, sewer, and water utilities, … | Projects → Family-Owned Grocery Store → Full description (the seed is fixed in code for new installs) |

## Fixed in code or import data

| # | Page | Where | Current text | Fix | Source |
|---|---|---|---|---|---|
| 16 | Underground Utilities | "Sanitary Sewer" card | Sewer mains, manholes, wet wells and utility tie-ins. | Sewer mains, manholes, wet wells, and utility tie-ins. | Code: `underground-utilities.php` |
| 17 | Underground Utilities | Link under "Ready for demanding connections." | Explore Pump Station Services | Explore Pump Stations (the same pattern as "Explore Site Development" / "Explore Underground Utilities") | Code: `underground-utilities.php` |
| 18 | Projects and 2.5-Acre Commercial Site | Full description ("sitework" is the spelling used everywhere else on the site) | Completed all site work scopes, … | Completed all sitework scopes, … | Seed data (`app/seed.php`) plus the import package. The import corrects the live text automatically while it is still the original; no dashboard change is needed. |
| 19 | Family-Owned Grocery Store (new installs) | Full description | storm, sewer and water utilities | storm, sewer, and water utilities | Seed data (`app/seed.php`). The live site needs row 15. |
| 20 | Reliant Market District (arrives with the import) | Summary, description, SEO title, meta description | Site work for… / Site Work | Sitework for… / Sitework | Import data (`tools/project-import/manifest.php`) |
| 21 | Contact (new installs) | Meta description phone | — | The seed already uses (470) 982-4069 | No change needed; the live site needs row 2 |
