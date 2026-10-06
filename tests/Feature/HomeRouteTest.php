<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsGym;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use BuildsGym, RefreshDatabase;

    #[Test]
    public function guests_land_on_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    #[Test]
    public function signed_in_users_go_straight_to_their_own_page(): void
    {
        $member = $this->makeMember($this->makeBranch());

        $this->actingAs($member->user)->get('/')->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function the_login_page_offers_both_ways_to_sign_up(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'))
            ->assertSee(route('trainer.register'));
    }
}
