# AGENTS.md — KALT

Instrucciones de proyecto para cualquier agente de IA (Antigravity con Claude Sonnet / Gemini, Claude Code, u otros). Aplican a **todas** las interacciones en este repo.

## Qué es KALT

App web de nutrición deportiva (antes "FitDiet"). Genera planes de dieta personalizados de forma determinista (sin IA en V1), seguimiento diario, lista de la compra automática y estadísticas.

Stack: Laravel 11 + PostgreSQL 16 + Redis 7 (backend) · Vue 3 + Vite + Tailwind CSS v4 + Pinia (frontend) · Docker.

## Documentos de referencia obligatoria

Antes de planificar o implementar cualquier ticket, consulta:

- `KALT_Manuscrito_V2.1.md` — diseño técnico completo: modelo de datos, motor de cálculo, seguridad, fases de desarrollo.
- `DESIGN.md` — sistema de diseño vigente (sustituye a la sección 9 del manuscrito). Paleta cálida, tokens de macros, navegación responsive (sidebar desktop / bottom bar mobile).
- `WORKFLOW.md` — flujo de trabajo obligatorio por ticket (resumen abajo).

Si hay conflicto entre el manuscrito y `DESIGN.md` en temas de diseño visual, **`DESIGN.md` prevalece**.

## Flujo de trabajo (resumen — ver WORKFLOW.md para el detalle)

1. **Plan**: lee el ticket `.md`, analiza alcance, propone un plan.
2. **Aprobación**: el programador aprueba el plan o lo modifica (nunca lo rechaza sin más).
3. **Implementación**: implementa el plan aprobado.
4. **Tests**: indica tests Pest (los ejecutas tú) + tests manuales concretos (los ejecuta el programador).
5. **Revisión técnica**: check ligero por defecto (seguridad evidente, duplicidad clara, optimizaciones obvias). Análisis profundo solo si el ticket toca código sensible (auth, datos de salud, motor de cálculo) o si el programador lo pide.
6. **Iteración**: si se aceptan cambios de la revisión, se implementan y se repite el paso 5 para esos cambios.
7. **Commits**: mensajes profesionales y descriptivos. **Nunca mencionar que el código fue generado/asistido por IA** (ni en el mensaje, ni co-author, ni firma).
8. **Push**: se propone al programador, él decide cuándo.
9. **Retrospectiva**: tras el push, analiza la sesión — qué falló/ralentizó, comandos no disponibles que dieron error. Propón cambios al flujo o al entorno. Si se aceptan, consolida notas de entorno en `ENVIRONMENT_NOTES.md`.

## Reglas críticas de seguridad y datos (siempre activas)

- Toda restricción alimentaria (alergias/intolerancias) se valida en backend, nunca solo en frontend.
- Todas las claves primarias expuestas en API son UUID.
- Cada endpoint que accede a un recurso de usuario llama a `$this->authorize()` con su Policy correspondiente.
- Campos de salud (peso, grasa corporal, alergias) usan el cast `Encrypted` de Laravel.
- `$fillable` explícito en todos los modelos, nunca `$guarded = []`.
- Validación vía FormRequest, no en el controller.
- No usar `v-html` con datos de usuario.
- Sanctum en modo SPA cookie httpOnly — nunca tokens en localStorage/sessionStorage.

## Reglas críticas de diseño (siempre activas)

- El naranja (`--color-carbs`) es de uso **exclusivo** para representar carbohidratos. No usarlo en botones, CTAs, estados activos de navegación ni ningún otro elemento.
- El acento general de la UI es verde pastel (`--color-accent`, `#A8E063`).
- Toda feature de UI debe funcionar en sidebar (desktop ≥1024px) y bottom bar (mobile <1024px).

## Notas de entorno

Ver `ENVIRONMENT_NOTES.md` (si existe) para comandos/configuraciones específicas de este entorno (Windows/WSL2/PowerShell) que ya se sabe que fallan o requieren workaround.
