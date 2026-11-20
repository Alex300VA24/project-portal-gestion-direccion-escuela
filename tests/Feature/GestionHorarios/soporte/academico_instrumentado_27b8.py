"""Sincroniza la primera solución del modelo académico real, solo para pruebas."""
import json
import os
import runpy
import sys
import time

marca, token, raiz = sys.argv[1:4]
sys.path.insert(0, raiz)
from ortools.sat.python import cp_model

original = cp_model.CpSolver.solve
observado = False


class Observador(cp_model.CpSolverSolutionCallback):
    def on_solution_callback(self):
        temporal = marca + ".tmp"
        with open(temporal, "x", encoding="utf-8") as archivo:
            json.dump({"pid": os.getpid(), "padre": os.getppid(), "token": token, "fase": "callback_modelo_academico"}, archivo)
        os.replace(temporal, marca)
        time.sleep(20)


def solve(self, modelo, solution_callback=None):
    global observado
    if not observado and solution_callback is None:
        observado = True
        return original(self, modelo, Observador())
    return original(self, modelo, solution_callback)


cp_model.CpSolver.solve = solve
sys.argv = ["scripts.motor_aplicacion"]
runpy.run_module("scripts.motor_aplicacion", run_name="__main__")
