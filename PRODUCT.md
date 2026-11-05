# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Director de escuela, secretaría, profesor y alumno de una escuela profesional universitaria.

## Product Purpose

Centraliza el acceso a módulos de gestión académica e institucional. El módulo Calidad permite consultar procesos, indicadores, evidencias y planes de mejora desde la base compartida del portal.

## Operating Context

El acceso público empieza en la web institucional, que funciona como superficie de comunicación y entrada, no como módulo. Tras autenticarse, cada persona elige un módulo desde el portal. Calidad usa rutas propias bajo `/calidad`.

## Capabilities and Constraints

- Laravel 13, Blade, Alpine.js y Tailwind CSS.
- PostgreSQL organizado por esquemas y módulos.
- Calidad debe leer las tablas existentes en `calidad`, `core`, `seguridad` y `comun`.
- No se migran las tablas ni los datos de `projectCalidad`.
- El diseño de `projectCalidad` es la referencia visual y funcional.
- Rutas principales: `/web-institucional`, `/calidad/home` y `/calidad/mapa-procesos`.

## Brand Commitments

Voz institucional clara. Identidad SIGI/Portal de Gestión con azul marino, verde de acción, Fira Sans y códigos en Fira Code.

## Evidence on Hand

- Proyecto de referencia: `../projectCalidad`.
- Guía visual: `../projectCalidad/rediseño/DISENO.md`.
- Datos reales: esquemas actuales del portal; no se fabrican métricas.

## Product Principles

- Un acceso, varios módulos.
- Los datos compartidos mantienen una sola fuente de verdad.
- Cada ruta debe ser profunda, predecible y accesible.
- Un estado vacío explica la ausencia de datos sin simular resultados.

## Accessibility & Inclusion

Contraste AA, navegación por teclado, foco visible, controles de al menos 44 px y estados expresados con texto además de color.
