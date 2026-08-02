<?php
// tests/Feature/ExampleTest.php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La route racine redirige vers planning.index.
     * On vérifie la redirection (302) et non un 200.
     */
    public function test_root_redirects_to_planning(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('planning.index'));
    }
}