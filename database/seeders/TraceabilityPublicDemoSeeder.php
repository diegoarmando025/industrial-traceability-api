<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use App\Services\TraceabilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TraceabilityPublicDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $plant = Plant::query()->firstOrCreate(
                ['code' => 'TRACE-PUBLIC'],
                ['name' => 'Ensamble automotriz · Escenario ficticio']
            );

            $operator = User::query()->firstOrCreate(
                ['email' => 'public-operator@traceability.example.invalid'],
                ['name' => 'Operador de ejemplo', 'password' => Str::random(64)]
            );
            $supervisor = User::query()->firstOrCreate(
                ['email' => 'public-supervisor@traceability.example.invalid'],
                ['name' => 'Supervisor de ejemplo', 'password' => Str::random(64)]
            );
            // These accounts have no assigned plant memberships or shared credentials.

            $service = app(TraceabilityService::class);

            $released = $this->newBox($service, $plant, 'TRACE-PUBLIC-001', 'PN-ENS-104', 'SN-004201', $operator);
            if ($released->status === 'pending' && ! $released->scans()->exists()) {
                foreach ([
                    ['part_number', 'PN-ENS-104'],
                    ['serial_number', 'SN-004201'],
                    ['package_label', 'BOX:TRACE-PUBLIC-001:PN-ENS-104:SN-004201'],
                ] as [$type, $value]) {
                    $service->scan($released, $type, $value, $operator->id, trustedFixture: true);
                }
                $service->release($released, $supervisor->id, trustedFixture: true);
            }

            $blocked = $this->newBox($service, $plant, 'TRACE-PUBLIC-002', 'PN-ENS-205', 'SN-004202', $operator);
            if ($blocked->status === 'pending' && ! $blocked->scans()->exists()) {
                $service->scan($blocked, 'part_number', 'PN-ENS-999', $operator->id, trustedFixture: true);
                $service->release($blocked, $supervisor->id, trustedFixture: true);
            }

            $inProgress = $this->newBox($service, $plant, 'TRACE-PUBLIC-003', 'PN-ENS-306', 'SN-004203', $operator);
            if ($inProgress->status === 'pending' && ! $inProgress->scans()->exists()) {
                $service->scan($inProgress, 'part_number', 'PN-ENS-306', $operator->id, trustedFixture: true);
            }

            // Additional reserved examples are initialized only when first created.
            // Re-running this seeder never rewrites visitors' existing scans or releases.
            $this->createScenario($service, $plant, 'TRACE-PUBLIC-004', 'PN-ENS-407', 'SN-004204', $operator,
                function (Box $box) use ($service, $operator): void {
                    $service->scan($box, 'part_number', 'PN-INCORRECTO', $operator->id, trustedFixture: true);
                    $service->scan($box, 'part_number', $box->part_number, $operator->id, trustedFixture: true);
                    $service->scan($box, 'serial_number', $box->serial_number, $operator->id, trustedFixture: true);
                });

            $this->createScenario($service, $plant, 'TRACE-PUBLIC-005', 'PN-ENS-508', 'SN-004205', $operator,
                function (Box $box) use ($service, $operator): void {
                    foreach (['part_number', 'serial_number', 'package_label'] as $type) {
                        $service->scan($box, $type, \App\Support\BoxValidationRules::expectedValue($box, $type), $operator->id, trustedFixture: true);
                    }
                });

            $this->createScenario($service, $plant, 'TRACE-PUBLIC-006', 'PN-ENS-609', 'SN-004206', $operator,
                function (Box $box) use ($service, $operator, $supervisor): void {
                    $service->scan($box, 'part_number', $box->part_number, $operator->id, trustedFixture: true);
                    $service->scan($box, 'serial_number', $box->serial_number, $operator->id, trustedFixture: true);
                    $service->scan($box, 'package_label', 'BOX:ETIQUETA-INCORRECTA', $operator->id, trustedFixture: true);
                    $service->release($box, $supervisor->id, trustedFixture: true);
                });

            $this->createScenario($service, $plant, 'TRACE-PUBLIC-007', 'PN-ENS-710', 'SN-004207', $operator,
                function (Box $box) use ($service, $operator, $supervisor): void {
                    $service->scan($box, 'serial_number', 'SERIE-INCORRECTA', $operator->id, trustedFixture: true);
                    foreach (['part_number', 'serial_number', 'package_label'] as $type) {
                        $service->scan($box, $type, \App\Support\BoxValidationRules::expectedValue($box, $type), $operator->id, trustedFixture: true);
                    }
                    $service->release($box, $supervisor->id, trustedFixture: true);
                });

            $this->createScenario($service, $plant, 'TRACE-PUBLIC-008', 'PN-ENS-811', 'SN-004208', $operator,
                static function (Box $box): void {
                    // New unscanned box: available for an evaluator to complete.
                });
        });
    }


    private function createScenario(
        TraceabilityService $service,
        Plant $plant,
        string $code,
        string $part,
        string $serial,
        User $operator,
        callable $initialize
    ): void {
        $existing = Box::query()->where('code', $code)->first();
        if ($existing !== null) {
            if ((int) $existing->plant_id !== (int) $plant->id) {
                throw new RuntimeException('The fictional demo box code belongs to another plant.');
            }

            return;
        }

        $box = $service->create([
            'plant_id' => $plant->id,
            'code' => $code,
            'part_number' => $part,
            'serial_number' => $serial,
        ], $operator->id);

        $initialize($box);
    }

    private function newBox(TraceabilityService $service, Plant $plant, string $code, string $part, string $serial, User $operator): Box
    {
        $box = Box::query()->where('code', $code)->first();

        if ($box) {
            if ((int) $box->plant_id !== (int) $plant->id) {
                throw new RuntimeException('The fictional demo box code belongs to another plant.');
            }
            return $box;
        }

        return $service->create([
            'plant_id' => $plant->id,
            'code' => $code,
            'part_number' => $part,
            'serial_number' => $serial,
        ], $operator->id);
    }
}
