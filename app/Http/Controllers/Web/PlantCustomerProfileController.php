<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\PlantCustomerProfile;
use App\Support\BoxValidationRules;
use App\Support\PlantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantCustomerProfileController extends Controller
{
    public function __construct(private readonly PlantAccess $access) {}

    public function index(Request $request, Plant $plant): View
    {
        $this->access->authorize($request->user(), $plant->id, 'configure');
        $profiles = $plant->customerProfiles()->orderBy('code')->paginate(25);

        return view('plants.customer-profiles', compact('plant', 'profiles'));
    }

    public function store(Request $request, Plant $plant): RedirectResponse
    {
        $this->access->authorize($request->user(), $plant->id, 'configure');

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_-]{1,30}$/D',
                Rule::unique('plant_customer_profiles', 'code')->where('plant_id', $plant->id)],
            'name' => ['required', 'string', 'max:120'],
            'required_scans' => ['required', 'array', 'min:1', 'max:3'],
            'required_scans.*' => ['required', 'string', 'distinct', Rule::in(BoxValidationRules::SCAN_TYPES)],
            'package_label_prefix' => ['required', 'string', 'regex:/^[A-Z0-9_-]{1,16}$/D'],
            'package_label_format' => ['required', 'string', Rule::in(BoxValidationRules::LABEL_FORMATS)],
        ]);

        $rules = BoxValidationRules::normalize([
            'required_scans' => $data['required_scans'],
            'package_label_prefix' => $data['package_label_prefix'],
            'package_label_format' => $data['package_label_format'],
        ]);
        $rules['required_scans'] = array_values(array_filter(
            BoxValidationRules::SCAN_TYPES,
            fn (string $type): bool => in_array($type, $rules['required_scans'], true)
        ));

        $plant->customerProfiles()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'validation_rules' => $rules,
            'active' => true,
        ]);

        return redirect()->route('plants.customers.index', $plant)
            ->with('status', 'Perfil registrado. Disponible para cajas nuevas de esta planta.');
    }

    // Deactivation does not affect existing boxes or historical releases.
    public function deactivate(Request $request, Plant $plant, PlantCustomerProfile $profile): RedirectResponse
    {
        $this->access->authorize($request->user(), $plant->id, 'configure');
        abort_unless((int) $profile->plant_id === (int) $plant->id, 404);
        $profile->update(['active' => false]);

        return redirect()->route('plants.customers.index', $plant)
            ->with('status', 'Perfil desactivado. Las cajas existentes no cambian.');
    }
}
