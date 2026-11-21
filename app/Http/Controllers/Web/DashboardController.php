<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Box;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $plantIds = $request->user()->plants()->pluck('plants.id');

        $visibleBoxes = Box::query()->whereIn('plant_id', $plantIds);

        $counts = [
            'total' => (clone $visibleBoxes)->count(),
            'pending' => (clone $visibleBoxes)->where('status', 'pending')->count(),
            'released' => (clone $visibleBoxes)->where('status', 'released')->count(),
        ];

        $boxes = $visibleBoxes->with('plant:id,code,name')
            ->latest('id')->limit(10)->get();

        return view('dashboard', compact('counts', 'boxes'));
    }
}
