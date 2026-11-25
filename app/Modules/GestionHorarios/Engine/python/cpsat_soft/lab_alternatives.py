"""Compatibilidad de horarios de grupos, sin seleccionar ni matricular alumnos."""
from collections import defaultdict
from itertools import combinations
from ortools.sat.python import cp_model
from cpsat_hard.policy import alternative_lab_activities, opened_sections, section_label
from cpsat_hard.search_support import fixed_assumptions_copy


def key(i,a):
    return a['ciclo_curricular'],a['curso_id'],a['seccion_id'],a.get('subgrupo_id') or a.get('grupo_id')


def relevant(i,left,right,opened):
    return left[0] == right[0] and left[1] != right[1] and (section_label(i.sections[left[2]]) == section_label(i.sections[right[2]]) or len(opened[left[:2]]) == 1 or len(opened[right[:2]]) == 1)


def add(i,built):
    alternative=alternative_lab_activities(i); opened=opened_sections(i)
    slots=defaultdict(list); blocked={r['id'] for r in built.rejected}
    for j,c in enumerate(i.placements):
        if j not in built.placements or c.id in blocked or c.actividad_id not in alternative: continue
        for h in range(c.inicio,c.fin): slots[key(i,i.activities[c.actividad_id]),c.dia,h].append(built.placements[j])
    occupied=defaultdict(dict)
    for n,((group,day,h),values) in enumerate(sorted(slots.items())):
        var=built.model.new_bool_var(f'lab_alternativa_ocupada_{n}')
        built.model.add_max_equality(var,values);occupied[group][day,h]=var
    costs=[]
    for left,right in combinations(sorted(occupied),2):
        if not relevant(i,left,right,opened): continue
        simultaneous=[]
        for slot in sorted(occupied[left].keys() & occupied[right].keys()):
            cross=built.model.new_bool_var(f'lab_coincidencia_{len(costs)}_{len(simultaneous)}')
            built.model.add_min_equality(cross,[occupied[left][slot],occupied[right][slot]])
            simultaneous.append(cross)
        if simultaneous:
            cross=built.model.new_bool_var(f'lab_pareja_incompatible_{len(costs)}')
            built.model.add_max_equality(cross,simultaneous);costs.append(cross)
    return sum(costs)


def improve(built,initial,primary,cost,seconds,workers):
    """Mejorar compatibilidad sin empeorar el objetivo ponderado existente."""
    model=fixed_assumptions_copy(built.model)
    model.add(primary <= initial.value(primary))
    model.minimize(cost);model.clear_hints()
    for index in range(len(model.proto.variables)):
        v=model.get_int_var_from_proto_index(index);model.add_hint(v,initial.value(v))
    solver=cp_model.CpSolver();solver.parameters.max_time_in_seconds=seconds
    solver.parameters.num_search_workers=workers;solver.parameters.random_seed=1
    status=solver.solve(model)
    accepted=status in (cp_model.FEASIBLE,cp_model.OPTIMAL) and solver.value(cost) <= initial.value(cost)
    return (solver if accepted else initial),{'estado':solver.status_name(status),'aplicado':accepted,'tiempo_s':solver.wall_time,
        'prioridad':'DESEMPATE_SIN_EMPEORAR_EL_OBJETIVO_EXISTENTE','alcance':'No certifica óptimo global ni asigna estudiantes o vacantes.'}


def evaluate(i,rows):
    alternative=alternative_lab_activities(i);opened=opened_sections(i)
    occupied=defaultdict(set)
    for row in rows:
        a=i.activities[row['actividad_id']]
        if a['id'] in alternative: occupied[key(i,a)].update((row['dia'],h) for h in range(row['inicio'],row['fin']))
    pairs=defaultdict(lambda:{'compatibles':0,'incompatibles':0,'coincidencias':[]})
    for left,right in combinations(sorted(occupied),2):
        if not relevant(i,left,right,opened): continue
        pair=pairs[left[:3],right[:3]];overlap=occupied[left] & occupied[right]
        pair['incompatibles' if overlap else 'compatibles']+=1
        if overlap:
            pair['coincidencias'].append({'grupo_1':str(left[3]),'grupo_2':str(right[3]),
                'bloques':[{'dia':d,'inicio':h,'fin':h+1} for d,h in sorted(overlap)]})
    details=[{'curso_1':i.courses[l[1]]['nombre'],'seccion_1':i.sections[l[2]]['etiqueta'],
        'curso_2':i.courses[r[1]]['nombre'],'seccion_2':i.sections[r[2]]['etiqueta'],**v} for (l,r),v in pairs.items()]
    return {'tipo':'SOFT','pares_compatibles':sum(v['compatibles'] for v in pairs.values()),
        'pares_incompatibles':sum(v['incompatibles'] for v in pairs.values()),'detalle':details,
        'alcance':'Compatibilidad por pares de horarios completos de grupos LAB; no garantiza cualquier combinación, un itinerario global ni distribución de vacantes. Sin padrones no acredita compatibilidad individual.'}
