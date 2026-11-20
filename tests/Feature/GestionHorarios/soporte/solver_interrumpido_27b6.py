"""Solver real aislado: pausa observable dentro de un callback de solución."""
import json
import os
import sys
import time
from ortools.sat.python import cp_model

marca, token = sys.argv[1:3]
modelo = cp_model.CpModel()
x = modelo.new_bool_var("x")
modelo.maximize(x)


class Observador(cp_model.CpSolverSolutionCallback):
    def on_solution_callback(self):
        with open(marca, "x", encoding="utf-8") as archivo:
            json.dump({"pid": os.getpid(), "token": token, "fase": "callback_cp_sat"}, archivo)
        time.sleep(20)
        self.stop_search()


solver = cp_model.CpSolver()
solver.parameters.max_time_in_seconds = 30
solver.parameters.num_search_workers = 1
solver.solve(modelo, Observador())
