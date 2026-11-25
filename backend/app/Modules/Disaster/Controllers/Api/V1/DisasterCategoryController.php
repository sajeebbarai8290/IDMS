<?php

namespace App\Modules\Disaster\Controllers\Api\V1;

use App\Modules\Disaster\Models\DisasterCategory;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Disaster\Requests\StoreDisasterCategoryRequest;
use App\Modules\Disaster\Resources\DisasterCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DisasterCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Cache::remember('disaster:categories:active', 3600, function () {
            return DisasterCategory::where('is_active', true)->orderBy('name_en')->get();
        });

        return response()->json([
            'success' => true,
            'data' => DisasterCategoryResource::collection($categories),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreDisasterCategoryRequest $request): JsonResponse
    {
        Gate::authorize('manageCategories', DisasterEvent::class);

        $validated = $request->validated();
        $code = strtoupper($validated['code']);

        if (DisasterCategory::where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Category code already exists.',
            ]);
        }

        $category = DisasterCategory::create([
            'code' => $code,
            'name_en' => $validated['name_en'],
            'name_bn' => $validated['name_bn'],
            'subtypes' => $validated['subtypes'] ?? [],
            'icon' => $validated['icon'] ?? 'bi-exclamation-triangle-fill',
            'color_hex' => $validated['color_hex'] ?? '#0d6efd',
            'is_system' => false,
            'is_active' => true,
        ]);

        Cache::forget('disaster:categories:active');

        return response()->json([
            'success' => true,
            'message' => 'Disaster category created.',
            'category' => new DisasterCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }
}
