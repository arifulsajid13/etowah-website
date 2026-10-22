<?php
declare(strict_types=1);

/*
 * Initial content, taken from the original static pages and the capabilities statement.
 * Run once by the installer. No jobs, applications, or inquiries are created.
 */

/** Create all tables from schema.sql (safe to run again: every table uses IF NOT EXISTS). */
function run_schema(PDO $pdo): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(APP . '/schema.sql'));
    foreach (array_filter(array_map('trim', explode(';', $sql)), 'strlen') as $statement) {
        $pdo->exec($statement);
    }
}

function seed_database(PDO $pdo): void
{
    $pages = [
        'home' => ['Etowah Construction Group | Site Development & Utility Contractor in Georgia', 'Licensed Georgia utility contractor in Cartersville delivering site development, underground utilities, pump stations, and roadway construction for municipal, commercial, and residential clients.'],
        'site-development' => ['Site Development | Etowah Construction Group', 'Clearing, grading, mass earthwork, site preparation, and retaining walls for municipal, commercial, and residential projects in Georgia.'],
        'underground-utilities' => ['Underground Utilities | Etowah Construction Group', 'Water distribution, sanitary sewer, and storm drainage installation for municipal, commercial, and residential projects. Georgia Utility Contractor License UC002776.'],
        'pump-stations' => ['Pump Stations | Etowah Construction Group', 'Pump station new construction and rehabilitation — bypass pumping, wet wells, pumps, process piping, and electrical equipment for municipal water and wastewater systems.'],
        'roadways-stormwater' => ['Roadways & Stormwater | Etowah Construction Group', 'Asphalt paving, DOT-road work, curb and gutter, public-roadway tie-ins, and underground stormwater retention in Georgia.'],
        'markets' => ['Markets | Etowah Construction Group', 'Etowah Construction Group serves municipal water and wastewater, commercial development, residential development, and roadway infrastructure clients in Georgia and Tennessee.'],
        'projects' => ['Projects | Etowah Construction Group', 'Site development, underground utility, pump station, and roadway projects across Georgia and Tennessee.'],
        'about' => ['About | Etowah Construction Group', 'Etowah Construction Group is a licensed Georgia utility contractor in Cartersville delivering site development, underground utility, roadway, and pump-station construction.'],
        'careers' => ['Careers | Etowah Construction Group', 'Join Etowah Construction Group, a licensed Georgia utility contractor in Cartersville. View open positions and apply.'],
        'contact' => ['Contact | Etowah Construction Group', 'Discuss your site development, underground utility, pump station, or roadway project with Etowah Construction Group. Call ' . SETTING_DEFAULTS['phone'] . '.'],
    ];

    // [page, slot, label, path, alt, width, height]
    $images = [
        ['home', 'hero', 'Hero image', 'assets/images/heroes/hero-home.jpg', 'Excavator working beside a long open utility trench with pipe in warm morning light', 1920, 1080],
        ['site-development', 'hero', 'Hero image', 'assets/images/heroes/hero-site-development.jpg', 'Bulldozers grading red clay across a large earthwork site', 1487, 836],
        ['site-development', 'proof', '“Full site packages” photo', 'assets/images/sections/proof-site-development.jpg', 'Excavator and roller working on red clay beside a block retaining wall', 1200, 900],
        ['underground-utilities', 'hero', 'Hero image', 'assets/images/heroes/hero-underground-utilities.jpg', 'Open utility trench with gravel bedding and pipe sections staged along the site', 1487, 836],
        ['underground-utilities', 'proof', '“Complex utility work” photo', 'assets/images/sections/proof-underground-utilities.jpg', 'Pipe laid on gravel bedding in an open trench', 1200, 900],
        ['pump-stations', 'hero', 'Hero image', 'assets/images/heroes/hero-pump-stations.jpg', 'Deep shored excavation with access ladders and formwork at the base', 1690, 950],
        ['pump-stations', 'proof', '“Deep excavation” photo', 'assets/images/sections/proof-pump-stations.jpg', 'Worker setting a concrete structure in a deep excavation beside an excavator bucket', 1200, 900],
        ['roadways-stormwater', 'hero', 'Hero image', 'assets/images/heroes/hero-roadways.jpg', 'Asphalt paver laying fresh pavement on a road through a pine forest', 1690, 950],
        ['roadways-stormwater', 'proof', '“Public roadway work” photo', 'assets/images/sections/proof-roadways.jpg', 'Crew member finishing fresh concrete against a curb form', 1200, 900],
        ['markets', 'hero', 'Hero image', 'assets/images/heroes/hero-markets.jpg', 'Concrete pipe sections staged beside a newly paved road with fresh curb', 1920, 1080],
        ['markets', 'municipal', 'Municipal section photo', 'assets/images/projects/bartow-water-pump-station.jpg', 'Gate valve connected to black ductile iron pipe', 1200, 800],
        ['markets', 'commercial', 'Commercial section photo', 'assets/images/projects/alpharetta-commercial-site.jpg', 'Wheel loader working on a commercial site with reinforcing steel and a crane behind', 1200, 800],
        ['markets', 'residential', 'Residential section photo', 'assets/images/projects/residential-neighborhood.jpg', 'Crew paving a new residential street lined with curb and sidewalks', 1200, 800],
        ['markets', 'roadway', 'Roadway section photo', 'assets/images/projects/cobb-county-asphalt.jpg', 'Asphalt paver laying fresh pavement on a long rural road', 1200, 800],
        ['projects', 'hero', 'Hero image', 'assets/images/heroes/hero-projects.jpg', 'Aerial view of an active construction site with equipment and staged materials', 1920, 1080],
        ['about', 'hero', 'Hero image', 'assets/images/heroes/hero-about.jpg', 'Utility crew in high-visibility shirts working together at a roadside excavation', 1690, 950],
        ['about', 'team', '“Who we are” photo', 'assets/images/sections/about-team.jpg', 'Two site supervisors in hard hats reviewing plans on a jobsite', 1200, 900],
        ['careers', 'hero', 'Hero image', 'assets/images/heroes/hero-careers.jpg', 'Utility crew in high-visibility shirts working together at a roadside excavation', 1690, 950],
        ['contact', 'hero', 'Hero image', 'assets/images/heroes/hero-contact.jpg', 'Excavator parked on a quiet jobsite at dusk', 1920, 1080],
        ['global', 'cta-bg', 'Background of the “Let’s discuss your project” band (all pages)', 'assets/images/sections/cta-bg.jpg', '', 2100, 900],
    ];

    // services: service_key => [sort_order, card_title|null, card_summary|null]
    $projects = [
        [
            'title' => 'Sewage Pump Station Rebuild', 'slug' => 'chattanooga-rebuild', 'status' => 'current',
            'client_name' => 'City of Chattanooga', 'location' => 'Chattanooga, TN', 'market' => 'municipal', 'primary_service' => 'pump-stations',
            'summary' => '35-foot specialty excavation, new equipment, and public-roadway utility tie-in.',
            'description' => 'Complete demolition and reconstruction requiring a 35-foot specialty excavation for a new wet well, installation of all-new equipment, and a utility tie-in within a public roadway.',
            'image' => ['assets/images/projects/chattanooga-pump-station-rebuild.jpg', 'Looking down into a deep circular shaft excavation as equipment is lowered in'],
            'home_position' => 'card1',
            'services' => ['pump-stations' => [1, null, 'Complete demolition and reconstruction with a 35-foot specialty excavation for a new wet well.']],
        ],
        [
            'title' => '2.5-Acre Commercial Site', 'slug' => 'alpharetta-commercial', 'status' => 'current',
            'client_name' => null, 'location' => 'Alpharetta, GA', 'market' => 'commercial', 'primary_service' => 'underground-utilities',
            'summary' => 'Full sitework, water, sewer, storm piping, and underground retention.',
            'description' => 'Completed all site work scopes, including 1,340 LF of sewer piping on and off site, 1,480 LF of water piping on and off site, 1,220 LF of storm piping, and a 52,000-cubic-foot underground retention pond.',
            'image' => ['assets/images/projects/alpharetta-commercial-site.jpg', 'Wheel loader working on a commercial site with reinforcing steel and a crane behind'],
            'home_position' => 'card2',
            'services' => [
                'underground-utilities' => [2, 'Commercial Site Utilities', '1,340 LF sewer • 1,480 LF water • 1,220 LF storm. 52,000 cubic feet of underground retention.'],
                'roadways-stormwater'   => [2, null, '1,220 LF of storm piping and a 52,000-cubic-foot underground retention pond.'],
            ],
        ],
        [
            'title' => 'Water Pump Station', 'slug' => 'bartow-pump-station', 'status' => 'completed',
            'client_name' => 'Bartow County Water Department', 'location' => 'Bartow County, GA', 'market' => 'municipal', 'primary_service' => 'pump-stations',
            'summary' => '24-inch restrained-joint ductile iron, station piping and pumps, with excavation to 25 feet.',
            'description' => 'Installed 24-inch restrained-joint ductile iron pipe, interior station piping and pumps, with excavation to a depth of 25 feet.',
            'image' => ['assets/images/projects/bartow-water-pump-station.jpg', 'Gate valve connected to black ductile iron pipe'],
            'home_position' => 'none',
            'services' => ['underground-utilities' => [1, null, null], 'pump-stations' => [3, null, null]],
        ],
        [
            'title' => 'Sewage Pump Station Rehabilitation', 'slug' => 'chattanooga-rehab', 'status' => 'completed',
            'client_name' => 'City of Chattanooga', 'location' => 'Chattanooga, TN', 'market' => 'municipal', 'primary_service' => 'pump-stations',
            'summary' => 'Bypass pumping, equipment demolition, wet well rehabilitation, and new pumps.',
            'description' => 'Established bypass pumping, demolished existing equipment, rehabilitated the wet well, and installed new pumps.',
            'image' => ['assets/images/projects/chattanooga-pump-station-rehab.jpg', 'Pumps and green process piping inside a pump station'],
            'home_position' => 'none',
            'services' => ['pump-stations' => [2, null, null]],
        ],
        [
            'title' => '5.5 Miles of Roadway Asphalt', 'slug' => 'cobb-county-asphalt', 'status' => 'completed',
            'client_name' => null, 'location' => 'Cobb County, GA', 'market' => 'roadway', 'primary_service' => 'roadways-stormwater',
            'summary' => 'Asphalt work across multiple DOT roads.',
            'description' => 'Completed 5.5 miles of asphalt work across multiple DOT roads.',
            'image' => ['assets/images/projects/cobb-county-asphalt.jpg', 'Asphalt paver laying fresh pavement on a long rural road'],
            'home_position' => 'banner',
            'services' => ['roadways-stormwater' => [1, null, null]],
        ],
        [
            'title' => 'Two-Phase Single-Family Neighborhood', 'slug' => 'residential-neighborhood', 'status' => 'completed',
            'client_name' => null, 'location' => null, 'market' => 'residential', 'primary_service' => 'site-development',
            'summary' => 'Clearing, grading, water distribution, storm drainage, curb and gutter, and asphalt paving, with approximately 85,000 CY of mass dirt movement.',
            'description' => 'Full site development: clearing, grading, water distribution, storm drainage, curb and gutter, and asphalt paving, including approximately 85,000 CY of mass dirt movement.',
            'image' => ['assets/images/projects/residential-neighborhood.jpg', 'Crew paving a new residential street lined with curb and sidewalks'],
            'home_position' => 'none',
            'services' => ['site-development' => [1, null, null]],
        ],
        [
            'title' => 'Family-Owned Grocery Store', 'slug' => 'grocery-store', 'status' => 'completed',
            'client_name' => null, 'location' => null, 'market' => 'commercial', 'primary_service' => 'site-development',
            'summary' => 'Full sitework including grading, utilities, retaining wall, underground stormwater retention, curbing, and asphalt paving.',
            'description' => 'Full sitework: clearing, grading, storm, sewer and water utilities, retaining wall construction, underground stormwater retention, curbing, and asphalt paving.',
            'image' => ['assets/images/projects/grocery-store-sitework.jpg', 'Crew placing concrete paving and curbing on a commercial site'],
            'home_position' => 'none',
            'services' => ['site-development' => [2, null, null]],
        ],
        [
            'title' => 'Commercial Storage Facility', 'slug' => 'storage-facility', 'status' => 'completed',
            'client_name' => null, 'location' => null, 'market' => 'commercial', 'primary_service' => 'site-development',
            'summary' => 'Grading, storm pipe, and water pipe scopes.',
            'description' => 'Site and utility improvements: grading, storm pipe, and water pipe scopes.',
            'image' => ['assets/images/projects/storage-facility.jpg', 'Large corrugated storm pipe staged on a graded site beside an excavator'],
            'home_position' => 'none',
            'services' => ['site-development' => [3, null, null]],
        ],
    ];

    $pdo->beginTransaction();
    try {
        // INSERT IGNORE: running the installer again never overwrites settings or SEO text already edited.
        $stmt = $pdo->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
        foreach (SETTING_DEFAULTS as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $stmt->execute(['schema_version', (string) SCHEMA_VERSION]);

        $stmt = $pdo->prepare('INSERT IGNORE INTO pages (page_key, meta_title, meta_description) VALUES (?, ?, ?)');
        foreach ($pages as $key => [$title, $description]) {
            $stmt->execute([$key, $title, $description]);
        }

        $stmt = $pdo->prepare('INSERT INTO page_images (page_key, slot, label, path, alt, width, height, is_placeholder)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                               ON DUPLICATE KEY UPDATE label = VALUES(label)'); // keeps any replaced photo
        foreach ($images as $row) {
            $stmt->execute($row);
        }

        $insertProject = $pdo->prepare('INSERT INTO projects
            (title, slug, status, client_name, location, market, primary_service, summary, description,
             image_path, image_alt, image_width, image_height, is_placeholder_image, home_position, sort_order, published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1200, 800, 1, ?, ?, 1)');
        $insertService = $pdo->prepare('INSERT INTO project_services (project_id, service_key, sort_order, card_title, card_summary) VALUES (?, ?, ?, ?, ?)');
        $exists = $pdo->prepare('SELECT id FROM projects WHERE slug = ?');
        foreach ($projects as $i => $p) {
            $exists->execute([$p['slug']]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $insertProject->execute([
                $p['title'], $p['slug'], $p['status'], $p['client_name'], $p['location'], $p['market'], $p['primary_service'],
                $p['summary'], $p['description'], $p['image'][0], $p['image'][1], $p['home_position'], ($i + 1) * 10,
            ]);
            $projectId = (int) $pdo->lastInsertId();
            foreach ($p['services'] as $service => [$order, $cardTitle, $cardSummary]) {
                $insertService->execute([$projectId, $service, $order * 10, $cardTitle, $cardSummary]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
