"""Días regulares y sábado excepcional de repetición con disponibilidad explícita."""
POLICY = 'REPETICION_SABADO_DECLARADO_V1'
WEEK = ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO')


def saturday_declared(windows):
    return windows is not None and any(w['dia'] == 'SABADO' and w['inicio'] < w['fin'] for w in windows)


def allowed_day(cal, day, *, repetition=False, windows=None, cycle=None, label=None, policy=None):
    if day == 'SABADO' and repetition and policy == POLICY:
        return saturday_declared(windows)
    if day not in cal['dias']:
        return False
    if day != 'SABADO':
        return True
    if cal.get('sabados') == 'NINGUNO' or not cal['sabados_por_ciclo'].get(str(cycle), cal['sabados_por_ciclo'].get(cycle, False)):
        return False
    if cal.get('sabados') == 'SELECCIONADOS':
        return any(x['ciclo'] == cycle and (not x['secciones'] or label in x['secciones']) for x in cal.get('sabados_alcances', []))
    return True


def activity_days(cal, *, repetition=False, windows=None, policy=None):
    days = set(cal['dias'])
    if policy == POLICY and repetition and saturday_declared(windows):
        days.add('SABADO')
    return [day for day in WEEK if day in days]


def planning_days(instance):
    """Eje interno para objetivos/exportadores; no cambia el calendario del periodo."""
    days = set(instance.raw['calendario']['dias'])
    if instance.raw.get('politica_sabado_repeticion') == POLICY:
        days |= {c.dia for c in instance.placements}
    return [d for d in WEEK if d in days]
