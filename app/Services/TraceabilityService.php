<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Plant;
use App\Models\PlantCustomerProfile;
use App\Support\BoxValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TraceabilityService
{
    public const SCAN_TYPES = ['part_number', 'serial_number', 'package_label'];

    public function create(array $data, int $userId): Box
    {
        return DB::transaction(function () use ($data, $userId): Box {
            $plant = Plant::query()->findOrFail($data['plant_id']);
            $profile = null;
            if (! empty($data['customer_profile_id'])) {
                $profile = PlantCustomerProfile::query()
                    ->where('plant_id', $plant->id)
                    ->where('active', true)
                    ->findOrFail($data['customer_profile_id']);
            }
            $rules = BoxValidationRules::normalize($profile?->validation_rules ?? $plant->validation_rules);
            if (($rules['package_label_format'] ?? 'colon') === 'pipe') {
                foreach (['code', 'part_number', 'serial_number'] as $field) {
                    if (str_contains($data[$field], '|')) {
                        throw ValidationException::withMessages([
                            $field => 'El campo no puede contener | cuando se utiliza el formato alternativo.',
                        ]);
                    }
                }
            }
            $box = Box::query()->create([
                'plant_id' => $plant->id,
                'customer_profile_id' => $profile?->id,
                'code' => $data['code'],
                'part_number' => $data['part_number'],
                'serial_number' => $data['serial_number'],
                'status' => 'pending',
                'validation_rules' => $rules,
            ]);
            $box->events()->create([
                'user_id' => $userId,
                'event_type' => 'created',
                'details' => ['code' => $box->code, 'customer_profile_id' => $profile?->id],
                'created_at' => now(),
            ]);
            return $box;
        });
    }

    public function scan(Box $box, string $type, string $value, int $userId): array
    {
        return DB::transaction(function () use ($box, $type, $value, $userId): array {
            $locked = Box::query()->lockForUpdate()->findOrFail($box->id);
            if ($locked->status === 'released') {
                throw new ConflictHttpException('La caja ya fue liberada.');
            }
            $expected = BoxValidationRules::expectedValue($locked, $type);
            $accepted = hash_equals($expected, $value);
            $scan = $locked->scans()->create([
                'user_id' => $userId,
                'scan_type' => $type,
                'scanned_value' => $value,
                'accepted' => $accepted,
                'reason' => $accepted ? null : 'value_mismatch',
                'created_at' => now(),
            ]);
            return ['scan_id' => $scan->id, 'accepted' => $accepted, 'reason' => $scan->reason];
        });
    }

    public function release(Box $box, int $userId): array
    {
        return DB::transaction(function () use ($box, $userId): array {
            $locked = Box::query()->lockForUpdate()->findOrFail($box->id);
            if ($locked->status === 'released') {
                throw new ConflictHttpException('La caja ya fue liberada.');
            }
            $rules = BoxValidationRules::forBox($locked);
            $missing = BoxValidationRules::pending($locked);
            if ($missing !== []) {
                $locked->events()->create([
                    'user_id' => $userId,
                    'event_type' => 'release_blocked',
                    'details' => ['pending_scans' => $missing],
                    'created_at' => now(),
                ]);
                return ['released' => false, 'pending_scans' => $missing];
            }
            $locked->update(['status' => 'released', 'released_at' => now()]);
            $locked->events()->create([
                'user_id' => $userId,
                'event_type' => 'released',
                'details' => ['validated_scans' => $rules['required_scans']],
                'created_at' => now(),
            ]);
            return ['released' => true, 'pending_scans' => []];
        });
    }
}
