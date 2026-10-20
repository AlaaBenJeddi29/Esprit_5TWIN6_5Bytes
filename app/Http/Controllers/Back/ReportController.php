<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    use ResolvesListingFilters;

    /**
     * Liste paginée des signalements, avec recherche, type, statut et période.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        ['from' => $from, 'to' => $to] = $this->listingDateRange($request);
        $perPage = $this->listingPerPage($request, 10);

        $reports = Report::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when(
                array_key_exists($status, Report::STATUSES),
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                array_key_exists($type, Report::TYPES),
                fn ($query) => $query->where('type', $type)
            )
            ->when($from !== null, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('back.reports.index', [
            'reports' => $reports,
            'statuses' => Report::STATUSES,
            'types' => Report::TYPES,
            'search' => $search,
            'currentStatus' => $status,
            'currentType' => $type,
            'from' => $from,
            'to' => $to,
            'perPage' => $perPage,
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
