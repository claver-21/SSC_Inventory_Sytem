<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function __construct(
        protected AuditLogService $auditLog,
        protected NotificationService $notifications
    ) {
    }

    public function recordMovement(
        InventoryItem|int $item,
        string $movementType,
        int $quantity,
        User $actor,
        string $remarks = '',
        array $options = []
    ): StockMovement {
        return DB::transaction(function () use ($item, $movementType, $quantity, $actor, $remarks, $options): StockMovement {
            $itemId = $item instanceof InventoryItem ? $item->id : $item;
            $locked = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($itemId);

            if ($movementType !== StockMovement::TYPE_ADJUSTMENT && $quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity must be greater than zero.',
                ]);
            }

            if ($movementType === StockMovement::TYPE_ADJUSTMENT && $quantity === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Adjustment quantity delta cannot be zero.',
                ]);
            }

            $prevQty = (int) $locked->quantity;
            $prevAvail = (int) $locked->available_quantity;
            $borrowedQty = max(0, $prevQty - $prevAvail);

            $newQty = $prevQty;
            $newAvail = $prevAvail;
            $fromLocationId = $locked->location_id;
            $toLocationId = $options['to_location_id'] ?? null;
            $fromBorrowed = (bool) ($options['from_borrowed'] ?? false);

            switch ($movementType) {
                case StockMovement::TYPE_STOCK_IN:
                    $newQty = $prevQty + $quantity;
                    $newAvail = $prevAvail + $quantity;
                    if ($newAvail > 0 && $locked->status === InventoryItem::STATUS_BORROWED) {
                        $locked->status = InventoryItem::STATUS_AVAILABLE;
                    }
                    break;

                case StockMovement::TYPE_STOCK_OUT:
                    if ($quantity > $prevAvail) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient available stock for {$locked->name} (available: {$prevAvail}).",
                        ]);
                    }
                    $newQty = $prevQty - $quantity;
                    $newAvail = $prevAvail - $quantity;
                    break;

                case StockMovement::TYPE_BORROWING:
                    if ($quantity > $prevAvail) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient available stock to borrow {$locked->name} (available: {$prevAvail}).",
                        ]);
                    }
                    $newQty = $prevQty;
                    $newAvail = $prevAvail - $quantity;
                    if ($newAvail === 0 && $locked->status === InventoryItem::STATUS_AVAILABLE) {
                        $locked->status = InventoryItem::STATUS_BORROWED;
                    }
                    break;

                case StockMovement::TYPE_RETURNING:
                    if ($prevAvail + $quantity > $prevQty) {
                        throw ValidationException::withMessages([
                            'quantity' => "Return quantity exceeds outstanding loaned quantity for {$locked->name}.",
                        ]);
                    }
                    $newQty = $prevQty;
                    $newAvail = $prevAvail + $quantity;
                    if ($newAvail > 0 && in_array($locked->status, [InventoryItem::STATUS_BORROWED, InventoryItem::STATUS_RESERVED], true)) {
                        $locked->status = InventoryItem::STATUS_AVAILABLE;
                    }
                    break;

                case StockMovement::TYPE_ADJUSTMENT:
                    $newQty = $prevQty + $quantity;
                    if ($newQty < 0) {
                        throw ValidationException::withMessages([
                            'quantity' => "Adjustment cannot reduce total stock below zero for {$locked->name}.",
                        ]);
                    }
                    if ($newQty < $borrowedQty) {
                        throw ValidationException::withMessages([
                            'quantity' => "Cannot adjust total stock below currently borrowed units ({$borrowedQty}) for {$locked->name}.",
                        ]);
                    }
                    $newAvail = $newQty - $borrowedQty;
                    break;

                case StockMovement::TYPE_TRANSFER:
                    if (! $toLocationId) {
                        throw ValidationException::withMessages([
                            'to_location_id' => 'Destination storage location is required for transfer.',
                        ]);
                    }
                    if ($quantity > $prevQty) {
                        throw ValidationException::withMessages([
                            'quantity' => "Transfer quantity cannot exceed current stock ({$prevQty}) for {$locked->name}.",
                        ]);
                    }
                    $locked->location_id = (int) $toLocationId;
                    break;

                case StockMovement::TYPE_DAMAGED:
                    if ($fromBorrowed) {
                        // Already deducted from available_quantity upon borrowing; hold out of available pool
                        $newQty = $prevQty;
                        $newAvail = $prevAvail;
                    } else {
                        if ($quantity > $prevAvail) {
                            throw ValidationException::withMessages([
                                'quantity' => "Insufficient available units to mark as damaged for {$locked->name} (available: {$prevAvail}).",
                            ]);
                        }
                        $newQty = $prevQty;
                        $newAvail = $prevAvail - $quantity;
                    }
                    if (($options['mark_item_damaged'] ?? false) || $newAvail === 0) {
                        $locked->status = InventoryItem::STATUS_DAMAGED;
                    }
                    break;

                case StockMovement::TYPE_MISSING:
                    if ($fromBorrowed) {
                        if ($quantity > $prevQty || ($prevQty - $quantity) < $prevAvail) {
                            throw ValidationException::withMessages([
                                'quantity' => "Invalid missing quantity reported for {$locked->name}.",
                            ]);
                        }
                        $newQty = $prevQty - $quantity;
                        $newAvail = $prevAvail;
                    } else {
                        if ($quantity > $prevAvail || $quantity > $prevQty) {
                            throw ValidationException::withMessages([
                                'quantity' => "Insufficient available stock to mark missing for {$locked->name}.",
                            ]);
                        }
                        $newQty = $prevQty - $quantity;
                        $newAvail = $prevAvail - $quantity;
                    }
                    if ($newQty === 0 || ($options['mark_item_missing'] ?? false)) {
                        $locked->status = InventoryItem::STATUS_MISSING;
                    }
                    break;

                case StockMovement::TYPE_DISPOSAL:
                    if ($fromBorrowed) {
                        if ($quantity > $prevQty || ($prevQty - $quantity) < $prevAvail) {
                            throw ValidationException::withMessages([
                                'quantity' => "Invalid disposal quantity for {$locked->name}.",
                            ]);
                        }
                        $newQty = $prevQty - $quantity;
                        $newAvail = $prevAvail;
                    } else {
                        if ($quantity > $prevAvail || $quantity > $prevQty) {
                            throw ValidationException::withMessages([
                                'quantity' => "Insufficient available stock to dispose for {$locked->name}.",
                            ]);
                        }
                        $newQty = $prevQty - $quantity;
                        $newAvail = $prevAvail - $quantity;
                    }
                    if ($newQty === 0) {
                        $locked->status = InventoryItem::STATUS_DISPOSED;
                    }
                    break;

                default:
                    throw ValidationException::withMessages([
                        'movement_type' => "Unsupported stock movement type: {$movementType}",
                    ]);
            }

            if ($newQty < 0 || $newAvail < 0 || $newAvail > $newQty) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock integrity violation detected (negative or mismatched available quantity).',
                ]);
            }

            $locked->quantity = $newQty;
            $locked->available_quantity = $newAvail;
            $locked->updated_by = $actor->id;
            $locked->save();

            /** @var ?Model $reference */
            $reference = $options['reference'] ?? null;

            $movement = StockMovement::create([
                'transaction_reference' => $this->generateReference(),
                'inventory_item_id' => $locked->id,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'previous_quantity' => $prevQty,
                'new_quantity' => $newQty,
                'previous_available_quantity' => $prevAvail,
                'new_available_quantity' => $newAvail,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'reference_type' => $reference ? $reference->getMorphClass() : ($options['reference_type'] ?? null),
                'reference_id' => $reference?->getKey() ?? ($options['reference_id'] ?? null),
                'processed_by' => $actor->id,
                'transaction_date' => $options['transaction_date'] ?? now(),
                'remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $this->auditLog->log(
                action: 'stock.' . $movementType,
                module: 'stock_movements',
                auditable: $locked,
                oldValues: [
                    'quantity' => $prevQty,
                    'available_quantity' => $prevAvail,
                    'location_id' => $fromLocationId,
                ],
                newValues: [
                    'quantity' => $newQty,
                    'available_quantity' => $newAvail,
                    'location_id' => $locked->location_id,
                ],
                description: sprintf(
                    '%s (%s): %+d unit(s). %s',
                    $locked->name,
                    $movement->transaction_reference,
                    $quantity,
                    $remarks
                ),
                recordLabel: $locked->property_code,
                actor: $actor
            );

            if ($locked->isLowStock() && $newAvail < $prevAvail) {
                $this->notifications->sendToRoles(
                    [Role::SLUG_ADMIN],
                    'low_stock',
                    'Low Inventory Stock Alert',
                    "{$locked->name} ({$locked->property_code}) is low on stock ({$newAvail} remaining; minimum: {$locked->minimum_stock_level}).",
                    'warning',
                    route('inventory.show', $locked),
                    $locked
                );
            }

            return $movement;
        });
    }

    private function generateReference(): string
    {
        return 'SM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
