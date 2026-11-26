<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'slug' => Role::SLUG_ADMIN,
                'name' => 'Admin',
                'description' => 'Full system management: inventory, categories, borrow approvals, returns, incidents, reports, users, audit logs, settings, and SQL backups.',
                'is_system' => true,
            ],
            [
                'slug' => Role::SLUG_MEMBER,
                'name' => 'SSC Student',
                'description' => 'Views SSC equipment availability, submits borrow and return requests, views own borrow history, reports incidents, and views basic reports.',
                'is_system' => true,
            ],
        ];

        $roleModels = [];
        foreach ($roles as $roleData) {
            $roleModels[$roleData['slug']] = Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }

        $permissions = [
            ['slug' => 'dashboard.view', 'name' => 'View Dashboard', 'module' => 'dashboard'],
            ['slug' => 'inventory.view', 'name' => 'View Inventory Items', 'module' => 'inventory'],
            ['slug' => 'inventory.manage', 'name' => 'Create & Edit Inventory Items', 'module' => 'inventory'],
            ['slug' => 'inventory.archive', 'name' => 'Archive & Restore Inventory Items', 'module' => 'inventory'],
            ['slug' => 'categories.manage', 'name' => 'Manage Categories & Locations', 'module' => 'categories'],
            ['slug' => 'borrowings.view_own', 'name' => 'View Own Borrowing Requests', 'module' => 'borrowings'],
            ['slug' => 'borrowings.view_all', 'name' => 'View All Borrowing Requests', 'module' => 'borrowings'],
            ['slug' => 'borrowings.request', 'name' => 'Submit Borrowing & Return Requests', 'module' => 'borrowings'],
            ['slug' => 'borrowings.approve', 'name' => 'Approve & Reject Borrowing Requests', 'module' => 'borrowings'],
            ['slug' => 'borrowings.process_return', 'name' => 'Process Returns & Damage Inspections', 'module' => 'borrowings'],
            ['slug' => 'incidents.report', 'name' => 'Report Equipment or Security Incidents', 'module' => 'incidents'],
            ['slug' => 'incidents.resolve', 'name' => 'Investigate & Resolve Incidents', 'module' => 'incidents'],
            ['slug' => 'stock.view', 'name' => 'View Stock Movements', 'module' => 'stock'],
            ['slug' => 'stock.record', 'name' => 'Record Stock Movements', 'module' => 'stock'],
            ['slug' => 'procurement.view', 'name' => 'View Procurement & Suppliers', 'module' => 'procurement'],
            ['slug' => 'procurement.manage', 'name' => 'Record Acquisitions & Manage Suppliers', 'module' => 'procurement'],
            ['slug' => 'audits.view', 'name' => 'View Physical Inventory Audits', 'module' => 'audits'],
            ['slug' => 'audits.conduct', 'name' => 'Create & Conduct Physical Audits', 'module' => 'audits'],
            ['slug' => 'audits.approve', 'name' => 'Approve Audit Stock Adjustments', 'module' => 'audits'],
            ['slug' => 'reports.view_basic', 'name' => 'View Basic Inventory Reports', 'module' => 'reports'],
            ['slug' => 'reports.view_all', 'name' => 'View All Management Reports', 'module' => 'reports'],
            ['slug' => 'reports.export', 'name' => 'Print & Export Reports', 'module' => 'reports'],
            ['slug' => 'users.manage', 'name' => 'Manage Users & Role Permissions', 'module' => 'users'],
            ['slug' => 'audit_logs.view', 'name' => 'View System Audit Logs', 'module' => 'audit_logs'],
            ['slug' => 'settings.manage', 'name' => 'Manage System Settings & Backups', 'module' => 'settings'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['slug']] = Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        $adminIds = collect($permissionModels)->pluck('id')->all();

        $memberSlugs = [
            'dashboard.view',
            'inventory.view',
            'borrowings.view_own',
            'borrowings.request',
            'incidents.report',
            'reports.view_basic',
            'reports.export',
        ];

        $roleModels[Role::SLUG_ADMIN]->permissions()->sync($adminIds);
        $roleModels[Role::SLUG_MEMBER]->permissions()->sync(
            collect($memberSlugs)->map(fn (string $slug) => $permissionModels[$slug]->id)->all()
        );
    }
}
