<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_home_is_spanish_and_offers_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Iniciar sesión');
    }
}
