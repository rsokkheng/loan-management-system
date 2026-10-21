<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\District;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Child options for the cascading address dropdowns.
 */
class LocationController extends Controller
{
    public function districts(Request $request): JsonResponse
    {
        return $this->options(District::options('province_id', $request->integer('parent')));
    }

    public function communes(Request $request): JsonResponse
    {
        return $this->options(Commune::options('district_id', $request->integer('parent')));
    }

    public function villages(Request $request): JsonResponse
    {
        return $this->options(Village::options('commune_id', $request->integer('parent')));
    }

    /**
     * @param  array<int, string>  $options
     */
    private function options(array $options): JsonResponse
    {
        return response()->json(
            collect($options)->map(fn (string $name, int $id) => ['id' => $id, 'name' => $name])->values(),
        );
    }
}
