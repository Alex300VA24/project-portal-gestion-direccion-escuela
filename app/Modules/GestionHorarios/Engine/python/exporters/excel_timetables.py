"""XLSX semanal determinista con vistas por grupo y ambiente, sin pérdidas."""
from collections import defaultdict
from datetime import datetime
from io import BytesIO
import json
import math
from pathlib import Path
import re
import textwrap
import unicodedata
from zipfile import ZipFile, ZIP_DEFLATED, ZipInfo
from openpyxl import Workbook, load_workbook
from openpyxl.formatting.rule import FormulaRule
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.pagebreak import Break
from .events import validated_events, validated_metrics, reservations, utilization, overlaps

COLORS = {'T':'DDEBF7','P':'E2EFDA','LAB':'E4DFEC','T/P_MIXTA_FUENTE':'DDEBF7',
          'RESERVA':'FCE4D6','RECESO':'E7E6E6'}
HEAD = '24435B'
ROMAN = dict(enumerate(('I','II','III','IV','V','VI','VII','VIII','IX','X'),1))
FIELDS = ('ref','id','curso_id','curso','ciclo','seccion','subgrupo','tipo','docente_id',
          'docente','ambiente_id','ambiente','dia','inicio','fin','duracion_h','origen','actividad_ids')
UTIL_FIELDS = ('ambiente_id','ambiente','tipo','capacidad','horas_disponibles','horas_modelo_union',
    'horas_reservas_union','horas_ocupadas_union','horas_libres','ocupacion','solapes_sesiones_h','horas_fuera_calendario')


def put(ws, row, col, value):
    cell = ws.cell(row,col,value)
    if isinstance(value,str):
        cell.data_type = 's'  # Texto de fuente, nunca una fórmula inyectada.
    cell.font = Font(name='Arial',size=10)
    cell.alignment = Alignment(vertical='center',wrap_text=True)
    return cell


def name_for(wb, text):
    name = unicodedata.normalize('NFKD',text).encode('ascii','ignore').decode().upper()
    name = re.sub(r'[^A-Z0-9_]+','_',name).strip('_') or 'HORARIO'
    candidate = name[:31]
    n = 1
    while candidate in wb.sheetnames:
        n += 1
        candidate = name[:26]+'_'+str(n)
    return candidate


def setup(ws, title, columns):
    ws.sheet_view.showGridLines = False
    put(ws,2,1,title).font = Font(name='Arial',size=14,bold=True,color=HEAD)
    if columns>1: ws.merge_cells(start_row=2,start_column=1,end_row=2,end_column=columns)
    ws.row_dimensions[2].height = 28
    ws.freeze_panes = 'B7'
    ws.sheet_properties.pageSetUpPr.fitToPage = True
    ws.page_setup.orientation = 'landscape'
    ws.page_setup.paperSize = ws.PAPERSIZE_A3
    ws.page_setup.fitToWidth = 1
    ws.page_setup.fitToHeight = 0
    ws.print_options.horizontalCentered = True
    ws.print_title_rows = '1:6'
    ws.oddFooter.center.text = 'Página &P de &N'
    for n in range(1,columns+1):
        ws.column_dimensions[get_column_letter(n)].width = 30 if n>1 else 19


def header(ws, labels):
    for n, label in enumerate(labels,1):
        c = put(ws,6,n,label)
        c.fill = PatternFill('solid',fgColor=HEAD)
        c.font = Font(name='Arial',size=10,bold=True,color='FFFFFF')
        c.alignment = Alignment(horizontal='center',vertical='center',wrap_text=True)
    ws.row_dimensions[6].height = 30


def table(wb, name, title, labels, rows):
    ws = wb.create_sheet(name)
    setup(ws,title,len(labels));header(ws,labels)
    for r, row in enumerate(rows,7):
        for col, value in enumerate(row,1):
            put(ws,r,col,value if value is not None else 'No disponible')
        ws.row_dimensions[r].height = 38
    ws.auto_filter.ref = f'A6:{get_column_letter(len(labels))}{max(6,ws.max_row)}'
    ws.print_area = f'A1:{get_column_letter(len(labels))}{ws.max_row}'
    return ws


def event_text(e):
    kind = 'LAB · SUBGRUPO '+e['subgrupo'] if e['tipo']=='LAB' else e['tipo']
    if kind=='T/P_MIXTA_FUENTE': kind='T/P · Fuente mixta'
    return '\n'.join((e['curso'], f"{kind} · Ciclo {e['ciclo']} · Sección {e['seccion']}",
        e['docente'],e['ambiente'],f"{e['inicio']:02}:00–{e['fin']:02}:00 · {e['ref']}",
        'Fijo M0' if e['origen']=='REPETICION_FIJA_M0' else
        'Reserva institucional' if e['tipo']=='RESERVA' else '')) .rstrip()


def grid(wb, title, events, cal, context, closures=()):
    ws = wb.create_sheet(name_for(wb,title))
    setup(ws,title,1+len(cal['dias']))
    put(ws,3,1,context)
    ws.row_dimensions[3].height=20
    ws.merge_cells(start_row=3,start_column=1,end_row=3,end_column=1+len(cal['dias']))
    for col,(key,label) in enumerate((('T','T · Teoría'),('P','P · Práctica'),('LAB','LAB · Solo subgrupo'),
                                      ('RESERVA','Reserva institucional'),('RECESO','Receso / cierre')),1):
        c=put(ws,4,col,label);c.fill=PatternFill('solid',fgColor=COLORS[key])
    ws.row_dimensions[4].height = 24
    assignments = {}
    lanes = 1
    for day in cal['dias']:
        ends = []
        for e in sorted((e for e in events if e['dia']==day),key=lambda e:(e['inicio'],e['fin'],e['id'])):
            lane = next((n for n,end in enumerate(ends) if end<=e['inicio']),len(ends))
            if lane==len(ends): ends.append(e['fin'])
            else: ends[lane]=e['fin']
            assignments[e['id']] = lane
        lanes = max(lanes,len(ends))
    note = 'Filas auxiliares por hora: '+str(lanes)+'. Las sesiones paralelas se conservan por separado.'
    put(ws,5,1,note)
    ws.merge_cells(start_row=5,start_column=1,end_row=5,end_column=1+len(cal['dias']))
    ws.row_dimensions[5].height = 24
    header(ws,['Hora',*cal['dias']])
    def row_for(h,lane=0): return 7+(h-cal['hora_inicial'])*lanes+lane
    for h in range(cal['hora_inicial'],cal['hora_final']):
        for lane in range(lanes):
            r = row_for(h,lane)
            put(ws,r,1,f'{h:02}:00–{h+1:02}:00'+(f' / vía {lane+1}' if lanes>1 else ''))
            ws.row_dimensions[r].height = 64
            for col in range(2,len(cal['dias'])+2):
                c = put(ws,r,col,'')
                c.border = Border(left=Side(style='hair',color='CCD5DB'),bottom=Side(style='hair',color='CCD5DB'))
                if cal['receso']['inicio']<=h<cal['receso']['fin']:
                    c.value = 'RECESO'
                    c.fill = PatternFill('solid',fgColor=COLORS['RECESO'])
                    ws.row_dimensions[r].height = 25
                elif any(b['dia']==cal['dias'][col-2] and b['inicio']<=h<b['fin'] for b in closures):
                    c.value='CIERRE GLOBAL';c.fill=PatternFill('solid',fgColor=COLORS['RECESO'])
    positions = []
    for e in sorted(events,key=lambda e:e['id']):
        col = cal['dias'].index(e['dia'])+2
        lane = assignments[e['id']]
        start,end = row_for(e['inicio'],lane),row_for(e['fin']-1,lane)
        text = event_text(e)
        # Margen para mayúsculas, palabras largas y sustitución de fuentes en LibreOffice.
        lines = sum(max(1,len(textwrap.wrap(line,26))) for line in text.splitlines())
        for h in range(e['inicio'],e['fin']):
            r = row_for(h,lane)
            c = put(ws,r,col,text)
            c.font = Font(name='Arial',size=9,color='17354A')
            c.fill = PatternFill('solid',fgColor=COLORS.get(e['tipo'],'FFF2CC'))
            c.alignment = Alignment(vertical='center',wrap_text=True)
            if lanes>1 or e['duracion_h']==1:
                ws.row_dimensions[r].height = max(ws.row_dimensions[r].height,lines*13+14)
        if lanes==1 and end>start:
            ws.merge_cells(start_row=start,start_column=col,end_row=end,end_column=col)
        positions.append({'id':e['id'],'sheet':ws.title,'column':col,'rows':[row_for(h,lane) for h in range(e['inicio'],e['fin'])],
                          'hours':list(range(e['inicio'],e['fin'])),'ref':e['ref']})
    ws.print_area = f'A1:{get_column_letter(len(cal["dias"])+1)}{ws.max_row}'
    if cal['hora_inicial']<cal['receso']['fin']<cal['hora_final']:
        ws.row_breaks.append(Break(id=row_for(cal['receso']['fin'])-1))
    return positions


def canonical_save(wb,path,stamp):
    raw = BytesIO();wb.save(raw)
    # openpyxl actualiza modified al guardar: fijar también ese dato y el ZIP.
    with ZipFile(raw) as source, ZipFile(path,'w',compression=ZIP_DEFLATED,compresslevel=9) as target:
        for name in sorted(source.namelist()):
            data = source.read(name)
            if name=='docProps/core.xml':
                data = re.sub(rb'(<dcterms:modified[^>]*>)[^<]*(</dcterms:modified>)',
                    lambda m:m[1]+stamp.encode()+m[2],data)
            info = ZipInfo(name,date_time=(2000,1,1,0,0,0));info.compress_type=ZIP_DEFLATED
            info.external_attr = 0o600 << 16
            target.writestr(info,data)


def export_excel(instance,outcome,path,*,spec=None):
    path = Path(path)
    if path.exists(): raise ValueError('El XLSX de destino ya existe; no sobrescribir evidencia')
    events,report = validated_events(instance,outcome)
    metrics = validated_metrics(instance,outcome,spec)
    reserved = reservations(instance)
    usage = utilization(instance,events,reserved)
    wb = Workbook();wb.remove(wb.active)
    generated = outcome.get('generado_UTC','No registrado en la solución')
    phase = 'HARD+SOFT' if 'valor_objetivo' in outcome else 'HARD'
    context = f"{instance.raw.get('metadata',{}).get('periodo','No indicado')} · {outcome.get('escenario','B')} · {phase} · PROPUESTA SIN APROBACIÓN"
    stamp = generated if generated!='No registrado en la solución' else '2000-01-01T00:00:00Z'
    date = datetime.fromisoformat(stamp.replace('Z','+00:00')).replace(tzinfo=None)
    wb.properties.created=date;wb.properties.modified=date;wb.properties.creator='Motor CP-SAT'
    rule_issues = sum(len(r['violaciones']) for r in report['reglas'].values())+len(report['otras_violaciones'])
    physical = overlaps(events)
    rows = [('Periodo',instance.raw.get('metadata',{}).get('periodo')),
        ('Escenario',outcome.get('escenario','B')),('Fase',phase),('Generación UTC',generated),
        ('Estado CP-SAT',outcome['estado']),('Estado académico','PROPUESTA SIN APROBACIÓN'),
        ('Validación independiente',report['estado']),('Cursos',len({e['curso_id'] for e in events})),
        ('Secciones de oferta',len({e['seccion_id'] for e in events})),('Actividades cubiertas',report['n_actividades']),
        ('Sesiones físicas',len(events)),('Horas físicas cubiertas',report['horas_programadas']),
        ('Aulas utilizadas',len({e['ambiente_id'] for e in events if e['ambiente_id'] and instance.rooms[e['ambiente_id']]['tipo']=='AULA'})),
        ('Laboratorios utilizados',len({e['ambiente_id'] for e in events if e['ambiente_id'] and instance.rooms[e['ambiente_id']]['tipo']=='LAB'})),
        ('Conflictos HARD no exceptuados',rule_issues),('Solapes físicos de ambiente',len(physical)),
        ('Excepciones aplicadas',len(report.get('excepciones_aplicadas',[]))),
        ('Objetivo entero',outcome.get('valor_objetivo')),('Mejor cota',outcome.get('mejor_cota')),
        ('Tiempo solver (s)',outcome.get('tiempo_solver_s'))]
    rows += list((metrics or {}).get('indicadores',{}).items())
    summary = table(wb,'RESUMEN','Horario académico semanal',['Concepto','Valor'],rows)
    summary.column_dimensions['A'].width=48;summary.column_dimensions['B'].width=85
    groups = defaultdict(list)
    for e in events: groups[e['grupo']].append(e)
    positions = []
    closures=[b for b in instance.raw['bloqueos'] if b['alcance'] in ('GLOBAL','GLOBAL_CONDICIONAL')]
    for (cycle,section),ee in sorted(groups.items()):
        title = f'CICLO_{ROMAN.get(cycle,str(cycle))}_{section}'
        positions += grid(wb,title,ee,instance.raw['calendario'],context,closures)
    for rid in sorted({e['ambiente_id'] for e in events+reserved if e['ambiente_id']}):
        room = instance.rooms[rid]
        ee = [e for e in events+reserved if e['ambiente_id']==rid]
        positions += grid(wb,room['nombre'],ee,instance.raw['calendario'],context,closures)
    labels = ['ID','Ambiente','Tipo','Capacidad (est.)','Disponibles (h)','Modelo unión (h)',
        'Reservas unión (h)','Ocupadas unión (h)','Libres (h)','Ocupación (%)','Solapes modelo (h)','Fuera calendario (h)']
    table(wb,'UTILIZACION_AMBIENTES','Utilización temporal semanal',labels,[[r[k] for k in UTIL_FIELDS] for r in usage])
    u = wb['UTILIZACION_AMBIENTES']
    put(u,3,1,usage[0]['denominador'] if usage else 'Sin ambientes')
    u.merge_cells('A3:L4');u.row_dimensions[3].height=30;u.row_dimensions[4].height=25
    for col in range(1,13): u.column_dimensions[get_column_letter(col)].width=19
    u.column_dimensions['B'].width=28;u.column_dimensions['C'].width=12;u.column_dimensions['D'].width=14
    u.row_dimensions[6].height=40
    for r in range(7,u.max_row+1): u.cell(r,10).number_format='0.0%'
    detail = table(wb,'SESIONES','Sesiones físicas de la solución',FIELDS,
        [[json.dumps(e[k],ensure_ascii=False) if k=='actividad_ids' else e[k] for k in FIELDS] for e in events])
    if events:
        detail.conditional_formatting.add(f'B7:B{detail.max_row}',FormulaRule(
            formula=[f'COUNTIF($B$7:$B${detail.max_row},B7)>1'],fill=PatternFill('solid',fgColor='FFC7CE')))
    table(wb,'RESERVAS','Bloqueos que consumen ambiente',
        ['id','ambiente','dia','inicio','fin','duracion_h','curso'],
        [[e[k] for k in ('id','ambiente','dia','inicio','fin','duracion_h','curso')] for e in reserved])
    checks = [('Solver',outcome['estado']),('Validador independiente',report['estado']),
        ('Conflictos HARD no exceptuados',rule_issues),('Actividades cubiertas',report['n_actividades']),
        ('Horas cubiertas',report['horas_programadas']),('Certificación completa H01–H22',report['certificacion_completa_H01_H22']),
        ('Aprobación institucional','NO OTORGADA POR EL MOTOR')]
    checks += [(k,v['estado']) for k,v in report['reglas'].items()]
    checks += [(r['regla'],r['detalle']) for r in report['limitaciones']]
    checks += [('Excepción autorizada',json.dumps(e,ensure_ascii=False)) for e in report.get('excepciones_aplicadas',[])]
    checks += [('Incidencia de fuente',json.dumps(e,ensure_ascii=False))
        for e in instance.raw.get('auditoria_repeticiones_fijas',{}).get('incidencias_fuente',[])]
    checks += [('Solape físico conservado',json.dumps(e,ensure_ascii=False)) for e in physical]
    checks += [('Denominador temporal',usage[0]['denominador'] if usage else 'No hay ambientes'),
        ('Apertura física','El calendario del modelo no acredita horarios de apertura del edificio.'),
        ('Subgrupos LAB','Filas paralelas son subgrupos distintos; no representan asistencia de toda la sección.')]
    v = table(wb,'VALIDACION','Validación y alcance',['Comprobación','Resultado'],checks)
    v.column_dimensions['A'].width=44;v.column_dimensions['B'].width=110
    for row in v.iter_rows(min_row=7):
        v.row_dimensions[row[0].row].height=max(38,math.ceil(len(str(row[1].value))/100)*14)
    manifest = {'schema_version':'1.0','sesiones':len(events),'horas':report['horas_programadas'],
        'vistas':positions,'utilizacion':usage,'solapes_fisicos':physical,'hojas':wb.sheetnames,
        'aprobado':False,'validacion':report['estado']}
    path.parent.mkdir(parents=True,exist_ok=True)
    pending=path.with_suffix('.xlsx.pending')
    if pending.exists(): raise ValueError('Exportación incompleta previa: conservar y usar otra salida')
    canonical_save(wb,pending,date.isoformat()+'Z')
    # openpyxl comprueba extensión; usar stream para verificar el archivo provisional.
    with pending.open('rb') as stream:
        verify_workbook(instance,outcome,stream,manifest)
    pending.rename(path)
    return manifest


def verify_workbook(instance,outcome,path,manifest):
    """Reabre el archivo final y contrasta filas y celdas contra la solución."""
    expected,_ = validated_events(instance,outcome)
    wb = load_workbook(path,data_only=False)
    detail = list(wb['SESIONES'].iter_rows(min_row=7,values_only=True))
    if len(detail)!=len(expected): raise ValueError('Excel omitió o duplicó sesiones')
    for values,e in zip(detail,expected):
        for key,value in zip(FIELDS,values):
            target = json.dumps(e[key],ensure_ascii=False) if key=='actividad_ids' else e[key]
            if value != (target if target!='' else None):
                raise ValueError('Excel alteró '+e['id']+' / '+key)
    by_id = {e['id']:e for e in expected+reservations(instance)}
    occupied = set()
    counts = defaultdict(int)
    for p in manifest['vistas']:
        e = by_id[p['id']];ws=wb[p['sheet']]
        if p['hours']!=list(range(e['inicio'],e['fin'])): raise ValueError('Horas de vista alteradas')
        counts[e['id']]+=1
        for row in p['rows']:
            key=(ws.title,row,p['column'])
            if key in occupied: raise ValueError('Dos sesiones sobrescriben una celda')
            occupied.add(key)
            cell = ws.cell(row,p['column'])
            if cell.value is None:
                merged = next((r for r in ws.merged_cells.ranges if cell.coordinate in r),None)
                if merged: cell=ws.cell(merged.min_row,merged.min_col)
            if not cell.value or e['ref'] not in cell.value:
                raise ValueError('Sesión ausente de la cuadrícula: '+e['id'])
    for e in expected:
        if counts[e['id']] != 1+bool(e['ambiente_id']): raise ValueError('Vistas incompletas de '+e['id'])
    for e in reservations(instance):
        if counts[e['id']] != 1: raise ValueError('Reserva ausente de vista de ambiente')
    expected_usage=utilization(instance,expected,reservations(instance))
    actual_usage=list(wb['UTILIZACION_AMBIENTES'].iter_rows(min_row=7,values_only=True))
    if len(actual_usage)!=len(expected_usage): raise ValueError('Ambientes omitidos en utilización')
    for values,row in zip(actual_usage,expected_usage):
        for key,value in zip(UTIL_FIELDS,values):
            target=row[key] if row[key] is not None else 'No disponible'
            if isinstance(target,float) and isinstance(value,(int,float)):
                match=math.isclose(target,value,abs_tol=1e-12)
            else: match=target==value
            if not match: raise ValueError('Utilización alterada: '+row['ambiente_id']+' / '+key)
    actual_reserved=list(wb['RESERVAS'].iter_rows(min_row=7,values_only=True))
    expected_reserved=[tuple(e[k] for k in ('id','ambiente','dia','inicio','fin','duracion_h','curso'))
        for e in reservations(instance)]
    if actual_reserved!=expected_reserved: raise ValueError('Reservas alteradas en XLSX')
    wb.close()
    return {'estado':'VALIDO','sesiones':len(expected),'horas':sum(e['duracion_h'] for e in expected)}
