<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicDemoController extends Controller
{
    private function plant(): Plant
    {
        abort_unless(config('traceability_demo.enabled'), 404);

        return Plant::query()
            ->where('code', config('traceability_demo.plant_code'))
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        $plant = $this->plant();

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'released'])],
        ]);

        $base = Box::query()->where('plant_id', $plant->id);
        $counts = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'released' => (clone $base)->where('status', 'released')->count(),
        ];

        $boxes = $base->when(
            ! empty($filters['status']),
            fn ($q) => $q->where('status', $filters['status'])
        )->latest('id')->paginate(12)->withQueryString();

        return response()->view('public-demo.index', compact('plant', 'counts', 'boxes', 'filters'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Box $box)
    {
        $plant = $this->plant();
        abort_unless((int) $box->plant_id === (int) $plant->id, 404);

        $box->load([
            'scans' => fn ($q) => $q->orderBy('id'),
            'events' => fn ($q) => $q->orderBy('id'),
        ]);

        return response()->view('public-demo.show', compact('box'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }
}
