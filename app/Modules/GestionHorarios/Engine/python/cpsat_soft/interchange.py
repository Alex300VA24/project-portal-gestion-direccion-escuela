"""Compatibilidad blanda entre secciones alternativas; no inventa matrícula."""
from collections import defaultdict
from itertools import combinations


def add(i, built):
    from cpsat_hard.policy import opened_sections
    opened = opened_sections(i)
    m = built.model
    blocked = {r['id'] for r in built.rejected}
    slots, loads = defaultdict(list), defaultdict(int)
    for a in i.activities.values():
        if a['es_repeticion'] or a.get('tipo_grupo') == 'TESIS':
            continue
        key = (a['ciclo_curricular'], i.sections[a['seccion_id']]['etiqueta'], a['curso_id'])
        loads[key] += a['horas_semanales']
    for j,c in enumerate(i.placements):
        if j not in built.placements or c.id in blocked:
            continue
        a = i.activities[c.actividad_id]
        if a['es_repeticion'] or a.get('tipo_grupo') == 'TESIS':
            continue
        key = (a['ciclo_curricular'], i.sections[a['seccion_id']]['etiqueta'], a['curso_id'])
        for h in range(c.inicio, c.fin):
            slots[key, c.dia, h].append(built.placements[j])
    occupied = defaultdict(dict)
    for n, ((key,day,h), xs) in enumerate(slots.items()):
        present = m.new_bool_var(f'op_curso_seccion_{n}')
        m.add_max_equality(present, xs)
        occupied[key][day,h] = present
    terms, bound = [], 0
    for left,right in combinations(sorted(occupied), 2):
        if left[0] != right[0] or left[1] == right[1] or left[2] == right[2]:
            continue
        if not built.optional_presence and i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' and (len(opened[left[0],left[2]]) == 1 or len(opened[right[0],right[2]]) == 1):
            continue  # Ya es HARD; no duplicar presencias de conflicto imposibles.
        common = occupied[left].keys() & occupied[right].keys()
        bound += min(loads[left], loads[right], len(common))
        for slot in sorted(common):
            a,b = occupied[left][slot], occupied[right][slot]
            cross = m.new_bool_var(f'op_cruce_secciones_{len(terms)}')
            m.add(cross <= a); m.add(cross <= b); m.add(cross >= a+b-1)
            terms.append(cross)
    return sum(terms), bound


def evaluate(i, rows):
    """Cuenta cada hora de cada par de cursos/secciones una sola vez."""
    from cpsat_hard.policy import opened_sections
    opened = opened_sections(i)
    occupied = defaultdict(set)
    for row in rows:
        a = i.activities[row['actividad_id']]
        if a['es_repeticion'] or a.get('tipo_grupo') == 'TESIS':
            continue
        key = (a['ciclo_curricular'], i.sections[a['seccion_id']]['etiqueta'], a['curso_id'])
        occupied[key].update((row['dia'], h) for h in range(row['inicio'], row['fin']))
    crosses = []
    for left,right in combinations(sorted(occupied), 2):
        if left[0] != right[0] or left[1] == right[1] or left[2] == right[2]:
            continue
        if i.raw.get('politica_conflictos_operativa') == 'SECCION_Y_COMPARTIDOS_V4' and (len(opened[left[0],left[2]]) == 1 or len(opened[right[0],right[2]]) == 1):
            continue
        common = occupied[left] & occupied[right]
        if common:
            crosses.append({'ciclo': left[0], 'seccion_1': left[1], 'curso_1': i.courses[left[2]]['nombre'],
                'seccion_2': right[1], 'curso_2': i.courses[right[2]]['nombre'],
                'horas': len(common), 'bloques': [{'dia': d, 'inicio': h, 'fin': h+1} for d,h in sorted(common)]})
    return {'tipo': 'SOFT', 'horas_de_cruce': sum(c['horas'] for c in crosses), 'pares_con_cruce': len(crosses), 'cruces': crosses,
        'alcance': 'Cruces adicionales entre alternativas de distintas secciones; no son violaciones HARD. Matrícula comprueba la selección personal.'}
