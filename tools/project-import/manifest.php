<?php
/*
 * Source for dist/etowah-projects-import.zip (built by tools/build-project-import.php).
 * Photos and videos are read from client-media/ (originals, never published or committed).
 *
 * Honesty rules for all text here: describe only what the photos show, plus the location and the client's notes.
 * No invented sizes, footages, dates, client names, budgets, or outcomes. Neutral wording for current work.
 *
 * crop: [left, top, right, bottom] as fractions of the upright photo (applied before resizing).
 */

return [
    // Homepage spots. Each is only changed on the live site if it still holds the original (seeded) project.
    'home' => ['card1' => 'alpharetta-commercial', 'card2' => 'chattanooga-rebuild', 'banner' => 'bartow-pump-station'],
    'home_defaults' => ['card1' => 'chattanooga-rebuild', 'card2' => 'alpharetta-commercial', 'banner' => 'cobb-county-asphalt'],

    'projects' => [
        // ---------------------------------------------------------------- existing: Alpharetta (current work)
        [
            'slug' => 'alpharetta-commercial', 'mode' => 'update', 'folder' => 'Alpharetta',
            'fill' => [
                'description' => [
                    'value' => "Completed all site work scopes, including 1,340 LF of sewer piping on and off site, 1,480 LF of water piping on and off site, 1,220 LF of storm piping, and a 52,000-cubic-foot underground retention pond.\n\nPhotos show clearing and grading, deep utility trenches, precast manholes, the underground stormwater modules, and utility work in the adjacent street.",
                    'if' => [
                        'Completed all site work scopes, including 1,340 LF of sewer piping on and off site, 1,480 LF of water piping on and off site, 1,220 LF of storm piping, and a 52,000-cubic-foot underground retention pond.',
                        'Completing all site work scopes, including 1,340 LF of sewer piping on and off site, 1,480 LF of water piping on and off site, 1,220 LF of storm piping, and a 52,000-cubic-foot underground retention pond.',
                    ],
                ],
                'seo_title'       => ['value' => '2.5-Acre Commercial Site in Alpharetta, GA | Etowah'],
                'seo_description' => ['value' => 'Commercial sitework in Alpharetta, GA: 1,340 LF of sewer, 1,480 LF of water, 1,220 LF of storm piping, and 52,000 cubic feet of underground retention.'],
            ],
            'main' => ['file' => 'logo_2026-10-20_14-32-05-410.jpeg', 'crop' => [0, 0, 1, 0.9],
                'alt' => 'Excavator digging beside precast manholes and stacked underground stormwater modules on a commercial site in Alpharetta, Georgia'],
            'gallery' => [
                ['file' => 'logo_2026-07-15_16-55-36-261.jpeg', 'alt' => 'Excavator clearing and stockpiling soil at the start of a commercial site in Alpharetta, Georgia'],
                ['file' => 'logo_2026-10-02_16-51-22-000.jpeg', 'alt' => 'Dozer, roller, and skid steer grading a commercial site in Alpharetta, Georgia, beside a pile of rip rap'],
                ['file' => 'logo_2026-10-14_16-29-29-888.jpeg', 'alt' => 'Excavator and dozer working beside stacked underground stormwater modules in Alpharetta, Georgia'],
                ['file' => 'logo_2026-10-14_16-25-12-194.jpeg', 'alt' => 'Open excavation with a precast manhole section on a commercial site in Alpharetta, Georgia'],
                ['file' => 'logo_2026-10-16_19-34-23-087.jpeg', 'alt' => 'Roller compacting soil near precast manhole sections and an open excavation in Alpharetta, Georgia'],
                ['file' => 'logo_2026-10-12_17-22-46-480.jpeg', 'crop' => [0, 0, 1, 0.83], 'alt' => 'Crew setting a precast manhole on gravel bedding with green PVC pipe in Alpharetta, Georgia'],
                ['file' => 'logo_2026-07-30_18-21-59-713.jpeg', 'alt' => 'Deep utility trench with a trench box beside existing buildings in Alpharetta, Georgia'],
                ['file' => 'logo_2026-09-02_16-58-02-549.jpeg', 'alt' => 'Compact excavator working in a deep utility excavation in Alpharetta, Georgia'],
                ['file' => 'logo_2026-09-02_16-58-04-813.jpeg', 'alt' => 'Crew member laying green PVC pipe in a deep trench beside a compact excavator in Alpharetta, Georgia'],
                ['file' => 'logo_2026-09-02_13-00-24.jpeg', 'alt' => 'Excavator bucket in a narrow trench below existing utility lines in Alpharetta, Georgia'],
                ['file' => 'logo_2026-08-10_15-34-54-790.jpeg', 'alt' => 'Trench box and green PVC pipe in a trench cut along a public street in Alpharetta, Georgia'],
                ['file' => 'logo_2026-07-22_14-26-37-762.jpeg', 'alt' => 'Precast manhole top set at grade beside a trench compactor in Alpharetta, Georgia'],
            ],
        ],

        // ---------------------------------------------------------------- existing: Chattanooga rebuild (folder "Tiftonia", current work)
        [
            'slug' => 'chattanooga-rebuild', 'mode' => 'update', 'folder' => 'Tiftonia',
            'fill' => [
                'location' => ['value' => 'Tiftonia, Chattanooga, TN', 'if' => ['Chattanooga, TN']],
                'description' => [
                    'value' => "Complete demolition and reconstruction requiring a 35-foot specialty excavation for a new wet well, installation of all-new equipment, and a utility tie-in within a public roadway.\n\nPhotos show the shored excavation in Tiftonia, with steel trench boxes, bracing, and crews working at the base of the pit.",
                    'if' => ['Complete demolition and reconstruction requiring a 35-foot specialty excavation for a new wet well, installation of all-new equipment, and a utility tie-in within a public roadway.'],
                ],
                'seo_title'       => ['value' => 'Sewage Pump Station Rebuild in Chattanooga, TN | Etowah'],
                'seo_description' => ['value' => 'Pump station rebuild in Tiftonia, Chattanooga, TN: a 35-foot specialty excavation for a new wet well, all-new equipment, and a roadway utility tie-in.'],
            ],
            'main' => ['file' => 'logo_2026-06-04_16-16-08-601.jpeg',
                'alt' => 'Excavator working inside a trench box at a roadside excavation in Tiftonia, Chattanooga, Tennessee'],
            'gallery' => [
                ['file' => 'logo_2026-08-25_07-47-20-969.jpeg', 'alt' => 'Compact excavator beside steel trench shoring at the excavation in Tiftonia, Chattanooga, Tennessee'],
                ['file' => 'logo_2026-10-21_11-32-10-892.jpeg', 'alt' => 'Looking along a shored excavation with steel bracing and a GPS survey rover in Tiftonia, Chattanooga, Tennessee'],
                ['file' => 'logo_2026-10-11_21-19-56-522.jpeg', 'alt' => 'Crew working at the base of a deep shored excavation in Tiftonia, Chattanooga, Tennessee'],
                ['file' => 'logo_2026-10-21_11-32-04-482.jpeg', 'alt' => 'Deep shored excavation with a dewatering hose at the base in Tiftonia, Chattanooga, Tennessee'],
            ],
        ],

        // ---------------------------------------------------------------- existing: Bartow water pump station (folder "Highway 75 Pump Station")
        [
            'slug' => 'bartow-pump-station', 'mode' => 'update', 'folder' => 'Highway 75 Pump Station',
            'fill' => [
                'description' => [
                    'value' => "Installed 24-inch restrained-joint ductile iron pipe, interior station piping and pumps, with excavation to a depth of 25 feet.\n\nPhotos show the shotcrete-faced excavation, the reinforced concrete structure and large station piping, precast vaults, and valve work on the water line.",
                    'if' => ['Installed 24-inch restrained-joint ductile iron pipe, interior station piping and pumps, with excavation to a depth of 25 feet.'],
                ],
                'seo_title'       => ['value' => 'Water Pump Station in Bartow County, GA | Etowah'],
                'seo_description' => ['value' => 'Water pump station in Bartow County, GA: 24-inch restrained-joint ductile iron pipe, interior station piping and pumps, and excavation to 25 feet.'],
            ],
            'main' => ['file' => 'logo_2025-06-23_09-06-22.jpeg',
                'alt' => 'Crew and drill rig working along a shotcrete-faced excavation wall at a water pump station site in Bartow County, Georgia'],
            'gallery' => [
                ['file' => 'IMG_4054.jpg', 'alt' => 'Compact excavator in the excavation below a shotcrete-faced wall at the pump station site in Bartow County, Georgia'],
                ['file' => 'logo_2025-07-22_19-35-36.jpeg', 'alt' => 'Excavator grading the floor of the pump station excavation in Bartow County, Georgia'],
                ['file' => 'logo_2025-08-28_15-10-16.jpeg', 'alt' => 'Rebar cages and forms for the concrete pump station structure, with large pipe set in place, in Bartow County, Georgia'],
                ['file' => 'logo_2025-09-17_12-32-23.jpeg', 'alt' => 'Concrete walls of the pump station structure inside the excavation in Bartow County, Georgia'],
                ['file' => 'HWY 75 PS_20251111_155425.jpg', 'alt' => 'Large flanged pipe and tee inside the concrete pump station structure in Bartow County, Georgia'],
                ['file' => 'logo_2026-07-16_15-51-31-277.jpeg', 'alt' => 'Blue valves and station piping inside the pump station in Bartow County, Georgia'],
                ['file' => 'logo_2025-07-03_10-23-41.jpeg', 'alt' => 'Crew setting a precast manhole on a large pipe in a trench at the pump station site in Bartow County, Georgia'],
                ['file' => 'logo_2025-09-16_09-02-02.jpeg', 'alt' => 'Restrained-joint ductile iron pipe entering a precast structure in Bartow County, Georgia'],
                ['file' => 'logo_2026-02-27_14-42-46.jpeg', 'alt' => 'Ductile iron water line and red gate valve leading to a precast vault in Bartow County, Georgia'],
                ['file' => 'logo_2026-02-27_15-11-32.jpeg', 'alt' => 'Excavator setting a precast concrete vault in Bartow County, Georgia'],
                ['file' => 'logo_2026-04-08_12-55-51-715.jpeg', 'alt' => 'Crew connecting a valve on a large water line inside a trench box in Bartow County, Georgia'],
            ],
        ],

        // ---------------------------------------------------------------- new: Blue Ridge (finished work)
        [
            'slug' => 'blue-ridge-pump-station', 'mode' => 'new', 'folder' => 'Blue Ridge', 'sort_after' => 'bartow-pump-station',
            'project' => [
                'title' => 'Blue Ridge Pump Station', 'status' => 'completed', 'client_name' => null, 'location' => 'Blue Ridge, GA',
                'market' => 'municipal', 'primary_service' => 'pump-stations',
                'summary' => 'Pump station work in Blue Ridge, Georgia: a precast wet well with a lined interior, two submersible pumps, discharge piping, and a hatch slab.',
                'description' => "Pump station work in Blue Ridge, Georgia. The precast concrete wet well has a lined interior, an access ladder, guide rails, and two submersible pumps with their discharge piping.\n\nAt the surface, the station has a concrete top slab with an access hatch and stainless control panels on a steel stand. Trailer-mounted pumps and hoses were staged on site while the work was underway.",
                'seo_title' => 'Pump Station in Blue Ridge, GA | Etowah Construction',
                'seo_description' => 'Pump station work in Blue Ridge, Georgia: a precast wet well with a lined interior, two submersible pumps, discharge piping, and an access hatch slab.',
                'services' => ['pump-stations'],
            ],
            'main' => ['file' => 'logo_2026-06-02_18-29-20-462.jpeg',
                'alt' => 'Pump station top slab with an access hatch beside stainless control panels in Blue Ridge, Georgia'],
            'gallery' => [
                ['file' => 'logo_2026-04-01_21-54-42-850.jpeg', 'alt' => 'Precast wet well top with hatch framing, with trailer-mounted pumps and a telehandler on site in Blue Ridge, Georgia'],
                ['file' => 'logo_2026-04-01_21-54-43-244.jpeg', 'alt' => 'Open precast wet well with a lined interior, discharge pipes, and an access ladder in Blue Ridge, Georgia'],
                ['file' => 'logo_2026-04-22_17-14-44-631.jpeg', 'alt' => 'Looking down into the lined wet well with guide rails and a ventilation duct in Blue Ridge, Georgia'],
                ['file' => 'logo_2026-05-07_16-27-51-963.jpeg', 'alt' => 'Two submersible pumps at the bottom of the wet well in Blue Ridge, Georgia'],
            ],
        ],

        // ---------------------------------------------------------------- new: Twigs Farmers Market (finished work; location to confirm)
        [
            'slug' => 'twigs-farmers-market', 'mode' => 'new', 'folder' => 'Twigs Farmers Market', 'sort_after' => 'blue-ridge-pump-station',
            'project' => [
                'title' => 'Twigs Farmers Market', 'status' => 'completed', 'client_name' => null, 'location' => null,
                'market' => 'commercial', 'primary_service' => 'roadways-stormwater',
                'summary' => 'Sitework for Twigs Farmers Market, including mass excavation, a lined underground stormwater storage system, and a precast concrete tank.',
                'description' => "Sitework for Twigs Farmers Market. Crews carried out mass excavation and built a lined underground stormwater storage system: a compacted gravel base, a liner, and modular storage units assembled into one large bed.\n\nThe work also included setting a two-compartment precast concrete tank in a trench with a boom truck, shown in the project video.",
                'seo_title' => 'Twigs Farmers Market Sitework | Etowah Construction',
                'seo_description' => 'Sitework for Twigs Farmers Market: mass excavation, a lined underground stormwater storage system, and a precast concrete tank set by boom truck.',
                'services' => ['roadways-stormwater', 'site-development'],
            ],
            'main' => ['file' => '(2) logo_2025-12-13_10-22-04.jpeg',
                'alt' => 'Modular underground stormwater storage system laid in a lined excavation at Twigs Farmers Market'],
            'gallery' => [
                ['file' => 'logo_2025-11-05_13-53-14.jpeg', 'alt' => 'Excavator and deep excavation at the Twigs Farmers Market site'],
                ['file' => 'logo_2025-12-13_10-25-40.jpeg', 'alt' => 'Dozer and excavator on mass excavation near a signalized intersection at Twigs Farmers Market'],
                ['file' => '(3) logo_2025-12-13_10-22-04.jpeg', 'alt' => 'Roller compacting the gravel base inside the lined excavation at Twigs Farmers Market'],
                ['file' => 'logo_2025-12-13_10-22-04.jpeg', 'alt' => 'Liner and the first stacked storage modules on the gravel base at Twigs Farmers Market'],
                ['file' => 'logo_2025-12-13_10-19-15.jpeg', 'alt' => 'Crew placing modular stormwater storage units and a pipe connection at Twigs Farmers Market'],
                ['file' => 'logo_2026-02-15_11-22-55.jpeg', 'alt' => 'Two-compartment precast concrete tank set in a trench at Twigs Farmers Market'],
                ['type' => 'video', 'file' => '2026-02-15_11-22-55.mp4', 'poster_at' => 6, 'audio' => false,
                    'title' => 'Setting a precast concrete tank at Twigs Farmers Market',
                    'alt' => 'A boom truck lowers a two-compartment precast concrete tank into a trench at the Twigs Farmers Market site.'],
            ],
        ],

        // ---------------------------------------------------------------- new: Reliant Market District (finished work; location to confirm)
        [
            'slug' => 'reliant-market-district', 'mode' => 'new', 'folder' => 'Reliant Market District', 'sort_after' => 'twigs-farmers-market',
            'project' => [
                'title' => 'Reliant Market District', 'status' => 'completed', 'client_name' => null, 'location' => null,
                'market' => 'commercial', 'primary_service' => 'site-development',
                'summary' => 'Site work for Reliant Market District, including grading, a footing trench with rebar, a storm drain inlet, concrete curb, and asphalt paving.',
                'description' => "Site work for Reliant Market District. Photos from the site show grading with compact excavators, a PVC cleanout and wood formwork on a building pad, and a storm drain inlet.\n\nLater work included a long footing trench with rebar along the site fence, new concrete curb, and fresh asphalt paving next to the townhomes.",
                'seo_title' => 'Reliant Market District Site Work | Etowah Construction',
                'seo_description' => 'Site work at Reliant Market District: grading, a footing trench with rebar, a storm drain inlet, new concrete curb, and asphalt paving.',
                'services' => ['site-development', 'roadways-stormwater'],
            ],
            'main' => ['file' => 'logo_2026-06-21_11-57-30-961.jpeg', 'crop' => [0, 0.16, 1, 0.66],
                'alt' => 'New asphalt paving and concrete curb next to townhomes at Reliant Market District'],
            'gallery' => [
                ['file' => 'logo_2026-06-21_11-57-25-141.jpeg', 'alt' => 'Compact excavator grading a building pad with a PVC cleanout and wood formwork at Reliant Market District'],
                ['file' => 'logo_2026-06-21_11-57-26-550.jpeg', 'alt' => 'Compact excavator on a wet site beside a storm drain inlet at Reliant Market District'],
                ['file' => 'logo_2026-06-21_11-58-31-797.jpeg', 'alt' => 'Rebar set in a long footing trench along the site fence at Reliant Market District'],
            ],
        ],
    ],
];
