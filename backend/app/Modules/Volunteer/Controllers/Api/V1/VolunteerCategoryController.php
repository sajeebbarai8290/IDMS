<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\Volunteer\Models\SkillCatalog;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Requests\StoreSkillCatalogRequest;
use App\Modules\Volunteer\Requests\StoreVolunteerCategoryRequest;
use App\Modules\Volunteer\Resources\SkillCatalogResource;
use App\Modules\Volunteer\Resources\VolunteerCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class VolunteerCategoryController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $query = VolunteerCategory::orderBy('sort_order')->orderBy('name_en');
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => VolunteerCategoryResource::collection($query->get()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeCategory(StoreVolunteerCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = VolunteerCategory::create([
            'code' => $validated['code'],
            'name_en' => $validated['name_en'],
            'name_bn' => $validated['name_bn'],
            'description' => $validated['description'] ?? null,
            'color_hex' => $validated['color_hex'] ?? '#0d6efd',
            'sort_order' => (int) ($validated['sort_order'] ?? 200),
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLog::log(
            'VOLUNTEER_CATEGORY_CREATED',
            VolunteerCategory::class,
            $category->id,
            null,
            ['code' => $category->code, 'name_en' => $category->name_en],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateCategory(StoreVolunteerCategoryRequest $request, VolunteerCategory $category): JsonResponse
    {
        $validated = $request->validated();
        $category->update(collect($validated)->only([
            'name_en',
            'name_bn',
            'description',
            'color_hex',
            'sort_order',
            'is_active',
        ])->toArray());

        return response()->json([
            'success' => true,
            'data' => new VolunteerCategoryResource($category->fresh()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function skills(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $query = SkillCatalog::with('category')->orderBy('name_en');
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => SkillCatalogResource::collection($query->get()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeSkill(StoreSkillCatalogRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $skill = SkillCatalog::create([
            'category_id' => $validated['category_id'] ?? null,
            'skill_code' => $validated['skill_code'],
            'name_en' => $validated['name_en'],
            'name_bn' => $validated['name_bn'],
            'description' => $validated['description'] ?? null,
            'requires_certification' => $validated['requires_certification'] ?? false,
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLog::log(
            'VOLUNTEER_SKILL_CATALOG_CREATED',
            SkillCatalog::class,
            $skill->id,
            null,
            ['skill_code' => $skill->skill_code],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new SkillCatalogResource($skill->load('category')),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateSkill(StoreSkillCatalogRequest $request, SkillCatalog $skill): JsonResponse
    {
        $validated = $request->validated();
        $skill->update(collect($validated)->only([
            'category_id',
            'name_en',
            'name_bn',
            'description',
            'requires_certification',
            'is_active',
        ])->toArray());

        return response()->json([
            'success' => true,
            'data' => new SkillCatalogResource($skill->fresh('category')),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
