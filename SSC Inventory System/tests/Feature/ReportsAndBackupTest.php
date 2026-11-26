<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReportsAndBackupTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@pcb-ssc.edu.ph')->firstOrFail();
        $this->student = User::where('email', 'student@pcb-ssc.edu.ph')->firstOrFail();
    }

    public function test_five_streamlined_reports_export_pdf_excel_and_csv(): void
    {
        $this->assertCount(5, ReportService::REPORTS);

        foreach (array_keys(ReportService::REPORTS) as $slug) {
            $this->actingAsVerified($this->admin)
                ->get(route('reports.show', $slug))
                ->assertStatus(200)
                ->assertSee('POLYTECHNIC COLLEGE OF BOTOLAN')
                ->assertSee('STUDENT SUPREME COUNCIL');
        }

        // PDF export
        $pdfRes = $this->actingAsVerified($this->admin)->get(route('reports.pdf', 'complete-inventory'));
        $pdfRes->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $pdfRes->getContent());

        // Excel (.xlsx) export
        $xlsxRes = $this->actingAsVerified($this->admin)->get(route('reports.excel', 'complete-inventory'));
        $xlsxRes->assertStatus(200);
        $this->assertStringStartsWith('PK', $xlsxRes->getContent());

        // CSV export
        $csvRes = $this->actingAsVerified($this->admin)->get(route('reports.csv', 'complete-inventory'));
        $csvRes->assertStatus(200);
        $this->assertStringContainsString('POLYTECHNIC COLLEGE OF BOTOLAN', $csvRes->streamedContent());

        // Member gets 403 on officer/admin-only reports
        $this->actingAsVerified($this->student)->get(route('reports.show', 'borrowed-overdue'))->assertStatus(403);
        $this->actingAsVerified($this->student)->get(route('reports.show', 'accountability'))->assertStatus(200);
    }

    public function test_audit_log_immutability_and_step_up_sql_backup_with_sha256(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'action' => 'IMMUTABLE_TEST',
            'module' => 'security',
            'description' => 'Test entry',
            'created_at' => now(),
        ]);

        try {
            $log->update(['description' => 'Modified']);
            $this->fail('Expected RuntimeException on AuditLog update.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        try {
            $log->delete();
            $this->fail('Expected RuntimeException on AuditLog delete.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        // Backup requires Step-Up MFA
        $this->actingAsVerified($this->admin, false)
            ->get(route('settings.backup'))
            ->assertRedirect(route('mfa.step-up'));

        // Step-Up verified returns SQL + valid SHA-256 header
        $res = $this->actingAsVerified($this->admin, true)
            ->get(route('settings.backup'));
        $res->assertStatus(200);

        $sql = $res->getContent();
        $headerHash = $res->headers->get('X-Backup-SHA256');
        $this->assertSame(hash('sha256', $sql), $headerHash);
        $this->assertStringContainsString('inventory_items', $sql);
    }
}
