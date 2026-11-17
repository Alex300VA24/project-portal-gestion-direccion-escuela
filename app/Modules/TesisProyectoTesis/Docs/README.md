# Documentación · Tesis y Proyecto de Tesis

Módulo del portal de la Escuela de Ingeniería Informática que gestiona el proceso de titulación en dos etapas: **Proyecto de tesis (F1)** y **Tesis (F2)**. Abarca desde el registro del proyecto hasta el acta de sustentación.

| Manual | Para quién | Contenido |
|---|---|---|
| [Manual de usuario](MANUAL_USUARIO.md) | Estudiantes, asesores, Director, Secretaría y jurados | Qué puede hacer cada rol, paso a paso y con capturas; qué significa cada estado; mensajes frecuentes; glosario |
| [Manual de flujos](MANUAL_FLUJOS.md) | Quien prueba o revisa el módulo | 15 casos de prueba sobre los escenarios `PT-DEMO-01…11`, con cuentas, pasos, resultado esperado y la prueba automática que cubre cada caso |
| [Manual de arquitectura](MANUAL_ARQUITECTURA.md) | Desarrolladores y grupo de gestión | Capas, rutas y Policy, servicios, modelos, migraciones, contrato con `core`/`seguridad`, seguridad, diagrama de estados, seeders, pruebas, decisiones y limitaciones |

## Inicio rápido

```bash
docker compose up -d --build app
docker compose exec app php artisan module:migrate tesis-proyecto-tesis
docker compose exec -u www-data -e APP_ENV=local app php artisan db:seed --force \
  --class="App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder"
```

1. Entra en `http://localhost:8000/login` con cualquier cuenta demo, por ejemplo `director@portal.local`. La contraseña demo está en el [Manual de flujos](MANUAL_FLUJOS.md#preparación).
2. Abre `/tesis-proyecto-tesis`.

Las capturas de pantalla están en [`img/`](img/).
