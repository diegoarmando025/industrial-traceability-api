<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Support\PlantAccess;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoxHistoryController extends Controller
{
    public function __construct(private readonly PlantAccess $access) {}

    private function filters(Request $request): array
    {
        return $request->validate([
            'kind' => ['nullable', Rule::in(['scans', 'events'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'result' => ['nullable', Rule::in(['accepted', 'rejected'])],
            'scan_type' => ['nullable', Rule::in(['part_number', 'serial_number', 'package_label'])],
        ]);
    }

    private function records(Box $box, array $filters): HasMany
    {
        $kind = $filters['kind'] ?? 'scans';
        $query = $kind === 'events' ? $box->events() : $box->scans();

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }
        if ($kind === 'scans') {
            if (isset($filters['result'])) {
                $query->where('accepted', $filters['result'] === 'accepted');
            }
            if (! empty($filters['scan_type'])) {
                $query->where('scan_type', $filters['scan_type']);
            }
        }

        return $query->orderByDesc('id');
    }

    public function index(Request $request, Box $box): View
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'read');
        $filters = $this->filters($request);
        $box->load('plant:id,code,name');
        $kind = $filters['kind'] ?? 'scans';
        $records = $this->records($box, $filters)->with('user:id,name')
            ->paginate(20)->withQueryString();

        return view('boxes.history', compact('box', 'filters', 'kind', 'records'));
    }

    public function export(Request $request, Box $box): StreamedResponse
    {
        $this->access->authorize($request->user(), (int) $box->plant_id, 'read');
        $filters = $this->filters($request);
        $kind = $filters['kind'] ?? 'scans';
        $query = $this->records($box, $filters)->with('user:id,name');

        // Maximum export size is bounded for shared hosting. Do not silently
        // truncate reports: the user must narrow filters to 10,000 rows or less.
        abort_if((clone $query)->limit(10001)->count() > 10000, 422,
            'El reporte excede 10,000 registros. Reduce el intervalo de fechas.');

        $filename = 'traceability-'.$kind.'-box-'.$box->id.'.csv';

        return response()->streamDownload(function () use ($query, $kind, $box): void {
            $out = fopen('php://output', 'wb');
            // UTF-8 BOM helps Excel interpret accented Spanish headers.
            fwrite($out, "\xEF\xBB\xBF");
            if ($kind === 'scans') {
                fputcsv($out, ['Caja', 'Fecha', 'Tipo', 'Valor leído', 'Resultado', 'Motivo', 'Usuario'], ',', '"', '');
            } else {
                fputcsv($out, ['Caja', 'Fecha', 'Evento', 'Detalle', 'Usuario'], ',', '"', '');
            }

            foreach ($query->limit(10000)->cursor() as $record) {
                $date = $record->created_at?->format('Y-m-d H:i:s') ?? '';
                if ($kind === 'scans') {
                    $cells = [$box->code, $date, $record->scan_type,
                        $record->scanned_value, $record->accepted ? 'Aceptada' : 'Rechazada',
                        $record->reason ?? '', $record->user?->name ?? ''];
                } else {
                    $cells = [$box->code, $date, $record->event_type,
                        json_encode($record->details ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $record->user?->name ?? ''];
                }

                // Prevent spreadsheet formula execution when opening exported
                // externally supplied scan values, codes or user names in Excel.
                $cells = array_map(static function ($value): string {
                    $value = (string) $value;
                    return preg_match('/^[\x00-\x20]*[=+@\-]/', $value) ? "'".$value : $value;
                }, $cells);
                fputcsv($out, $cells, ',', '"', '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
