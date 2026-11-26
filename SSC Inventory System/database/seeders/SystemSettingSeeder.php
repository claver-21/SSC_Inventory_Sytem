<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'group' => 'organization',
                'key' => 'institution_name',
                'value' => 'POLYTECHNIC COLLEGE OF BOTOLAN',
                'type' => 'string',
                'label' => 'Institution Name',
                'description' => 'Official college name printed on headers and official reports.',
            ],
            [
                'group' => 'organization',
                'key' => 'organization_name',
                'value' => 'STUDENT SUPREME COUNCIL',
                'type' => 'string',
                'label' => 'Organization Name',
                'description' => 'Official council name printed on headers and official reports.',
            ],
            [
                'group' => 'organization',
                'key' => 'campus_address',
                'value' => 'Botolan, Zambales, Philippines',
                'type' => 'string',
                'label' => 'Campus Address',
                'description' => 'Institutional address shown in official report subheaders.',
            ],
            [
                'group' => 'inventory',
                'key' => 'property_code_prefix',
                'value' => 'PCB-SSC',
                'type' => 'string',
                'label' => 'Default Property Code Prefix',
                'description' => 'Prefix used when auto-generating unique property codes.',
            ],
            [
                'group' => 'inventory',
                'key' => 'default_low_stock_threshold',
                'value' => '2',
                'type' => 'integer',
                'label' => 'Default Low Stock Threshold',
                'description' => 'Default minimum available quantity before flagging low stock.',
            ],
            [
                'group' => 'borrowing',
                'key' => 'default_borrowing_days',
                'value' => '7',
                'type' => 'integer',
                'label' => 'Default Borrowing Duration (Days)',
                'description' => 'Suggested borrowing window when creating new borrowing requests.',
            ],
            [
                'group' => 'borrowing',
                'key' => 'upcoming_due_notice_days',
                'value' => '1',
                'type' => 'integer',
                'label' => 'Upcoming Return Reminder Window (Days)',
                'description' => 'Days before expected return date to send upcoming deadline notifications.',
            ],
            [
                'group' => 'reports',
                'key' => 'report_prepared_by_title',
                'value' => 'SSC Inventory Custodian',
                'type' => 'string',
                'label' => 'Report Signatory 1 Title (Prepared by)',
                'description' => 'Role title under the Prepared by signature line.',
            ],
            [
                'group' => 'reports',
                'key' => 'report_noted_by_name',
                'value' => 'SSC President',
                'type' => 'string',
                'label' => 'Report Signatory 2 Name (Noted by)',
                'description' => 'Name or designation under Noted by on official reports.',
            ],
            [
                'group' => 'reports',
                'key' => 'report_noted_by_title',
                'value' => 'Student Supreme Council President',
                'type' => 'string',
                'label' => 'Report Signatory 2 Title (Noted by)',
                'description' => 'Designation title under Noted by on official reports.',
            ],
            [
                'group' => 'reports',
                'key' => 'report_approved_by_name',
                'value' => 'SSC Adviser / SASO Director',
                'type' => 'string',
                'label' => 'Report Signatory 3 Name (Approved by)',
                'description' => 'Name or designation under Approved by on official reports.',
            ],
            [
                'group' => 'reports',
                'key' => 'report_approved_by_title',
                'value' => 'Student Affairs & Services Office',
                'type' => 'string',
                'label' => 'Report Signatory 3 Title (Approved by)',
                'description' => 'Designation title under Approved by on official reports.',
            ],
            [
                'group' => 'security',
                'key' => 'idle_timeout_minutes',
                'value' => '15',
                'type' => 'integer',
                'label' => 'Standard Idle Timeout (Minutes)',
                'description' => 'Automatically logs out inactive SSC Student sessions.',
            ],
            [
                'group' => 'security',
                'key' => 'admin_idle_timeout_minutes',
                'value' => '10',
                'type' => 'integer',
                'label' => 'Admin Idle Timeout (Minutes)',
                'description' => 'Automatically logs out inactive Admin sessions.',
            ],
            [
                'group' => 'security',
                'key' => 'allowed_email_domain',
                'value' => 'pcb-ssc.edu.ph',
                'type' => 'string',
                'label' => 'Allowed SSC Email Domain',
                'description' => 'Only institutional SSC accounts matching this domain may register and log in.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
