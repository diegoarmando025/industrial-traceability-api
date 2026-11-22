<?php

namespace App\Console\Commands;

use App\Models\Plant;
use Database\Seeders\TraceabilityPublicDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetPortfolioDemo extends Command
{
    protected $signature = 'portfolio:reset-demo {--dry-run : Mostrar registros sin modificar nada}';
    protected $description = 'Restaurar únicamente cajas y escaneos ficticios de TRACE-PUBLIC.';

    public function handle(): int
    {
        $plant = Plant::where('code', 'TRACE-PUBLIC')->first();
        if (! $plant) {
            $this->error('No existe TRACE-PUBLIC. No se modificó ningún registro.');
            return self::FAILURE;
        }
        if ($plant->validation_rules !== null) {
            $this->error('La planta ficticia tiene reglas personalizadas. Restáuralas de forma auditada antes de restablecer cajas.');
            return self::FAILURE;
        }

        $ids = DB::table('boxes')->where('plant_id', $plant->id)->pluck('id');
        $counts = [
            'cajas' => $ids->count(),
            'escaneos' => DB::table('box_scans')->whereIn('box_id', $ids)->count(),
            'eventos' => DB::table('box_events')->whereIn('box_id', $ids)->count(),
        ];
        $this->table(['Recurso ficticio', 'Registros'], collect($counts)->map(fn($n, $k) => [$k, $n])->values()->all());

        if ($this->option('dry-run')) {
            $this->info('Simulación sin cambios.');
            return self::SUCCESS;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Operación permitida únicamente en terminal interactiva.');
            return self::FAILURE;
        }
        $this->warn('Se eliminarán las cajas y operaciones de visitantes de TRACE-PUBLIC; otras plantas y usuarios se conservan.');
        if (! $this->confirm('¿Confirmas que verificaste un respaldo recuperable de la base de datos?', false)) {
            return self::FAILURE;
        }
        if ((string) $this->ask('Escribe RESTABLECER TRACE-PUBLIC para continuar') !== 'RESTABLECER TRACE-PUBLIC') {
            $this->error('Confirmación incorrecta.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($plant): void {
                $locked = Plant::whereKey($plant->id)->lockForUpdate()->firstOrFail();
                if ($locked->code !== 'TRACE-PUBLIC' || $locked->validation_rules !== null) {
                    throw new \RuntimeException('La configuración ficticia cambió; cancelado.');
                }
                $ids = DB::table('boxes')->where('plant_id', $locked->id)->pluck('id');
                DB::table('box_scans')->whereIn('box_id', $ids)->delete();
                DB::table('box_events')->whereIn('box_id', $ids)->delete();
                DB::table('boxes')->where('plant_id', $locked->id)->delete();
                // Do not delete users, plant memberships, validation audit logs or
                // customer profiles; they can be shared with other demo workflows.
                (new TraceabilityPublicDemoSeeder())->run();
            }, 3);
        } catch (Throwable $e) {
            report($e);
            $this->error('Error: operación revertida. Consulta los logs.');
            return self::FAILURE;
        }
        $this->info('Escenario ficticio de cajas restaurado.');
        return self::SUCCESS;
    }
}
