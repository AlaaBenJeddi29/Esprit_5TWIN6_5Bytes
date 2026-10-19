<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\DetectIncidentSimilaritiesRequest;
use App\Http\Requests\Incident\ReviewIncidentSimilarityRequest;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Services\IncidentSimilarityService;
use App\Services\SimilarityReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class IncidentSimilarityController extends Controller
{
    public function __construct(
        private readonly IncidentSimilarityService $similarities,
        private readonly SimilarityReviewService $reviews,
    ) {}

    public function detect(DetectIncidentSimilaritiesRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('manageSimilarities', $incident);

        try {
            $created = $this->similarities->detect($incident);
        } catch (Throwable $exception) {
            Log::error('Manual incident similarity detection failed.', [
                'incident_id' => $incident->getKey(),
                'user_id' => $request->user()->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'similarities' => 'La recherche a échoué. Veuillez réessayer plus tard.',
            ]);
        }

        return back()->with(
            'success',
            $created->isEmpty()
                ? 'Aucun incident similaire détecté.'
                : sprintf('%d incident(s) similaire(s) détecté(s).', $created->count()),
        );
    }

    public function decide(
        ReviewIncidentSimilarityRequest $request,
        Incident $incident,
        IncidentSimilarity $similarity,
    ): RedirectResponse {
        Gate::authorize('manageSimilarities', $incident);

        if ((int) $similarity->incident_id !== (int) $incident->getKey()) {
            abort(404);
        }

        try {
            $this->reviews->decide(
                $similarity,
                (string) $request->validated('decision'),
                $request->validated('note'),
                $request->user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Incident similarity decision failed.', [
                'incident_id' => $incident->getKey(),
                'similarity_id' => $similarity->getKey(),
                'user_id' => $request->user()->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'decision' => 'La décision n\'a pas pu être enregistrée. Veuillez réessayer.',
            ]);
        }

        return back()->with('success', 'La décision a été enregistrée.');
    }
}
