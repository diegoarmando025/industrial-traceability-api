<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxEvent;
use App\Models\BoxScan;
use App\Support\PlantAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalReportController extends Controller
{
    public function __construct(private readonly PlantAccess $access) {}

    private function report(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'plant_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse($filters['from'] ?? $today->subDays(29)->toDateString());
        $to = CarbonImmutable::parse($filters['to'] ?? $today->toDateString());

        if ($to->lessThan($from) || $from->diffInDays($to) > 89) {
            throw ValidationException::withMessages([
                'from' => 'Selecciona un intervalo válido de máximo 90 días.',
            ]);
        }

        $plants = $request->user()->plants()->orderBy('name')->get(['plants.id', 'plants.code', 'plants.name']);
        $ids = $plants->pluck('id')->map(fn ($id) => (int) $id);

        if (isset($filters['plant_id'])) {
            $this->access->authorize($request->user(), (int) $filters['plant_id'], 'read');
            $ids = collect([(int) $filters['plant_id']]);
        }

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $fromTs = $fromDate.' 00:00:00';
        $untilTs = $to->addDay()->toDateString().' 00:00:00';

        $boxBase = Box::query()->whereIn('plant_id', $ids);
        $scansBase = BoxScan::query()->whereHas('box', fn ($q) => $q->whereIn('plant_id', $ids));
        $eventsBase = BoxEvent::query()->whereHas('box', fn ($q) => $q->whereIn('plant_id', $ids));

        $registered = (clone $boxBase)->where('created_at', '>=', $fromTs)
            ->where('created_at', '<', $untilTs)->count();
        $released = (clone $boxBase)->where('released_at', '>=', $fromTs)
            ->where('released_at', '<', $untilTs)->count();
        $scans = (clone $scansBase)->where('created_at', '>=', $fromTs)
            ->where('created_at', '<', $untilTs);
        $totalScans = (clone $scans)->count();
        $rejected = (clone $scans)->where('accepted', false)->count();
        $blocked = (clone $eventsBase)->where('event_type', 'release_blocked')
            ->where('created_at', '>=', $fromTs)->where('created_at', '<', $untilTs)->count();

        $dailyScans = (clone $scans)->selectRaw('DATE(created_at) as day, COUNT(*) as total, SUM(CASE WHEN accepted = 0 THEN 1 ELSE 0 END) as rejected')
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('day')->get()->keyBy('day');
        $dailyRegistered = (clone $boxBase)->where('created_at', '>=', $fromTs)
            ->where('created_at', '<', $untilTs)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))->get()->keyBy('day');
        $dailyReleased = (clone $boxBase)->where('released_at', '>=', $fromTs)
            ->where('released_at', '<', $untilTs)
            ->selectRaw('DATE(released_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(released_at)'))->get()->keyBy('day');

        $days = [];
        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $allScans = (int) ($dailyScans[$date]->total ?? 0);
            $badScans = (int) ($dailyScans[$date]->rejected ?? 0);
            $days[] = [
                'date' => $date,
                'registered' => (int) ($dailyRegistered[$date]->total ?? 0),
                'released' => (int) ($dailyReleased[$date]->total ?? 0),
                'accepted' => $allScans - $badScans,
                'rejected' => $badScans,
            ];
        }

        $plantSummary = (clone $boxBase)
            ->selectRaw('plant_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as released', ['released'])
            ->groupBy('plant_id')->get()->keyBy('plant_id');
        $visiblePlants = $plants->whereIn('id', $ids)->map(function ($plant) use ($plantSummary) {
            $aggregate = $plantSummary[$plant->id] ?? null;

            return [
                'code' => $plant->code,
                'name' => $plant->name,
                'total' => (int) ($aggregate?->total ?? 0),
                'released' => (int) ($aggregate?->released ?? 0),
            ];
        })->values();

        return [
            'filters' => ['from' => $fromDate, 'to' => $toDate, 'plant_id' => $filters['plant_id'] ?? null],
            'plants' => $plants,
            'kpis' => [
                'registered' => $registered,
                'released' => $released,
                'scans' => $totalScans,
                'rejected' => $rejected,
                'rejection_pct' => $totalScans ? round($rejected * 100 / $totalScans, 1) : null,
                'blocked' => $blocked,
            ],
            'days' => $days,
            'plantSummary' => $visiblePlants,
        ];
    }

    public function index(Request $request): View
    {
        return view('reports.operations', $this->report($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->report($request);
        $filename = 'traceability-reporte-'.$report['filters']['from'].'-'.$report['filters']['to'].'.csv';

        return response()->streamDownload(static function () use ($report): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Fecha', 'Cajas registradas', 'Cajas liberadas', 'Lecturas aceptadas', 'Lecturas rechazadas'], ',', '"', '');
            foreach ($report['days'] as $day) {
                fputcsv($stream, array_values($day), ',', '"', '');
            }
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
