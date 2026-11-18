#!/usr/bin/env python3
"""Run: IF_DEMO_MANIFEST=... IF_DEMO_PASSWORD=... python3 tests/Browser/investigacion_formativa.py.

Requires a running local application, a WebDriver, and a fresh demo dataset.
The script changes only demo data through the normal browser forms.

Use IF_BROWSER=chromium for a true 390 pixel viewport. Firefox clamps the
window to 500 pixels, so its narrow captures are reported as the measured
width instead of the requested one.
"""

import base64
import json
import os
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo


class Browser:
    def __init__(self):
        self.endpoint = os.environ.get("WEBDRIVER_URL", "http://127.0.0.1:4449")
        self.app = os.environ.get("IF_DEMO_URL", "http://127.0.0.1:8059").rstrip("/")
        if not self.app.startswith(("http://127.0.0.1:", "http://localhost:")):
            raise ValueError("Use a local disposable demo application.")
        self.manifest = json.loads(Path(os.environ["IF_DEMO_MANIFEST"]).read_text())
        self.password = os.environ["IF_DEMO_PASSWORD"]
        self.accounts = {row["nombre"]: row["email"] for row in self.manifest["cuentas"]}
        self.output = Path(os.environ["IF_BROWSER_OUTPUT"])
        self.output.mkdir(parents=True, exist_ok=False)
        self.prefix = ""
        self.checks = []
        self.viewports = []
        self.comentario_largo = (
            "Primera linea del comentario.\n"
            "Segunda linea con un token largo sin espacios: " + "X" * 120
        )
        self.kind = os.environ.get("IF_BROWSER", "chromium").lower()
        session = self.call("POST", "/session", {
            "capabilities": {"alwaysMatch": self.capabilities()},
        })
        self.prefix = "/session/" + session["sessionId"]
        self.version = session["capabilities"]["browserVersion"]

    def capabilities(self):
        if self.kind == "chromium":
            binary = os.environ.get("CHROMIUM_BINARY", "")
            options = {
                "args": [
                    "--headless=new", "--no-sandbox", "--disable-dev-shm-usage",
                    "--disable-gpu", "--hide-scrollbars", "--window-size=1280,950",
                ],
            }
            if binary:
                options["binary"] = binary
            return {
                "browserName": "chrome",
                "pageLoadStrategy": "eager",
                "timeouts": {"pageLoad": 20000, "script": 10000, "implicit": 0},
                "goog:chromeOptions": options,
            }
        if self.kind != "firefox":
            raise ValueError("IF_BROWSER must be chromium or firefox.")
        return {
            "browserName": "firefox",
            "pageLoadStrategy": "eager",
            "timeouts": {"pageLoad": 20000, "script": 10000, "implicit": 0},
            "moz:firefoxOptions": {
                "binary": os.environ.get("FIREFOX_BINARY", "/usr/bin/firefox"),
                "args": ["-headless", "--width=1280", "--height=950"],
            },
        }

    def call(self, method, path, data=None, timeout=40):
        request = urllib.request.Request(
            self.endpoint + self.prefix + path,
            data=None if data is None else json.dumps(data).encode(),
            headers={"Content-Type": "application/json"}, method=method,
        )
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                value = json.load(response)["value"]
        except urllib.error.HTTPError as error:
            raise RuntimeError(error.read().decode()) from error
        if isinstance(value, dict) and "error" in value:
            raise RuntimeError(value)
        return value

    def js(self, script, *args):
        return self.call("POST", "/execute/sync", {"script": script, "args": list(args)})

    def wait(self, condition):
        for _ in range(100):
            if condition():
                return
            time.sleep(0.1)
        raise AssertionError("Browser condition did not become true.")

    def visit(self, path):
        self.call("POST", "/url", {"url": self.app + path})
        self.wait(lambda: self.js("return document.readyState") != "loading")

    def section(self, workspace, screen, name="completa"):
        section_id = self.manifest["secciones"][name]
        self.visit(f"/investigacion-formativa/{workspace}/secciones/{section_id}/{screen}")

    def element(self, selector):
        return self.call("POST", "/element", {
            "using": "css selector", "value": selector,
        })["element-6066-11e4-a52e-4f735466cecf"]

    def fill(self, selector, value):
        element_id = self.element(selector)
        self.call("POST", f"/element/{element_id}/clear", {})
        self.call("POST", f"/element/{element_id}/value", {"text": value})

    def fill_multiline(self, selector, value):
        self.js(
            "const e = document.querySelector(arguments[0]);"
            " e.value = arguments[1];"
            " e.dispatchEvent(new Event('input', {bubbles:true}));",
            selector, value,
        )

    def click(self, selector):
        self.call("POST", f"/element/{self.element(selector)}/click", {})

    def button(self, label):
        element = self.js(
            "return [...document.querySelectorAll('main button')].find(e=>e.textContent.trim()===arguments[0])",
            label,
        )
        assert element, label
        self.call("POST", "/element/" + element["element-6066-11e4-a52e-4f735466cecf"] + "/click", {})

    def text(self):
        return self.js("return document.body.innerText")

    def check(self, name, condition):
        assert condition(), name
        self.checks.append(name)
        print("PASS", name, flush=True)

    def computed(self, selector, prop):
        return self.js(
            "const e = document.querySelector(arguments[0]);"
            " return e ? getComputedStyle(e).getPropertyValue(arguments[1]) : null;",
            selector, prop,
        )

    def set_viewport(self, width, height=950):
        """Set the viewport and return the measured inner width."""
        if self.kind == "chromium":
            self.call("POST", "/goog/cdp/execute", {
                "cmd": "Emulation.setDeviceMetricsOverride",
                "params": {"width": width, "height": height, "deviceScaleFactor": 1, "mobile": False},
            })
        self.call("POST", "/window/rect", {"width": width, "height": height})
        time.sleep(0.25)
        return int(self.js("return window.innerWidth"))

    def capture(self, name, widths=(1280, 390)):
        (self.output / f"{name}.html").write_text(self.call("GET", "/source"))
        for width in widths:
            actual = self.set_viewport(width)
            self.viewports.append({"captura": name, "requested": width, "actual": actual})
            self.check(f"{name}: viewport requested {width} actual {actual}", lambda: self.js(
                "return window.innerWidth") == width)
            self.check(f"{name}: no horizontal overflow at {actual}", lambda: self.js(
                "return document.documentElement.scrollWidth <= window.innerWidth+1",
            ))
            (self.output / f"{name}-{actual}.png").write_bytes(
                base64.b64decode(self.call("GET", "/screenshot")),
            )
        self.set_viewport(1280)

    def check_theme(self):
        """Confirm the theme and contrast controls change the rendered colors."""
        light = {
            "body": self.computed("body", "background-color"),
            "topbar": self.computed(".if-topbar", "background-color"),
            "avatar": self.computed(".if-avatar", "color"),
            "avatar_bg": self.computed(".if-avatar", "background-color"),
        }
        self.click('header.if-topbar button[aria-label="Cambiar tema de color"]')
        self.wait(lambda: self.computed("body", "background-color") != light["body"])
        dark = {
            "body": self.computed("body", "background-color"),
            "topbar": self.computed(".if-topbar", "background-color"),
            "avatar": self.computed(".if-avatar", "color"),
            "avatar_bg": self.computed(".if-avatar", "background-color"),
        }
        self.check("Theme toggle changes the body background", lambda: dark["body"] != light["body"])
        self.check("Theme toggle changes the topbar background", lambda: dark["topbar"] != light["topbar"])
        self.check("Dark theme changes the avatar foreground", lambda: dark["avatar"] != light["avatar"])
        self.check("Dark theme changes the avatar background", lambda: dark["avatar_bg"] != light["avatar_bg"])
        (self.output / "tema-oscuro.png").write_bytes(
            base64.b64decode(self.call("GET", "/screenshot")),
        )

        self.click(".if-user-trigger")
        self.wait(lambda: self.js("return !!document.querySelector('.if-menu-a11y')"))
        self.click(".if-menu-a11y")
        self.wait(lambda: self.js("return !!document.querySelector('button[aria-label=\"Alternar alto contraste\"]')"))
        self.click('button[aria-label="Alternar alto contraste"]')
        self.wait(lambda: self.js("return document.documentElement.dataset.contrast") == "alto")
        contrast = {
            "body": self.computed("body", "background-color"),
            "topbar": self.computed(".if-topbar", "background-color"),
            "avatar": self.computed(".if-avatar", "color"),
        }
        self.check("High contrast changes the body background", lambda: contrast["body"] != dark["body"])
        self.check("High contrast changes the avatar foreground", lambda: contrast["avatar"] != dark["avatar"])
        (self.output / "alto-contraste.png").write_bytes(
            base64.b64decode(self.call("GET", "/screenshot")),
        )
        self.click(".quality-reset")
        self.wait(lambda: self.js("return document.documentElement.dataset.contrast") == "normal")
        self.check("Preferences reset returns the light body background", lambda: self.computed(
            "body", "background-color") == light["body"])

    def check_brand(self):
        """Confirm the sidebar brand name and caption use separate lines."""
        self.check("Brand name and caption use separate lines", lambda: self.js("""
            const strong = document.querySelector('.if-brand strong');
            const small = document.querySelector('.if-brand small');
            if (!strong || !small) return false;
            const a = strong.getBoundingClientRect();
            const b = small.getBoundingClientRect();
            return getComputedStyle(strong).display === 'block'
                && getComputedStyle(small).display === 'block'
                && b.top >= a.bottom - 1;
        """))

    def check_comment_layout(self):
        """Confirm the survey comment keeps its line break and wraps long tokens."""
        actual = self.set_viewport(390)
        self.viewports.append({"captura": "comentario-confirmacion", "requested": 390, "actual": actual})
        self.check(f"Comment layout: viewport requested 390 actual {actual}", lambda: self.js(
            "return window.innerWidth") == 390)
        self.check("Comment keeps its line break", lambda: self.js("""
            const q = document.querySelector('.if-quote');
            if (!q) return false;
            const line = parseFloat(getComputedStyle(q).lineHeight) || 20;
            return getComputedStyle(q).whiteSpace === 'pre-wrap' && q.clientHeight >= line * 1.8;
        """))
        self.check("Long comment token stays inside the block", lambda: self.js("""
            const q = document.querySelector('.if-quote');
            return !!q && q.scrollWidth <= q.clientWidth + 1;
        """))
        self.check("Comment page has no horizontal overflow", lambda: self.js(
            "return document.documentElement.scrollWidth <= window.innerWidth+1",
        ))
        (self.output / "comentario-confirmacion-390.png").write_bytes(
            base64.b64decode(self.call("GET", "/screenshot")),
        )
        self.set_viewport(1280)

    def check_rubric_criterion(self):
        """Confirm the rubric editor adds and removes styled criteria."""
        before = self.js("return document.querySelectorAll('#criterios [data-criterio]').length")
        self.click("#agregar-criterio")
        self.wait(lambda: self.js(
            "return document.querySelectorAll('#criterios [data-criterio]').length") == before + 1)
        self.check("Rubric adds a criterion with styled fields", lambda: self.js("""
            const fieldsets = document.querySelectorAll('#criterios [data-criterio]');
            const last = fieldsets[fieldsets.length - 1];
            if (!last) return false;
            const area = last.querySelector('textarea');
            const score = last.querySelector('input[type=number]');
            if (!area || !score) return false;
            return !!area.closest('.if-field') && !!score.closest('.if-field')
                && area.getBoundingClientRect().width > 0
                && score.getBoundingClientRect().width > 0;
        """))
        self.js("document.querySelector('#criterios [data-criterio]:last-child [data-quitar-criterio]').click()")
        self.wait(lambda: self.js(
            "return document.querySelectorAll('#criterios [data-criterio]').length") == before)
        self.check("Rubric removes the added criterion", lambda: self.js(
            "return document.querySelectorAll('#criterios [data-criterio]').length") == before)

    def login(self, name):
        self.call("DELETE", "/cookie")
        self.visit("/login")
        self.fill("#email", self.accounts[name])
        self.fill("#password", self.password)
        self.click("button.auth-submit")
        self.wait(lambda: "/login" not in self.call("GET", "/url"))
        self.visit("/investigacion-formativa")
        self.check(f"{name}: login", lambda: "Investigación Formativa" in self.text())

    def logout(self):
        self.js("document.querySelector('form[action$=\"/logout\"]').requestSubmit()")
        self.wait(lambda: self.call("GET", "/url") == self.app + "/")
        self.visit("/login")

    def configure_section(self, name="incompleta"):
        self.section("docente", "competencias", name)
        self.click('input[name="competencia_ids[]"]')
        self.button("Guardar competencias")
        self.wait(lambda: "Competencias guardadas." in self.text())
        self.section("docente", "competencias", name)
        self.check("Competency selection persists", lambda: self.js(
            'return document.querySelector(\'input[name="competencia_ids[]"]\').checked',
        ))
        self.capture("competencias-guardadas-" + name)

        self.section("docente", "rubrica", name)
        self.fill("#rubrica-nombre", "Rúbrica creada en navegador")
        self.fill("#criterio-0-descripcion", "Justificación del problema")
        for position in range(4):
            self.fill(f"#nivel-0-{position}-descripcion", f"Descripción del nivel {position + 1}")
            self.fill(f"#nivel-0-{position}-puntaje", str(position + 1))
        self.button("Guardar rúbrica")
        self.wait(lambda: "Rúbrica guardada." in self.text())
        self.section("docente", "rubrica", name)
        self.check("Rubric persists", lambda: self.js(
            'return document.getElementById("rubrica-nombre").value',
        ) == "Rúbrica creada en navegador")
        self.check_rubric_criterion()
        self.button("Guardar rúbrica")
        self.wait(lambda: "Rúbrica guardada." in self.text())
        self.section("docente", "rubrica", name)
        self.check("Rubric keeps one criterion after add and remove", lambda: self.js(
            'return document.querySelectorAll("#criterios [data-criterio]").length',
        ) == 1)
        self.capture("rubrica-guardada-" + name)

        self.section("docente", "cronograma", name)
        now = datetime.now(ZoneInfo("America/Lima"))
        dates = [(now + timedelta(days=days)).strftime("%Y-%m-%dT12:00") for days in (30, 60, 90)]
        set_dates = """
            for (let i = 0; i < arguments[0].length; i++) {
                const input = document.getElementById('fecha-' + i);
                input.value = arguments[0][i];
                input.dispatchEvent(new Event('input', {bubbles:true}));
                input.dispatchEvent(new Event('change', {bubbles:true}));
            }
        """
        self.js(set_dates, list(reversed(dates)))
        self.button("Guardar cronograma")
        self.wait(lambda: "Las fechas deben respetar el orden" in self.text())
        self.check("Schedule rejects reversed dates", lambda: "Las fechas deben respetar el orden" in self.text())
        self.js(set_dates, dates)
        self.button("Guardar cronograma")
        self.wait(lambda: "Cronograma guardado." in self.text())
        self.section("docente", "cronograma", name)
        self.check("Schedule preserves Lima input", lambda: self.js(
            'return [0,1,2].map(i=>document.getElementById("fecha-"+i).value)',
        ) == dates)
        self.check("Configuration is complete", lambda: "La configuración de la sección está completa." in self.text())
        self.capture("cronograma-guardado-" + name)
        self.logout()
        self.login("docente-a")
        self.section("docente", "rubrica", name)
        self.check("Configuration persists after login", lambda: self.js(
            'return document.getElementById("rubrica-nombre").value',
        ) == "Rúbrica creada en navegador")

    def toggle_section(self):
        self.visit("/investigacion-formativa/director/cursos")
        section_id = self.manifest["secciones"]["deshabilitada"]
        selector = f'form[action$="/{section_id}/habilitacion"]'
        initial = self.js(
            'return document.querySelector(arguments[0]).querySelector("input[name=habilitada]").value',
            selector,
        )
        opposite = "0" if initial == "1" else "1"
        for before, after in ((initial, opposite), (opposite, initial)):
            self.check("Section toggle initial state " + before, lambda: self.js(
                'return document.querySelector(arguments[0]).querySelector("input[name=habilitada]").value',
                selector,
            ) == before)
            self.click(selector + " button")
            self.wait(lambda: self.js(
                'return document.querySelector(arguments[0]).querySelector("input[name=habilitada]").value',
                selector,
            ) == after)
            self.visit("/investigacion-formativa/director")
            self.visit("/investigacion-formativa/director/cursos")
            self.check("Section toggle persists " + after, lambda: self.js(
                'return document.querySelector(arguments[0]).querySelector("input[name=habilitada]").value',
                selector,
            ) == after)

    def prepare_presentation(self):
        self.login("director-a")
        self.visit("/investigacion-formativa/director/cursos")
        section_id = self.manifest["secciones"]["deshabilitada"]
        selector = f'form[action$="/{section_id}/habilitacion"]'
        self.check("Presentation starts with disabled section", lambda: self.js(
            'return document.querySelector(arguments[0]).querySelector("input[name=habilitada]").value',
            selector,
        ) == "1")
        self.click(selector + " button")
        self.wait(lambda: self.js(
            'return document.querySelector(arguments[0]).querySelector("input[name=habilitada]").value',
            selector,
        ) == "0")
        self.check("Director enables presentation section", lambda: "quedó habilitada" in self.text())
        self.login("docente-a")
        self.configure_section("deshabilitada")
        self.manifest["secciones"]["completa"] = section_id

    def current_title(self):
        return self.js(
            'return document.querySelector("[data-titulo-propuesto]").textContent.trim()',
        )

    def check_secretary(self):
        """Confirm the secretary workspace lists the assigned sections."""
        self.login("secretaria-a")
        self.visit("/investigacion-formativa/secretaria")
        self.check("Secretary workspace lists the assigned school", lambda: "Escuelas asignadas" in self.text())
        self.check("Secretary workspace lists the sections", lambda: "Secciones habilitadas" in self.text())
        self.capture("secretaria-secciones")
        section_id = self.manifest["secciones"]["completa"]
        self.visit(f"/investigacion-formativa/secretaria/secciones/{section_id}/encuesta")
        self.check("Secretary opens the survey workspace", lambda: "Encuesta de la sección" in self.text())
        self.check("Secretary cannot close the survey", lambda: "Cerrar encuesta" not in self.text())
        self.capture("secretaria-encuesta")

    def review_title(self, href):
        reason = "Delimitar el estudio a los procesos de matrícula."
        corrected = "Estudio de procesos de matrícula en la escuela"
        self.click('input[name=estado][value="Cambio solicitado"]')
        self.fill("#motivo-revision", reason)
        self.button("Registrar revisión")
        self.wait(lambda: "Cambio solicitado" in self.text() and reason in self.text())
        self.check("Teacher requests title correction with reason", lambda: reason in self.text())
        self.capture("docente-cambio-solicitado")

        self.login("integrante")
        self.section("alumno", "mi-grupo")
        self.check("Member sees correction request", lambda: "Cambio solicitado" in self.text() and reason in self.text())
        self.check("Member has no title editor", lambda: self.js(
            'return document.querySelector("#titulo-propuesto") === null && document.querySelector(\'main form[action$="/titulo"]\') === null',
        ))
        self.capture("integrante-solo-lectura")

        self.login("coordinador")
        self.section("alumno", "mi-grupo")
        self.check("Coordinator sees correction reason", lambda: reason in self.text())
        self.fill("#titulo-propuesto", corrected)
        self.button("Guardar título")
        self.wait(lambda: self.current_title() == corrected)
        self.check("Corrected title returns to registered state", lambda: self.js(
            'return document.querySelector("section[aria-labelledby=titulo-propuesta] > div span").textContent.includes("Registrado")',
        ))
        self.check("Correction keeps earlier feedback", lambda: reason in self.text() and "Estudio de procesos académicos" in self.text())
        self.capture("coordinador-titulo-corregido")
        self.logout()
        self.login("coordinador")
        self.section("alumno", "mi-grupo")
        self.check("Corrected title persists after login", lambda: self.current_title() == corrected)

        self.login("docente-a")
        self.visit(href.removeprefix(self.app))
        self.check("Teacher sees corrected current title", lambda: self.current_title() == corrected)
        self.click("input[name=estado][value=Aceptado]")
        self.button("Registrar revisión")
        self.wait(lambda: "Aceptado" in self.text())
        self.check("Teacher accepts corrected title", lambda: self.current_title() == corrected and "Aceptado" in self.text())
        self.capture("docente-correccion-aceptada")

        self.login("integrante")
        self.section("alumno", "mi-grupo")
        self.check("Member sees accepted corrected title", lambda: self.current_title() == corrected and "Aceptado" in self.text())
        self.check("Accepted title remains read-only for member", lambda: self.js(
            'return document.querySelector("#titulo-propuesto") === null && document.querySelector(\'main form[action$="/titulo"]\') === null',
        ))
        self.check("Member sees full title review history", lambda: reason in self.text() and "Estudio de procesos académicos" in self.text() and "Historial de revisiones" in self.text())
        self.capture("integrante-titulo-aceptado")

    def run(self):
        self.prepare_presentation()
        self.login("coordinador")
        self.check_brand()
        self.check_theme()
        self.capture("alumno-cursos")
        self.section("alumno", "grupo/crear")
        self.capture("grupo-crear")
        self.click("main button[type=submit]")
        self.check("Required group fields", lambda: self.js(
            'return document.querySelectorAll("main :invalid").length',
        ) > 0)
        self.fill("#grupo-nombre", "Grupo de demostración")
        self.fill("#grupo-titulo", "Estudio de procesos académicos")
        self.js("""
            const label = [...document.querySelectorAll('main label')]
                .find(e=>e.textContent.includes('Demo integrante'));
            label.querySelector('input').click();
            for (const id of ['grupo-linea', 'grupo-tipo-producto']) {
                const select = document.getElementById(id);
                select.selectedIndex = 1;
                select.dispatchEvent(new Event('change', {bubbles:true}));
            }
        """)
        self.click("main button[type=submit]")
        self.wait(lambda: "mi-grupo" in self.call("GET", "/url"))
        self.check("Group creation", lambda: "Grupo de demostración" in self.text())
        self.capture("alumno-grupo")
        self.logout()
        self.login("coordinador")
        self.section("alumno", "mi-grupo")
        self.check("Group persists after login", lambda: "Estudio de procesos académicos" in self.text())
        self.call("POST", "/refresh", {})
        self.check("Group persists after refresh", lambda: "Estudio de procesos académicos" in self.text())

        self.login("docente-a")
        self.capture("docente-cursos")
        self.configure_section()
        for screen in ("competencias", "rubrica", "cronograma"):
            self.section("docente", screen)
            self.capture("docente-" + screen)
        self.section("docente", "grupos")
        self.capture("docente-grupos")
        href = self.js("""
            return [...document.querySelectorAll('main a')]
                .map(e=>e.getAttribute('href')).find(s=>/grupos\\/\\d+$/.test(s));
        """)
        assert href
        self.visit(href.removeprefix(self.app))
        self.capture("docente-grupo")
        self.review_title(href)

        self.login("director-a")
        self.capture("director-resumen")
        self.visit("/investigacion-formativa/director/cursos")
        self.capture("director-cursos")
        self.toggle_section()
        self.section("director", "encuesta")
        self.capture("director-encuesta-vacia")
        self.fill('textarea[name="preguntas[0]"]', "¿El trabajo fortalece la investigación?")
        self.button("Agregar pregunta")
        self.fill('textarea[name="preguntas[1]"]', "¿La orientación fue clara?")
        self.button("Guardar borrador")
        self.wait(lambda: "Publicar encuesta" in self.text())
        self.capture("director-encuesta-borrador")
        self.button("Publicar encuesta")
        self.wait(lambda: "Cerrar encuesta" in self.text())
        self.capture("director-encuesta-publicada")
        self.check("Published survey", lambda: "¿La orientación fue clara?" in self.text())

        self.login("integrante")
        self.section("alumno", "mi-grupo")
        self.check("Member reads reviewed title", lambda: "Aceptado" in self.text())
        self.section("alumno", "encuesta")
        self.capture("alumno-encuesta")
        self.js("""
            for (const name of ['respuestas[0][valor]', 'respuestas[1][valor]']) {
                document.querySelector('input[name="'+name+'"][value="5"]').click();
            }
        """)
        self.fill_multiline("textarea[name=comentario]", self.comentario_largo)
        self.click("main button[type=submit]")
        self.wait(lambda: "Respuesta registrada" in self.text())
        self.capture("alumno-encuesta-confirmada")
        self.logout()
        self.login("integrante")
        self.section("alumno", "encuesta")
        self.check("Survey persists after login", lambda: "Respuesta registrada" in self.text())

        self.login("director-a")
        self.section("director", "resultados")
        self.capture("director-resultados")
        self.check("Survey report", lambda: "5.00" in self.text() and "Comentarios confidenciales" in self.text())
        self.check("Report omits respondent name", lambda: "Demo integrante" not in self.text())
        self.check_comment_layout()
        self.section("director", "encuesta")
        self.button("Cerrar encuesta")
        self.wait(lambda: "La encuesta se cerró" in self.text())
        self.section("director", "encuesta")
        self.check("Survey remains closed", lambda: "Cerrar encuesta" not in self.text() and "Publicar encuesta" not in self.text())
        self.capture("director-encuesta-cerrada")
        self.login("integrante")
        self.section("alumno", "encuesta")
        self.check("Saved response survives closure", lambda: "Respuesta registrada" in self.text())
        self.login("director-a")
        self.js("""
            const select = document.getElementById('filtro-periodo');
            select.selectedIndex = select.options.length - 1;
            select.dispatchEvent(new Event('change', {bubbles:true}));
        """)
        self.button("Filtrar")
        self.wait(lambda: "periodo=" in self.call("GET", "/url"))
        self.check("History period filter", lambda: "IFD-historica" in self.text() and "IFD-completa" not in self.text())
        self.capture("director-historial-filtrado")
        self.section("director", "resultados", "historica")
        self.capture("director-historial")
        self.check("Closed history", lambda: "Cerrada" in self.text())
        self.check_secretary()
        self.login("multirrol")
        self.capture("selector-multirrol")
        self.check("Workspace selector", lambda: "Docencia" in self.text() and "Estudiante" in self.text())


if __name__ == "__main__":
    browser = Browser()
    try:
        browser.run()
        result = {
            "browser": browser.kind.capitalize() + " " + browser.version,
            "passed": len(browser.checks),
            "checks": browser.checks,
            "viewports": browser.viewports,
            "upload_download": "BLOCKED G4",
            "evaluation_browser": "BLOCKED G4: no production delivery writer",
        }
        (browser.output / "results.json").write_text(json.dumps(result, ensure_ascii=False, indent=2))
    except Exception:
        try:
            (browser.output / "failure.html").write_text(browser.call("GET", "/source", timeout=5))
            (browser.output / "failure.png").write_bytes(base64.b64decode(browser.call("GET", "/screenshot", timeout=5)))
        except (OSError, RuntimeError) as capture_error:
            print("Could not capture browser failure:", capture_error, file=sys.stderr)
        raise
    finally:
        failed = sys.exc_info()[0] is not None
        try:
            browser.call("DELETE", "", None, timeout=5)
        except (OSError, RuntimeError) as close_error:
            if not failed:
                raise
            print("Could not close failed browser session:", close_error, file=sys.stderr)
