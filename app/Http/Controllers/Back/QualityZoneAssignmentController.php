<?php

namespace App\Http\Controllers\Back;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\UpdateQualityZoneAssignmentsRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityZoneAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QualityZoneAssignmentController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $search = $request->string('search')->toString();
        $perPage = $this->listingPerPage($request);

        $gestionnaires = User::query()
            ->where('role', UserRole::Gestionnaire->value)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $assignments = QualityZoneAssignment::query()
            ->whereIn('user_id', $gestionnaires->getCollection()->modelKeys())
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('zone_id')->map(fn ($id) => (int) $id)->all());

        return view('back.quality.assignments.index', [
            'gestionnaires' => $gestionnaires,
            'zones' => Zone::query()->orderBy('nom')->get(),
            'assignments' => $assignments,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function update(UpdateQualityZoneAssignmentsRequest $request): RedirectResponse
    {
        $gestionnaire = User::findOrFail($request->integer('gestionnaire_id'));
        abort_unless($gestionnaire->isGestionnaire(), 404);

        $zoneIds = array_values(array_unique(array_map('intval', $request->input('zone_ids', []))));

        DB::transaction(function () use ($gestionnaire, $zoneIds) {
            QualityZoneAssignment::query()->where('user_id', $gestionnaire->id)->delete();

            foreach ($zoneIds as $zoneId) {
                QualityZoneAssignment::create([
                    'user_id' => $gestionnaire->id,
                    'zone_id' => $zoneId,
                ]);
            }
        });

        return redirect()
            ->route('back.quality-assignments.index')
            ->with('success', "Le périmètre qualité de « {$gestionnaire->name} » a été mis à jour (".count($zoneIds).' zone(s)).');
    }
}
