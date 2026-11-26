<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OverdueMonitoringService
{
    public function __construct(
        protected NotificationService $notifications,
        protected SettingsService $settings
    ) {
    }

    public function run(): array
    {
        $today = Carbon::today();
        $noticeDays = max(0, (int) $this->settings->get('upcoming_due_notice_days', 1));
        $markedOverdue = 0;
        $upcomingNotified = 0;

        DB::transaction(function () use ($today, $noticeDays, &$markedOverdue, &$upcomingNotified): void {
            $overdueRequests = BorrowingRequest::query()
                ->whereIn('status', [
                    BorrowingRequest::STATUS_APPROVED,
                    BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                    BorrowingRequest::STATUS_OVERDUE,
                ])
                ->whereDate('expected_return_date', '<', $today)
                ->get();

            foreach ($overdueRequests as $request) {
                $changed = $request->status !== BorrowingRequest::STATUS_OVERDUE;
                if ($changed) {
                    $request->status = BorrowingRequest::STATUS_OVERDUE;
                }

                $shouldNotify = $request->overdue_notified_at === null
                    || $request->overdue_notified_at->lt($today);

                if ($shouldNotify) {
                    $days = max(1, (int) $request->expected_return_date->copy()->startOfDay()->diffInDays($today));
                    $this->notifications->sendToUser(
                        $request->requester_user_id,
                        'borrowing_overdue',
                        'Overdue Equipment Return',
                        "Borrowing {$request->transaction_code} is overdue by {$days} day(s). Please return borrowed items immediately.",
                        'danger',
                        route('borrowings.show', $request),
                        $request
                    );

                    $this->notifications->sendToRoles(
                        [Role::SLUG_ADMIN],
                        'borrowing_overdue',
                        'Overdue Borrowing Detected',
                        "Borrowing {$request->transaction_code} by {$request->borrower_name} is {$days} day(s) overdue.",
                        'danger',
                        route('borrowings.show', $request),
                        $request,
                        $request->requester_user_id
                    );

                    $request->overdue_notified_at = now();
                    $markedOverdue++;
                }

                if ($changed || $shouldNotify) {
                    $request->save();
                }
            }

            if ($noticeDays > 0) {
                $windowEnd = $today->copy()->addDays($noticeDays);
                $upcomingRequests = BorrowingRequest::query()
                    ->whereIn('status', [
                        BorrowingRequest::STATUS_APPROVED,
                        BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                    ])
                    ->whereDate('expected_return_date', '>=', $today)
                    ->whereDate('expected_return_date', '<=', $windowEnd)
                    ->whereNull('upcoming_notified_at')
                    ->get();

                foreach ($upcomingRequests as $request) {
                    $dueLabel = $request->expected_return_date->format('M d, Y');
                    $this->notifications->sendToUser(
                        $request->requester_user_id,
                        'borrowing_upcoming_due',
                        'Upcoming Return Deadline',
                        "Reminder: Borrowing {$request->transaction_code} is due on {$dueLabel}.",
                        'warning',
                        route('borrowings.show', $request),
                        $request
                    );
                    $request->upcoming_notified_at = now();
                    $request->save();
                    $upcomingNotified++;
                }
            }
        });

        return [
            'overdue_processed' => $markedOverdue,
            'upcoming_notified' => $upcomingNotified,
        ];
    }
}
