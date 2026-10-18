<?php

namespace App\Http\Controllers\Back;

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
    private const SUBJECTS = [
        'project' => 'Projet',
        'phase' => 'Phase',
        'funding' => 'Financement',
        'document' => 'Document',
    ];

    public function index(Request $request): View
    {
        $subject = $request->string('subject')->toString();
        $projectId = $request->integer('project_id');

        $events = AuditEvent::query()
            ->with(['user', 'project'])
            ->when(isset(self::SUBJECTS[$subject]), fn ($query) => $query->where('subject_type', $subject))
            ->when($projectId > 0, fn ($query) => $query->where('project_id', $projectId))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('back.audit-events.index', [
            'events' => $events,
            'subjects' => self::SUBJECTS,
            'subject' => $subject,
            'project' => $projectId > 0 ? Project::find($projectId) : null,
            'projectId' => $projectId,
        ]);
    }
}
