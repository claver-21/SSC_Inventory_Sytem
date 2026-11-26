<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BorrowingRequest;
use App\Models\IncidentReport;
use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ReturnTransaction;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryAndBorrowingTest extends TestCase
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

    public function test_admin_can_register_inventory_with_encrypted_notes_and_unique_code(): void
    {
        Storage::fake('public');
        $category = InventoryCategory::firstOrFail();
        $condition = InventoryCondition::where('slug', 'excellent')->firstOrFail();

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('camera.png', $pngBytes);

        $this->actingAsVerified($this->admin)->post(route('inventory.store'), [
            'property_code' => 'PCB-SSC-AVE-0099',
            'name' => 'Council DSLR Camera',
            'category_id' => $category->id,
            'serial_number' => 'SN-CAM-9988',
            'sensitive_notes' => 'Keep inside locked drawer #2',
            'quantity' => 2,
            'minimum_stock_level' => 1,
            'unit_of_measurement' => 'unit',
            'status' => InventoryItem::STATUS_AVAILABLE,
            'condition_id' => $condition->id,
            'image' => $file,
        ])->assertRedirect();

        $item = InventoryItem::where('property_code', 'PCB-SSC-AVE-0099')->firstOrFail();
        $this->assertSame(2, $item->available_quantity);
        Storage::disk('public')->assertExists($item->image_path);

        // Duplicate code rejected
        $this->actingAsVerified($this->admin)->post(route('inventory.store'), [
            'property_code' => 'PCB-SSC-AVE-0099',
            'name' => 'Dup Camera',
            'category_id' => $category->id,
            'quantity' => 1,
            'minimum_stock_level' => 1,
            'unit_of_measurement' => 'unit',
            'status' => InventoryItem::STATUS_AVAILABLE,
            'condition_id' => $condition->id,
        ])->assertSessionHasErrors('property_code');
    }

    public function test_borrowing_double_check_verification_partial_return_damaged_inspection_and_overdue(): void
    {
        $walkie = InventoryItem::where('property_code', 'PCB-SSC-AVE-0001')->firstOrFail();
        $startAvail = $walkie->available_quantity; // 10

        // Member submits request for 4 walkie-talkies with editable borrower details
        $this->actingAsVerified($this->student)->post(route('borrowings.store'), [
            'borrower_name' => 'Maria Santos',
            'school_id' => '24-12345',
            'department_organization' => 'SSC Events Committee',
            'purpose' => 'PCB General Assembly',
            'request_date' => now()->toDateString(),
            'date_borrowed' => now()->toDateString(),
            'expected_return_date' => now()->addDays(2)->toDateString(),
            'items' => [
                ['inventory_item_id' => $walkie->id, 'quantity_requested' => 4],
            ],
        ])->assertRedirect();

        $borrowing = BorrowingRequest::latest('id')->firstOrFail();
        $this->assertSame('Maria Santos', $borrowing->borrower_name);
        $this->assertSame('24-12345', $borrowing->school_id);
        $this->assertSame('SSC Events Committee', $borrowing->department_organization);
        $borrowingItem = $borrowing->items()->firstOrFail();

        // Approve WITHOUT count_verified checkbox fails validation
        $this->actingAsVerified($this->admin)
            ->post(route('borrowings.approve', $borrowing), [])
            ->assertSessionHasErrors('count_verified');

        // Approve WITH count_verified succeeds and deducts available_quantity
        $this->actingAsVerified($this->admin)
            ->post(route('borrowings.approve', $borrowing), ['count_verified' => '1'])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->assertSame($startAvail - 4, $walkie->fresh()->available_quantity);
        $this->assertTrue($borrowing->fresh()->count_verified);

        $good = InventoryCondition::where('slug', 'good')->firstOrFail();
        $poor = InventoryCondition::where('slug', 'poor')->firstOrFail();

        // Partial return of 3 good units with double-check verified -> available increases by 3
        $this->actingAsVerified($this->admin)->post(route('borrowings.return.store', $borrowing), [
            'borrowing_item_id' => $borrowingItem->id,
            'quantity_returned' => 3,
            'actual_return_date' => now()->toDateString(),
            'condition_on_return_id' => $good->id,
            'return_outcome' => 'good',
            'count_verified' => '1',
            'remarks' => '3 radios returned intact',
        ])->assertSessionHas('success');

        $this->assertSame($startAvail - 1, $walkie->fresh()->available_quantity);

        // Last 1 unit returned damaged -> flagged for inspection, does NOT increase available_quantity
        $this->actingAsVerified($this->admin)->post(route('borrowings.return.store', $borrowing), [
            'borrowing_item_id' => $borrowingItem->id,
            'quantity_returned' => 1,
            'actual_return_date' => now()->toDateString(),
            'condition_on_return_id' => $poor->id,
            'return_outcome' => 'damaged',
            'count_verified' => '1',
            'remarks' => 'Broken antenna clip',
        ])->assertSessionHas('success');

        $this->assertSame($startAvail - 1, $walkie->fresh()->available_quantity);
        $flagged = ReturnTransaction::where('flagged_for_inspection', true)->firstOrFail();

        // Clear inspection -> restores last unit
        $this->actingAsVerified($this->admin)->post(route('returns.inspect', $flagged), [
            'resolution' => ReturnTransaction::INSPECTION_CLEARED,
            'condition_id' => $good->id,
            'inspection_remarks' => 'Replaced antenna clip',
        ])->assertSessionHas('success');

        $this->assertSame($startAvail, $walkie->fresh()->available_quantity);
    }

    public function test_incident_response_nist_csf_lifecycle_and_unlock_user(): void
    {
        // Member reports phishing incident
        $this->actingAsVerified($this->student)->post(route('incidents.store'), [
            'category' => IncidentReport::CATEGORY_SECURITY,
            'incident_type' => IncidentReport::TYPE_PHISHING,
            'description' => 'Suspicious link claiming SSC password reset in committee chat.',
        ])->assertSessionHas('success');

        $incident = IncidentReport::firstOrFail();
        $this->assertSame(IncidentReport::STATUS_REPORTED, $incident->status);

        // Admin updates through contained -> lessons_learned
        $this->actingAsVerified($this->admin)->post(route('incidents.resolve', $incident), [
            'status' => IncidentReport::STATUS_LESSONS_LEARNED,
            'resolution_notes' => 'Warned all council officers in official channel and verified MFA active.',
            'lessons_learned' => 'Remind new officers monthly never to click unverified reset links.',
        ])->assertSessionHas('success');

        $incident->refresh();
        $this->assertSame(IncidentReport::STATUS_LESSONS_LEARNED, $incident->status);
        $this->assertNotNull($incident->lessons_learned);

        // Admin can unlock a locked student account using Step-Up
        $this->student->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(15)])->save();
        $this->actingAsVerified($this->admin, true)
            ->post(route('users.unlock', $this->student))
            ->assertSessionHas('success');

        $this->assertFalse($this->student->fresh()->isLocked());
        $this->assertSame(0, $this->student->fresh()->failed_login_attempts);
    }

    public function test_one_click_return_automatically_restores_inventory_and_clears_active_loan(): void
    {
        $walkie = InventoryItem::where('property_code', 'PCB-SSC-AVE-0001')->firstOrFail();
        $startAvail = $walkie->available_quantity;

        $this->actingAsVerified($this->student)->post(route('borrowings.store'), [
            'borrower_name' => $this->student->name,
            'school_id' => $this->student->school_id,
            'department_organization' => 'Supreme Student Council',
            'purpose' => 'Campus Orientation',
            'request_date' => now()->toDateString(),
            'date_borrowed' => now()->toDateString(),
            'expected_return_date' => now()->addDays(2)->toDateString(),
            'items' => [
                ['inventory_item_id' => $walkie->id, 'quantity_requested' => 2],
            ],
        ])->assertRedirect();

        $borrowing = BorrowingRequest::latest('id')->firstOrFail();

        $this->actingAsVerified($this->admin)
            ->post(route('borrowings.approve', $borrowing), ['count_verified' => '1'])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->assertSame($startAvail - 2, $walkie->fresh()->available_quantity);

        // Admin can send Notice to Return notification to borrowing officer; officer cannot call notify-return
        $this->actingAsVerified($this->student)
            ->post(route('borrowings.notify-return', $borrowing))
            ->assertStatus(403);

        $this->actingAsVerified($this->admin)
            ->post(route('borrowings.notify-return', $borrowing))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'type' => 'return_reminder',
            'reference_id' => $borrowing->id,
        ]);

        $this->actingAsVerified($this->admin)
            ->post(route('borrowings.return-request', $borrowing))
            ->assertStatus(403);

        $this->actingAsVerified($this->student)
            ->post(route('borrowings.return-request', $borrowing))
            ->assertRedirect(route('borrowings.index'));

        $borrowing->refresh();
        $this->assertSame(BorrowingRequest::STATUS_RETURNED, $borrowing->status);
        $this->assertFalse($borrowing->isActiveLoan());
        $this->assertSame($startAvail, $walkie->fresh()->available_quantity);

        $returnTx = ReturnTransaction::where('borrowing_request_id', $borrowing->id)->firstOrFail();

        $this->actingAsVerified($this->admin)
            ->get(route('borrowings.index', ['tab' => 'history']))
            ->assertStatus(200)
            ->assertSee($borrowing->transaction_code)
            ->assertSee($this->student->name)
            ->assertSee($returnTx->return_code);

        // Admin cannot access borrow creation or submit borrowing requests
        $this->actingAsVerified($this->admin)
            ->get(route('borrowings.create'))
            ->assertStatus(403);

        $this->actingAsVerified($this->admin)
            ->post(route('borrowings.store'), [
                'borrower_name' => $this->admin->name,
                'school_id' => $this->admin->school_id,
                'department_organization' => 'Supreme Student Council',
                'purpose' => 'Blocked Admin Test',
                'request_date' => now()->toDateString(),
                'date_borrowed' => now()->toDateString(),
                'expected_return_date' => now()->addDays(2)->toDateString(),
                'items' => [
                    ['inventory_item_id' => $walkie->id, 'quantity_requested' => 1],
                ],
            ])->assertStatus(403);
    }

    public function test_admin_and_officer_can_delete_notifications_and_completed_borrowing_history(): void
    {
        $studentRole = Role::where('slug', Role::SLUG_MEMBER)->firstOrFail();
        $otherStudent = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Other Officer',
            'email' => 'other.officer@pcb-ssc.edu.ph',
            'school_id' => 'PCB-SSC-2026-099',
            'is_active' => true,
        ]);

        // Notification deletions for both Admin and Officer + cross-user 403 check
        $studentNotif1 = AppNotification::create([
            'user_id' => $this->student->id,
            'type' => 'return_reminder',
            'title' => 'Officer Alert 1',
            'message' => 'Please return speaker.',
            'severity' => 'warning',
        ]);
        $studentNotif2 = AppNotification::create([
            'user_id' => $this->student->id,
            'type' => 'borrowing_approved',
            'title' => 'Officer Alert 2',
            'message' => 'Request approved.',
            'severity' => 'success',
        ]);
        $adminNotif1 = AppNotification::create([
            'user_id' => $this->admin->id,
            'type' => 'borrowing_requested',
            'title' => 'Admin Alert 1',
            'message' => 'New request submitted.',
            'severity' => 'info',
        ]);
        $adminNotif2 = AppNotification::create([
            'user_id' => $this->admin->id,
            'type' => 'borrowing_returned',
            'title' => 'Admin Alert 2',
            'message' => 'Equipment returned.',
            'severity' => 'success',
        ]);

        // Student cannot delete Admin's notification
        $this->actingAsVerified($this->student)
            ->delete(route('notifications.destroy', $adminNotif1))
            ->assertStatus(403);
        $this->assertDatabaseHas('notifications', ['id' => $adminNotif1->id]);

        // Student UI shows Clear All and Delete buttons, then deletes one and clears the rest
        $this->actingAsVerified($this->student)
            ->get(route('notifications.index'))
            ->assertStatus(200)
            ->assertSee('Clear All')
            ->assertSee('Delete');

        $this->actingAsVerified($this->student)
            ->delete(route('notifications.destroy', $studentNotif1))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $studentNotif1->id]);
        $this->assertDatabaseHas('notifications', ['id' => $studentNotif2->id]);

        $this->actingAsVerified($this->student)
            ->delete(route('notifications.destroy-all'))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $studentNotif2->id]);
        $this->assertDatabaseHas('notifications', ['id' => $adminNotif1->id]);

        // Admin deletes one notification and clears the rest
        $this->actingAsVerified($this->admin)
            ->delete(route('notifications.destroy', $adminNotif1))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $adminNotif1->id]);

        $this->actingAsVerified($this->admin)
            ->delete(route('notifications.destroy-all'))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $adminNotif2->id]);

        // Borrowing history deletions: own returned, blocked active, cross-user 403, admin cleanup
        $makeBorrowing = function (User $requester, string $code, string $status): BorrowingRequest {
            return BorrowingRequest::create([
                'transaction_code' => $code,
                'requester_user_id' => $requester->id,
                'borrower_name' => $requester->name,
                'school_id' => $requester->school_id ?? 'PCB-001',
                'department_organization' => 'Supreme Student Council',
                'purpose' => 'Council Activity',
                'request_date' => now()->toDateString(),
                'date_borrowed' => now()->toDateString(),
                'expected_return_date' => now()->addDays(2)->toDateString(),
                'status' => $status,
            ]);
        };

        $studentReturned = $makeBorrowing($this->student, 'BOR-TEST-RET1', BorrowingRequest::STATUS_RETURNED);
        $studentRejected = $makeBorrowing($this->student, 'BOR-TEST-REJ1', BorrowingRequest::STATUS_REJECTED);
        $studentActive = $makeBorrowing($this->student, 'BOR-TEST-ACT1', BorrowingRequest::STATUS_APPROVED);
        $otherReturned = $makeBorrowing($otherStudent, 'BOR-TEST-OTH1', BorrowingRequest::STATUS_RETURNED);
        $otherCancelled = $makeBorrowing($otherStudent, 'BOR-TEST-OTH2', BorrowingRequest::STATUS_CANCELLED);

        // Officer cannot delete another officer's history record
        $this->actingAsVerified($this->student)
            ->delete(route('borrowings.destroy', $otherReturned))
            ->assertStatus(403);
        $this->assertNotSoftDeleted('borrowing_requests', ['id' => $otherReturned->id]);

        // Officer cannot delete active loan
        $this->actingAsVerified($this->student)
            ->delete(route('borrowings.destroy', $studentActive))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('borrowing_requests', ['id' => $studentActive->id]);

        // Officer deletes own returned record (after seeing history actions) and clears remaining own completed history
        $this->actingAsVerified($this->student)
            ->get(route('borrowings.index', ['tab' => 'history']))
            ->assertStatus(200)
            ->assertSee('Clear History')
            ->assertSee('Delete');

        $this->actingAsVerified($this->student)
            ->delete(route('borrowings.destroy', $studentReturned))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSoftDeleted('borrowing_requests', ['id' => $studentReturned->id]);

        $this->actingAsVerified($this->student)
            ->delete(route('borrowings.clear-history'))
            ->assertRedirect(route('borrowings.index', ['tab' => 'history']));
        $this->assertSoftDeleted('borrowing_requests', ['id' => $studentRejected->id]);
        $this->assertNotSoftDeleted('borrowing_requests', ['id' => $studentActive->id]);
        $this->assertNotSoftDeleted('borrowing_requests', ['id' => $otherReturned->id]);

        // Admin cannot delete active loan
        $this->actingAsVerified($this->admin)
            ->delete(route('borrowings.destroy', $studentActive))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('borrowing_requests', ['id' => $studentActive->id]);

        // Admin sees history delete controls, deletes any completed record, and clears remaining completed history while preserving active loan
        $this->actingAsVerified($this->admin)
            ->get(route('borrowings.index', ['tab' => 'history']))
            ->assertStatus(200)
            ->assertSee('Clear History')
            ->assertSee('Delete');

        $this->actingAsVerified($this->admin)
            ->delete(route('borrowings.destroy', $otherReturned))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSoftDeleted('borrowing_requests', ['id' => $otherReturned->id]);

        $this->actingAsVerified($this->admin)
            ->delete(route('borrowings.clear-history'))
            ->assertRedirect(route('borrowings.index', ['tab' => 'history']));
        $this->assertSoftDeleted('borrowing_requests', ['id' => $otherCancelled->id]);
        $this->assertNotSoftDeleted('borrowing_requests', ['id' => $studentActive->id]);
    }
}
