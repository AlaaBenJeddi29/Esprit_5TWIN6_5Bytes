<?php

namespace Tests\Unit;

use App\Exceptions\InvalidComplaintTransitionException;
use App\Services\ComplaintWorkflowService;
use PHPUnit\Framework\TestCase;

class ComplaintWorkflowRulesTest extends TestCase
{
    private ComplaintWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = new ComplaintWorkflowService;
    }

    public function test_complaint_lifecycle_follows_the_defined_transitions(): void
    {
        $this->assertSame(
            [
                'en_attente' => ['en_examen'],
                'en_examen' => ['acceptee', 'rejetee'],
                'acceptee' => ['cloturee'],
                'rejetee' => ['cloturee'],
            ],
            ComplaintWorkflowService::TRANSITIONS,
        );
    }

    public function test_allowed_transitions_are_enforced(): void
    {
        $this->assertTrue($this->workflow->canTransition('en_attente', 'en_examen'));
        $this->assertTrue($this->workflow->canTransition('en_examen', 'acceptee'));
        $this->assertTrue($this->workflow->canTransition('en_examen', 'rejetee'));
        $this->assertTrue($this->workflow->canTransition('acceptee', 'cloturee'));
        $this->assertTrue($this->workflow->canTransition('rejetee', 'cloturee'));

        $this->assertFalse($this->workflow->canTransition('en_attente', 'acceptee'));
        $this->assertFalse($this->workflow->canTransition('acceptee', 'rejetee'));
        $this->assertFalse($this->workflow->canTransition('cloturee', 'en_examen'));
        $this->assertFalse($this->workflow->canTransition(null, 'en_examen'));
    }

    public function test_only_acceptance_and_rejection_require_a_justification(): void
    {
        $this->assertTrue($this->workflow->requiresJustification('acceptee'));
        $this->assertTrue($this->workflow->requiresJustification('rejetee'));
        $this->assertFalse($this->workflow->requiresJustification('en_examen'));
        $this->assertFalse($this->workflow->requiresJustification('cloturee'));
    }

    public function test_rejected_transition_message_names_both_statuses(): void
    {
        $message = InvalidComplaintTransitionException::between('en_attente', 'acceptee')->getMessage();

        $this->assertStringContainsString('En attente', $message);
        $this->assertStringContainsString('Acceptée', $message);
    }

    public function test_missing_complaint_and_double_reopening_have_explicit_messages(): void
    {
        $this->assertStringContainsString(
            'aucune réclamation',
            InvalidComplaintTransitionException::noComplaint()->getMessage(),
        );

        $this->assertStringContainsString(
            'déjà été réouvert',
            InvalidComplaintTransitionException::alreadyReopened()->getMessage(),
        );
    }
}
