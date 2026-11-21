<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Services\TraceabilityService;
use App\Support\PlantAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoxController extends Controller
{
    public function __construct(
        private readonly TraceabilityService $traceability,
        private readonly PlantAccess $access,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:60', 'unique:boxes,code'],
            'part_number' => ['required', 'string', 'max:80'],
            'serial_number' => ['required', 'string', 'max:80'],
        ]);

        $this->access->authorize($request->user(), (int) $data['plant_id'], 'create');

        $box = $this->traceability->create($data, $request->user()->id);

        return response()->json(['data' => $box], 201);
    }

    public function show(Request $request, Box $box): JsonResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'read');

        return response()->json(['data' => $box]);
    }

    public function scan(Request $request, Box $box): JsonResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'scan');

        $data = $request->validate([
            'scan_type' => ['required', Rule::in(TraceabilityService::SCAN_TYPES)],
            'value' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->traceability->scan($box, $data['scan_type'], $data['value'], $request->user()->id);

        return response()->json(['data' => $result], $result['accepted'] ? 201 : 422);
    }

    public function release(Request $request, Box $box): JsonResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'release');

        $result = $this->traceability->release($box, $request->user()->id);

        return response()->json(['data' => $result], $result['released'] ? 200 : 409);
    }

    public function history(Request $request, Box $box): JsonResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'read');

        return response()->json(['data' => [
            'events' => $box->events()->orderBy('id')->get(),
            'scans' => $box->scans()->orderBy('id')->get(),
        ]]);
    }
}
