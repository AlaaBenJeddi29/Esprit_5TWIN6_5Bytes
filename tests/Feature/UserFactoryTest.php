<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unsaved_factory_user_is_active_by_default(): void
    {
        // Le rapport global vérifie is_active sur l'instance en mémoire, avant
        // tout rechargement depuis la base : la factory doit donc la fournir.
        $user = User::factory()->make();

        $this->assertTrue($user->is_active);
        $this->assertArrayHasKey('is_active', $user->getAttributes());
    }

    public function test_a_persisted_factory_user_is_active_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->fresh()->is_active);
    }
}
