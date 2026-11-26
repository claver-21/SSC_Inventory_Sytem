<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tin_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::create(array_merge($validated, ['is_active' => true]));

        $this->auditLog->log(
            action: 'supplier.created',
            module: 'procurement',
            auditable: $supplier,
            newValues: $supplier->only(['name', 'contact_person', 'phone']),
            description: "Added supplier {$supplier->name}.",
            recordLabel: $supplier->name,
            actor: $request->user()
        );

        return redirect()->route('procurement.index', ['tab' => 'suppliers'])
            ->with('success', "Supplier '{$supplier->name}' added.");
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tin_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));

        return redirect()->route('procurement.index', ['tab' => 'suppliers'])
            ->with('success', "Supplier '{$supplier->name}' updated.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->acquisitions()->exists()) {
            $supplier->update(['is_active' => false]);

            return redirect()->route('procurement.index', ['tab' => 'suppliers'])
                ->with('success', "Supplier '{$supplier->name}' has purchase records and was marked inactive.");
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()->route('procurement.index', ['tab' => 'suppliers'])
            ->with('success', "Supplier '{$name}' archived.");
    }
}
