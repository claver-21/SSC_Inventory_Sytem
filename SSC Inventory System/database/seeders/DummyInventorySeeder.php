<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Database\Seeder;

class DummyInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (InventoryItem::withTrashed()->count() > 0) {
            return;
        }

        $stockService = app(InventoryStockService::class);
        $admin = User::where('email', 'admin@pcb-ssc.edu.ph')->first() ?? User::first();
        if (! $admin) {
            return;
        }

        $categories = InventoryCategory::pluck('id', 'code');
        $conditions = InventoryCondition::pluck('id', 'slug');
        $locations = InventoryLocation::pluck('id', 'name');

        $dummyItems = [
            [
                'property_code' => 'PCB-SSC-CMP-0001',
                'name' => 'Council Official Laptop',
                'description' => 'Dummy demo unit for SSC documentation and event presentations.',
                'category_id' => $categories['CMP'] ?? InventoryCategory::value('id'),
                'brand_model' => 'Lenovo ThinkPad E14 (Dummy)',
                'serial_number' => 'DUMMY-SN-LPT-001',
                'sensitive_notes' => 'Keep locked inside Property Cabinet SC-101B when unattended.',
                'quantity' => 1,
                'minimum_stock_level' => 1,
                'unit_of_measurement' => 'unit',
                'acquisition_cost' => 38500.00,
                'condition_id' => $conditions['excellent'] ?? InventoryCondition::value('id'),
                'location_id' => $locations['Property & Supply Cabinet'] ?? InventoryLocation::value('id'),
                'remarks' => 'Dummy inventory record for demonstration.',
            ],
            [
                'property_code' => 'PCB-SSC-AVE-0001',
                'name' => 'UHF Two-Way Walkie-Talkie Set',
                'description' => 'Dummy communication radios for campus assemblies and intramurals.',
                'category_id' => $categories['AVE'] ?? InventoryCategory::value('id'),
                'brand_model' => 'Baofeng UV-5R (Dummy)',
                'serial_number' => 'DUMMY-SN-WT-010',
                'sensitive_notes' => 'Verify antenna and charger count before & after every event.',
                'quantity' => 10,
                'minimum_stock_level' => 3,
                'unit_of_measurement' => 'unit',
                'acquisition_cost' => 1450.00,
                'condition_id' => $conditions['good'] ?? InventoryCondition::value('id'),
                'location_id' => $locations['SSC Main Office'] ?? InventoryLocation::value('id'),
                'remarks' => 'Dummy inventory record for demonstration.',
            ],
            [
                'property_code' => 'PCB-SSC-AVE-0002',
                'name' => 'Portable PA Sound Speaker',
                'description' => 'Dummy rechargeable PA speaker with wireless handheld microphones.',
                'category_id' => $categories['AVE'] ?? InventoryCategory::value('id'),
                'brand_model' => 'JBL PartyBox Demo',
                'serial_number' => 'DUMMY-SN-PA-002',
                'sensitive_notes' => 'Test microphone battery voltage prior to release.',
                'quantity' => 2,
                'minimum_stock_level' => 1,
                'unit_of_measurement' => 'unit',
                'acquisition_cost' => 12500.00,
                'condition_id' => $conditions['good'] ?? InventoryCondition::value('id'),
                'location_id' => $locations['Audio-Visual Booth'] ?? InventoryLocation::value('id'),
                'remarks' => 'Dummy inventory record for demonstration.',
            ],
            [
                'property_code' => 'PCB-SSC-EVT-0001',
                'name' => 'Event Tarpaulin Stand Kit',
                'description' => 'Dummy adjustable aluminum backdrop stands for council events.',
                'category_id' => $categories['EVT'] ?? InventoryCategory::value('id'),
                'brand_model' => 'Standard 8x8 Frame (Dummy)',
                'serial_number' => 'DUMMY-SN-TS-004',
                'sensitive_notes' => null,
                'quantity' => 4,
                'minimum_stock_level' => 1,
                'unit_of_measurement' => 'set',
                'acquisition_cost' => 1800.00,
                'condition_id' => $conditions['good'] ?? InventoryCondition::value('id'),
                'location_id' => $locations['SSC Storage Room'] ?? InventoryLocation::value('id'),
                'remarks' => 'Dummy inventory record for demonstration.',
            ],
            [
                'property_code' => 'PCB-SSC-FUR-0001',
                'name' => 'Foldable Registration Table',
                'description' => 'Dummy 6-foot folding plastic utility tables for student registration desks.',
                'category_id' => $categories['FUR'] ?? InventoryCategory::value('id'),
                'brand_model' => 'Lifetime 6ft (Dummy)',
                'serial_number' => 'DUMMY-SN-TBL-003',
                'sensitive_notes' => null,
                'quantity' => 3,
                'minimum_stock_level' => 1,
                'unit_of_measurement' => 'piece',
                'acquisition_cost' => 2600.00,
                'condition_id' => $conditions['fair'] ?? InventoryCondition::value('id'),
                'location_id' => $locations['Student Activity Center'] ?? InventoryLocation::value('id'),
                'remarks' => 'Dummy inventory record for demonstration.',
            ],
            [
                'property_code' => 'PCB-SSC-SUP-0001',
                'name' => 'SSC Event Supplies Starter Pack',
                'description' => 'Dummy consumable kit (markers, masking tape, ID lanyards, bond paper).',
                'category_id' => $categories['SUP'] ?? InventoryCategory::value('id'),
                'brand_model' => 'Mixed Stationery (Dummy)',
                'serial_number' => null,
                'sensitive_notes' => null,
                'quantity' => 8,
                'minimum_stock_level' => 3,
                'unit_of_measurement' => 'pack',
                'acquisition_cost' => 450.00,
                'condition_id' => $conditions['excellent'] ?? InventoryCondition::value('id'),
                'location_id' => $locations['SSC Storage Room'] ?? InventoryLocation::value('id'),
                'remarks' => 'Dummy inventory record for demonstration.',
            ],
        ];

        foreach ($dummyItems as $entry) {
            $qty = $entry['quantity'];
            unset($entry['quantity']);

            $item = InventoryItem::create(array_merge($entry, [
                'quantity' => 0,
                'available_quantity' => 0,
                'acquisition_date' => now()->subDays(14)->toDateString(),
                'status' => InventoryItem::STATUS_AVAILABLE,
                'responsible_user_id' => $admin->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]));

            $stockService->recordMovement(
                item: $item,
                movementType: StockMovement::TYPE_STOCK_IN,
                quantity: $qty,
                actor: $admin,
                remarks: 'Initial dummy demo stock seed.'
            );
        }
    }
}
