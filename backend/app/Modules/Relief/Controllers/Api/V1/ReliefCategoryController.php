<?php

namespace App\Modules\Relief\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Relief\Requests\StoreReliefCategoryRequest;
use App\Modules\Relief\Requests\UpdateReliefCategoryRequest;
use App\Modules\Relief\Resources\ReliefCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ReliefCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyRequisitions', \App\Modules\Relief\Models\ReliefRequisition::class);

        $categories = ReliefCategory::withCount('commodities')
            ->when(
                $request->boolean('active_only', false),
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy('name_en')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ReliefCategoryResource::collection($categories),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreReliefCategoryRequest $request): JsonResponse
    {
        Gate::authorize('manageCategories', ReliefCategory::class);

        $category = ReliefCategory::create([
            'code' => strtoupper($request->validated('code')),
            'name_en' => $request->validated('name_en'),
            'name_bn' => $request->validated('name_bn'),
            'description' => $request->validated('description'),
            'icon' => $request->validated('icon'),
            'color_hex' => $request->validated('color_hex'),
            'is_system' => false,
            'is_active' => $request->validated('is_active', true),
        ]);

        AuditLog::log(
            'RELIEF_CATEGORY_CREATED',
            ReliefCategory::class,
            $category->id,
            null,
            ['code' => $category->code, 'name_en' => $category->name_en],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function update(UpdateReliefCategoryRequest $request, ReliefCategory $category): JsonResponse
    {
        Gate::authorize('manageCategories', ReliefCategory::class);

        $category->update($request->validated());

        AuditLog::log(
            'RELIEF_CATEGORY_UPDATED',
            ReliefCategory::class,
            $category->id,
            null,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefCategoryResource($category->fresh()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
