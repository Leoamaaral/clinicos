<?php

namespace Tests\Feature;

use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_quotes_page(): void
    {
        $user = User::factory()->create();

        Treatment::create([
            'name' => 'Depilação a laser - AXILA',
            'single_price' => 200,
            'package_6_price' => 1080,
            'package_price' => 1200,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/quotes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('quotes/index')
                ->has('treatments', 1)
                ->has('combos')
                ->has('discountRules'));
    }

    public function test_guest_cannot_view_quotes_page(): void
    {
        $this->get('/quotes')->assertRedirect('/login');
    }
}
