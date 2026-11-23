<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Plant;
use Database\Seeders\TraceabilityPublicDemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PortfolioScenarioMaintenanceController extends Controller
{
    public function complete(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_portfolio_owner === true
            && $request->user()?->is_portfolio_demo_admin !== true, 403);

        $request->validate(['confirmation' => ['required', 'in:COMPLETAR DEMO']]);

        try {
            $added = DB::transaction(function (): int {
                // Lock the dedicated demo plant to serialize maintenance operations.
                $plant = Plant::query()->where('code', 'TRACE-PUBLIC')->lockForUpdate()->firstOrFail();
                $before = Box::query()->where('plant_id', $plant->id)->count();
                app(TraceabilityPublicDemoSeeder::class)->run();
                return Box::query()->where('plant_id', $plant->id)->count() - $before;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['demo_scenario' => 'No fue posible completar los ejemplos ficticios. Consulta los registros del sistema.']);
        }

        Log::info('Traceability public demo scenario completed', [
            'owner_id' => $request->user()->id,
            'added_boxes' => $added,
        ]);

        return back()->with('status', $added > 0
            ? "Se añadieron {$added} cajas ficticias sin modificar las existentes."
            : 'Los ejemplos ya están registrados; no se modificaron las cajas existentes.');
    }
}
