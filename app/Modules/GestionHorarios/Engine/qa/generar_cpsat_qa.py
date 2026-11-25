"""Puente CLI de QA: concilia la estructura histórica y utiliza las asignaciones vigentes de PostgreSQL.
No escribe la base. PHP conserva el resultado solamente tras verificar vigencia.
"""
import argparse
import hashlib
import importlib.util
import json
import sys
from pathlib import Path

TIPOS = {'T': 'TEORIA', 'P': 'PRACTICA', 'LAB': 'LABORATORIO'}

def exigir(cond, mensaje):
    if not cond:
        raise ValueError(mensaje)

def codigo_docente(identidad):
    return 'QA-' + hashlib.sha256(str(identidad).encode()).hexdigest()[:20]

def minutos(hora):
    h, m, *s = str(hora).split(':')
    exigir(not s or s == ['00'], 'Hora con segundos no admitida.')
    return int(h) * 60 + int(m)

def unico(filas, clave, etiqueta):
    resultado = {}
    for fila in filas:
        k = clave(fila)
        exigir(k not in resultado, f'{etiqueta}: identidad duplicada.')
        resultado[k] = fila
    return resultado

def actualizar_disponibilidad(e, db, doc, ids_doc, bloques):
    """PostgreSQL gobierna las declaraciones generales estrictas; no inventa reglas particulares."""
    decl = unico(db['declaraciones'], lambda d: int(d['id_docente']), 'Declaraciones')
    originales = unico(e['disponibilidades'], lambda d: int(doc[codigo_docente(d['docente_id'])]['id_docente']), 'Declaraciones históricas')
    exigir(set(decl) <= set(ids_doc), 'Declaración de docente sin correspondencia.')
    franjas = {}
    claves = set()
    for r in db['disponibilidades']:
        id_d = int(r['id_docente']); dia = r['dia']; i, f = minutos(r['inicio']), minutos(r['fin'])
        k = (id_d, dia, i, f)
        exigir(id_d in decl and decl[id_d]['estado'] == 'PRESENTADA', 'Franja sin declaración presentada.')
        exigir(dia in ('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO') and (i,f) in bloques and i%60==0 and f%60==0 and i<f, 'Franja fuera del calendario o de la granularidad del motor.')
        exigir(k not in claves, 'Franja duplicada.'); claves.add(k)
        franjas.setdefault(id_d, set()).add((dia,i,f))
    cambios = []
    nuevas = []
    for id_d, identidad in sorted(ids_doc.items()):
        original = originales.get(id_d); d = decl.get(id_d)
        if not original and not d: continue  # preservar la política de origen de docentes nunca declarados
        if d:
            exigir(d['estado'] in ('PENDIENTE','NO_PRESENTADA','PRESENTADA'), 'Estado de declaración desconocido.')
            exigir(d['tratamiento_regular']=='ESTRICTA', 'Tratamiento FLEXIBLE no traducido; seleccione ESTRICTA o adapte su política explícitamente.')
        estado = 'DECLARADA' if d and d['estado']=='PRESENTADA' else 'SIN_DECLARAR'
        actuales = franjas.get(id_d, set())
        esperadas = {(f['dia'], i, t) for f in (original or {}).get('franjas', []) for i,t in bloques if i>=f['inicio']*60 and t<=f['fin']*60}
        if original and original['estado']==estado and actuales==esperadas:
            nuevas.append(original); continue  # conservar exactamente las ventanas históricas no modificadas
        item = dict(original or {}, docente_id=identidad, estado=estado,
                    preferencia=(original or {}).get('preferencia','SIN_PREFERENCIA'),
                    franjas=[{'dia':dia,'inicio':i//60,'fin':f//60} for dia,i,f in sorted(actuales)])
        nuevas.append(item)
        cambios.append({'docente':codigo_docente(identidad),'estado':estado,'bloques':len(actuales)})
    e['disponibilidades'] = nuevas
    e['_disponibilidad_actualizada_qa'] = cambios

def aplicar_bloqueos_registrados(e, db, ids_doc):
    """Solo el contrato horario explícito y obligatorio del periodo. Rechazar reglas no traducidas."""
    ids_amb = {int(r['id_ambiente']): r['codigo'] for r in db['ambientes'] if 'id_ambiente' in r}
    rooms = {r['codigo']: r['id'] for r in e['ambientes']}
    vistos = set()
    for r in db['restricciones']:
        exigir(isinstance(r, dict), 'Restricción institucional no traducida.')
        exigir(r.get('ambito') == 'PERIODO' and r.get('id_periodo') is not None, 'Restricción sin alcance de periodo traducible.')
        if int(r['id_periodo']) != int(db['periodo']): continue
        exigir(str(r.get('codigo','')).startswith('QA-BLOQ-') and r.get('tipo')=='OTRO' and r.get('severidad')=='DURA', 'Restricción no traducida: '+str(r.get('codigo','')))
        p = json.loads(r['parametros']) if isinstance(r['parametros'],str) else r['parametros']
        exigir(isinstance(p,dict) and set(p)=={'contrato','alcance','objetivo','dia','inicio','fin'} and p['contrato']=='QA_BLOQUEO_V1','Contrato de bloqueo desconocido.')
        a,o,d,i,f=(p[k] for k in ('alcance','objetivo','dia','inicio','fin'))
        exigir(a in ('GLOBAL','CICLO','DOCENTE','AMBIENTE') and d in ('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO') and type(i) is int and type(f) is int and 7<=i<f<=20,'Franja de bloqueo inválida.')
        exigir((o is None if a=='GLOBAL' else type(o) is int and o>0),'Objetivo de bloqueo inválido.')
        x={'dia':d,'inicio':i,'fin':f}
        if a=='CICLO':
            exigir(1<=o<=10,'Ciclo inválido.'); x['ciclo']=o
        elif a=='DOCENTE':
            exigir(o in ids_doc,'Docente de bloqueo no conciliado.'); x['docente_id']=ids_doc[o]
        elif a=='AMBIENTE':
            exigir(o in ids_amb and ids_amb[o] in rooms,'Ambiente de bloqueo no conciliado.'); x['ambiente_id']=rooms[ids_amb[o]]
        ident='QA-BLOQ-'+str(int(r['id_restriccion']))
        exigir(ident not in vistos,'Restricción con múltiples alcances no traducidos.'); vistos.add(ident)
        e['bloqueos'].append({'id':ident,'alcance':a,'datos':x,'fuente':'RESTRICCION_PERIODO_QA'})
    e['_bloqueos_qa']=len(vistos)

def conciliar(e, db, fuente):
    sec = unico(db['secciones'], lambda s: (s['curso'], s['seccion']), 'Secciones PostgreSQL')
    orig_sec = unico(e['secciones'], lambda s: (s['codigo'], s['etiqueta']), 'Secciones del fixture')
    faltantes = sorted(set(orig_sec) - set(sec))
    adicionales = sorted(set(sec) - set(orig_sec))
    cambios = []
    for k in sorted(set(sec) & set(orig_sec)):
        p, s = sec[k], orig_sec[k]
        for campo, real, esperado in (
            ('ciclo', int(p['ciclo']), s['ciclo']),
            ('capacidad', p['capacidad'], s['capacidad']),
            ('horas T/P/L', [int(p[x]) for x in ('ht', 'hp', 'hl')],
             [s['horas'][x] for x in ('T', 'P', 'LAB')]),
        ):
            if real != esperado:
                cambios.append(f'{k[0]}/{k[1]} {campo}: PostgreSQL={real}, JSON={esperado}')
    if faltantes or adicionales or cambios:
        detalle = [f'Secciones activas: PostgreSQL={len(sec)}, JSON={len(orig_sec)}.']
        if faltantes:
            detalle.append('Faltan en PostgreSQL: ' + ', '.join(f'{c}/{s}' for c, s in faltantes) + '.')
        if adicionales:
            detalle.append('Adicionales en PostgreSQL: ' + ', '.join(
                f'{c}/{s} (id={sec[(c,s)]["id_seccion"]})' for c, s in adicionales) + '.')
        if cambios:
            detalle.append('Datos distintos: ' + '; '.join(cambios) + '.')
        raise ValueError(' '.join(detalle) + ' No se ejecutará el motor con datos no conciliados.')
    por_id = {}
    for k, s in orig_sec.items():
        p = sec[k]
        exigir(int(p['ciclo']) == s['ciclo'] and p['capacidad'] == s['capacidad'], f'Capacidad o ciclo distinto: {k}.')
        exigir([int(p[x]) for x in ('ht', 'hp', 'hl')] == [s['horas'][x] for x in ('T', 'P', 'LAB')], f'Horas curriculares distintas: {k}.')
        por_id[s['id']] = p
    doc = unico(db['docentes'], lambda d: d['codigo_docente'], 'Docentes')
    d_origen = unico(fuente['filas']['docentes'], lambda d: codigo_docente(d['id']), 'Docentes originales')
    exigir(set(doc) == set(d_origen), 'Catálogo docente diferente del fixture.')
    for k, d in d_origen.items():
        exigir(doc[k]['estado'] == d['estado'] and doc[k]['horas_maximas'] == d['max_horas_semanales'], 'Estado o carga máxima docente modificados.')
    # El contrato original usa los identificadores privados. No se copian nombres al resultado persistido.
    ids_doc = {int(doc[k]['id_docente']): str(d['id']) for k, d in d_origen.items()}
    a_orig = unico(e['actividades'], lambda a: a['id'], 'Actividades originales')
    a_db = unico(db['actividades'], lambda a: (int(a['id_seccion']), a['tipo'], a['dia'], minutos(a['inicio']), minutos(a['fin'])), 'Actividades PostgreSQL')
    asig = unico(db['asignaciones'], lambda a: int(a['id_actividad']), 'Asignaciones vigentes')
    mapa = {}
    por_actividad = {}
    for s in e['sesiones']:
        a = a_orig[s['actividad_id']]
        p = por_id[a['seccion_id']]
        k = (int(p['id_seccion']), TIPOS[a['tipo']], s['dia'], s['inicio']*60, s['fin']*60)
        exigir(k in a_db, f'Sesión de origen no conciliada: {p["curso"]} / {p["seccion"]}.')
        fila = a_db.pop(k)
        id_a = int(fila['id'])
        d = asig.pop(id_a, None)
        exigir(d is not None, f'Falta asignación vigente: actividad QA {id_a}.')
        id_doc = int(d['id_docente'])
        exigir(id_doc in ids_doc, f'Docente sin correspondencia: actividad QA {id_a}.')
        nuevo_doc = ids_doc[id_doc]
        exigir(doc[codigo_docente(nuevo_doc)]['estado'] == 'ACTIVO', f'Docente inactivo: actividad QA {id_a}.')
        por_actividad.setdefault(a['id'], []).append((id_a, nuevo_doc))
        exigir(s['sesion_motor'] not in mapa, 'Sesión de motor duplicada.')
        mapa[s['sesion_motor']] = {'id': id_a, 'seccion_id': int(p['id_seccion']), 'tipo': TIPOS[a['tipo']], 'duracion': (s['fin']-s['inicio'])*60,
            'docentes': [{'id_docente': int(d['id_docente']), 'codigo_docente': codigo_docente(nuevo_doc), 'estado': d['estado']}],
            'actividad_motor': a['id'], 'grupo': a.get('etiqueta_grupo'), 'sesion_motor': s['sesion_motor']}
    exigir(not a_db and not asig and len(mapa) == 175, 'Actividades o asignaciones adicionales/no conciliadas.')
    # Conservar patrones, subgrupos y población. El contrato HORARIOS_MOTOR_V1
    # admite un solo docente por actividad (no uno por sesión del mismo patrón).
    # No dividir el patrón porque alteraría restricciones y calidad académica.
    cambios_docentes = []
    for id_act, a in a_orig.items():
        filas = por_actividad.get(id_act, [])
        exigir(len(filas) == len(a['duraciones']), 'Patrón de actividad incompleto.')
        destinos = {d for _, d in filas}
        p = por_id[a['seccion_id']]
        if len(destinos) != 1:
            detalle = ', '.join(f'{i}={codigo_docente(d)}' for i, d in filas)
            raise ValueError(f'Docentes diferentes dentro de una actividad: {p["curso"]}/{p["seccion"]} '
                             f'{a["tipo"]} {a.get("etiqueta_grupo") or ""}. '
                             f'Actividades QA: {detalle}. Asigne el mismo docente a las sesiones '
                             'de este patrón o adapte explícitamente el modelo de responsabilidades.')
        nuevo = next(iter(destinos))
        if nuevo != str(a['docente_id']):
            anterior = str(a['docente_id'])
            cambios_docentes.append({'curso': p['curso'], 'seccion': p['seccion'],
                                     'tipo': a['tipo'], 'grupo': a.get('etiqueta_grupo'),
                                     'actividades_qa': [i for i, _ in filas],
                                     'anterior': codigo_docente(anterior), 'vigente': codigo_docente(nuevo)})
            # Mismo criterio que AsignacionCursos.php del original:
            # retirar las ventanas particulares del docente anterior, no heredarlas.
            a.update(docente_id=nuevo, docente_local_id=nuevo,
                     franjas_origen=None, regla_disponibilidad='SIN_DECLARAR_CAMBIO_DOCENTE',
                     asignacion_origen_id=None, fuente_asignacion='ASIGNACION_QA_POSTGRES',
                     estado_asignacion='ASIGNADO')
    e['_reasignaciones_qa'] = cambios_docentes
    bloques = {(minutos(b['inicio']), minutos(b['fin'])) for b in db['bloques']}
    exigir(bloques == {(b['inicio']*60, b['fin']*60) for b in e['configuracion']['calendario']['bloques_hora_base']}, 'Calendario modificado: requiere adaptación.')
    ambientes = unico(db['ambientes'], lambda a:a['codigo'], 'Ambientes PostgreSQL')
    exigir(set(ambientes) == {r['codigo'] for r in e['ambientes']}, 'Catálogo de ambientes diferente.')
    for r in e['ambientes']:
        p=ambientes[r['codigo']]
        exigir(p['tipo'] == ('LABORATORIO' if r['tipo']=='LAB' else 'AULA') and p['capacidad']==r['capacidad'] and p['estado']==('ACTIVO' if r['activo'] else 'INACTIVO'), 'Ambiente modificado: '+r['codigo'])
    actualizar_disponibilidad(e, db, doc, ids_doc, bloques)
    exigir(not db['bloqueos'] and not db['horarios'], 'Hay bloqueos u horarios institucionales no traducidos.')
    aplicar_bloqueos_registrados(e,db,ids_doc)
    demanda_origen = {o['codigo']:int(o['demanda_estimada']) for o in fuente['filas']['ofertas_academicas'] if o['activa']}
    demanda_db = {r['codigo']:int(r['total']) for r in db['demanda']}
    exigir(len(demanda_db)==len(db['demanda']) and all(int(r['manana'])==int(r['tarde'])==0 for r in db['demanda']), 'Preferencias de demanda modificadas o duplicadas.')
    oferta_orig={(o['codigo'],o['tipo_oferta']) for o in fuente['filas']['ofertas_academicas'] if o['activa']}
    oferta_db={(o['codigo'],o['tipo_oferta']) for o in db['oferta']}
    exigir(len(oferta_db)==len(db['oferta']) and oferta_db==oferta_orig and all(o['estado']=='PLANIFICADA' for o in db['oferta']), 'La oferta académica difiere de la planificación de origen.')
    exigir(demanda_db==demanda_origen, 'La demanda vigente no coincide con la demostración original.')
    if db['prioridad']:
        pr=db['prioridad']; orden=json.loads(pr['orden']) if isinstance(pr['orden'],str) else pr['orden']
        exigir(len(set(orden))==len(orden) and all(int(x) in ids_doc for x in orden), 'Prioridad QA inválida.')
        e['prioridad_docente']={'politica':'SOFT_P01_LEXICOGRAFICA_V1','version':int(pr['version']), 'orden':[{'docente_id':ids_doc[int(x)],'posicion':i+1} for i,x in enumerate(orden)]}
    return mapa, por_id, sorted(bloques)

def resultado_revision(e, db, mapa, secciones, bloques, r, validacion):
    v=r.get('validacion_historica',{})
    exigir(r.get('estado') in ('OPTIMAL','FEASIBLE') and v.get('cumple_reglas_con_datos_disponibles') is True, 'El motor no acreditó las reglas disponibles.')
    exigir(validacion.get('valida') is True and validacion.get('completa') is True and validacion.get('sesiones_programadas')==175 and validacion.get('sesiones_esperadas')==175, 'Validación independiente incompleta o incorrecta.')
    out=unico(r['sesiones'],lambda s:s['sesion_motor'],'Resultado CP-SAT')
    exigir(set(out)==set(mapa),'Cobertura de sesiones distinta del fixture.')
    rooms={a['id']:a['codigo'] for a in e['ambientes']}
    sesiones={}
    for k,s in out.items():
        a=mapa[k]
        exigir(s['actividad_id']==a['actividad_motor'] and (s['fin']-s['inicio'])*60==a['duracion'], 'Identidad o duración alterada.')
        sesiones[str(a['id'])]={'dia':s['dia'],'inicio':s['inicio']*60,'fin':s['fin']*60,'ambiente':rooms[s['ambiente_id']] if s['ambiente_id'] is not None else None}
    secs=[{k:p[k] for k in ('id_seccion','seccion','capacidad','ciclo','curso','nombre')} for p in secciones.values()]
    return {'plan':int(db['plan']),'periodo':int(db['periodo']),'secciones':secs,'actividades':list(mapa.values()),'bloques':bloques,'sesiones':sesiones,
            'cpsat_qa':{'estado':r['estado'],'sesiones':175,'actividades_motor':len(e['actividades']),'validacion_operativa':True,'reglas_con_datos_disponibles':True,'certificacion_completa_H01_H22':v.get('certificacion_completa_H01_H22') is True,'politica':e['configuracion'].get('politica_conflictos'),'prioridad_version':(e.get('prioridad_docente') or {}).get('version')}}

def diagnosticar_capacidad_docente(e, mapa, secciones):
    """Reproduce la cota necesaria de capacidad del motor, sin resolver ni escribir."""
    from collections import defaultdict
    from scripts.motor_aplicacion import normalizar
    from cpsat_hard.domains import violations
    i = normalizar(e)
    slots = defaultdict(set)
    for c in i.placements:
        if i.raw.get('politica_repeticion_fija') and i.activities[c.actividad_id]['es_repeticion']:
            continue
        if not violations(i, c, 'B'):
            slots[c.docente_id].update((c.dia, h) for h in range(c.inicio, c.fin))
    for r in i.raw.get('repeticiones_fijas_m0', []):
        slots[r['docente_id']].update((r['dia'], h) for h in range(r['inicio'], r['fin']))
    por_actividad = defaultdict(list)
    for a in mapa.values():
        por_actividad[a['actividad_motor']].append(a['id'])
    salida = []
    for docente in i.teachers:
        propias = [a for a in i.activities.values() if a['docente_id'] == docente]
        carga = sum(a['horas_semanales'] for a in propias)
        compatibles = len(slots[docente])
        if carga <= compatibles:
            continue
        dias = ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO')
        franjas = []
        for dia in dias:
            for h in sorted(h for d, h in slots[docente] if d == dia):
                if franjas and franjas[-1]['dia'] == dia and franjas[-1]['fin'] == h:
                    franjas[-1]['fin'] = h + 1
                else:
                    franjas.append({'dia': dia, 'inicio': h, 'fin': h + 1})
        actividades = []
        for a in propias:
            sec = secciones[a['seccion_id']]
            actividades.append({'curso': sec['curso'], 'seccion': sec['seccion'], 'tipo': a['tipo'],
                                'horas': a['horas_semanales'], 'actividades_qa': por_actividad[a['id']]})
        salida.append({'docente': codigo_docente(docente), 'carga': carga, 'horas_compatibles': compatibles,
                       'deficit': carga - compatibles, 'franjas_compatibles': franjas, 'actividades': actividades})
    return salida


def diagnosticos_publicos(filas):
    """Campos explícitos: no exponer identificadores históricos ni texto libre."""
    salida = []
    for fila in filas:
        d = {'categoria': fila.get('categoria')}
        for k in ('candidatas_rechazadas', 'carga', 'horas_unicas_en_dominio', 'deficit',
                  'horas_requeridas', 'horas_compatibles', 'ciclo', 'certeza', 'tipo', 'despues_de'):
            if k in fila:
                d[k] = fila[k]
        if fila.get('docente_id') is not None:
            d['docente'] = codigo_docente(fila['docente_id'])
        salida.append(d)
    return salida


def sesiones_edicion(e, mapa, edicion):
    base=edicion['base']; existentes=base['sesiones']; por_id={str(a['id']): (k,a) for k,a in mapa.items()}
    exigir(set(existentes)==set(por_id),'El horario no tiene todas las sesiones esperadas.')
    anteriores={str(a['id']):a for a in base['actividades']}
    exigir(set(anteriores)==set(por_id),'Las actividades del horario cambiaron; recalcule primero.')
    exigir(edicion.get('actividad') is None or str(edicion['actividad']) in por_id,'Actividad no reconocida.')
    rooms={r['codigo']:r['id'] for r in e['ambientes']}; acts={a['id']:a for a in e['actividades']}
    result=[]
    for id_a,(key,a) in por_id.items():
        anterior=anteriores[id_a]
        exigir(all(anterior.get(k)==a.get(k) for k in ('seccion_id','duracion','tipo','actividad_motor','sesion_motor','grupo','docentes')), 'Las actividades o asignaciones cambiaron; recalcule primero.')
        s=dict(existentes[id_a]); act=acts[a['actividad_motor']]
        if id_a==str(edicion.get('actividad')):
            s.update(dia=edicion['dia'],inicio=edicion['inicio'],fin=edicion['inicio']+a['duracion'],ambiente=edicion['ambiente'])
        exigir(type(s['inicio']) is int and type(s['fin']) is int and s['inicio']%60==s['fin']%60==0 and s['fin']-s['inicio']==a['duracion'],'Duración o franja no admitida.')
        room=s.get('ambiente'); exigir(room is None or room in rooms,'Ambiente desconocido.')
        fila={'sesion_motor':key,'actividad_id':a['actividad_motor'],'dia':s['dia'],'inicio':s['inicio']//60,'fin':s['fin']//60,'ambiente_id':rooms.get(room),'fijada':False,'origen':'EDICION_MANUAL'}
        if 'sesion_fisica_id' in act:
            fila.update(sesion_fisica_id=act['sesion_fisica_id'],secciones_participantes=act['secciones_participantes'])
        result.append(fila)
    return result

def main():
    p=argparse.ArgumentParser(); p.add_argument('portal',type=Path);p.add_argument('fuente',type=Path);p.add_argument('modo',choices=['prevalidar','generar','editar','reorganizar','aprobar','publicar']);p.add_argument('segundos',type=int);args=p.parse_args()
    exigir(1<=args.segundos<=300,'Tiempo permitido: 1 a 300 segundos.')
    motor=args.portal/'app/Modules/GestionHorarios/Engine/python'; sys.path.insert(0,str(motor))
    path=args.portal/'tests/Feature/GestionHorarios/python/comparar_demo.py'
    spec=importlib.util.spec_from_file_location('comparar_demo_qa',path);mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
    from scripts.motor_aplicacion import ejecutar
    raw=args.fuente.read_bytes(); fuente=json.loads(raw); e=mod.entrada(args.fuente)
    exigir(hashlib.sha256(args.fuente.read_bytes()).digest()==hashlib.sha256(raw).digest(),'Fuente modificada durante la lectura.')
    # PHP transmite JSON UTF-8; no depender de la codificación de stdin de Windows.
    db=json.loads(sys.stdin.buffer.read().decode('utf-8')); mapa, secs,bloques=conciliar(e,db,fuente)
    def progreso(fase): print('CP-SAT: '+fase,file=sys.stderr,flush=True)
    cal = db.get('calendario_qa')
    if cal:
        dias = cal['dias']; ciclos = cal['sabados_ciclos']
        exigir(bool(dias) and len(set(dias))==len(dias) and set(dias)<=set(('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES')), 'Días laborables inválidos.')
        exigir(all(type(c) is int and 1<=c<=10 for c in ciclos) and len(set(ciclos))==len(ciclos), 'Excepciones de sábado inválidas.')
        c=e['configuracion']['calendario']
        c.update(dias=dias+(['SABADO'] if ciclos else []),sabados='SELECCIONADOS' if ciclos else 'NINGUNO',sabados_por_ciclo={str(i):i in ciclos for i in range(1,11)},sabados_alcances=[{'ciclo':i,'secciones':[]} for i in ciclos])
    e['sesiones']=[]
    if args.modo in ('reorganizar','aprobar','publicar'):
        e['sesiones']=sesiones_edicion(e,mapa,{'base':db['_base']})
    e.update(alcance=[],tiempo_limite=args.segundos,priorizar_factibilidad=True,objetivo_generacion='reorganizar' if args.modo=='reorganizar' else 'generar')
    prep=ejecutar(dict(e,operacion='COMPROBAR_PREPARACION'),progreso)
    # No devolver identificadores privados ni mensajes libres del motor.
    resumen={'estado':prep.get('estado'),'bloqueadas':prep.get('bloqueadas'),'advertencias':prep.get('advertencias'),'listas':prep.get('listas'), 'candidatas':None,'revision':None,'reasignaciones_qa':e['_reasignaciones_qa'],'disponibilidad_actualizada_qa':e['_disponibilidad_actualizada_qa'],'bloqueos_personalizados':e['_bloqueos_qa']}
    if prep.get('bloqueadas') or prep.get('estado') not in ('LISTO','REQUIERE_ATENCION'):
        print(json.dumps(resumen));return
    entrada=ejecutar(dict(e,operacion='VALIDAR_ENTRADA'),progreso); exigir(entrada.get('estado')=='ENTRADA_VALIDA','Entrada rechazada por motor.')
    resumen['candidatas']=entrada.get('candidatas')
    resumen['deficit_docente_previo'] = diagnosticar_capacidad_docente(e, mapa, secs)
    if args.modo in ('editar','aprobar','publicar'):
        sesiones=sesiones_edicion(e,mapa,db['_edicion']) if args.modo=='editar' else e['sesiones']
        valid=ejecutar(dict(e,operacion='VALIDAR_HORARIO',completa=True,sesiones_candidatas=sesiones),progreso)['validacion_operativa']
        if not valid.get('valida') or not valid.get('completa'):
            errores=valid.get('errores',[])
            categorias={'Disponibilidad docente':'Disponibilidad docente','Sábado no autorizado':'Sábado no autorizado','Intervalo/actividad incompatible':'Intervalo fuera del calendario','Ambiente incompatible':'Tipo o capacidad de ambiente','Ambiente no asignado':'Ambiente no permitido','Conflicto global':'Cruce de docente, ambiente o grupo','Cruce entre cursos':'Cruce entre cursos','Bloqueo institucional':'Bloqueo institucional','Sesión fijada alterada':'Sesión fijada'}
            causas=sorted({v for error in errores for k,v in categorias.items() if error.startswith(k)})
            resumen.update(estado='MOVIMIENTO_RECHAZADO',motivo='Movimiento incompatible: '+('; '.join(causas) if causas else 'orden académico u otra regla operativa')+'.',errores_validacion=len(errores))
        else:
            try:
                revisado=ejecutar(dict(e,operacion='REVISAR_PROPUESTA',sesiones=sesiones),progreso)
            except ValueError:
                resumen.update(estado='MOVIMIENTO_RECHAZADO',motivo='La sesión queda fuera de las candidatas vigentes del motor.')
            else:
                v=revisado['validacion_historica']
                if v.get('cumple_reglas_con_datos_disponibles') is not True:
                    reglas=[k for k,d in v.get('reglas',{}).items() if d.get('estado')=='FAIL']
                    resumen.update(estado='MOVIMIENTO_RECHAZADO',motivo='Restricciones HARD incumplidas'+(': '+', '.join(reglas) if reglas else '')+'.')
                else:
                    resumen['estado']='FEASIBLE'
                    resumen['revision']=resultado_revision(e,db,mapa,secs,bloques,dict(estado='FEASIBLE',sesiones=sesiones,validacion_historica=v),revisado['validacion_operativa'])
                    resumen['revision']['cpsat_qa']['origen']='EDICION_MANUAL'
    if args.modo in ('generar','reorganizar'):
        r=ejecutar(dict(e,operacion='REORGANIZAR_HORARIO' if args.modo=='reorganizar' else 'GENERAR_HORARIO'),progreso);resumen['estado']=r.get('estado');resumen['diagnosticos']=[d.get('categoria') for d in r.get('diagnosticos',[])]
        resumen['diagnosticos_detalle'] = diagnosticos_publicos(r.get('diagnosticos', []))
        if r.get('estado') in ('OPTIMAL','FEASIBLE'):
            valid=ejecutar(dict(e,operacion='VALIDAR_HORARIO',completa=True,sesiones_candidatas=r['sesiones']),progreso)['validacion_operativa']
            resumen['revision']=resultado_revision(e,db,mapa,secs,bloques,r,valid)
    print(json.dumps(resumen,ensure_ascii=True,allow_nan=False))

if __name__=='__main__':
    try: main()
    except Exception as exc:
        # Mostrar los errores explícitos de conciliación, no excepciones de datos privados.
        print(json.dumps({'estado':'ERROR','mensaje':str(exc) if isinstance(exc,ValueError) else type(exc).__name__,'revision':None},ensure_ascii=True))
        sys.exit(2)
