"""Compara la misma demostración privada con dos instalaciones del motor."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import sys


def entrada(archivo):
    fuente = json.loads(archivo.read_text(encoding="utf-8"))
    if (fuente["origen"], fuente["destino"]) != ("2013-II", "2026-II"):
        raise ValueError("La fuente debe ser la demostración derivada de 2013-II")
    f = fuente["filas"]

    def filas(tabla, campos=()):
        return [{**r, **{k: json.loads(r[k]) for k in campos if isinstance(r[k], str)}}
                for r in f[tabla]]

    origen = json.loads(f["instantaneas_prematricula"][0]["datos"])
    cfg = json.loads(f["propuestas_horarios"][0]["configuracion"])
    cfg["calendario"] = json.loads(f["periodos_academicos"][0]["calendario"])
    cfg["politica_sabado_repeticion"] = "REPETICION_SABADO_DECLARADO_V1"
    grupos = {g["id"]: g for g in f["grupos_academicos"]}
    secciones = [s for s in filas("secciones_propuestas", ("estudiantes", "horas")) if s["estado"] != "CANCELADA"]
    cursos = {s["id"]: s["curso_id"] for s in secciones}
    for s in secciones:
        s["es_grupo_asesoria"] |= s["nombre"].strip().upper() in {
            "TESIS", "TESIS I", "TESIS II", "PROYECTO DE TESIS", "PROYECTO DE TESIS I", "PROYECTO DE TESIS II"}
    actividades = [a for a in filas("actividades_programables", ("estudiantes", "duraciones", "franjas_origen")) if a["activa"]]
    for a in actividades:
        g = grupos.get(a["grupo_id"], {})
        a.update(particion_acreditada=g.get("particion_acreditada"), etiqueta_grupo=g.get("etiqueta"),
                 tipo_grupo=g.get("tipo"), ambiente_a_decision_docente=not a["requiere_ambiente"]
                 and cursos[a["seccion_id"]] in cfg["ambientes_a_decision_docente"])
    return dict(contrato="HORARIOS_MOTOR_V1", periodo=fuente["destino"], configuracion=cfg,
                docentes=origen["docentes"], metadatos=origen["metadatos"],
                version_fuente_vigente=f["fuentes_locales"][0]["version"],
                secciones=secciones, actividades=actividades, ambientes=filas("ambientes", ("equipamiento",)),
                disponibilidades=filas("disponibilidades_docentes", ("franjas",)),
                bloqueos=[b for b in filas("bloqueos_institucionales", ("datos",)) if b["activo"]],
                sesiones=f["sesiones_programadas"], ventanas_origen=f["ventanas_disponibilidad_origen"])


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("original", type=Path, help="Directorio laravel de referencia")
    parser.add_argument("--segundos", type=int, default=30)
    args = parser.parse_args()
    datos = entrada(args.original / "database/seeders/datos/demo_2026_ii/origen_2013_ii.json")
    portal = Path(__file__).resolve().parents[4] / "app/Modules/GestionHorarios/Engine/python"
    for nombre, directorio in (("original", args.original / "python"), ("portal", portal)):
        for op in ("COMPROBAR_PREPARACION", "VALIDAR_ENTRADA", "REVISAR_PROPUESTA", "VALIDAR_HORARIO", "GENERAR_HORARIO"):
            solicitud = dict(datos, operacion=op, completa=True, tiempo_limite=args.segundos,
                             alcance=[], priorizar_factibilidad=True)
            result = subprocess.run([sys.executable, "-B", "-m", "scripts.motor_aplicacion"],
                                    cwd=directorio, env={**os.environ, "PYTHONPATH": str(directorio)},
                                    input=json.dumps(solicitud), text=True, capture_output=True,
                                    timeout=args.segundos + 180, check=True)
            r = json.loads(result.stdout)
            v = r.get("validacion_operativa", {})
            hard = r.get("validacion_historica", {})
            print(nombre, op, json.dumps(dict(estado=r.get("estado"), valida=v.get("valida"),
                  hard=hard.get("estado"), certificacion=hard.get("certificacion_completa_H01_H22"),
                  candidatas=r.get("candidatas"), bloqueadas=r.get("bloqueadas"), advertencias=r.get("advertencias") if op == "COMPROBAR_PREPARACION" else None,
                  sesiones=v.get("sesiones_programadas"), esperadas=v.get("sesiones_esperadas"),
                  errores=len(v.get("errores", [])), busqueda_s=r.get("tiempo_busqueda_s"),
                  objetivo=r.get("valor_objetivo"), cota=r.get("mejor_cota"),
                  diagnosticos=[d.get("categoria") for d in r.get("diagnosticos", [])])), flush=True)
            if op == "GENERAR_HORARIO" and r.get("estado") in {"OPTIMAL", "FEASIBLE"}:
                if not (hard.get("cumple_reglas_con_datos_disponibles") and v.get("valida")
                        and v.get("sesiones_programadas") == v.get("sesiones_esperadas") == 175):
                    raise SystemExit("La solución del motor incumple el control HARD o el operativo.")
            if op == "VALIDAR_HORARIO" and not (v.get("valida") and v.get("sesiones_programadas") == 175):
                raise SystemExit("La validación completa de las 175 sesiones falló.")
