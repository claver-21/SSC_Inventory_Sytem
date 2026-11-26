<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\DatabaseBackupService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected DatabaseBackupService $backupService,
        protected AuditLogService $auditLog
    ) {
    }

    public function index(): View
    {
        return view('settings.index', [
            'groupedSettings' => $this->settings->allGrouped(),
            'lastBackupMeta' => session('last_backup_meta'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:255'],
        ]);

        $changes = $this->settings->setMany($validated['settings'], $request->user());

        if (! empty($changes)) {
            $this->auditLog->log(
                action: 'SETTINGS_UPDATED',
                module: 'settings',
                oldValues: collect($changes)->map(fn ($c) => $c['old'])->all(),
                newValues: collect($changes)->map(fn ($c) => $c['new'])->all(),
                description: 'Updated system configuration settings.',
                recordLabel: 'system_settings',
                actor: $request->user()
            );
        }

        return back()->with('success', 'System settings updated successfully.');
    }

    public function downloadBackup(Request $request): Response
    {
        $sql = $this->backupService->generateSqlBackup();
        $sha256 = hash('sha256', $sql);
        $filename = 'pcb_ssc_inventory_backup_' . now()->format('Ymd_His') . '.sql';

        $this->auditLog->log(
            action: 'DATABASE_BACKUP_DOWNLOADED',
            module: 'settings',
            newValues: ['filename' => $filename, 'sha256' => $sha256],
            description: "Downloaded SQL backup {$filename} (SHA-256: {$sha256}).",
            recordLabel: $filename,
            actor: $request->user()
        );

        return response($sql, 200, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($sql),
            'X-Backup-SHA256' => $sha256,
        ]);
    }
}
