"""Prueba ficticia del puente CP-SAT; requiere HORARIOS_PYTHON_BUNDLE."""
import json
import os
import subprocess
import sys

from ortools.sat.python import cp_model

model = cp_model.CpModel()
x = model.new_int_var(0, 3, 'x')
model.add(x >= 2)
model.minimize(x)
solver = cp_model.CpSolver()
estado_minimo = solver.solve(model)
assert estado_minimo == cp_model.OPTIMAL
assert json.loads(json.dumps({'estado': solver.status_name(estado_minimo), 'x': solver.value(x)}))['x'] == 2
print('ORTOOLS_MINIMO: OPTIMAL x=2')

dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES']
estudiantes = [f'FICTICIO_{n}' for n in range(12)]
cal = {'dias': dias + ['SABADO'], 'hora_inicial': 7, 'hora_final': 20,
       'receso': {'inicio': 13, 'fin': 14}, 'sabados_por_ciclo': {'2': False},
       'bloques_hora_base': [{'inicio': h, 'fin': h + 1} for h in range(7, 20) if h != 13]}
secciones = [{'id': f'S{n}', 'curso_id': str(900+n), 'nombre': f'Curso {n}', 'ciclo': 2,
              'etiqueta': 'A', 'capacidad': 20, 'estado': 'AUTORIZADA',
              'horas': {'T': 2, 'P': 2 if n == 2 else 0, 'LAB': 2 if n == 1 else 0},
              'estudiantes': [{'estudiante_id': e, 'preferencia': 'TARDE'} for e in estudiantes]}
             for n in (1, 2)]
actividades = [{'id': f'A{n}', 'seccion_id': f'S{sec}', 'docente_id': f'D{sec}', 'tipo': tipo,
                'horas': 2, 'duraciones': [2], 'estudiantes': estudiantes,
                'subgrupo': 'G1' if tipo == 'LAB' else None, 'repeticion': False,
                'fuente_asignacion': 'FICTICIA'}
               for n, sec, tipo in ((1, 1, 'T'), (2, 1, 'LAB'), (3, 2, 'T'), (4, 2, 'P'))]
entrada = {'contrato': 'HORARIOS_MOTOR_V1', 'periodo': 'FICTICIO',
           'configuracion': {'calendario': cal, 'capacidad_subgrupo_autorizada': 20},
           'secciones': secciones, 'actividades': actividades,
           'ambientes': [{'id': 'R1', 'codigo': 'R1', 'nombre': 'Aula', 'tipo': 'AULA', 'capacidad': 20, 'activo': True},
                         {'id': 'R2', 'codigo': 'R2', 'nombre': 'Aula 2', 'tipo': 'AULA', 'capacidad': 20, 'activo': True},
                         {'id': 'L1', 'codigo': 'L1', 'nombre': 'Laboratorio', 'tipo': 'LAB', 'capacidad': 20, 'activo': True}],
           'disponibilidades': [{'docente_id': f'D{n}', 'estado': 'DECLARADA',
                                 'franjas': [{'dia': dia, 'inicio': 7, 'fin': 20} for dia in dias]}
                                for n in (1, 2)],
           'bloqueos': [], 'sesiones': [], 'tiempo_limite': 5, 'alcance': []}
bundle = os.environ['HORARIOS_PYTHON_BUNDLE']
env = dict(os.environ, PYTHONPATH=bundle, PYTHONDONTWRITEBYTECODE='1')
proceso = subprocess.run([sys.executable, '-B', '-m', 'scripts.motor_aplicacion'], input=json.dumps(entrada),
                         text=True, capture_output=True, cwd=bundle, env=env, timeout=30, check=True)
resultado = json.loads(proceso.stdout)
assert resultado['contrato'] == 'HORARIOS_RESULTADO_V1', resultado
assert resultado['estado'] in ('OPTIMAL', 'FEASIBLE'), resultado
assert resultado['validacion_operativa']['valida'] and resultado['validacion_operativa']['completa'], resultado
sesiones = resultado['sesiones']
assert len(sesiones) == 4, resultado
acts = {a['id']: a for a in actividades}
rooms = {r['id']: r for r in entrada['ambientes']}
for s in sesiones:
    a = acts[s['actividad_id']]
    assert s['fin'] - s['inicio'] == 2
    assert s['dia'] in dias and s['inicio'] >= 7 and s['fin'] <= 20
    assert rooms[s['ambiente_id']]['capacidad'] >= len(a['estudiantes'])
    assert a['tipo'] != 'LAB' or rooms[s['ambiente_id']]['tipo'] == 'LAB'
for i, a in enumerate(sesiones):
    for b in sesiones[i+1:]:
        if a['dia'] == b['dia'] and a['inicio'] < b['fin'] and b['inicio'] < a['fin']:
            aa, bb = acts[a['actividad_id']], acts[b['actividad_id']]
            assert aa['docente_id'] != bb['docente_id']
            assert aa['seccion_id'] != bb['seccion_id']
            assert a['ambiente_id'] != b['ambiente_id']
print('MOTOR_ACADEMICO:', resultado['estado'], 'sesiones:', len(sesiones), 'validacion_operativa: correcta')

entrada['disponibilidades'][0]['franjas'] = []
p = subprocess.run([sys.executable, '-B', '-m', 'scripts.motor_aplicacion'], input=json.dumps(entrada),
                   text=True, capture_output=True, cwd=bundle, env=env, timeout=30, check=True)
inviable = json.loads(p.stdout)
assert inviable['estado'] == 'INFEASIBLE', inviable
assert not inviable['sesiones']
print('DISPONIBILIDAD_VACIA: INFEASIBLE')
