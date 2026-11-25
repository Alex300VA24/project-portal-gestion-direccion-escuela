<?php
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/LectoresPublicacionQa.php';
use App\Modules\GestionHorarios\Services\LectoresPublicacionQa as L;
$casos=[
 L::autorizado('alumno@test.example','alumno@test.example;profesor@test.example'),
 !L::autorizado('otro@test.example','alumno@test.example;profesor@test.example'),
 !L::autorizado('alumno@test.example','*'),
 !L::autorizado('',''),
 L::docente('profesor@test.example','profesor@test.example=12')===12,
 L::docente('otro@test.example','profesor@test.example=12')===null,
 L::docente('profesor@test.example','profesor@test.example=12;profesor@test.example=13')===null,
 L::docente('profesor@test.example','profesor@test.example=-1')===null,
 L::docente('profesor@test.example','profesor@test.example=abc')===null,
 L::docente('profesor@test.example','')===null,
];
foreach ($casos as $i=>$ok) if (!$ok) throw new RuntimeException('Caso fallido: '.($i+1));
echo count($casos)." comprobaciones correctas.\n";
