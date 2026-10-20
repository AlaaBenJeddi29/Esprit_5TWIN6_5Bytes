<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\IncidentAssistComposeRequest;
use App\Http\Requests\Incident\IncidentAssistQuestionsRequest;
use App\Services\IncidentWritingAssistant;
use Illuminate\Http\JsonResponse;

/**
 * Aide consultative à la rédaction : aucune écriture en base, aucun envoi de
 * signalement. Le citoyen reste seul à valider le texte avant soumission.
 */
class IncidentWritingAssistController extends Controller
{
    public function __construct(private readonly IncidentWritingAssistant $assistant) {}

    public function questions(IncidentAssistQuestionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->assistant->questions(
            $data['title'],
            $data['description'],
            $data['type'] ?? null,
        ));
    }

    public function compose(IncidentAssistComposeRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->assistant->compose(
            $data['title'],
            $data['description'],
            $data['answers'],
        ));
    }
}
