<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Models\Project\AuditEvent;
use App\Models\Project\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Journal d'audit Gestion 5 — lecture seule. Aucune route de modification
 * ni de suppression : les entrées sont en insertion seule.
 */
class AuditEventController extends Controller
{
    use ResolvesListingFilters;

    private const SUBJECTS = [
        'project' => 'Projet',
        'phase' => 'Phase',
        'funding' => 'Financement',
        'document' => 'Document',
    ];

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $subject = $request->string('subject')->toString();
        $action = $request->string('action')->toString();
        $projectId = $request->integer('project_id');
        ['from' => $from, 'to' => $to] = $this->listingDateRange($request);
        $perPage = $this->listingPerPage($request);

        $actions = AuditEvent::query()
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $events = AuditEvent::query()
            ->with(['user', 'project'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('summary', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(isset(self::SUBJECTS[$subject]), fn ($query) => $query->where('subject_type', $subject))
            ->when($actions->contains($action), fn ($query) => $query->where('action', $action))
            ->when($projectId > 0, fn ($query) => $query->where('project_id', $projectId))
            ->when($from !== null, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('back.audit-events.index', [
            'events' => $events,
            'subjects' => self::SUBJECTS,
            'actions' => $actions,
            'subject' => $subject,
            'action' => $action,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'perPage' => $perPage,
            'project' => $projectId > 0 ? Project::find($projectId) : null,
            'projectId' => $projectId,
        ]);
    }
}
