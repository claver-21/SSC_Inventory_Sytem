<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login_and_login_renders(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertStatus(200)
            ->assertSee('Polytechnic College of Botolan')
            ->assertSee('Supreme Student Council');
    }
}
