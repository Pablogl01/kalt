# WORKFLOW.md — Flujo de trabajo KALT (Claude Code / OpenCode)

Antes de aplicar este flujo, consulta siempre `AGENTS.md` (contexto del proyecto), `DESIGN.md` (sistema de diseño) y `KALT_Manuscrito.md` (diseño técnico).

Este documento define el flujo de trabajo estándar para el desarrollo de KALT con asistencia de IA. Debe seguirse para cada ticket/feature.

## 1. Inicio del flujo — Ticket en Markdown

El programador crea un archivo `.md` con la solicitud de la feature/cambio.

La IA debe:
- Analizar el contenido del ticket.
- Identificar alcance, archivos afectados y dependencias relevantes del proyecto (consultar `CLAUDE.md` y `DESIGN.md` si aplica).
- Generar un **plan de implementación** claro, dividido en pasos.

## 2. Aprobación del plan

El plan se presenta al programador, que tiene dos opciones:
- **Aprobar** el plan tal cual.
- **Modificar** el plan (ajustes, cambios de enfoque, pasos adicionales/eliminados).

> No existe la opción de "rechazar". Si el plan no convence, se modifica hasta llegar a una versión aprobable.

Una vez aprobado (con o sin modificaciones), se procede a la implementación.

## 3. Implementación

La IA implementa el plan aprobado paso a paso.

Al finalizar, la IA debe entregar al programador:
- Resumen de los cambios realizados.
- **Listado de tests a realizar**, separando:
  - Tests automáticos (Pest) que la IA debe ejecutar y reportar resultado.
  - Tests manuales concretos que el programador debe ejecutar sobre la nueva funcionalidad (pasos específicos, no genéricos).

## 4. Validación de tests

- El programador ejecuta los tests manuales indicados.
- La IA ejecuta y reporta los tests Pest.
- Si algún test falla → se corrige y se repite el paso 3-4 para lo afectado.
- Si todos los tests pasan → se avanza al paso 5.

## 5. Revisión técnica (vulnerabilidades, duplicidad, optimizaciones)

**Por defecto, este paso es ligero**: la IA hace un repaso rápido del código implementado y solo señala:
- Problemas de seguridad evidentes (validación de inputs, IDOR, exposición de datos sensibles).
- Duplicidad clara y directa con código existente.
- Optimizaciones obvias de bajo coste.

**Análisis profundo** (revisión exhaustiva de vulnerabilidades, refactors, optimizaciones de rendimiento) solo se realiza si:
- El ticket toca código sensible (autenticación, datos de usuario, motor de cálculo/lógica crítica de negocio).
- El programador lo solicita explícitamente.

Los hallazgos (ligeros o profundos) se presentan como una lista de cambios propuestos.

## 6. Iteración sobre cambios propuestos

- El programador acepta 0, algunos o todos los cambios propuestos.
- Si se acepta algún cambio → se implementa y se vuelve al paso 5 (revisión) para los cambios introducidos.
- Si no se acepta ningún cambio → se avanza al paso 7.

## 7. Commits

- Una vez el programador da el visto bueno final al código, la IA prepara los commits.
- **Los mensajes de commit no deben hacer referencia al uso de IA** en ningún caso (ni en el cuerpo, ni co-author, ni firma).
- Los commits deben describir el cambio de forma clara y profesional, agrupados lógicamente (no un commit gigante por ticket si tiene sentido dividirlo).

## 8. Push

- Tras los commits, la IA propone hacer push al programador.
- El programador decide cuándo ejecutar el push.

## 9. Retrospectiva de sesión

Tras el push, la IA analiza la conversación de la sesión y reporta:
- Pasos que fallaron o ralentizaron el flujo.
- Comandos ejecutados que no estaban disponibles en el entorno (y dieron error).
- Cualquier fricción repetida o evitable.

La IA propone cambios concretos al flujo o al entorno basados en este análisis.

- Si el programador acepta algún cambio → se aplica.
- Las notas de entorno (comandos no disponibles, fixes de Windows/WSL2/PowerShell, etc.) se consolidan en `ENVIRONMENT_NOTES.md` para evitar repetir el mismo problema en sesiones futuras.

---

Aquí termina el ciclo de vida del flujo para ese ticket.
