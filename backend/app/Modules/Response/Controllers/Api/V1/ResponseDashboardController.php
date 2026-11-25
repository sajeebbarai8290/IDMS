<?php

namespace App\Modules\Response\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseActivityType;
use App\Modules\Response\Models\ResponseCenter;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Requests\StoreResponseActivityRequest;
use App\Modules\Response\Services\ResponseManagementService;
use App\Modules\Response\Services\ResponseSpatialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ResponseDashboardController extends Controller
{
    public function __construct(
        protected ResponseManagementService $responseService,
        protected ResponseSpatialService $spatialService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResponseOperation::class);

        return response()->json([
            'success' => true,
            'data' => $this->responseService->getDashboardAnalytics(
                $request->user(),
                $request->only(['disaster_event_id'])
            ),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResponseOperation::class);

        return response()->json([
            'success' => true,
            'data' => $this->spatialService->buildResponseGeoJson(
                $request->user(),
                $request->only(['response_operation_id', 'disaster_event_id'])
            ),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResponseOperation::class);
        $user = $request->user();
        $isExternal = $user->hasRole('ROLE_EXTERNAL_PARTNER');

        $activities = ResponseActivityType::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'code', 'name_en', 'name_bn', 'icon', 'color_hex', 'is_system']);

        $centers = ResponseCenter::inJurisdiction($user)
            ->with('administrativeUnit:id,name_en,name_bn,tier_level')
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'center_code' => $c->center_code,
                'center_type' => $c->center_type,
                'name_en' => $c->name_en,
                'name_bn' => $c->name_bn,
                'administrative_unit_id' => $c->administrative_unit_id,
                'contact_phone' => $isExternal ? null : $c->contact_phone,
                'latitude' => $c->latitude,
                'longitude' => $c->longitude,
            ]);

        $assets = ResourceAsset::inJurisdiction($user)
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'asset_code' => $a->asset_code,
                'asset_type' => $a->asset_type,
                'name_en' => $a->name_en,
                'name_bn' => $a->name_bn,
                'registration_no' => $isExternal ? null : $a->registration_no,
                'capacity_info' => $a->capacity_info,
                'status' => $a->status,
                'administrative_unit_id' => $a->administrative_unit_id,
            ]);

        $disasters = DisasterEvent::orderByDesc('declaration_date')
            ->limit(50)
            ->get(['id', 'disaster_code', 'name_en', 'name_bn', 'severity_level', 'lifecycle_phase']);

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $activities,
                'response_centers' => $centers,
                'resource_assets' => $assets,
                'disasters' => $disasters,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeActivity(StoreResponseActivityRequest $request): JsonResponse
    {
        $activity = ResponseActivityType::create([
            'code' => strtoupper($request->validated('code')),
            'name_en' => $request->validated('name_en'),
            'name_bn' => $request->validated('name_bn'),
            'description' => $request->validated('description'),
            'icon' => $request->validated('icon'),
            'color_hex' => $request->validated('color_hex'),
            'is_system' => false,
            'is_active' => true,
        ]);

        AuditLog::log(
            'RESPONSE_ACTIVITY_CREATED',
            ResponseActivityType::class,
            $activity->id,
            null,
            ['code' => $activity->code],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $activity,
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }
}
