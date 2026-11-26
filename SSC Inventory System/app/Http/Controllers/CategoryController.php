<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryLocation;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        return view('categories.index', [
            'categories' => InventoryCategory::withCount('items')->orderBy('name')->get(),
            'locations' => InventoryLocation::with('custodian')->withCount('items')->orderBy('name')->get(),
            'custodians' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'activeTab' => $request->input('tab', 'categories'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('inventory_categories', 'name')],
            'code' => ['required', 'string', 'max:20', Rule::unique('inventory_categories', 'code')],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = InventoryCategory::create([
            'name' => $validated['name'],
            'code' => strtoupper(trim($validated['code'])),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        $this->auditLog->log(
            action: 'category.created',
            module: 'categories',
            auditable: $category,
            newValues: $category->only(['name', 'code', 'is_active']),
            description: "Created inventory category {$category->name} ({$category->code}).",
            recordLabel: $category->code,
            actor: $request->user()
        );

        return redirect()->route('categories.index', ['tab' => 'categories'])
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    public function update(Request $request, InventoryCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('inventory_categories', 'name')->ignore($category->id)],
            'code' => ['required', 'string', 'max:20', Rule::unique('inventory_categories', 'code')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $category->only(['name', 'code', 'description', 'is_active']);

        $category->update([
            'name' => $validated['name'],
            'code' => strtoupper(trim($validated['code'])),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->auditLog->log(
            action: 'category.updated',
            module: 'categories',
            auditable: $category,
            oldValues: $old,
            newValues: $category->only(['name', 'code', 'description', 'is_active']),
            description: "Updated inventory category {$category->name}.",
            recordLabel: $category->code,
            actor: $request->user()
        );

        return redirect()->route('categories.index', ['tab' => 'categories'])
            ->with('success', "Category '{$category->name}' updated.");
    }

    public function destroy(Request $request, InventoryCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            $category->update(['is_active' => false]);

            return redirect()->route('categories.index', ['tab' => 'categories'])
                ->with('success', "Category '{$category->name}' has linked items and was marked inactive instead.");
        }

        $label = $category->code;
        $name = $category->name;
        $category->delete();

        $this->auditLog->log(
            action: 'category.archived',
            module: 'categories',
            auditable: $category,
            description: "Archived unused category {$name} ({$label}).",
            recordLabel: $label,
            actor: $request->user()
        );

        return redirect()->route('categories.index', ['tab' => 'categories'])
            ->with('success', "Category '{$name}' archived.");
    }
}
