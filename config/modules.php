<?php

$module = static fn (
    string $name,
    string $schema,
    string $directory,
    bool $migrationsEnabled = false,
): array => [
    'name' => $name,
    'schema' => $schema,
    'migration_path' => "app/Modules/{$directory}/Database/Migrations",
    'migrations_enabled' => $migrationsEnabled,
    'dependencies' => $migrationsEnabled ? ['shared'] : [],
];

return [
    'pre-matricula' => $module('Pre Matrícula', 'prematricula', 'PreMatricula', true),
    'bienestar' => $module('Bienestar', 'bienestar', 'Bienestar', true),
    'investigacion-formativa' => $module('Investigación Formativa', 'investigacion_formativa', 'InvestigacionFormativa', true),
    'seguimiento-estudiante' => $module('Seguimiento al Estudiante', 'seguimiento_estudiante', 'SeguimientoEstudiante', true),
    'web-institucional' => $module('Web Institucional', 'web_institucional', 'WebInstitucional', true),
    'calidad' => $module('Calidad', 'calidad', 'Calidad', true),
    'gestion-horarios' => $module('Gestión de Horarios', 'gestion_horarios', 'GestionHorarios', true),
    'cotecu' => $module('COTECU', 'cotecu', 'Cotecu', true),
    'gestion-practicas' => $module('Gestión de Prácticas', 'gestion_practicas', 'GestionPracticas', true),
    'tesis-proyecto-tesis' => $module('Tesis y Proyecto de Tesis', 'tesis', 'TesisProyectoTesis', true),
    'gestion-matriculas' => $module('Gestión de Matrículas', 'gestion_matriculas', 'GestionMatriculas', true),
    'seguimiento-egresado' => $module('Seguimiento al Egresado', 'seguimiento_egresado', 'SeguimientoEgresado', true),
];
