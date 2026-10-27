# Inventory of the original static site (before PHP conversion)

Captured from the `.html` files at commit `2142c5c`. Baseline screenshots (375/768/1024/1440) were taken with headless Chrome before any change.

## Shared chrome

- **Head:** charset, viewport, title, description, og:type=website, og:site_name "Etowah Construction Group", og:title (= title), og:description (= description), og:image (page hero, relative path), theme-color `#262F36`, icon + apple-touch-icon `logo-icon.png`, Google Fonts preconnect + `Archivo:wght@700;800&family=Inter:wght@400;500;600`, `style.css`, `main.js` (defer). `<html lang="en" class="no-js">`.
- **Header:** brand `logo-horizontal.png` 739×128 alt "Etowah Construction Group"; nav-toggle (menu/close icons); nav: Services dropdown (4 services) | Markets | Projects | About | Contact + `btn btn-primary nav-cta` "Discuss Your Project".
  - Active state: `aria-current="page"` on the nav-link; on service pages the dropdown button gets `is-active` and the dropdown link gets `aria-current="page"`.
- **CTA band** (`footer.site-footer` → `.cta-row`): h2.cta-title (per page), contact name, tel `+16787638047` "678-763-8047", mailto Kenny@etowahconstruction.com, "Get in Touch" → contact.
  - Titles: default "Let’s discuss your next project."; site-dev "…your site development project."; utilities "…your utility project."; pump "…your pump station project."; roadways "…your roadway project."
- **Footer grid:** brand col (logo, "Licensed Utility Contractor, State of Georgia. License No. UC002776.", "Municipal • Commercial • Residential • Roadway"), Menu (Home, Markets, Projects, About, Contact), Services (4), Contact (name, phone, email, "Cartersville, Georgia"). Legal: "© 2026 Etowah Construction Group LLC · Licensed Utility Contractor, State of Georgia · License No. UC002776".
- **Contact page** uses `site-footer site-footer--compact` (no CTA row).
- CTA background image `assets/images/sections/cta-bg.jpg` (2100×900) is set in CSS on `.site-footer`.

## Pages (title / description / hero)

| key | title | hero (w×h) | hero alt |
|---|---|---|---|
| home | Etowah Construction Group \| Site Development &amp; Utility Construction in Georgia | heroes/hero-home.jpg 1920×1080 | Excavator working beside a long open utility trench with pipe in warm morning light |
| site-development | Site Development \| Etowah Construction Group | heroes/hero-site-development.jpg 1487×836 | Bulldozers grading red clay across a large earthwork site |
| underground-utilities | Underground Utilities \| Etowah Construction Group | heroes/hero-underground-utilities.jpg 1487×836 | Open utility trench with gravel bedding and pipe sections staged along the site |
| pump-stations | Pump Stations \| Etowah Construction Group | heroes/hero-pump-stations.jpg 1690×950 | Deep shored excavation with access ladders and formwork at the base |
| roadways-stormwater | Roadways &amp; Stormwater \| Etowah Construction Group | heroes/hero-roadways.jpg 1690×950 | Asphalt paver laying fresh pavement on a road through a pine forest |
| markets | Markets \| Etowah Construction Group | heroes/hero-markets.jpg 1920×1080 | Concrete pipe sections staged beside a newly paved road with fresh curb |
| projects | Projects \| Etowah Construction Group | heroes/hero-projects.jpg 1920×1080 | Aerial view of an active construction site with equipment and staged materials |
| about | About \| Etowah Construction Group | heroes/hero-about.jpg 1690×950 | Utility crew in high-visibility shirts working together at a roadside excavation |
| contact | Contact \| Etowah Construction Group | heroes/hero-contact.jpg 1920×1080 | Excavator parked on a quiet jobsite at dusk |
| careers (new) | Careers \| Etowah Construction Group | heroes/hero-careers.jpg (missing → copy of hero-about, placeholder) | — |

Descriptions are copied verbatim into `app/seed.php`.

## Other image slots

| page / slot | path | size | alt |
|---|---|---|---|
| site-development / proof | sections/proof-site-development.jpg | 1200×900 | Excavator and roller working on red clay beside a block retaining wall |
| underground-utilities / proof | sections/proof-underground-utilities.jpg | 1200×900 | Pipe laid on gravel bedding in an open trench |
| pump-stations / proof | sections/proof-pump-stations.jpg | 1200×900 | Worker setting a concrete structure in a deep excavation beside an excavator bucket |
| roadways-stormwater / proof | sections/proof-roadways.jpg | 1200×900 | Crew member finishing fresh concrete against a curb form |
| about / team | sections/about-team.jpg | 1200×900 | Two site supervisors in hard hats reviewing plans on a jobsite |
| markets / municipal, commercial, residential, roadway | projects/bartow…, alpharetta…, residential…, cobb… | 1200×800 | same alts as the projects |
| global / cta-bg | sections/cta-bg.jpg | 2100×900 | (decorative CSS background) |

## Page sections

- **Home:** hero--home (eyebrow CARTERSVILLE, GEORGIA; h1 "Built from<br> the ground up."; two buttons) → stats (4, from settings) → capabilities (4 cap-cards, static) → Selected Projects (2 `project-card--plain` cards + `project-banner`).
  - Card meta = `STATUS • LOCATION`, text = project summary. Banner meta = `STATUS • LOCATION`, title only.
- **Service pages:** hero (breadcrumb Home / Services / X, CTA button) → intro + scope panel (static) → scope cards (static) → proof (image slot + static checklist) → Experience (dynamic).
  - Experience grid: `project-grid--3` when 3 cards, else `project-grid`. Card meta = `(LOCATION or MARKET) • STATUS`.
  - Card titles/text differ per service page, so they are stored per service in `project_services`.
  - Exception: the utilities page Bartow card shows only "BARTOW COUNTY, GA" (no status); it now shows "• COMPLETED" too, for consistency.
  - Orders: site-dev = residential, grocery, storage; utilities = bartow, alpharetta; pump = rebuild, rehab, bartow; roadways = cobb, alpharetta.
- **Markets:** hero → intro-center + jump strip → 4 market rows (alternating `--flip` / `section--alt`), each with an image, static copy and checklist, Related projects (dynamic), and Related services (static).
- **Projects:** hero → Current (`project-grid`, `project-card--large`) → Completed (`project-grid--3`).
  - Article `id=slug`, meta `STATUS • LOCATION • MARKET` (or `STATUS • MARKET` with no location). Text = "Client. " + description. One tag = the primary service.
- **About:** hero → who we are split (team image slot) → why (3 cards) → core capabilities spec-list → markets on-dark band. All static.
- **Contact:** hero--short → contact card (name, phone, email, city, license, "Serving municipal, commercial, residential, and roadway clients.") + form card (name*, company, email*, phone, project_type select, location, message*).

## Projects (seed, in sort order)

| # | slug | title | status | location | market | client | primary service | services (per-page order) |
|---|---|---|---|---|---|---|---|---|
| 1 | chattanooga-rebuild | Sewage Pump Station Rebuild | current | Chattanooga, TN | municipal | City of Chattanooga | pump | pump(1) — home card1 |
| 2 | alpharetta-commercial | 2.5-Acre Commercial Site | current | Alpharetta, GA | commercial | — | utilities | utilities(2, title "Commercial Site Utilities"), roadways(2) — home card2 |
| 3 | bartow-pump-station | Water Pump Station | completed | Bartow County, GA | municipal | Bartow County Water Department | pump | utilities(1), pump(3) |
| 4 | chattanooga-rehab | Sewage Pump Station Rehabilitation | completed | Chattanooga, TN | municipal | City of Chattanooga | pump | pump(2) |
| 5 | cobb-county-asphalt | 5.5 Miles of Roadway Asphalt | completed | Cobb County, GA | roadway | — | roadways | roadways(1) — home banner |
| 6 | residential-neighborhood | Two-Phase Single-Family Neighborhood | completed | — | residential | — | site-dev | site-dev(1) |
| 7 | grocery-store | Family-Owned Grocery Store | completed | — | commercial | — | site-dev | site-dev(2) |
| 8 | storage-facility | Commercial Storage Facility | completed | — | commercial | — | site-dev | site-dev(3) |

Markets "Related projects" ordering on the old page: municipal = rebuild, rehab, bartow. It now follows the global project order (rebuild, bartow, rehab). The other markets are unchanged.
