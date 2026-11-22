<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Services\TraceabilityService;
use App\Support\PlantAccess;
use App\Support\BoxValidationRules;
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

        $profiles = \App\Models\PlantCustomerProfile::query()
            ->whereIn('plant_id', $plants->pluck('id'))
            ->where('active', true)->with('plant:id,code')->orderBy('code')->get();

        return view('boxes.create', compact('plants', 'profiles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:60', 'unique:boxes,code'],
            'part_number' => ['required', 'string', 'max:80'],
            'serial_number' => ['required', 'string', 'max:80'],
            'customer_profile_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->access->authorize($request->user(), (int) $data['plant_id'], 'create');
        $box = $this->traceability->create($data, $request->user()->id);

        return redirect()->route('boxes.show', $box)->with('status', 'Caja registrada.');
    }

    public function show(Request $request, Box $box): View
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'read');
        $box->load('plant:id,code,name', 'customerProfile:id,code');
        $scans = $box->scans()->with('user:id,name')->orderByDesc('id')->limit(20)->get();
        $events = $box->events()->with('user:id,name')->orderByDesc('id')->limit(20)->get();

        $pendingScans = BoxValidationRules::pending($box);
        $role = $request->user()->plants()->whereKey($box->plant_id)->firstOrFail()->pivot->role;
        $canScan = in_array($role, ['operator', 'supervisor'], true) && $box->status !== 'released';
        $canRelease = $role === 'supervisor' && $box->status !== 'released';

        return view('boxes.show', compact('box', 'scans', 'events', 'pendingScans', 'canScan', 'canRelease'));
    }
    public function scan(Request $request, Box $box): RedirectResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'scan');

        $data = $request->validate([
            'scan_type' => ['required', Rule::in(TraceabilityService::SCAN_TYPES)],
            'value' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->traceability->scan($box, $data['scan_type'], $data['value'], $request->user()->id);

        return redirect()->route('boxes.show', $box)->with(
            $result['accepted'] ? 'status' : 'warning',
            $result['accepted'] ? 'Lectura válida.' : 'Lectura rechazada: el valor no coincide.'
        );
    }

    public function release(Request $request, Box $box): RedirectResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'release');

        $result = $this->traceability->release($box, $request->user()->id);

        return redirect()->route('boxes.show', $box)->with(
            $result['released'] ? 'status' : 'warning',
            $result['released']
                ? 'Caja liberada.'
                : 'No se puede liberar. Faltan lecturas válidas: '.implode(', ', $result['pending_scans']).'.'
        );
    }
}
