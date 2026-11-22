<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\PlantValidationRuleChange;
use App\Support\BoxValidationRules;
use App\Support\PlantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlantValidationRulesController extends Controller
{
    public function __construct(private readonly PlantAccess $access) {}

    public function index(Request $request): View
    {
        $plants = $request->user()->plants()
            ->wherePivot('role', 'supervisor')
            ->orderBy('name')
            ->get();

        abort_if($plants->isEmpty(), 403);

        return view('plants.validation-index', compact('plants'));
    }

    public function edit(Request $request, Plant $plant): View
    {
        $this->access->authorize($request->user(), $plant->id, 'configure');

        $rules = BoxValidationRules::normalize($plant->validation_rules);
        $changes = PlantValidationRuleChange::query()
            ->where('plant_id', $plant->id)
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate(15);

        return view('plants.validation-edit', compact('plant', 'rules', 'changes'));
    }

    public function update(Request $request, Plant $plant): RedirectResponse
    {
        $this->access->authorize($request->user(), $plant->id, 'configure');

        $data = $request->validate([
            'required_scans' => ['required', 'array', 'min:1', 'max:3'],
            'required_scans.*' => ['required', 'string', 'distinct', Rule::in(BoxValidationRules::SCAN_TYPES)],
            'package_label_prefix' => ['required', 'string', 'regex:/^[A-Z0-9_-]{1,16}$/D'],
            'package_label_format' => ['sometimes', 'string', Rule::in(BoxValidationRules::LABEL_FORMATS)],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $incoming = BoxValidationRules::normalize([
            'required_scans' => $data['required_scans'],
            'package_label_prefix' => $data['package_label_prefix'],
            'package_label_format' => $data['package_label_format'] ?? 'colon',
        ]);

        // Normalize order so selecting identical options does not create audit noise.
        $incoming['required_scans'] = array_values(array_filter(
            BoxValidationRules::SCAN_TYPES,
            fn (string $type): bool => in_array($type, $incoming['required_scans'], true)
        ));

        $changed = DB::transaction(function () use ($request, $plant, $data, $incoming): bool {
            $locked = Plant::query()->lockForUpdate()->findOrFail($plant->id);

            if ($locked->validation_rules_version !== (int) $data['version']) {
                throw ValidationException::withMessages([
                    'version' => 'Las reglas cambiaron desde que abriste esta pantalla. Actualiza la página antes de guardar.',
                ]);
            }

            $previous = BoxValidationRules::normalize($locked->validation_rules);
            if ($previous === $incoming) {
                return false;
            }

            $nextVersion = $locked->validation_rules_version + 1;
            // Version is internal, never mass-assignable from user input.
            $locked->validation_rules = $incoming;
            $locked->validation_rules_version = $nextVersion;
            $locked->save();

            PlantValidationRuleChange::query()->create([
                'plant_id' => $locked->id,
                'user_id' => $request->user()->id,
                'version' => $nextVersion,
                'previous_rules' => $previous,
                'new_rules' => $incoming,
            ]);

            return true;
        });

        return redirect()->route('plants.validation.edit', $plant)
            ->with('status', $changed
                ? 'Reglas actualizadas. Se aplicarán solamente a las cajas nuevas.'
                : 'No hubo cambios en la configuración.');
    }
}
