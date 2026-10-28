<?php

namespace App\Shared\Services;

abstract class ModuleService
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): array
    {
        return $data;
    }
}
