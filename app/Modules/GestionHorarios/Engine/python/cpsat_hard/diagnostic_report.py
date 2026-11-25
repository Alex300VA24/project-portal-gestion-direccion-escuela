"""Diagnóstico legible con asignaturas, testigos mínimos y acciones pendientes."""
from itertools import combinations
from collections import Counter
from .domains import windows_for
from .exporting import write_json


def minimal_capacity_witnesses(courses):
    """Subconjuntos mínimos por inclusión que violan la capacidad del dominio.

    No se confunden con un núcleo CP-SAT mínimo ni con un plan global mínimo.
    Los horarios de esta instancia tienen pocos cursos; si son más de 16 se
    informa explícitamente que la búsqueda exhaustiva no se ejecutó.
    """
    if len(courses)>16:
        return {'exhaustivo':False,'motivo':'Más de 16 cursos en el horario','testigos':[]}
    witnesses=[]
    found=[]
    for size in range(1,len(courses)+1):
        for indexes in combinations(range(len(courses)),size):
            subset=set(indexes)
            if any(earlier<=subset for earlier in found): continue
            rows=[courses[k] for k in indexes]
            slots={tuple(s) for r in rows for s in r['slots_permitidos']}
            load=sum(r['minimo_horas_ocupadas'] for r in rows)
            if load>len(slots):
                found.append(subset)
                witnesses.append({'curso_ids':[r['curso_id'] for r in rows],'horas_minimas':load,
                                  'horas_unicas':len(slots),'deficit':load-len(slots),'slots':sorted(slots)})
    return {'exhaustivo':True,'testigos':witnesses}


def detailed(i,built,status,scenario):
    blocked={r['id'] for r in built.rejected}
    deficits=[]
    for row in built.cycle_bounds:
        if not row['deficit']: continue
        courses=[]
        for course in row['cursos']:
            aa=[a for a in i.activities.values() if a['curso_id']==course['curso_id'] and built.cohorts[a['id']]==(row['ciclo'],row['seccion_horario'])]
            slots=set(map(tuple,course['slots_permitidos']))
            activities=[]
            for a in aa:
                ws=windows_for(i,a,scenario)
                activities.append({'actividad_id':a['id'],'tipo':a['tipo'],'horas':a['horas_semanales'],
                    'docente_id':a['docente_id'],'docente':i.teachers[a['docente_id']].get('nombre',a['docente_id']),
                    'regla_disponibilidad':a['regla_disponibilidad'],'ventanas_aplicables':ws,
                    'patrones':[p for p in i.patterns.values() if p['actividad_id']==a['id']]})
            courses.append({**course,'asignatura':i.courses[course['curso_id']]['nombre'],'actividades':activities,
                            'dominio_saturado':course['minimo_horas_ocupadas']==len(slots)})
        overlaps=[]
        for left,right in combinations(courses,2):
            if left['dominio_saturado'] and right['dominio_saturado']:
                common=set(map(tuple,left['slots_permitidos'])) & set(map(tuple,right['slots_permitidos']))
                if common: overlaps.append({'curso_1':left['curso_id'],'curso_2':right['curso_id'],
                                            'slots_forzados_en_conflicto':sorted(common),'horas':len(common)})
        deficits.append({**row,'cursos':courses,'coincidencias_forzadas':overlaps,
                         'testigos_minimos_por_inclusion':minimal_capacity_witnesses(courses),
                         'restricciones_responsables':[
                             {'regla':'H04','evidencia':f"No solapamiento del horario {row['ciclo']}-{row['seccion_horario']}"},
                             {'regla':'H08','evidencia':'Unión de ventanas aplicables registrada por actividad; repeticiones solo M0 observado'},
                             {'regla':'H09','evidencia':'Carga curricular obligatoria, no reducible para obtener factibilidad'},
                             {'regla':'H02/H05/H06','evidencia':'Cotas de ocupación derivadas de cargas docentes y de sección/subgrupo'},
                             {'regla':'H07/H10','evidencia':'Se conservan docentes y patrones; una propuesta de disponibilidad debe admitir las sesiones completas'}],
                         'cambio_minimo_necesario':{'tipo':'AMPLIACION_DISPONIBILIDAD',
                             'cota_inferior_horas_nuevas':row['deficit'],
                             'autorizado':False,'estado':'REQUIERE_DISPONIBILIDAD_VALIDADA_Y_ACTUALIZAR_DOMINIOS',
                             'alcance':'Cota necesaria para este déficit; no garantiza factibilidad global'}})
    from .fixed_repetitions import analyze
    from .institutional_blocks import inspect as inspect_blocks
    return {'repeticiones_fijas':analyze(i) if i.raw.get('politica_repeticion_fija') else None,
            'bloqueos_institucionales':inspect_blocks(i,scenario),
            'estado_solver':status,'escenario':scenario,'unidad_conflicto':'CICLO_Y_SECCION_DE_HORARIO',
            'correccion_ya_autorizada':{'cambio':'Permitir cruce A/B con recursos y estudiantes explícitos compatibles',
                                      'fuente':'ACLARACION_EXPRESA_USUARIO','aplicada':True},
            'deficits':deficits,'n_candidatas_bloqueadas':len(blocked),
            'rechazos_por_regla':dict(Counter(m['regla'] for r in built.rejected for m in r['motivos'])),
            'advertencias':['Los grupos de Tesis G1/G2 y las secciones consolidadas requieren correspondencia con horarios estudiantiles; no se infiere matrícula de etiquetas.',
                            'Solo los subconjuntos indicados son testigos verificados; el núcleo CP-SAT no se afirma mínimo.',
                            'Los planes de disponibilidad son propuestas, no autorizaciones ni cambios de datos originales.'],
            'fuentes_sha256':i.hashes}


def markdown(report,repairs=None):
    if report.get('repeticiones_fijas'):
        fixed=report['repeticiones_fijas']
        lines=['# Diagnóstico de repeticiones fijas M0','',
               f"Estado del modelo completo: **{report['estado_solver']}**.",'',
               'Se conservan literalmente días, horas, docentes, ambientes y tipos de M0. No se agrupan repeticiones como matrícula común III-R; solo se aplican padrones o pares confirmados. No se proponen ampliaciones ni redistribuciones.','',
               '| M0 | Curso | Tipo fuente | Día | Horario | Docente | Ambiente |','| --- | --- | --- | --- | --- | --- | --- |']
        for e in fixed['sesiones_fijas']:
            lines.append(f"| {e['m0_id']} | {e['curso_id']} | {e['tipo']} | {e['dia']} | {e['inicio']:02d}–{e['fin']:02d} | {e['docente_id']} | {e['ambiente']} |")
        lines+=['','## Incompatibilidades preservadas y verificables','']
        for issue in fixed['incidencias']:
            if issue['regla'] in ('H02','H03'):
                lines.append(f"- **{issue['regla']}**: {', '.join(issue['sesiones'])}, cursos {issue['cursos']}, recurso {issue['recurso']}, {issue['dia']} {issue['inicio']:02d}–{issue['fin']:02d}. {issue['detalle']}.")
            else:
                lines.append(f"- **{issue['regla']}**, curso {issue['curso_id']}: M0 {issue['horas_M0_por_tipo']}, mixtas {issue['horas_mixtas']}; currículo {issue['horas_curriculares']}. {issue['detalle']}.")
        lines+=['','## Datos de fuente pendientes de conciliación','']
        for issue in fixed['traza_fuentes'].get('incidencias_fuente',[]):
            lines.append(f"- {issue['m0_id']}: {issue['detalle']}. {issue['tratamiento']}.")
        lines+=['','Las propuestas anteriores quedan como evidencia histórica. Las discrepancias entre sesiones fijas, ambientes y componentes curriculares deben conciliarse mediante una política explícita; no se resuelven inventando recursos o tipos.','',
                'Las sesiones mixtas conservan su intervalo físico completo; cualquier reparto T/P es solo contabilidad de carga sin atribuir un orden temporal oficial. Las candidatas móviles de repetición no se usan.']
        return '\n'.join(lines)+'\n'
    lines=['# Diagnóstico de horarios por ciclo y sección','',
           f"Estado de producción: **{report['estado_solver']}**. A y B son horarios distintos. Repeticiones: ciclo y sección del grupo asistente.",'',
           'La corrección A/B está autorizada por el usuario y ya se aplicó. Las ampliaciones de disponibilidad que se describen requieren validación; no se incorporaron a producción.','',
           '| Horario | Horas mínimas | Horas disponibles | Déficit |','| --- | --- | --- | --- |']
    for row in report['deficits']:
        lines.append(f"| {row['ciclo']}-{row['seccion_horario']} | {row['minimo_horas_cursos_sin_cruce']} | {row['horas_unicas_en_dominio']} | {row['deficit']} |")
    if not report['deficits']: lines.append('| Ningún déficit en esta cota | — | — | 0 |')
    for row in report['deficits']:
        lines+=['',f"## Horario {row['ciclo']}-{row['seccion_horario']}",'',
                '| Curso | Asignatura | Cota de ocupación | Docentes |','| --- | --- | --- | --- |']
        names={c['curso_id']:c['asignatura'] for c in row['cursos']}
        for c in row['cursos']:
            teachers=sorted({a['docente_id']+' '+a['docente'] for a in c['actividades']})
            lines.append(f"| {c['curso_id']} | {c['asignatura']} | {c['minimo_horas_ocupadas']} h | {'; '.join(teachers)} |")
        lines+=['','Coincidencias forzadas: cada curso de estos pares necesita ocupar todos los bloques de su dominio.','']
        for pair in row['coincidencias_forzadas']:
            slots=', '.join(f'{day} {h:02d}:00–{h+1:02d}:00' for day,h in pair['slots_forzados_en_conflicto'])
            lines.append(f"- {names[pair['curso_1']]} / {names[pair['curso_2']]}: {slots}.")
        lines+=['','Testigos mínimos por inclusión (no núcleos CP-SAT mínimos):','']
        for w in row['testigos_minimos_por_inclusion']['testigos']:
            labels=' + '.join(names[k] for k in w['curso_ids'])
            lines.append(f"- {labels}: al menos {w['horas_minimas']} h en {w['horas_unicas']} h distintas; déficit {w['deficit']} h.")
        lines+=['','Restricciones implicadas: H04 (mismo horario), H08 (ventanas), H09 (horas), H02/H05/H06 (ocupación mínima), H07/H10 (docentes y patrones preservados).', '',
                f"Para reparar esta cota mediante disponibilidad se necesitan al menos {row['deficit']} horas adicionales. Es una necesidad demostrada, no una autorización ni una garantía de factibilidad global."]
    if repairs:
        lines+=['','## Propuestas calculadas de ampliación mínima','',
                'Este cálculo separado minimiza horas nuevas de disponibilidad y, a igualdad, docentes afectados. No optimiza preferencias SOFT. Su alcance es el horario deficitario aislado; no demuestra por sí solo la factibilidad de toda la instancia.']
        for plan in repairs:
            lines+=['',f"### Plan {plan['horario']}",'',f"Estado de la búsqueda: {plan['estado']}. Autorización: **pendiente**."]
            if 'horas_nuevas' not in plan: continue
            lines += ['',f"Horas nuevas: **{plan['horas_nuevas']}**; docentes afectados: **{plan['docentes_afectados']}**. Mínimo demostrado en este problema local: {plan['minimo_local_demostrado']}.",'',
                      '| Docente | Curso | Día | Intervalo propuesto |','| --- | --- | --- | --- |']
            for w in plan['ventanas_propuestas']:
                lines.append(f"| {w['docente_id']} | {w['curso_id']} | {w['dia']} | {w['inicio']:02d}:00–{w['fin']:02d}:00 |")
            lines+=['',f"Validación del horario local bajo el supuesto de autorizar estas ventanas: {plan['validacion_local']['estado']}. Ninguna ventana se añadió a los archivos originales."]
    lines+=['','## Antes de volver a generar','',
            '1. Obtener confirmación de los intervalos propuestos para cada docente, curso y periodo; actualmente están pendientes.',
            '2. Registrar una nueva versión de las ventanas y regenerar candidatas con trazabilidad; conservar la instancia original.',
            '3. Ejecutar nuevamente toda la instancia con H01–H22 y validar en otro proceso. Un plan local aprobado habilita un reintento, no garantiza el resultado global.',
            '4. Completar matrícula, correspondencias de grupos especiales y respaldos institucionales para cerrar la certificación integral.']
    return '\n'.join(lines)+'\n'


def write_report(out, report, repairs=None):
    write_json(out/'diagnostico_detallado.json',report)
    (out/'DIAGNOSTICO.md').write_text(markdown(report,repairs),encoding='utf-8')
