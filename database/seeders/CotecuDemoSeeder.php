<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Cotecu\Models\DocumentoOficial;
use App\Modules\Cotecu\Models\Registro;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\Core\Persona;
use App\Shared\Models\Core\PlanEstudios;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Datos de prueba de COTECU: semestres académicos y cuentas institucionales.
 *
 * Idempotente: se puede ejecutar cuantas veces sea necesario.
 * Uso (en este orden):
 *   1. php artisan db:seed                     (DatabaseSeeder: roles y cuentas base)
 *   2. php artisan db:seed --class=CotecuPermissionsSeeder
 *   3. php artisan db:seed --class=CotecuDemoSeeder
 *
 * core.periodo_academico, core.persona y seguridad.usuario son tablas
 * institucionales compartidas: este seeder solo siembra lo mínimo para
 * probar el módulo (los FK de cotecu.silabos apuntan a ellas). En
 * producción los carga quien administre core/seguridad; COTECU solo lee.
 *
 * El catálogo del plan, el catálogo académico (áreas, departamento,
 * cursos), las secciones con sus sílabos, la lista de cotejo y los
 * documentos oficiales también son demo. Limpieza (hijos primero, por
 * las FK):
 *   DELETE FROM cotecu.documentos_oficiales;
 *   DELETE FROM cotecu.resultados_silabos_items WHERE revision_silabo_id IN
 *     (SELECT id FROM cotecu.revisiones_silabo WHERE silabo_id IN
 *       (SELECT id FROM cotecu.silabos WHERE id_profesor IN
 *         (SELECT id_usuario FROM seguridad.usuario WHERE correo_institucional LIKE '%@portal.local')));
 *   DELETE FROM cotecu.revisiones_silabo WHERE silabo_id IN
 *     (SELECT id FROM cotecu.silabos WHERE id_profesor IN
 *       (SELECT id_usuario FROM seguridad.usuario WHERE correo_institucional LIKE '%@portal.local'));
 *   DELETE FROM cotecu.silabos WHERE id_profesor IN
 *     (SELECT id_usuario FROM seguridad.usuario WHERE correo_institucional LIKE '%@portal.local');
 *   DELETE FROM cotecu.lista_cotejo_silabos_items WHERE lista_cotejo_silabo_id IN
 *     (SELECT id FROM cotecu.listas_cotejo_silabos WHERE nombre = 'Lista de cotejo de sílabos 2026');
 *   DELETE FROM cotecu.listas_cotejo_silabos WHERE nombre = 'Lista de cotejo de sílabos 2026';
 *   DELETE FROM cotecu.resultados_items WHERE revision_id IN
 *     (SELECT id FROM cotecu.revisiones_plan_curricular);
 *   DELETE FROM cotecu.revisiones_plan_curricular;
 *   DELETE FROM cotecu.lista_cotejo_items WHERE lista_cotejo_id IN
 *     (SELECT id FROM cotecu.listas_cotejo WHERE nombre = 'Lista de cotejo de planes 2026');
 *   DELETE FROM cotecu.listas_cotejo WHERE nombre = 'Lista de cotejo de planes 2026';
 *   DELETE FROM core.seccion WHERE id_curso_plan IN
 *     (SELECT id_curso_plan FROM core.curso_plan WHERE id_plan IN
 *       (SELECT id_plan FROM core.plan_estudios WHERE codigo = 'PLAN-INF-2020'));
 *   DELETE FROM core.docente WHERE codigo_docente IN ('DOC-DEMO','DOC-AJENO');
 *   DELETE FROM cotecu.planes_curriculares WHERE id_plan IN
 *     (SELECT id_plan FROM core.plan_estudios
 *       WHERE codigo IN ('PLAN-INF-2010', 'PLAN-INF-2020'));
 *   DELETE FROM core.curso_plan WHERE id_plan IN
 *     (SELECT id_plan FROM core.plan_estudios WHERE codigo = 'PLAN-INF-2020');
 *   DELETE FROM core.curso WHERE codigo IN
 *     ('INF-101','MAT-101','INF-102','INF-103','MAT-102','INF-201','MAT-201',
 *      'INF-202','INF-203','MAT-202','INF-301','INF-302','INF-303','INF-304',
 *      'ING-301','INF-401','INF-402');
 *   DELETE FROM core.area_formacion WHERE codigo IN ('CSC','MAT','ING');
 *   DELETE FROM core.departamento_academico WHERE codigo = 'DA-INF';
 *   DELETE FROM core.plan_estudios WHERE codigo IN ('PLAN-INF-2010', 'PLAN-INF-2020');
 *   DELETE FROM core.escuela_profesional WHERE codigo = 'EP-INF';
 *   DELETE FROM core.facultad WHERE codigo = 'FAC-CFM';
 */
class CotecuDemoSeeder extends Seeder
{
    /**
     * Periodos de prueba. El último queda Activo: es el que usan las
     * pantallas por defecto y el único en el que se puede subir un sílabo.
     */
    private const PERIODOS = [
        [
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-04-13',
            'fecha_fin' => '2026-08-08',
            'numero_semanas' => 17,
            'estado' => 'Cerrado',
        ],
        [
            'codigo' => '2026-II',
            'anio' => 2026,
            'tipo' => 'II',
            'fecha_inicio' => '2026-08-24',
            'fecha_fin' => '2026-12-19',
            'numero_semanas' => 17,
            'estado' => 'Activo',
        ],
    ];

    /**
     * Cuentas del portal (public.users) con su contraparte institucional:
     * core.persona + seguridad.usuario, que es a donde apuntan las claves
     * foráneas de todos los módulos (cotecu.silabos.id_profesor, etc.).
     *
     * Puente: seguridad.usuario.correo_institucional = users.email.
     * Son filas de prueba: la app solo las lee y el acceso sigue siendo
     * el de Laravel (public.users). Si el grupo de gestión redefine la
     * estrategia de identidad, se limpian con:
     *   DELETE FROM seguridad.usuario WHERE correo_institucional LIKE '%@portal.local';
     *   DELETE FROM core.persona WHERE numero_documento LIKE '7000000%';
     */
    private const USUARIOS = [
        ['email' => 'director@portal.local', 'numero_documento' => '70000001', 'nombres' => 'Director', 'apellido_paterno' => 'Escuela'],
        ['email' => 'secretaria@portal.local', 'numero_documento' => '70000002', 'nombres' => 'Secretaría', 'apellido_paterno' => 'Académica'],
        ['email' => 'profesor@portal.local', 'numero_documento' => '70000003', 'nombres' => 'Profesor', 'apellido_paterno' => 'Demo'],
        ['email' => 'alumno@portal.local', 'numero_documento' => '70000004', 'nombres' => 'Alumno', 'apellido_paterno' => 'Demo'],
        // Director de COTECU: el profesor convocado para una nueva
        // currícula. No lleva rol institucional (el rol profesor trae
        // permisos de sílabos que no le corresponden): entra solo con
        // permisos directos de plan.
        ['email' => 'director-cotecu@portal.local', 'numero_documento' => '70000005', 'nombres' => 'Director', 'apellido_paterno' => 'COTECU'],
        // Docente ajeno al Profesor Demo: existe para comprobar que
        // cada docente solo ve (y sube) las secciones que le fueron
        // asignadas. Entra con el rol profesor.
        ['email' => 'docente-ajeno@portal.local', 'numero_documento' => '70000006', 'nombres' => 'Docente', 'apellido_paterno' => 'Ajeno'],
    ];

    /**
     * Catálogo académico mínimo para ver planes curriculares:
     * facultad → escuela → plan de estudios → documentos del plan.
     * Valores fijos acordados.
     */
    private const FACULTAD = [
        'codigo' => 'FAC-CFM',
        'nombre' => 'Ciencias Físicas y Matemáticas',
    ];

    private const ESCUELA = [
        'codigo' => 'EP-INF',
        'nombre' => 'Ingeniería Informática',
        'estado' => 'Activo',
    ];

    private const PLAN = [
        'codigo' => 'PLAN-INF-2020',
        'nombre' => 'Plan de estudios 2020 - Ingeniería Informática',
        'version' => '2020',
        'anio_aprobacion' => 2020,
        'resolucion_aprobacion' => 'R.D. N° 142-2020-UNT',
        'fecha_vigencia_inicio' => '2021-03-01',
        'es_predeterminado' => true,
        'estado' => 'Vigente',
    ];

    /**
     * Plan anterior de la misma escuela: es lo que hace de la pantalla
     * Planes curriculares un registro de años anteriores. Solo el
     * documento aprobado, sin cursos ni secciones (aquellos ya no se
     * dictan) y sin ser el predeterminado del catálogo.
     */
    private const PLAN_ANTERIOR = [
        'codigo' => 'PLAN-INF-2010',
        'nombre' => 'Plan de estudios 2010 - Ingeniería Informática',
        'version' => '2010',
        'anio_aprobacion' => 2010,
        'resolucion_aprobacion' => 'R.D. N° 88-2010-UNT',
        'fecha_vigencia_inicio' => '2011-03-01',
        'es_predeterminado' => false,
        'estado' => 'Anterior',
    ];

    /**
     * Documentos del plan vigente que COTECU revisa (cotecu.planes_curriculares).
     * Sin id_periodo: es por plan y se versiona, no por semestre.
     * Los tres estados de la revisión están representados: v1 Validado
     * (aprobado), v2 Pendiente (alimenta la cola) y v3 Observado
     * (ya revisado).
     * archivo_url es una ruta simulada: la subida de archivo todavía no
     * está implementada (ver el aviso en el formulario de planes).
     */
    private const PLANES_CURRICULARES = [
        ['version' => 1, 'estado' => 'Validado', 'archivo_url' => 'demo/planes/plan-inf-2020-v1.pdf'],
        ['version' => 2, 'estado' => 'Pendiente', 'archivo_url' => 'demo/planes/plan-inf-2020-v2.pdf'],
        ['version' => 3, 'estado' => 'Observado', 'archivo_url' => 'demo/planes/plan-inf-2020-v3.pdf'],
    ];

    /**
     * Documentos del plan anterior: solo el aprobado, que es el que
     * aparece en Planes curriculares y el que deja probar una subida
     * sin bloqueo (no tiene versiones en revisión).
     */
    private const PLANES_CURRICULARES_ANTERIOR = [
        ['version' => 1, 'estado' => 'Validado', 'archivo_url' => 'demo/planes/plan-inf-2010-v1.pdf'],
    ];

    /**
     * Áreas de formación de core.area_formacion. core.curso_plan.id_area
     * es NOT NULL: cada curso del plan debe pertenecer a un área.
     */
    private const AREAS = [
        ['codigo' => 'CSC', 'nombre' => 'Ciencia de la Computación'],
        ['codigo' => 'MAT', 'nombre' => 'Matemática'],
        ['codigo' => 'ING', 'nombre' => 'Ingeniería'],
    ];

    /**
     * Departamento académico de la escuela (FK a la facultad demo).
     * Se siembra por si el selector de cursos pasa a ser por secciones.
     */
    private const DEPARTAMENTO = [
        'codigo' => 'DA-INF',
        'nombre' => 'Informática',
    ];

    /**
     * Cursos del PLAN-INF-2020 en core.curso (créditos) y su fila en
     * core.curso_plan (ciclo, tipo y área). La tabla de cursos no tiene
     * código propio: se deriva de core.curso.codigo, único por diseño.
     *
     * Hay al menos dos cursos por ciclo del 1 al 4: es lo que permite
     * probar el filtro de ciclo de la pantalla Sílabos en los dos
     * semestres (en -I corren los impares y en -II los pares).
     */
    private const CURSOS = [
        ['ciclo' => 1, 'codigo' => 'INF-101', 'nombre' => 'Fundamentos de Programación', 'creditos' => 3, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 1, 'codigo' => 'MAT-101', 'nombre' => 'Cálculo I', 'creditos' => 4, 'area' => 'MAT', 'tipo' => 'Obligatorio'],
        ['ciclo' => 1, 'codigo' => 'INF-102', 'nombre' => 'Introducción a la Ingeniería Informática', 'creditos' => 2, 'area' => 'ING', 'tipo' => 'Obligatorio'],
        ['ciclo' => 1, 'codigo' => 'INF-103', 'nombre' => 'Introducción a la Lógica', 'creditos' => 2, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 1, 'codigo' => 'MAT-102', 'nombre' => 'Geometría Analítica', 'creditos' => 4, 'area' => 'MAT', 'tipo' => 'Obligatorio'],
        ['ciclo' => 2, 'codigo' => 'INF-201', 'nombre' => 'Estructuras de Datos', 'creditos' => 4, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 2, 'codigo' => 'MAT-201', 'nombre' => 'Matemática Discreta', 'creditos' => 3, 'area' => 'MAT', 'tipo' => 'Obligatorio'],
        ['ciclo' => 2, 'codigo' => 'INF-202', 'nombre' => 'Programación Orientada a Objetos', 'creditos' => 3, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 2, 'codigo' => 'INF-203', 'nombre' => 'Fundamentos de Hardware', 'creditos' => 2, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 2, 'codigo' => 'MAT-202', 'nombre' => 'Estadística I', 'creditos' => 3, 'area' => 'MAT', 'tipo' => 'Obligatorio'],
        ['ciclo' => 3, 'codigo' => 'INF-301', 'nombre' => 'Bases de Datos', 'creditos' => 4, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 3, 'codigo' => 'INF-302', 'nombre' => 'Sistemas Operativos', 'creditos' => 4, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 3, 'codigo' => 'INF-303', 'nombre' => 'Electiva: Gestión de Proyectos', 'creditos' => 2, 'area' => 'ING', 'tipo' => 'Electivo'],
        ['ciclo' => 3, 'codigo' => 'INF-304', 'nombre' => 'Lenguajes de Programación', 'creditos' => 3, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 3, 'codigo' => 'ING-301', 'nombre' => 'Ingeniería Económica', 'creditos' => 2, 'area' => 'ING', 'tipo' => 'Electivo'],
        ['ciclo' => 4, 'codigo' => 'INF-401', 'nombre' => 'Ingeniería de Software', 'creditos' => 4, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
        ['ciclo' => 4, 'codigo' => 'INF-402', 'nombre' => 'Redes de Computadoras', 'creditos' => 4, 'area' => 'CSC', 'tipo' => 'Obligatorio'],
    ];

    /**
     * Docentes de prueba en core.docente. Puente completo de un sílabo
     * a su autor: users.email → seguridad.usuario → id_persona →
     * core.docente → core.seccion.id_docente_responsable.
     *
     * DOC-DEMO es el Profesor Demo (persona ya sembrada) y DOC-AJENO
     * carga la persona nueva 70000006: sus secciones no deben
     * aparecerle al Profesor Demo ni viceversa.
     */
    private const DOCENTES = [
        ['numero_documento' => '70000003', 'codigo_docente' => 'DOC-DEMO'],
        ['numero_documento' => '70000006', 'codigo_docente' => 'DOC-AJENO'],
    ];

    /**
     * Asignaciones docente–curso–semestre en core.seccion: es la tabla
     * intermedia que decide qué cursos carga cada profesor en su
     * selector. Un mismo curso con varios profesores son varias
     * secciones (una por responsable).
     *
     * En -II (semestre par) se asignan ciclos pares y en -I (impar)
     * ciclos impares, con un caso de curso de ciclo "equivocado" por
     * semestre para alimentar la opción Especial del filtro de ciclo.
     */
    private const SECCIONES = [
        // Periodo activo: el Profesor Demo tiene 6 cursos asignados...
        ['periodo' => '2026-II', 'curso' => 'INF-101', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-II', 'curso' => 'INF-102', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-II', 'curso' => 'INF-201', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-II', 'curso' => 'INF-301', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-II', 'curso' => 'INF-203', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-II', 'curso' => 'INF-401', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        // ...y el Docente Ajeno otros 2 (la prueba del filtro).
        ['periodo' => '2026-II', 'curso' => 'MAT-101', 'codigo' => 'A', 'docente' => 'DOC-AJENO'],
        ['periodo' => '2026-II', 'curso' => 'INF-302', 'codigo' => 'A', 'docente' => 'DOC-AJENO'],
        // Semestre cerrado: histórico del Profesor Demo para el
        // selector de semestre de Mis sílabos.
        ['periodo' => '2026-I', 'curso' => 'INF-101', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-I', 'curso' => 'INF-301', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
        ['periodo' => '2026-I', 'curso' => 'INF-201', 'codigo' => 'A', 'docente' => 'DOC-DEMO'],
    ];

    /**
     * Sílabos de prueba del Profesor Demo (cotecu.silabos): 6 en el
     * periodo activo y 3 históricos en el cerrado, para que las
     * pantallas Mis sílabos y Revisar sílabos no arranquen vacías en
     * ningún semestre y el filtro de ciclo de Sílabos tenga filas que
     * mostrar. En el activo los 3 estados están representados: 3
     * Validado, 2 Pendiente y 1 Observado. Los 2 Pendiente del periodo
     * activo son los que alimentan la cola de revisión (el backfill les
     * crea su registro al abrir la pantalla); el Observado ya fue
     * revisado y por eso no entra a la cola.
     *
     * Los Validados cubren los dos semestres con su paridad de ciclo
     * (INF-101 es ciclo 1 en -II, o sea un curso "Especial").
     * archivo_url usa la ruta simulada que genera el formulario.
     */
    private const SILABOS = [
        ['periodo' => '2026-II', 'curso' => 'INF-101', 'version' => 1, 'estado' => 'Validado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-101-2026-ii-v1.pdf'],
        ['periodo' => '2026-II', 'curso' => 'INF-102', 'version' => 1, 'estado' => 'Observado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-102-2026-ii-v1.pdf'],
        ['periodo' => '2026-II', 'curso' => 'INF-201', 'version' => 1, 'estado' => 'Pendiente', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-201-2026-ii-v1.pdf'],
        ['periodo' => '2026-II', 'curso' => 'INF-301', 'version' => 1, 'estado' => 'Pendiente', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-301-2026-ii-v1.pdf'],
        ['periodo' => '2026-II', 'curso' => 'INF-203', 'version' => 1, 'estado' => 'Validado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-203-2026-ii-v1.pdf'],
        ['periodo' => '2026-II', 'curso' => 'INF-401', 'version' => 1, 'estado' => 'Validado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-401-2026-ii-v1.pdf'],
        ['periodo' => '2026-I', 'curso' => 'INF-101', 'version' => 1, 'estado' => 'Validado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-101-2026-i-v1.pdf'],
        ['periodo' => '2026-I', 'curso' => 'INF-301', 'version' => 1, 'estado' => 'Validado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-301-2026-i-v1.pdf'],
        ['periodo' => '2026-I', 'curso' => 'INF-201', 'version' => 1, 'estado' => 'Validado', 'archivo_url' => 'storage/app/private/cotecu/silabos/inf-201-2026-i-v1.pdf'],
    ];

    /**
     * Lista de cotejo de sílabos (cotecu.listas_cotejo_silabos): es la
     * fila que exige la FK NOT NULL de revisiones_silabo.lista_cotejo_
     * silabo_id, sin la cual no se puede registrar ninguna revisión.
     *
     * Solo metadatos + los 10 ítems de cotejo: el marcado de cada revisión
     * se guarda aparte, en cotecu.resultados_silabos_items (cumple y
     * observación por ítem). El contenido definitivo de la lista lo carga
     * quien corresponda; mientras tanto esta es la demo.
     */
    private const LISTA_COTEJO_SILABO = [
        'nombre' => 'Lista de cotejo de sílabos 2026',
        'version' => 'v1',
        'estado' => 'Activo',
        'items' => [
            '¿Tiene el logo de la universidad?',
            '¿Tiene el nombre del curso?',
            '¿Tiene su cuadro de horas correctamente asignadas?',
            '¿Tiene los datos del docente?',
            '¿Tiene los resultados de aprendizaje?',
            '¿Tiene la metodología?',
            '¿Tiene la evaluación sumada al 100%?',
            '¿Tiene las unidades organizadas por semana?',
            '¿Tiene la bibliografía?',
            '¿Tiene el horario?',
        ],
    ];

    /**
     * Lista de cotejo de planes (cotecu.listas_cotejo): es la fila que
     * exige la FK NOT NULL de revisiones_plan_curricular.lista_cotejo_id,
     * sin la cual no se puede registrar ninguna revisión de plan.
     *
     * Piloto con las primeras secciones del currículo: la lista se
     * agrupa en secciones (campo seccion de lista_cotejo_items) y cada
     * una aporta sus ítems marcables, en el orden en que aparecen en el
     * documento. El título de la sección es la ruta completa cuando hay
     * niveles anidados («1. BASES GENERALES › 1.1. BASES NORMATIVAS»);
     * «Contiene» y la nota del MOEDUNT-2 son prosa del documento y no
     * son ítems. Solo metadatos + ítems; el marcado de cada revisión
     * queda en cotecu.resultados_items. El contenido definitivo lo
     * carga quien corresponda.
     */
    private const LISTA_COTEJO_PLAN = [
        'nombre' => 'Lista de cotejo de planes 2026',
        'version' => 'v1',
        'estado' => 'Activo',
        'secciones' => [
            ['titulo' => 'CARÁTULA', 'items' => [
                'Universidad Nacional de Trujillo',
                'Vicerrectorado Académico',
                'Facultad',
                'Currículo del Programa de Estudios de …',
                'Lugar y fecha (consigna año de aprobación)',
            ]],
            ['titulo' => 'PRESENTACIÓN', 'items' => [
                'Texto breve de exposición acerca de cómo se ha elaborado el currículo de manera general. Debe incluirse los nombres de los que han participado en la elaboración del currículo.',
            ]],
            ['titulo' => 'INTRODUCCIÓN', 'items' => [
                'Texto donde se presenta una visión general de la estructura y el contenido del documento curricular, de su enfoque pedagógico, organización, coherencia interna y externa de sus elementos, funcionamiento del plan de estudio, pertinencia e importancia social.',
            ]],
            ['titulo' => '1. BASES GENERALES › 1.1. BASES NORMATIVAS', 'items' => [
                '1.1.1 Nacional',
                '1.1.2 Institucional',
                'Profesional, si se diese el caso',
            ]],
            ['titulo' => '1. BASES GENERALES › 1.2. BASES INSTITUCIONALES › 1.2.1. Misión y visión', 'items' => [
                '1.2.1.1. De la UNT',
                '1.2.1.2. De la Facultad',
            ]],
            ['titulo' => '1. BASES GENERALES › 1.2. BASES INSTITUCIONALES › 1.2.2. Principios institucionales', 'items' => [
                '1.2.2.1. De la UNT',
                '1.2.2.2. De la Facultad',
            ]],
            ['titulo' => '1. BASES GENERALES › 1.3. BASES TEÓRICO-CONCEPTUALES', 'items' => [
                '1.3.1. Concepción del ser humano, sociedad y cultura',
                '1.3.2. Concepción epistemológica',
                '1.3.3. Concepción curricular',
            ]],
        ],
    ];

    /**
     * Documentos oficiales: el respaldo en papel de los flujos del
     * módulo, enlazado a la fuente oficial en Google Docs/Sheets. Cada
     * fila lleva su permiso propio (1 a 1, único): hoy lo recibe solo
     * el director de escuela en CotecuPermissionsSeeder; habilitar
     * otro perfil para un documento es asignar ese permiso a su rol.
     */
    private const DOCUMENTOS_OFICIALES = [
        [
            'titulo' => 'F-M01.01-DPA-009 Lista de cotejo para evaluar curriculo',
            'url' => 'https://docs.google.com/document/d/13qJov3hlY_MVPyefoXyfxL1WJHLSNPVs/edit',
            'permiso' => 'cotecu.documento.lista-cotejo-curriculo',
            'orden' => 1,
        ],
    ];

    public function run(): void
    {
        foreach (self::PERIODOS as $periodo) {
            PeriodoAcademico::firstOrCreate(['codigo' => $periodo['codigo']], $periodo);
        }

        foreach (self::USUARIOS as $cuenta) {
            $persona = Persona::firstOrCreate(
                ['tipo_documento' => 'DNI', 'numero_documento' => $cuenta['numero_documento']],
                [
                    'nombres' => $cuenta['nombres'],
                    'apellido_paterno' => $cuenta['apellido_paterno'],
                    'correo_personal' => $cuenta['email'],
                ],
            );

            Usuario::firstOrCreate(
                ['correo_institucional' => $cuenta['email']],
                [
                    'id_persona' => $persona->id_persona,
                    'contrasena_hash' => Hash::make('Portal2026!'),
                    'estado' => 'Activo',
                    'debe_cambiar_contrasena' => false,
                ],
            );
        }

        // Cuenta del portal del director de COTECU: sin rol institucional
        // (el rol profesor le daría acceso a sílabos, que no le corresponde).
        // Entra solo con permisos directos de COTECU: ver el módulo, ver
        // la lista de planes y subir planes. Exige CotecuPermissionsSeeder
        // previo.
        $directorCotecu = User::updateOrCreate(
            ['email' => 'director-cotecu@portal.local'],
            [
                'name' => 'Director COTECU',
                'email_verified_at' => now(),
                'password' => Hash::make('Portal2026!'),
            ],
        );
        $directorCotecu->syncRoles([]);
        $directorCotecu->syncPermissions(['cotecu.view', 'cotecu.plan.list', 'cotecu.plan.upload']);

        $this->sembrarPlanCurricular();
        $this->sembrarCatalogoAcademico();
        $this->sembrarSeccionesYSilabos();
        $this->sembrarListaCotejoSilabos();
        $this->sembrarListaCotejoPlanes();
        $this->sembrarDocumentosOficiales();

        $this->command?->info('Datos demo de COTECU listos: 2 periodos, 6 cuentas institucionales, director de COTECU, 2 planes curriculares (2020 y 2010), catálogo académico con 17 cursos, 11 secciones, 9 sílabos, lista de cotejo de sílabos con 10 ítems, lista de cotejo de planes con 17 ítems en 7 secciones y 1 documento oficial.');
    }

    /**
     * Cadena mínima para que la pantalla de planes tenga filas:
     * facultad → escuela → plan de estudios → documentos del plan.
     *
     * Siembra el plan vigente (del que cuelgan los cursos) y el
     * anterior, que es el que convierte Planes curriculares en un
     * registro de años. Idempotente por código de plan y por versión
     * del documento: re-ejecutar no duplica filas.
     */
    private function sembrarPlanCurricular(): void
    {
        DB::table('core.facultad')->updateOrInsert(
            ['codigo' => self::FACULTAD['codigo']],
            ['nombre' => self::FACULTAD['nombre']],
        );

        $idFacultad = DB::table('core.facultad')
            ->where('codigo', self::FACULTAD['codigo'])
            ->value('id_facultad');

        DB::table('core.escuela_profesional')->updateOrInsert(
            ['codigo' => self::ESCUELA['codigo']],
            array_merge(self::ESCUELA, ['id_facultad' => $idFacultad]),
        );

        $idEscuela = DB::table('core.escuela_profesional')
            ->where('codigo', self::ESCUELA['codigo'])
            ->value('id_escuela');

        $planes = [
            ['definicion' => self::PLAN_ANTERIOR, 'documentos' => self::PLANES_CURRICULARES_ANTERIOR],
            ['definicion' => self::PLAN, 'documentos' => self::PLANES_CURRICULARES],
        ];

        foreach ($planes as $entrada) {
            $plan = PlanEstudios::firstOrCreate(
                ['codigo' => $entrada['definicion']['codigo']],
                array_merge($entrada['definicion'], ['id_escuela' => $idEscuela]),
            );

            foreach ($entrada['documentos'] as $documento) {
                Registro::firstOrCreate(
                    ['id_plan' => $plan->id_plan, 'version' => $documento['version']],
                    array_merge($documento, ['nombre' => $entrada['definicion']['nombre']]),
                );
            }
        }
    }

    /**
     * Catálogo académico para el selector de cursos de sílabos:
     * áreas de formación, departamento de la escuela y los cursos del
     * PLAN-INF-2020 con su fila en core.curso_plan (ciclo, tipo, área).
     *
     * Idempotente por código: re-ejecutar no duplica filas.
     */
    private function sembrarCatalogoAcademico(): void
    {
        foreach (self::AREAS as $area) {
            DB::table('core.area_formacion')->updateOrInsert(
                ['codigo' => $area['codigo']],
                ['nombre' => $area['nombre']],
            );
        }

        $idFacultad = DB::table('core.facultad')
            ->where('codigo', self::FACULTAD['codigo'])
            ->value('id_facultad');

        DB::table('core.departamento_academico')->updateOrInsert(
            ['codigo' => self::DEPARTAMENTO['codigo']],
            ['nombre' => self::DEPARTAMENTO['nombre'], 'id_facultad' => $idFacultad],
        );

        $idPlan = PlanEstudios::query()
            ->where('codigo', self::PLAN['codigo'])
            ->value('id_plan');

        foreach (self::CURSOS as $curso) {
            DB::table('core.curso')->updateOrInsert(
                ['codigo' => $curso['codigo']],
                [
                    'nombre' => $curso['nombre'],
                    'creditos' => $curso['creditos'],
                    'estado' => 'Activo',
                ],
            );

            $idCurso = DB::table('core.curso')
                ->where('codigo', $curso['codigo'])
                ->value('id_curso');

            $idArea = DB::table('core.area_formacion')
                ->where('codigo', $curso['area'])
                ->value('id_area');

            DB::table('core.curso_plan')->updateOrInsert(
                ['id_plan' => $idPlan, 'id_curso' => $idCurso],
                [
                    'ciclo' => $curso['ciclo'],
                    'tipo' => $curso['tipo'],
                    'id_area' => $idArea,
                ],
            );
        }
    }

    /**
     * Docentes, sus secciones por semestre y los sílabos de prueba del
     * Profesor Demo. Es lo que alimenta el selector de cursos de Mis
     * sílabos: cada docente solo carga sus propias secciones.
     *
     * Idempotente: re-ejecutar no duplica filas (claves lógicas de cada
     * tabla: codigo_docente, la unique de core.seccion y la combinación
     * periodo+curso+versión del sílabo).
     */
    private function sembrarSeccionesYSilabos(): void
    {
        // Cuenta del portal del Docente Ajeno, con el mismo rol (y por
        // lo tanto los mismos permisos de COTECU) que el Profesor Demo.
        $ajeno = User::updateOrCreate(
            ['email' => 'docente-ajeno@portal.local'],
            [
                'name' => 'Docente Ajeno',
                'email_verified_at' => now(),
                'password' => Hash::make('Portal2026!'),
            ],
        );
        $ajeno->syncRoles(['profesor']);

        $idDepartamento = DB::table('core.departamento_academico')
            ->where('codigo', self::DEPARTAMENTO['codigo'])
            ->value('id_departamento');

        foreach (self::DOCENTES as $docente) {
            $idPersona = Persona::query()
                ->where('numero_documento', $docente['numero_documento'])
                ->value('id_persona');

            DB::table('core.docente')->updateOrInsert(
                ['codigo_docente' => $docente['codigo_docente']],
                [
                    'id_persona' => $idPersona,
                    'id_departamento' => $idDepartamento,
                    'estado' => 'Activo',
                ],
            );
        }

        $idPlan = PlanEstudios::query()
            ->where('codigo', self::PLAN['codigo'])
            ->value('id_plan');

        foreach (self::SECCIONES as $asignacion) {
            $idPeriodo = PeriodoAcademico::query()
                ->where('codigo', $asignacion['periodo'])
                ->value('id_periodo');

            $idCurso = DB::table('core.curso')
                ->where('codigo', $asignacion['curso'])
                ->value('id_curso');

            $idCursoPlan = DB::table('core.curso_plan')
                ->where('id_plan', $idPlan)
                ->where('id_curso', $idCurso)
                ->value('id_curso_plan');

            $idDocente = DB::table('core.docente')
                ->where('codigo_docente', $asignacion['docente'])
                ->value('id_docente');

            DB::table('core.seccion')->updateOrInsert(
                ['id_periodo' => $idPeriodo, 'id_curso_plan' => $idCursoPlan, 'codigo' => $asignacion['codigo']],
                ['id_docente_responsable' => $idDocente, 'estado' => 'Activo'],
            );
        }

        $idProfesor = Usuario::query()
            ->where('correo_institucional', 'profesor@portal.local')
            ->value('id_usuario');

        foreach (self::SILABOS as $silabo) {
            $idPeriodo = PeriodoAcademico::query()
                ->where('codigo', $silabo['periodo'])
                ->value('id_periodo');

            $idCurso = DB::table('core.curso')
                ->where('codigo', $silabo['curso'])
                ->value('id_curso');

            $idCursoPlan = DB::table('core.curso_plan')
                ->where('id_plan', $idPlan)
                ->where('id_curso', $idCurso)
                ->value('id_curso_plan');

            DB::table('cotecu.silabos')->updateOrInsert(
                ['id_periodo' => $idPeriodo, 'id_curso_plan' => $idCursoPlan, 'id_profesor' => $idProfesor, 'version' => $silabo['version']],
                ['archivo_url' => $silabo['archivo_url'], 'estado' => $silabo['estado']],
            );
        }
    }

    /**
     * Lista de cotejo de sílabos y sus 10 ítems: es lo que permite crear
     * registros en cotecu.revisiones_silabo (la FK lista_cotejo_silabo_id
     * es NOT NULL) y lo que el director de escuela marca al revisar.
     *
     * Idempotente: re-ejecutar no duplica filas (clave lógica de la
     * lista por nombre y de cada ítem por lista+orden).
     */
    private function sembrarListaCotejoSilabos(): void
    {
        DB::table('cotecu.listas_cotejo_silabos')->updateOrInsert(
            ['nombre' => self::LISTA_COTEJO_SILABO['nombre']],
            [
                'version' => self::LISTA_COTEJO_SILABO['version'],
                'estado' => self::LISTA_COTEJO_SILABO['estado'],
            ],
        );

        $idLista = DB::table('cotecu.listas_cotejo_silabos')
            ->where('nombre', self::LISTA_COTEJO_SILABO['nombre'])
            ->value('id');

        foreach (self::LISTA_COTEJO_SILABO['items'] as $indice => $descripcion) {
            DB::table('cotecu.lista_cotejo_silabos_items')->updateOrInsert(
                ['lista_cotejo_silabo_id' => $idLista, 'orden' => $indice + 1],
                ['descripcion' => $descripcion],
            );
        }
    }

    /**
     * Lista de cotejo de planes y sus 17 ítems agrupados en 7 secciones:
     * es lo que permite crear registros en cotecu.revisiones_plan_
     * curricular (la FK lista_cotejo_id es NOT NULL) y lo que el
     * director de escuela marca al revisar un plan curricular.
     *
     * Reescribe los ítems de la lista (borra los previos y vuelve a
     * insertarlos con su sección y su orden), de modo que cambiar el
     * contenido del piloto no deje órdenes huérfanos. Ojo: la FK
     * resultados_items.item_id es restrict, así que antes de borrar los
     * ítems se borran sus marcados; re-ejecutar el seeder deja las
     * revisiones de prueba sin marcar.
     */
    private function sembrarListaCotejoPlanes(): void
    {
        DB::table('cotecu.listas_cotejo')->updateOrInsert(
            ['nombre' => self::LISTA_COTEJO_PLAN['nombre']],
            [
                'version' => self::LISTA_COTEJO_PLAN['version'],
                'estado' => self::LISTA_COTEJO_PLAN['estado'],
            ],
        );

        $idLista = DB::table('cotecu.listas_cotejo')
            ->where('nombre', self::LISTA_COTEJO_PLAN['nombre'])
            ->value('id');

        $itemsPrevios = DB::table('cotecu.lista_cotejo_items')
            ->where('lista_cotejo_id', $idLista)
            ->pluck('id');

        if ($itemsPrevios->isNotEmpty()) {
            DB::table('cotecu.resultados_items')->whereIn('item_id', $itemsPrevios)->delete();
            DB::table('cotecu.lista_cotejo_items')->where('lista_cotejo_id', $idLista)->delete();
        }

        $orden = 0;

        foreach (self::LISTA_COTEJO_PLAN['secciones'] as $seccion) {
            foreach ($seccion['items'] as $descripcion) {
                DB::table('cotecu.lista_cotejo_items')->insert([
                    'lista_cotejo_id' => $idLista,
                    'seccion' => $seccion['titulo'],
                    'descripcion' => $descripcion,
                    'orden' => ++$orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Documentos oficiales con su enlace a la fuente oficial. Idempotente
     * por permiso (único por documento): re-ejecutar actualiza título,
     * URL y orden sin duplicar filas.
     */
    private function sembrarDocumentosOficiales(): void
    {
        foreach (self::DOCUMENTOS_OFICIALES as $documento) {
            DocumentoOficial::updateOrCreate(['permiso' => $documento['permiso']], $documento);
        }
    }
}
