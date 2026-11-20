<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) getenv('DB_DATABASE');
        if ($database === '') {
            $this->fail(
                'DB_DATABASE debe definirse con una base de datos PostgreSQL fresca y unica. '
                .'No se permite reutilizar una base de datos existente ni dejar el valor vacio.'
            );
        }
    }
}
