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

class TraceabilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data is restricted to local and testing environments.');
        }

        DB::transaction(function (): void {
            $plant = Plant::query()->firstOrCreate(
                ['code' => 'DEMO-QRO'],
                ['name' => 'Demo Manufacturing Plant']
            );

            $operator = $this->demoUser('operator@traceability.example.invalid', 'Demo Operator');
            $supervisor = $this->demoUser('supervisor@traceability.example.invalid', 'Demo Supervisor');

            $operator->plants()->syncWithoutDetaching([
                $plant->id => ['role' => 'operator'],
            ]);
            $supervisor->plants()->syncWithoutDetaching([
                $plant->id => ['role' => 'supervisor'],
            ]);

            $service = app(TraceabilityService::class);

            $complete = Box::query()->where('code', 'DEMO-QRO-001')->first();
            if ($complete === null) {
                $complete = $service->create([
                    'plant_id' => $plant->id,
                    'code' => 'DEMO-QRO-001',
                    'part_number' => 'DEMO-PN-100',
                    'serial_number' => 'DEMO-SN-100',
                ], $operator->id);

                foreach ([
                    'part_number' => 'DEMO-PN-100',
                    'serial_number' => 'DEMO-SN-100',
                    'package_label' => 'BOX:DEMO-QRO-001:DEMO-PN-100:DEMO-SN-100',
                ] as $type => $value) {
                    $service->scan($complete, $type, $value, $operator->id);
                }

                $service->release($complete, $supervisor->id);
            } elseif ($complete->plant_id !== $plant->id) {
                throw new RuntimeException('Demo box code already belongs to a different plant.');
            }

            $pending = Box::query()->where('code', 'DEMO-QRO-002')->first();
            if ($pending === null) {
                $pending = $service->create([
                    'plant_id' => $plant->id,
                    'code' => 'DEMO-QRO-002',
                    'part_number' => 'DEMO-PN-200',
                    'serial_number' => 'DEMO-SN-200',
                ], $operator->id);

                $service->scan($pending, 'part_number', 'WRONG-DEMO-PN', $operator->id);
            } elseif ($pending->plant_id !== $plant->id) {
                throw new RuntimeException('Demo box code already belongs to a different plant.');
            }
        });
    }

    private function demoUser(string $email, string $name): User
    {
        // Random, unknown passwords: the seed never exposes login credentials.
        // The API has no public login or token issuance endpoint.
        return User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Str::random(48)]
        );
    }
}
