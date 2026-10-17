<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Drought\StoreWaterLevelRequest;
use App\Http\Requests\Drought\UpdateWaterLevelRequest;
use App\Models\Drought\WaterLevel;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaterLevelController extends Controller
{
    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $type = $request->string('type_source')->toString();

        $levels = WaterLevel::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(array_key_exists($type, WaterLevel::TYPES), fn ($query) => $query->where('type_source', $type))
            ->latest('date_mesure')
            ->paginate(20)
            ->withQueryString();

        return view('back.drought.levels.index', [
            'levels' => $levels,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => WaterLevel::TYPES,
            'currentZone' => $zoneId ?: null,
            'currentType' => $type,
        ]);
    }

    public function create(): View
    {
        return view('back.drought.levels.create', $this->formData(new WaterLevel([
            'type_source' => 'barrage',
            'unite' => '%',
            'date_mesure' => now(),
        ])));
    }

    public function store(StoreWaterLevelRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['unite'] = '%';

        WaterLevel::create($data);

        return redirect()
            ->route('back.water-levels.index')
            ->with('success', 'La mesure de niveau a été enregistrée.');
    }

    public function edit(WaterLevel $level): View
    {
        return view('back.drought.levels.edit', $this->formData($level));
    }

    public function update(UpdateWaterLevelRequest $request, WaterLevel $level): RedirectResponse
    {
        $data = $request->validated();
        $data['unite'] = '%';

        $level->update($data);

        return redirect()
            ->route('back.water-levels.index')
            ->with('success', 'La mesure de niveau a été mise à jour.');
    }

    public function destroy(WaterLevel $level): RedirectResponse
    {
        $level->delete();

        return redirect()
            ->route('back.water-levels.index')
            ->with('success', 'La mesure de niveau a été supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(WaterLevel $level): array
    {
        return [
            'level' => $level,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => WaterLevel::TYPES,
        ];
    }
}
