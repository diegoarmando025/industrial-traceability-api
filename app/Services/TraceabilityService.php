<?php

namespace App\Services;

use App\Models\Box;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TraceabilityService
{
    public const SCAN_TYPES = ['part_number', 'serial_number', 'package_label'];

    public function create(array $data, int $userId): Box
    {
        return DB::transaction(function () use ($data, $userId): Box {
            $box = Box::query()->create($data + ['status' => 'pending']);
            $box->events()->create([
                'user_id' => $userId,
                'event_type' => 'created',
                'details' => ['code' => $box->code],
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
            $expected = match ($type) {
                'part_number' => $locked->part_number,
                'serial_number' => $locked->serial_number,
                'package_label' => 'BOX:' . $locked->code . ':' . $locked->part_number . ':' . $locked->serial_number,
            };
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
            $latest = $locked->scans()->orderByDesc('id')->get()->unique('scan_type')->keyBy('scan_type');
            $missing = array_values(array_filter(self::SCAN_TYPES, fn (string $type): bool =>
                ! $latest->has($type) || ! $latest->get($type)->accepted
            ));
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
                'details' => ['validated_scans' => self::SCAN_TYPES],
                'created_at' => now(),
            ]);
            return ['released' => true, 'pending_scans' => []];
        });
    }
}
