<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicInfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $type = $request->string('type')->toString();
        $statut = $request->string('statut')->toString();

        $infrastructures = Infrastructure::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(array_key_exists($type, array_flip(Infrastructure::TYPES)), fn ($query) => $query->where('type', $type))
            ->when(array_key_exists($statut, array_flip(Infrastructure::STATUTS)), fn ($query) => $query->where('statut', $statut))
            ->orderBy('nom')
            ->get();

        $mapPoints = $infrastructures
            ->filter(fn (Infrastructure $infrastructure) => $infrastructure->latitude && $infrastructure->longitude)
            ->map(fn (Infrastructure $infrastructure) => [
                'nom' => $infrastructure->nom,
                'type' => $infrastructure->typeLabel(),
                'statut' => $infrastructure->statutLabel(),
                'latitude' => (float) $infrastructure->latitude,
                'longitude' => (float) $infrastructure->longitude,
                'zone' => $infrastructure->zone?->nom,
                'url' => route('public.infrastructures.show', $infrastructure),
            ])
            ->values();

        return view('front.infrastructures.index', [
            'infrastructures' => $infrastructures,
            'mapPoints' => $mapPoints,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => Infrastructure::TYPES,
            'statuts' => Infrastructure::STATUTS,
            'currentZone' => $zoneId ?: null,
            'currentType' => $type,
            'currentStatut' => $statut,
        ]);
    }

    public function show(Infrastructure $infrastructure): View
    {
        $infrastructure->load([
            'zone',
            'maintenances' => fn ($query) => $query->latest('date_intervention')->limit(10),
            'projects' => fn ($query) => $query->latest()->limit(10),
            'incidents' => fn ($query) => $query->latest('reported_at')->limit(10),
        ]);

        return view('front.infrastructures.show', compact('infrastructure'));
    }
}
