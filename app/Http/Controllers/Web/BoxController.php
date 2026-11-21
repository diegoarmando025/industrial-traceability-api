<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Services\TraceabilityService;
use App\Support\PlantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class BoxController extends Controller
{
    public function __construct(
        private readonly TraceabilityService $traceability,
        private readonly PlantAccess $access,
    ) {}

    public function index(Request $request): View
    {
        $plants = $request->user()->plants()->orderBy('name')->get();
        $plantIds = $plants->pluck('id');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'released'])],
            'plant_id' => ['nullable', 'integer'],
        ]);

        $query = Box::query()->with('plant:id,code,name')->whereIn('plant_id', $plantIds);

        if (! empty($filters['plant_id'])) {
            $this->access->authorize($request->user(), (int) $filters['plant_id'], 'read');
            $query->where('plant_id', $filters['plant_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']);
            $query->where(function ($q) use ($term): void {
                $q->where('code', 'like', '%'.$term.'%')
                    ->orWhere('part_number', 'like', '%'.$term.'%')
                    ->orWhere('serial_number', 'like', '%'.$term.'%');
            });
        }

        return view('boxes.index', [
            'boxes' => $query->orderByDesc('id')->paginate(15)->withQueryString(),
            'plants' => $plants,
            'filters' => $filters,
            'canCreate' => $plants->contains(fn ($plant) => in_array($plant->pivot->role, ['operator', 'supervisor'], true)),
        ]);
    }

    public function create(Request $request): View
    {
        $plants = $request->user()->plants()
            ->wherePivotIn('role', ['operator', 'supervisor'])
            ->orderBy('name')->get();

        abort_if($plants->isEmpty(), 403);

        return view('boxes.create', compact('plants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:60', 'unique:boxes,code'],
            'part_number' => ['required', 'string', 'max:80'],
            'serial_number' => ['required', 'string', 'max:80'],
        ]);

        $this->access->authorize($request->user(), (int) $data['plant_id'], 'create');
        $box = $this->traceability->create($data, $request->user()->id);

        return redirect()->route('boxes.show', $box)->with('status', 'Caja registrada.');
    }

    public function show(Request $request, Box $box): View
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'read');
        $box->load('plant:id,code,name');
        $scans = $box->scans()->orderByDesc('id')->limit(20)->get();
        $events = $box->events()->orderByDesc('id')->limit(20)->get();

        return view('boxes.show', compact('box', 'scans', 'events'));
    }
}
