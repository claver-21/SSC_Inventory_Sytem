<?php

namespace Tests\Feature;

use App\Models\BorrowingRequest;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacAndSecurityTest extends TestCase
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

    public function test_defense_test_3_unauthorized_route_returns_403_and_logs_access_denied(): void
    {
        $this->actingAsVerified($this->student)
            ->get(route('users.index'))
            ->assertStatus(403);

        $this->actingAsVerified($this->student)
            ->get(route('settings.index'))
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ACCESS_DENIED',
            'module' => 'security',
            'record_label' => $this->student->email,
        ]);
    }

    public function test_own_record_rule_blocks_member_idor_and_logs_access_denied(): void
    {
        $item = InventoryItem::firstOrFail();

        $adminLoan = BorrowingRequest::create([
            'transaction_code' => 'BOR-IDOR-001',
            'requester_user_id' => $this->admin->id,
            'borrower_name' => $this->admin->name,
            'school_id' => 'PCB-OSAS-001',
            'department_organization' => 'SSC',
            'purpose' => 'Council Meeting',
            'request_date' => now()->toDateString(),
            'date_borrowed' => now()->toDateString(),
            'expected_return_date' => now()->addDays(2)->toDateString(),
            'status' => BorrowingRequest::STATUS_PENDING,
        ]);
        $adminLoan->items()->create([
            'inventory_item_id' => $item->id,
            'quantity_requested' => 1,
        ]);

        // Student member gets 403 on admin's loan + ACCESS_DENIED logged
        $this->actingAsVerified($this->student)
            ->get(route('borrowings.show', $adminLoan))
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ACCESS_DENIED',
            'record_label' => 'BOR-IDOR-001',
        ]);

        // Admin can view
        $this->actingAsVerified($this->admin)
            ->get(route('borrowings.show', $adminLoan))
            ->assertStatus(200);
    }

    public function test_defense_test_4_sql_injection_payloads_are_neutralized(): void
    {
        // SQLi on login
        $this->post(route('login.attempt'), [
            'email' => "' OR '1'='1",
            'password' => "' OR '1'='1",
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // SQLi on inventory search returns safe 200 without altering or dumping rows
        $beforeCount = InventoryItem::count();
        $this->actingAsVerified($this->student)
            ->get(route('inventory.index', ['search' => "' OR 1=1; DROP TABLE inventory_items; --"]))
            ->assertStatus(200)
            ->assertDontSee('Council Official Laptop');
        $this->assertSame($beforeCount, InventoryItem::count());
    }

    public function test_defense_test_5_xss_payload_is_html_escaped_and_never_executes(): void
    {
        $item = InventoryItem::firstOrFail();
        $xss = '<script>alert(1)</script>';

        $this->actingAsVerified($this->admin)
            ->put(route('inventory.update', $item), [
                'property_code' => $item->property_code,
                'name' => 'Test Speaker ' . $xss,
                'category_id' => $item->category_id,
                'minimum_stock_level' => 1,
                'unit_of_measurement' => 'unit',
                'status' => $item->status,
                'condition_id' => $item->condition_id,
            ])->assertRedirect(route('inventory.index'));

        $res = $this->actingAsVerified($this->student)
            ->get(route('inventory.show', $item))
            ->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringNotContainsString($xss, $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_sensitive_fields_are_encrypted_at_rest_and_my_data_exports_cleanly(): void
    {
        $item = InventoryItem::where('property_code', 'PCB-SSC-CMP-0001')->firstOrFail();
        $rawSerial = DB::table('inventory_items')->where('id', $item->id)->value('serial_number');
        $rawNotes = DB::table('inventory_items')->where('id', $item->id)->value('sensitive_notes');

        $this->assertNotSame('DUMMY-SN-LPT-001', $rawSerial);
        $this->assertSame('DUMMY-SN-LPT-001', $item->serial_number);
        $this->assertNotNull($rawNotes);
        $this->assertStringContainsString('Cabinet SC-101B', $item->sensitive_notes);

        // Audit Logs & My Data UI routes are completely removed (404 for both Student and Admin)
        $this->actingAsVerified($this->student)->get('/my-data')->assertStatus(404);
        $this->actingAsVerified($this->student)->get('/my-data/export')->assertStatus(404);
        $this->actingAsVerified($this->student)->get('/audit-logs')->assertStatus(404);

        $this->actingAsVerified($this->admin)->get('/my-data')->assertStatus(404);
        $this->actingAsVerified($this->admin)->get('/my-data/export')->assertStatus(404);
        $this->actingAsVerified($this->admin)->get('/audit-logs')->assertStatus(404);
    }
}
