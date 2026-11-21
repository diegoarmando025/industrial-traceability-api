<?php

namespace App\Support;

use App\Models\User;

class PlantAccess
{
    private const ALLOWED_ROLES = [
        'read' => ['viewer', 'operator', 'supervisor'],
        'create' => ['operator', 'supervisor'],
        'scan' => ['operator', 'supervisor'],
        'release' => ['supervisor'],
    ];

    public function authorize(User $user, int $plantId, string $action): void
    {
        // Do not disclose whether a plant or a box in another plant exists.
        $role = $user->plants()->whereKey($plantId)->first()?->pivot?->role;
        abort_if($role === null, 404);
        abort_unless(in_array($role, self::ALLOWED_ROLES[$action], true), 403);
    }
}
