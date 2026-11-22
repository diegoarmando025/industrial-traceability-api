<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Plant;
use App\Services\TraceabilityService;
use App\Support\PlantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BoxImportController extends Controller
{
    private const MAX_ROWS = 200;

    public function __construct(
        private readonly PlantAccess $access,
        private readonly TraceabilityService $traceability,
    ) {}

    public function create(Request $request): View
    {
        $plants = $request->user()->plants()->wherePivotIn('role', ['operator', 'supervisor'])->orderBy('name')->get();
        abort_if($plants->isEmpty(), 403);
        return view('boxes.import', compact('plants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);
        $plantId = (int) $data['plant_id'];
        $this->access->authorize($request->user(), $plantId, 'create');

        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'No fue posible leer el archivo.']);
        }
        try {
            $header = fgetcsv($handle);
            if (! is_array($header)) {
                throw ValidationException::withMessages(['file' => 'El CSV está vacío.']);
            }
            $header = array_map(static fn($cell) => trim((string)$cell), $header);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            if ($header !== ['code','part_number','serial_number']) {
                throw ValidationException::withMessages(['file' => 'Encabezados requeridos: code,part_number,serial_number (en ese orden).']);
            }
            $rows = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if (count($values) === 1 && trim((string)$values[0]) === '') {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'Máximo 200 cajas por archivo.']);
                }
                if (count($values) !== 3) {
                    throw ValidationException::withMessages(['file' => "Línea {$line}: se requieren tres columnas."]);
                }
                [$boxCode, $part, $serial] = array_map('trim', $values);
                if ($boxCode === '' || $part === '' || $serial === ''
                    || strlen($boxCode) > 60 || strlen($part) > 80 || strlen($serial) > 80) {
                    throw ValidationException::withMessages(['file' => "Línea {$line}: campos vacíos o demasiado largos."]);
                }
                if (isset($seen[$boxCode])) {
                    throw ValidationException::withMessages(['file' => "Código duplicado en archivo: {$boxCode}."]);
                }
                $seen[$boxCode] = true;
                $rows[] = compact('boxCode', 'part', 'serial');
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Incluye al menos una caja.']);
        }
        if (Box::query()->whereIn('code', array_column($rows, 'boxCode'))->exists()) {
            throw ValidationException::withMessages(['file' => 'Uno o más códigos ya están registrados. Ninguna caja fue importada.']);
        }

        try {
            DB::transaction(function () use ($rows, $plantId, $request): void {
                foreach ($rows as $row) {
                    $this->traceability->create([
                        'plant_id' => $plantId, 'code' => $row['boxCode'],
                        'part_number' => $row['part'], 'serial_number' => $row['serial'],
                    ], $request->user()->id);
                }
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            throw ValidationException::withMessages(['file' => 'No se pudo completar la importación. Ninguna caja del archivo se guardó. Revisa los códigos.']);
        }

        return redirect()->route('boxes.index', ['plant_id' => $plantId])
            ->with('status', count($rows).' cajas registradas correctamente.');
    }
}
