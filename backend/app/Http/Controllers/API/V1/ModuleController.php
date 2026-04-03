<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Module;
use App\Models\ModuleControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ModuleController extends BaseController
{
    // ─── Modules ───────────────────────────────────────────────────

    /**
     * GET /api/v1/modules
     * Requires: view_modules
     */
    public function index(Request $request): JsonResponse
    {
        $modules = Module::with('activeControls')
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->get();

        return $this->success($modules);
    }

    /**
     * GET /api/v1/modules/{id}
     * Requires: view_modules
     */
    public function show(string $id): JsonResponse
    {
        $module = Module::with('controls')->find($id);

        if (! $module) {
            return $this->error('Module not found.', 404);
        }

        return $this->success($module);
    }

    /**
     * POST /api/v1/modules
     * Requires: add_module
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:150|unique:modules,name',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $module = Module::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name, '_'),
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return $this->success($module, 'Module created successfully.', 201);
    }

    /**
     * PUT /api/v1/modules/{id}
     * Requires: edit_module
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $module = Module::find($id);

        if (! $module) {
            return $this->error('Module not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => "sometimes|string|max:150|unique:modules,name,{$module->module_id},module_id",
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        if ($request->has('name')) {
            $module->slug = Str::slug($request->name, '_');
        }

        $module->fill($request->only(['name', 'description', 'is_active']))->save();

        return $this->success($module, 'Module updated successfully.');
    }

    /**
     * DELETE /api/v1/modules/{id}
     * Requires: delete_module
     */
    public function destroy(string $id): JsonResponse
    {
        $module = Module::withCount('controls')->find($id);

        if (! $module) {
            return $this->error('Module not found.', 404);
        }

        if ($module->controls_count > 0) {
            return $this->error('Cannot delete module with existing controls. Remove all controls first.', 409);
        }

        $module->delete();

        return $this->success(null, 'Module deleted successfully.');
    }

    // ─── Module Controls ───────────────────────────────────────────

    /**
     * GET /api/v1/modules/{id}/controls
     * Requires: view_modules
     */
    public function controls(string $id): JsonResponse
    {
        $module = Module::find($id);

        if (! $module) {
            return $this->error('Module not found.', 404);
        }

        return $this->success($module->controls()->orderBy('name')->get());
    }

    /**
     * POST /api/v1/modules/{id}/controls
     * Add a control to a module. Requires: add_module
     */
    public function storeControl(Request $request, string $id): JsonResponse
    {
        $module = Module::find($id);

        if (! $module) {
            return $this->error('Module not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        $slug = Str::slug($request->name, '_');

        if (ModuleControl::where('slug', $slug)->exists()) {
            return $this->error("A control with slug '{$slug}' already exists.", 409);
        }

        $control = ModuleControl::create([
            'name'        => $request->name,
            'slug'        => $slug,
            'module_id'   => $module->module_id,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return $this->success($control, 'Control created successfully.', 201);
    }

    /**
     * PUT /api/v1/modules/controls/{controlId}
     * Requires: edit_module
     */
    public function updateControl(Request $request, string $controlId): JsonResponse
    {
        $control = ModuleControl::find($controlId);

        if (! $control) {
            return $this->error('Control not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        if ($request->has('name')) {
            $control->slug = Str::slug($request->name, '_');
        }

        $control->fill($request->only(['name', 'description', 'is_active']))->save();

        return $this->success($control, 'Control updated successfully.');
    }

    /**
     * DELETE /api/v1/modules/controls/{controlId}
     * Requires: delete_module
     */
    public function destroyControl(string $controlId): JsonResponse
    {
        $control = ModuleControl::find($controlId);

        if (! $control) {
            return $this->error('Control not found.', 404);
        }

        // Detach from all roles before deleting
        $control->roles()->detach();
        $control->delete();

        return $this->success(null, 'Control deleted successfully.');
    }
}