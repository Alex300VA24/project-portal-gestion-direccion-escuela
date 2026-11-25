"""Invariantes curriculares de secciones y subgrupos previos a CP-SAT."""
from collections import defaultdict
from math import ceil
from .data import poblacion_asesoria_no_declarada


def subgroup(a):
    # En V1 cada actividad LAB representa un subgrupo. No usar la etiqueta
    # S01/L1 de un curso como identidad estudiantil de otro curso.
    return a.get('subgrupo_id', a['id'])


def inspect_curriculum(i):
    errors = []
    lab_limit = i.raw.get('politica_operativa', {}).get('capacidad_subgrupo_autorizada', 20)
    def check(ok, rule, detail):
        if not ok:
            errors.append({'regla': rule, 'detalle': detail})
    for a in i.activities.values():
        if i.raw.get('politica_repeticion_fija') and a['es_repeticion']:
            continue  # La sesión física M0 reemplaza los patrones alternativos.
        ps = [p for p in i.patterns.values() if p['actividad_id'] == a['id']]
        check(bool(ps), 'H01', f"{a['id']}: sin patrones")
        for p in ps:
            ss = sorted([s for s in i.sessions.values() if s['patron_id'] == p['id']], key=lambda s: s['numero'])
            durations = [s['duracion'] for s in ss]
            check(bool(ss) and durations == p['duraciones'] and len(ss) == p['n_sesiones']
                  and [s['numero'] for s in ss] == list(range(1,len(ss)+1)), 'H10', p['id'])
            check(sum(durations) == p['horas_totales'] == a['horas_semanales'], 'H09', p['id'])
    for course in i.courses.values():
        sections = [s for s in i.sections.values() if s['curso_id'] == course['id']]
        check(len(sections) == course['n_secciones'], 'H14', f"{course['id']}: número de secciones")
        check(sum(s['estudiantes'] for s in sections) == course['demanda_experimental'], 'H13', str(course['id']))
        acts = [a for a in i.activities.values() if a['curso_id'] == course['id']]
        capacities = [i.rooms[r]['capacidad'] for a in acts if a['requiere_ambiente'] and a['tipo'] in ('T','P')
                      for r in a['ambientes_candidatos'] if i.rooms[r]['tipo'] == a['tipo_ambiente']]
        if capacities:
            check(len(sections) >= ceil(course['demanda_experimental']/max(capacities)), 'H14', str(course['id']))
        for section in sections:
            aa = [a for a in acts if section['id'] in a.get('secciones_participantes', [a['seccion_id']])]
            check(section['estudiantes'] > 0 or poblacion_asesoria_no_declarada(section), 'H13', section['id'])
            # Una generación parcial conserva H09 por actividad; la cobertura curricular
            # completa se exige al revisar la propuesta completa, nunca se acredita aquí.
            if i.raw.get('alcance_parcial'):
                continue
            for typ, key in (('T','ht'),('P','hp')):
                whole = [a for a in aa if a['tipo'] == typ]
                check(sum(a['horas_semanales'] for a in whole) == course[key], 'H09', f"{section['id']} {typ}")
                check(all((len(a['estudiantes_por_seccion'][section['id']]) if 'sesion_fisica_id' in a
                           else a['estudiantes']) == section['estudiantes'] for a in whole),
                      'H13', f"{section['id']} {typ} tamaño")
            lab = [a for a in aa if a['tipo'] == 'LAB']
            groups = defaultdict(list)
            for a in lab:
                groups[subgroup(a)].append(a)
            check(bool(groups) == bool(course['hl']), 'H18', section['id'])
            if course['hl']:
                section_limit = section.get('capacidad_laboratorio') or lab_limit
                expected = section.get('cantidad_grupos_laboratorio') or ceil(section['estudiantes']/section_limit)
                check(expected >= ceil(section['estudiantes']/section_limit) and len(groups) == expected, 'H16', section['id'])
                sizes = []
                for gid, members in groups.items():
                    nums = {a['estudiantes'] for a in members}
                    check(len(nums) == 1 and 0 < min(nums) <= section_limit, 'H15', str(gid))
                    sizes.append(members[0]['estudiantes'])
                    check(sum(a['horas_semanales'] for a in members) == course['hl'], 'H18', str(gid))
                    if all('estudiantes_ids' in a for a in members):
                        check(all(set(a['estudiantes_ids'])==set(members[0]['estudiantes_ids']) for a in members), 'H06', str(gid))
                check(sum(sizes) == section['estudiantes'], 'H17', section['id'])
            # Si se aportan padrones, una partición debe ser disjunta y cubrir
            # exactamente la población de la sección, no solo su cardinalidad.
            if groups and all('estudiantes_ids' in aa[0] for aa in groups.values()):
                sets = [set(m[0]['estudiantes_ids']) for m in groups.values()]
                check(sum(map(len,sets)) == len(set().union(*sets)), 'H06', section['id'])
                for whole in (a for a in aa if a['tipo'] in ('T','P') and 'estudiantes_ids' in a):
                    check(set().union(*sets)==set(whole['estudiantes_ids']), 'H17', section['id'])
    return errors


def limitations(i):
    notes = []
    complete = i.raw.get('cobertura_conflictos', {}).get('completa', False)
    rosters = all('estudiantes_ids' in a for a in i.activities.values())
    if not complete and not rosters:
        detail = ('Clases comunes protegidas; los grupos LAB alternativos pueden coincidir sin conflictos de recursos ni padrones reales. Sin padrones no se garantiza cualquier combinación posterior de grupos.' if i.raw.get('politica_grupos_laboratorio') == 'ALTERNATIVAS_SIN_ASIGNACION_V1' else
                  'Sin cruces dentro de cada sección y con cursos de sección única; otros cambios entre secciones son compatibilidad SOFT. Matrícula comprueba la selección real.' if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' else
                  'Se prohíben cruces entre cursos regulares distintos del ciclo en todas sus secciones y grupos. Faltan matrícula y selecciones personales para acreditar compatibilidad entre ciclos y repeticiones.'
                  if i.raw.get('politica_conflictos_operativa') == 'CICLO_COMPLETO_V3' else
                  'Se prohíben cruces dentro del mismo ciclo y sección de horario (asistente en repeticiones); A/B son horarios distintos. Faltan matrícula y correspondencias de grupos especiales o secciones consolidadas para acreditar conflictos adicionales.')
        notes.append({'regla':'H04','detalle':detail})
    if not rosters:
        notes.append({'regla':'H06','detalle':'Subgrupos V1 definidos por actividad LAB y tamaños; disyunción personal entre grupos no acreditada con matrícula.'})
    if i.raw.get('metadata', {}).get('calidad') == 'SUPUESTOS_EXPERIMENTALES_ETIQUETADOS':
        notes.append({'regla':'H07','detalle':'Se conserva asignación de la instancia contrafactual; no acredita aprobación institucional de las nuevas asignaciones.'})
    if any(a['regla_disponibilidad'] in ('DECLARADA_CON_EXTRA_B','ESPECIAL_FLEXIBILIDAD_EXP_NO_APROBADA','REPETICION_SOLO_M0_RESTRINGIDA','FLEXIBLE_INSTITUCIONAL_SUPUESTO_REGULAR') for a in i.activities.values()):
        notes.append({'regla':'H08','detalle':'Se aplica la política experimental etiquetada. Ventanas M0 observadas y flexibilidad supuesta no equivalen a disponibilidad institucional validada.'})
    return notes
