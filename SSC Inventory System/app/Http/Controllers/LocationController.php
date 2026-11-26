<?php

namespace App\Http\Controllers;

use App\Models\InventoryLocation;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140', Rule::unique('inventory_locations', 'name')],
            'building' => ['nullable', 'string', 'max:120'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'custodian_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $location = InventoryLocation::create([
            'name' => $validated['name'],
            'building' => $validated['building'] ?? null,
            'room_number' => $validated['room_number'] ?? null,
            'custodian_id' => $validated['custodian_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditLog->log(
            action: 'location.created',
            module: 'locations',
            auditable: $location,
            newValues: $location->only(['name', 'building', 'room_number']),
            description: "Created storage location {$location->name}.",
            recordLabel: $location->name,
            actor: $request->user()
        );

        return redirect()->route('categories.index', ['tab' => 'locations'])
            ->with('success', "Location '{$location->name}' created successfully.");
    }

    public function update(Request $request, InventoryLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140', Rule::unique('inventory_locations', 'name')->ignore($location->id)],
            'building' => ['nullable', 'string', 'max:120'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'custodian_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $location->only(['name', 'building', 'room_number', 'custodian_id', 'is_active']);

        $location->update([
            'name' => $validated['name'],
            'building' => $validated['building'] ?? null,
            'room_number' => $validated['room_number'] ?? null,
            'custodian_id' => $validated['custodian_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->auditLog->log(
            action: 'location.updated',
            module: 'locations',
            auditable: $location,
            oldValues: $old,
            newValues: $location->only(['name', 'building', 'room_number', 'custodian_id', 'is_active']),
            description: "Updated storage location {$location->name}.",
            recordLabel: $location->name,
            actor: $request->user()
        );

        return redirect()->route('categories.index', ['tab' => 'locations'])
            ->with('success', "Location '{$location->name}' updated.");
    }

    public function destroy(Request $request, InventoryLocation $location): RedirectResponse
    {
        if ($location->items()->exists()) {
            $location->update(['is_active' => false]);

            return redirect()->route('categories.index', ['tab' => 'locations'])
                ->with('success', "Location '{$location->name}' has assigned items and was marked inactive instead.");
        }

        $name = $location->name;
        $location->delete();

        $this->auditLog->log(
            action: 'location.archived',
            module: 'locations',
            auditable: $location,
            description: "Archived unused storage location {$name}.",
            recordLabel: $name,
            actor: $request->user()
        );

        return redirect()->route('categories.index', ['tab' => 'locations'])
            ->with('success', "Location '{$name}' archived.");
    }
}
