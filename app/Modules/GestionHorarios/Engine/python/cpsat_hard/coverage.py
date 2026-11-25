"""Cobertura institucional verificable, separada de factibilidad matemática.

La presencia de un nombre, un tamaño o una etiqueta no acredita un oficio.
Este informe inventaría lo disponible y lo pendiente; no autentica documentos.
"""
def assess(i, checks=None):
    acts=list(i.activities.values())
    labs=[a for a in acts if a['tipo']=='LAB']
    rosters=[a for a in acts if 'estudiantes_ids' in a]
    evidence=i.raw.get('evidencias_institucionales',{})
    definitions={
        'H04': (len(rosters),len(acts),'Matrícula oficial por actividad y equivalencias de secciones/grupos; o relación exhaustiva acreditada de pares de conflicto.'),
        'H06': (sum('estudiantes_ids' in a for a in labs),len(labs),'Padrones nominales de cada subgrupo LAB y sección matriz, con identidad, disyunción y cobertura verificables.'),
        'H07': (sum(a.get('fuente_asignacion_docente') is not None for a in acts),len(acts),'Asignación docente aprobada para cada oferta, sección y actividad; documento fuente identificado.'),
        'H08': (sum(a.get('fuente_disponibilidad_docente') is not None for a in acts),len(acts),'Disponibilidad formal por docente, curso/sección y escenario; acreditación de ventanas adicionales y flexibilidad.')}
    rows={}
    for rule,(available,total,needed) in definitions.items():
        supplied=evidence.get(rule,{})
        # Solo se reconoce una referencia trazada a bytes cargados. Aun así,
        # autenticidad y aprobación institucional requieren revisión externa.
        traced=bool(supplied.get('sha256') and supplied['sha256'] in i.hashes.values())
        failed=bool(checks and checks[rule]['estado']=='FAIL')
        status='FAIL' if failed else 'PASS' if total==0 else 'PARCIAL_DATOS'
        rows[rule]={'estado':status,'alcance':'COBERTURA_INSTITUCIONAL; NO_AUTENTICA_DOCUMENTOS',
            'actividades_con_datos':available,'actividades_requeridas':total,
            'referencia_documental_trazada':traced,'evidencia_requerida':needed,
            'pendientes':[] if total==0 else [needed,'Revisión y acreditación institucional de procedencia y vigencia.'],
            'actividades_sin_padron':[a['id'] for a in (labs if rule=='H06' else acts) if 'estudiantes_ids' not in a] if rule in ('H04','H06') else
                [a['id'] for a in acts if not a.get('fuente_asignacion_docente' if rule=='H07' else 'fuente_disponibilidad_docente')],
            'nota':'La implementación técnica y el cumplimiento con datos disponibles se informan por separado.'}
    return {'reglas':rows,'acreditacion_institucional_completa':all(r['estado']=='PASS' for r in rows.values()),
            'fuentes_sha256':i.hashes}
