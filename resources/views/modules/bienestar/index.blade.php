@extends('modules.bienestar.layout')

@section('title', 'Inicio · ' . $module)
@section('page-label', 'Inicio')

@section('content')
<div class="space-y-8">
    <!-- Encabezado ejecutivo del módulo -->
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md font-mono">
                    {{ $module }}
                </span>
                <span class="text-xs text-slate-500 font-mono">
                    esquema: <code>{{ $schema }}</code>
                </span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Gestión de Bienestar y Permanencia Estudiantil</h1>
            <p class="text-sm text-slate-600 mt-1">
                Acompañamiento psicopedagógico, orientación normativa y detección oportuna de riesgo académico de la Escuela de Informática.
            </p>
        </div>

        @auth
            <div class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl px-4 py-2.5 shadow-2xs">
                <span class="w-8 h-8 rounded-lg bg-indigo-900 text-white flex items-center justify-center font-bold text-xs">
                    {{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}
                </span>
                <div class="text-xs">
                    <strong class="text-slate-900 block">{{ auth()->user()->name }}</strong>
                    <span class="text-slate-500 font-mono">{{ Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario')->replace('_', ' ') }}</span>
                </div>
            </div>
        @endauth
    </header>

    @auth
        <!-- VISTA PERSONALIZADA SEGÚN EL ROL AUTENTICADO -->

        <!-- 1. Rol Alumno -->
        @if (auth()->user()->hasRole('alumno') || auth()->user()->can('bienestar.solicitar'))
            <section class="space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Servicios para el Estudiante</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Mis Solicitudes de Apoyo</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Registra solicitudes de apoyo socioeconómico o consulta el estado y dictamen emitido por Bienestar.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                                Gestionar solicitudes &rarr;
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Mis Citas de Consejería</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Consulta tus sesiones programadas de consejería psicopedagógica, seguimiento y recomendaciones.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.alumno.citas.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                                Ver agenda de citas &rarr;
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Normativa de Permanencia</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Infórmate sobre separación por reiterancia, inasistencias, reserva y procedimientos reglamentarios.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                                Consultar artículos &rarr;
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        @endif

        <!-- 2. Rol Profesor -->
        @if (auth()->user()->hasRole('profesor') || auth()->user()->can('bienestar.reportar'))
            <section class="space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Herramientas Docentes</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-amber-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Mis Reportes Preventivos</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Revisa los estudiantes que has reportado por riesgo de inasistencias o desaprobación y su estado de trámite.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.profesor.index') }}" class="inline-flex items-center text-xs font-bold text-amber-700 hover:text-amber-900">
                                Ver reportes emitidos &rarr;
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-amber-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Registrar Reporte de Riesgo</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Notifica a Bienestar sobre estudiantes matriculados en tus asignaturas con ausentismo reiterado o dificultades.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.profesor.create') }}" class="inline-flex items-center text-xs font-bold text-amber-700 hover:text-amber-900">
                                Nuevo reporte &rarr;
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-slate-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Normativa Académica</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Consulta la base normativa universitaria sobre inhabilitación por 30% de inasistencias y deberes docentes.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-xs font-bold text-slate-700 hover:text-slate-900">
                                Ver normativas &rarr;
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        @endif

        <!-- 3. Rol Secretaría / Bienestar -->
        @if (auth()->user()->hasRole('secretaria') || auth()->user()->can('bienestar.gestionar'))
            <section class="space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Operación de Bienestar</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-900 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Hub Operativo de Riesgo</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Bandeja central de alertas automáticas y docentes, gestión de casos, agendamiento de citas y derivaciones externas.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                            <a href="{{ route('bienestar.panel.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                                Abrir panel &rarr;
                            </a>
                            <a href="{{ route('bienestar.panel.alertas.create') }}" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-900">
                                + Alerta Manual
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-900 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Evaluación de Solicitudes</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Dictamina solicitudes de apoyo socioeconómico presentadas por estudiantes (Aprobada, Rechazada o En Evaluación).
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.panel.solicitudes.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                                Evaluar solicitudes &rarr;
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Artículos Normativos</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Redacta, edita y publica artículos orientadores sobre permanencia y régimen disciplinario para los estudiantes.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                            <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                                Ver catálogo &rarr;
                            </a>
                            <a href="{{ route('bienestar.informacion.create') }}" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-900">
                                + Redactar
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        @endif

        <!-- 4. Rol Director de Escuela -->
        @if (auth()->user()->hasRole('director_escuela') || auth()->user()->can('bienestar.estadisticas'))
            <section class="space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Supervisión y Análisis</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-purple-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-900 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Estadísticas Agregadas de Riesgo</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Indicadores consolidados por periodo lectivo protegidos con k-anonimato (celdas menores a 5 anonimizadas).
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.director.index') }}" class="inline-flex items-center text-xs font-bold text-purple-700 hover:text-purple-900">
                                Consultar métricas semestrales &rarr;
                            </a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-slate-400 hover:shadow-xs transition">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900">Normativa de Permanencia</h3>
                            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                                Base de conocimientos y disposiciones reglamentarias sobre permanencia estudiantil en la Escuela.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-xs font-bold text-slate-700 hover:text-slate-900">
                                Ver normativas &rarr;
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        @endif

    @else
        <!-- VISTA PÚBLICA PARA USUARIOS NO AUTENTICADOS -->
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Información Académica y Permanencia</h3>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        Acceso público a la normativa de permanencia universitaria: causas de separación, límites de inasistencia, retiro de cursos y procedimientos vigentes en la Escuela de Informática.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                        Consultar artículos públicos &rarr;
                    </a>
                </div>
            </article>

            <article class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-400 hover:shadow-xs transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-900 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Servicios Institucionales</h3>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        Los servicios de solicitudes de apoyo socioeconómico, citas de consejería, reportes preventivos docentes y gestión operativa requieren autenticación institucional.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('login') }}" class="inline-flex items-center text-xs font-bold text-indigo-700 hover:text-indigo-900">
                        Iniciar sesión institucional &rarr;
                    </a>
                </div>
            </article>
        </section>
    @endauth
</div>
@endsection
