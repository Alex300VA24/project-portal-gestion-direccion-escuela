# Revisión visual pendiente en Windows

La automatización de navegador intentada durante esta revisión no llegó a abrir una pestaña: la inicialización devolvió `e.nodeRepl?.setResponseMeta is not a function`. No se verificaron visualmente las pantallas.

Para repetir la comprobación con una instalación aislada:

1. Prepare una base PostgreSQL desechable y una copia de configuración de Laravel que apunte exclusivamente a ella. Verifique host, puerto, base y usuario antes de migrar o insertar datos. No utilice `portal_gestion_direccion` ni el worker compartido.
2. Prepare un usuario y datos ficticios en esa base. La navegación administrativa requiere además una implementación del contrato de escuelas cargada **solo en ese entorno de pruebas**; el repositorio no proporciona un proveedor institucional aprobado. Sin esa vinculación, debe aparecer el mensaje de acceso restringido y no se podrá revisar el resumen autorizado.
3. Inicie únicamente el servidor de la copia de pruebas, por ejemplo con `php artisan serve --host=127.0.0.1 --port=8016` desde un proceso cuya configuración y conexión aisladas ya se hayan verificado. Abra `http://127.0.0.1:8016/gestion-horarios`.
4. Compruebe la portada sin adscripción y, con la identidad ficticia autorizada, oferta, periodo, plan, **Comprobar preparación parcial**, enlace a secciones y revisiones. Cambie filtros y selecciones; pruebe con una segunda escuela y una revocación. La asignación docente, la disponibilidad administrativa y la generación deben continuar bloqueadas.
5. Repita a 1440×900 (escritorio), 768×1024 (tableta) y 375×812 (móvil) usando las herramientas de desarrollo del navegador. En portada, revise acceso con y sin adscripción; en oferta, seleccione plan y periodo, busque, filtre y ejecute el resumen; siga el enlace a secciones y compruebe que conserva la selección. Revise revisiones y la consulta pública de metadatos. Verifique teclado y foco, etiquetas, tablas desplazables, mensajes, navegación atrás y actualización Livewire. Una respuesta HTTP 200 no sustituye esta revisión.
6. Detenga solo el servidor y los recursos desechables que se hayan iniciado para la revisión. No habilite proveedores ficticios en producción.

7. Con cuatro cuentas ficticias, compruebe el acceso temporal por rol: director y secretaría ven su ingreso administrativo confirmado sin escuela acreditada; profesor ve su espacio y el aviso de identidad docente pendiente; alumno no accede a `/gestion-horarios/mi-disponibilidad`. Verifique que no se exponen datos privados. Revise también la consulta semanal autenticada de actividades publicadas, sus errores y estados vacíos, teclado y diseño móvil.

Las pruebas automatizadas con datos ficticios se ejecutan por separado; no crean una sesión de navegador ni constituyen una revisión visual.
