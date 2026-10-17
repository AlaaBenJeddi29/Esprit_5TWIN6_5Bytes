<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Liste paginée des signalements, avec filtre optionnel par statut.
     */
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $reports = Report::query()
            ->with('user')
            ->when(
                array_key_exists($status, Report::STATUSES),
                fn ($query) => $query->where('status', $status)
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('back.reports.index', [
            'reports' => $reports,
            'statuses' => Report::STATUSES,
            'currentStatus' => $status,
        ]);
    }

    /**
     * Formulaire de création.
     */
    public function create(): View
    {
        return view('back.reports.create', [
            'report' => new Report(['status' => 'nouveau']),
            'types' => Report::TYPES,
            'statuses' => Report::STATUSES,
        ]);
    }

    /**
     * Enregistrement d'un nouveau signalement.
     */
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['reported_at'] = $data['reported_at'] ?? now();

        $report = Report::create($data);

        return redirect()
            ->route('back.reports.show', $report)
            ->with('success', 'Le signalement a été créé avec succès.');
    }

    /**
     * Détail d'un signalement.
     */
    public function show(Report $report): View
    {
        $report->load('user');

        return view('back.reports.show', [
            'report' => $report,
        ]);
    }

    /**
     * Formulaire de modification.
     */
    public function edit(Report $report): View
    {
        return view('back.reports.edit', [
            'report' => $report,
            'types' => Report::TYPES,
            'statuses' => Report::STATUSES,
        ]);
    }

    /**
     * Mise à jour d'un signalement.
     */
    public function update(UpdateReportRequest $request, Report $report): RedirectResponse
    {
        $report->update($request->validated());

        return redirect()
            ->route('back.reports.show', $report)
            ->with('success', 'Le signalement a été mis à jour avec succès.');
    }
}
