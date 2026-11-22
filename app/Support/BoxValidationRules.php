<?php

namespace App\Support;

use App\Models\Box;
use InvalidArgumentException;

final class BoxValidationRules
{
    public const SCAN_TYPES = ['part_number', 'serial_number', 'package_label'];

    public const LABEL_FORMATS = ['colon', 'pipe'];

    public const DEFAULT = [
        'required_scans' => ['part_number', 'serial_number', 'package_label'],
        'package_label_prefix' => 'BOX',
    ];

    public static function normalize(?array $rules): array
    {
        $rules ??= self::DEFAULT;

        if (array_diff(array_keys($rules), array_keys(self::DEFAULT)) !== []) {
            throw new InvalidArgumentException('Unsupported validation rule key.');
        }

        $required = $rules['required_scans'] ?? self::DEFAULT['required_scans'];
        $prefix = $rules['package_label_prefix'] ?? self::DEFAULT['package_label_prefix'];
        $format = $rules['package_label_format'] ?? self::DEFAULT['package_label_format'];

        if (! is_array($required) || ! array_is_list($required)
            || $required === [] || count($required) !== count(array_unique($required))
            || array_diff($required, self::SCAN_TYPES) !== []
        ) {
            throw new InvalidArgumentException('Invalid required scan types.');
        }

        if (! is_string($prefix) || ! preg_match('/^[A-Z0-9_-]{1,16}$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid package label prefix.');
        }

        if (! is_string($format) || ! in_array($format, self::LABEL_FORMATS, true)) {
            throw new InvalidArgumentException('Invalid package label format.');
        }

        // Omit the default format from normalized output for compatibility with
        // historical JSON rule snapshots and existing public API assertions.
        $normalized = ['required_scans' => array_values($required), 'package_label_prefix' => $prefix];
        if ($format !== 'colon') {
            $normalized['package_label_format'] = $format;
        }

        return $normalized;
    }

    public static function forBox(Box $box): array
    {
        // Existing boxes without a snapshot retain their original BOX/three-scans behavior.
        return self::normalize($box->validation_rules);
    }

    public static function expectedValue(Box $box, string $type): string
    {
        $rules = self::forBox($box);

        return match ($type) {
            'part_number' => $box->part_number,
            'serial_number' => $box->serial_number,
            'package_label' => match ($rules['package_label_format'] ?? 'colon') {
                'colon' => $rules['package_label_prefix'].':'.$box->code.':'.$box->part_number.':'.$box->serial_number,
                'pipe' => $rules['package_label_prefix'].'|'.$box->part_number.'|'.$box->serial_number.'|'.$box->code,
            },
            default => throw new InvalidArgumentException('Unsupported scan type.'),
        };
    }

    public static function pending(Box $box): array
    {
        $latest = $box->scans()->orderByDesc('id')->get()->unique('scan_type')->keyBy('scan_type');

        return array_values(array_filter(
            self::forBox($box)['required_scans'],
            fn (string $type): bool => ! $latest->has($type) || ! $latest->get($type)->accepted
        ));
    }
}
