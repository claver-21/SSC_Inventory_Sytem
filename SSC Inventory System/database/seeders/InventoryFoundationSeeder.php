<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryLocation;
use Illuminate\Database\Seeder;

class InventoryFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            [
                'slug' => 'excellent',
                'name' => 'Excellent',
                'severity_rank' => 1,
                'badge_color' => 'emerald',
                'description' => 'Like new or pristine physical condition with zero defects.',
            ],
            [
                'slug' => 'good',
                'name' => 'Good',
                'severity_rank' => 2,
                'badge_color' => 'blue',
                'description' => 'Fully functional with normal minor wear from regular SSC operations.',
            ],
            [
                'slug' => 'fair',
                'name' => 'Fair',
                'severity_rank' => 3,
                'badge_color' => 'amber',
                'description' => 'Functional but shows noticeable wear or cosmetic flaws.',
            ],
            [
                'slug' => 'poor',
                'name' => 'Poor',
                'severity_rank' => 4,
                'badge_color' => 'orange',
                'description' => 'Requires inspection, repair, or close supervision before use.',
            ],
            [
                'slug' => 'non_functional',
                'name' => 'Non-functional',
                'severity_rank' => 5,
                'badge_color' => 'red',
                'description' => 'Inoperable or damaged beyond immediate use.',
            ],
        ];

        foreach ($conditions as $condition) {
            InventoryCondition::updateOrCreate(['slug' => $condition['slug']], $condition);
        }

        $categories = [
            ['code' => 'OFF', 'name' => 'Office Equipment', 'description' => 'Printers, scanners, laminators, shredders, and office machines.'],
            ['code' => 'CMP', 'name' => 'Computer Equipment', 'description' => 'Laptops, desktops, monitors, projectors, routers, and peripherals.'],
            ['code' => 'AVE', 'name' => 'Audio and Visual Equipment', 'description' => 'Microphones, speakers, mixers, amplifiers, cameras, and PA systems.'],
            ['code' => 'FUR', 'name' => 'Furniture and Fixtures', 'description' => 'Tables, chairs, cabinets, podiums, and display boards.'],
            ['code' => 'SPT', 'name' => 'Sports Equipment', 'description' => 'Balls, nets, scoreboards, whistles, and intramural gear.'],
            ['code' => 'EVT', 'name' => 'Event Materials', 'description' => 'Tents, banners, backdrops, stage props, and registration kits.'],
            ['code' => 'ELC', 'name' => 'Electrical Equipment', 'description' => 'Extension cords, voltage regulators, UPS units, and lighting.'],
            ['code' => 'SUP', 'name' => 'School Supplies', 'description' => 'Paper reams, folders, markers, pens, adhesives, and consumables.'],
            ['code' => 'OTH', 'name' => 'Other Organizational Properties', 'description' => 'Miscellaneous Student Supreme Council assets and memorabilia.'],
        ];

        foreach ($categories as $category) {
            InventoryCategory::updateOrCreate(
                ['code' => $category['code']],
                array_merge($category, ['is_active' => true])
            );
        }

        $locations = [
            [
                'name' => 'SSC Main Office',
                'building' => 'Student Center Building',
                'room_number' => 'SC-101',
                'description' => 'Primary Student Supreme Council office and administrative hub.',
            ],
            [
                'name' => 'SSC Storage Room',
                'building' => 'Student Center Building',
                'room_number' => 'SC-102',
                'description' => 'Secured equipment and supply storage room.',
            ],
            [
                'name' => 'Audio-Visual Booth',
                'building' => 'PCB Gymnasium / Covered Court',
                'room_number' => 'GYM-AV1',
                'description' => 'Sound system and multimedia control booth.',
            ],
            [
                'name' => 'Student Activity Center',
                'building' => 'Academic Annex',
                'room_number' => 'SAC-201',
                'description' => 'Shared organizational workspace and event staging area.',
            ],
            [
                'name' => 'Property & Supply Cabinet',
                'building' => 'Student Center Building',
                'room_number' => 'SC-101B',
                'description' => 'Locked cabinet for high-value electronics and sensitive documents.',
            ],
        ];

        foreach ($locations as $location) {
            InventoryLocation::updateOrCreate(
                ['name' => $location['name']],
                array_merge($location, ['is_active' => true])
            );
        }
    }
}
