<?php

namespace App\Core\Http\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdministrativeUnitController extends Controller
{
    /**
     * Retrieve administrative units tree or children.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdministrativeUnit::where('is_active', true);

        if ($tier = $request->query('tier_level')) {
            $query->where('tier_level', strtoupper($tier));
        }

        if ($parentId = $request->query('parent_id')) {
            $query->where('parent_id', $parentId);
        }

        $units = $query->orderBy('name_en')->get();

        return response()->json([
            'success' => true,
            'data' => $units->map(function ($u) {
                return [
                    'id' => $u->id,
                    'parent_id' => $u->parent_id,
                    'code' => $u->code,
                    'name_en' => $u->name_en,
                    'name_bn' => $u->name_bn,
                    'tier_level' => $u->tier_level,
                    'ancestry_path' => $u->ancestry_path,
                    'latitude' => $u->latitude,
                    'longitude' => $u->longitude,
                ];
            }),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get active disasters list for linking.
     */
    public function disasters(): JsonResponse
    {
        $disasters = \App\Modules\Disaster\Models\DisasterEvent::all();

        return response()->json([
            'success' => true,
            'data' => $disasters->map(function ($d) {
                return [
                    'id' => $d->id,
                    'disaster_code' => $d->disaster_code,
                    'name_en' => $d->name_en,
                    'name_bn' => $d->name_bn,
                    'disaster_type' => $d->disaster_type,
                    'severity_level' => $d->severity_level,
                    'lifecycle_phase' => $d->lifecycle_phase,
                ];
            }),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
