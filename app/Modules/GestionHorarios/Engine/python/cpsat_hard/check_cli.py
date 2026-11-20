"""Una única validación estructural de entradas; no certifica factibilidad global."""
import argparse
from collections import Counter
from pathlib import Path
from .data import InputError,load
from .domains import violations
from .precheck import inspect_curriculum,limitations
from .fixed_repetitions import analyze
from .exporting import write_json
from .institutional_blocks import inspect as inspect_blocks
from .coverage import assess


def inspect(i,scenario='B'):
    errors=inspect_curriculum(i);valid_sessions=Counter();rejected=0
    fixed_mode=bool(i.raw.get('politica_repeticion_fija'))
    for c in i.placements:
        if fixed_mode and i.activities[c.actividad_id]['es_repeticion']: continue
        if violations(i,c,scenario): rejected+=1
        else: valid_sessions[c.session_id]+=1
    missing=[]
    for aid,a in i.activities.items():
        if fixed_mode and a['es_repeticion']: continue
        patterns=[p for p in i.patterns.values() if p['actividad_id']==aid]
        viable=any(all(valid_sessions[s['id']] for s in i.sessions.values() if s['patron_id']==p['id']) for p in patterns)
        if not viable: missing.append(aid)
    fixed=analyze(i) if fixed_mode else None
    conflicts=[p for p in fixed['incidencias'] if not p.get('exceptuada_por_politica')] if fixed else []
    ok=not errors and not missing and not conflicts
    return {'estado':'INPUT_VALID' if ok else 'INPUT_INCONSISTENT','escenario':scenario,
        'errores_curriculares':errors,'actividades_sin_patron_viable':missing,
        'candidatas_rechazadas_por_dominio':rejected,'sesiones_fijas':fixed,
        'n_actividades':len(i.activities),'n_candidatas':len(i.placements),'fuentes_sha256':i.hashes,
        'limitaciones':limitations(i),'factibilidad_global':'NO_DEDUCIDA; ejecutar CP-SAT y validador independiente',
        'bloqueos_institucionales':inspect_blocks(i,scenario),'cobertura_institucional':assess(i)}


def main(argv=None):
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--instance',default='instancia_cp_sat_2026II.json')
    p.add_argument('--placements',default='colocaciones_candidatas.csv')
    p.add_argument('--policy');p.add_argument('--conflicts')
    p.add_argument('--institutional-blocks')
    p.add_argument('--scenario',choices=['A','B'],default='B');p.add_argument('--output')
    a=p.parse_args(argv)
    try: report=inspect(load(a.instance,a.placements,a.conflicts,a.policy,a.institutional_blocks),a.scenario)
    except (InputError,OSError,ValueError,KeyError,TypeError) as exc:
        report={'estado':'INPUT_INVALID','detalle':str(exc)}
    if a.output:
        Path(a.output).parent.mkdir(parents=True,exist_ok=True);write_json(a.output,report)
    print(f"{report['estado']}: actividades={report.get('n_actividades','?')} candidatas={report.get('n_candidatas','?')}")
    return 0 if report['estado']=='INPUT_VALID' else 2


if __name__=='__main__': raise SystemExit(main())
