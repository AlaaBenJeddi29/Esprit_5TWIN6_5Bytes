<?php

namespace Tests\Unit;

use App\Models\Incident\Incident;
use App\Services\SimilarityScorer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SimilarityScorerTest extends TestCase
{
    private SimilarityScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new SimilarityScorer;
    }

    public function test_quasi_identical_texts_score_high(): void
    {
        $incident = $this->incident();
        $candidate = $this->incident([
            'title' => 'Fuite d eau sur la chaussee devant l ecole',
            'description' => 'Une fuite importante d eau potable inonde la chaussee devant l ecole depuis ce matin.',
        ]);

        $this->assertGreaterThan(0.8, $this->scorer->textSimilarity(
            $incident->title.' '.$incident->description,
            $candidate->title.' '.$candidate->description,
        ));

        $this->assertGreaterThanOrEqual(90, $this->scorer->score($incident, $candidate));
    }

    public function test_unrelated_texts_score_low(): void
    {
        $incident = $this->incident();
        $candidate = $this->incident([
            'title' => 'Coupure de courant au centre administratif',
            'description' => 'Le transformateur électrique est hors service, aucun éclairage public ce soir.',
            'type' => 'autre',
            'severity' => 'faible',
            'zone_id' => 2,
            'latitude' => 43.2965,
            'longitude' => 5.3698,
            'reported_at' => Carbon::parse('2026-09-01 08:00:00'),
        ]);

        $this->assertLessThan(0.2, $this->scorer->textSimilarity(
            $incident->title.' '.$incident->description,
            $candidate->title.' '.$candidate->description,
        ));

        $this->assertLessThan(40, $this->scorer->score($incident, $candidate));
    }

    public function test_accents_and_punctuation_are_ignored(): void
    {
        $similarity = $this->scorer->textSimilarity(
            "Fuite d'eau à l'école — chaussée inondée !",
            'FUITE D EAU A L ECOLE CHAUSSEE INONDEE',
        );

        $this->assertSame(1.0, $similarity);
    }

    public function test_empty_or_stopword_only_texts_have_no_text_similarity(): void
    {
        $this->assertSame(0.0, $this->scorer->textSimilarity('', ''));
        $this->assertSame(0.0, $this->scorer->textSimilarity('le la de', ''));
        $this->assertSame(0.0, $this->scorer->textSimilarity('le la de', 'les des du'));
    }

    public function test_zone_bonus_increases_the_score(): void
    {
        $incident = $this->incident(['zone_id' => 1]);
        $sameZone = $this->incident(['zone_id' => 1]);
        $otherZone = $this->incident(['zone_id' => 2]);

        $sameZoneScore = $this->scorer->score($incident, $sameZone);
        $otherZoneScore = $this->scorer->score($incident, $otherZone);

        $this->assertGreaterThan($otherZoneScore, $sameZoneScore);
        $this->assertSame(15, $sameZoneScore - $otherZoneScore);
    }

    public function test_time_proximity_bonus_increases_the_score(): void
    {
        $incident = $this->incident(['reported_at' => Carbon::parse('2026-10-15 10:00:00')]);
        $near = $this->incident(['reported_at' => Carbon::parse('2026-10-15 11:00:00')]);
        $far = $this->incident(['reported_at' => Carbon::parse('2026-09-20 10:00:00')]);

        $nearScore = $this->scorer->score($incident, $near);
        $farScore = $this->scorer->score($incident, $far);

        $this->assertGreaterThan($farScore, $nearScore);
        $this->assertSame(9, $nearScore - $farScore);
    }

    public function test_same_severity_and_type_are_rewarded(): void
    {
        $incident = $this->incident();
        $same = $this->incident();
        $different = $this->incident(['type' => 'coupure', 'severity' => 'faible']);

        $this->assertGreaterThan(
            $this->scorer->score($incident, $different),
            $this->scorer->score($incident, $same),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function incident(array $overrides = []): Incident
    {
        return new Incident(array_merge([
            'type' => 'fuite',
            'title' => "Fuite d'eau sur la chaussée devant l'école",
            'description' => "Une fuite importante d'eau potable inonde la chaussée devant l'école depuis ce matin.",
            'zone_id' => 1,
            'severity' => 'haute',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'reported_at' => Carbon::parse('2026-10-15 10:00:00'),
        ], $overrides));
    }
}
