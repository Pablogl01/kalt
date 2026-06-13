# DESIGN.md — Sistema de Diseño KALT (v2)

Sustituye la dirección visual anterior (estilo ANZ banking / gris+blanco). Nueva dirección: paleta cálida (crema/naranja/negro), inspirada en referencia tipo "Flux Health Dashboard".

## 1. Navegación

- **Desktop (≥1024px):** sidebar fijo a la izquierda, fondo oscuro (`--color-surface-dark`), con icono+label por sección.
- **Mobile (<1024px):** bottom tab bar flotante, mismas secciones que el sidebar. Si hay más de 5 secciones, las menos usadas se agrupan bajo "Más" en mobile.
- Ambos comparten el mismo store de navegación (Pinia) para estado activo.

> Nota de alcance: cada feature nueva debe testearse en ambos breakpoints (incluido en checklist de tests manuales del flujo).

## 2. Paleta base

| Token | Uso | Color |
| --- | --- | --- |
| `--color-bg` | Fondo general (light mode) | Crema `#FDF6F0` |
| `--color-surface` | Tarjetas sobre fondo claro | Blanco `#FFFFFF` |
| `--color-surface-dark` | Sidebar / tarjetas de contraste oscuro (ej. heatmap, stats destacadas) | Negro cálido `#1F1B16` |
| `--color-text` | Texto principal | `#1F1B16` |
| `--color-text-muted` | Texto secundario | `#8A8178` |
| `--color-accent` | CTAs, elementos activos, badges positivos | Verde pastel `#A8E063` |
| `--color-accent-dark` | Variante hover/active de accent | `#7CC242` |

**Importante:** el naranja queda **reservado exclusivamente** para el token `--color-carbs`. No se usa naranja en botones, CTAs, sidebar activo, ni ningún elemento que no represente carbohidratos. El acento general de la app es el verde pastel (`--color-accent`), tomado de los elementos "Upgrade to Pro" / badges de la referencia.

## 3. Tokens semánticos de macros (sin cambios respecto al manuscrito)

| Token | Uso | Color |
| --- | --- | --- |
| `--color-protein` | Proteína | `#16A34A` |
| `--color-carbs` | Carbohidratos | `#EA580C` (uso exclusivo) |
| `--color-fat` | Grasas | `#CA8A04` |
| `--color-calories` | Calorías totales | `#2563EB` |
| `--color-goal` | Objetivo / referencia | `#64748B` |
| `--color-success-adherence` | Heatmap — adherencia completa | `#15803D` |
| `--color-partial-adherence` | Heatmap — adherencia parcial | `#86EFAC` |

## 4. Patrones de UI rescatados de la referencia

- **Números grandes y bold** para métricas clave (ej. "4,3k kcal hoy"), con badge de variación (`+5%`) en `--color-accent` cuando es positivo, rojo suave (`#F87171`) cuando es negativo.
- **Tarjetas de contraste oscuro** (`--color-surface-dark`) para destacar bloques concretos: AdherenceHeatmap y/o resumen semanal en Stats. No usar como fondo general.
- **Badges redondeados** para porcentajes y estados (estilo "+10%", "Monthly ▾").

## 5. Tipografía

- **Tipografía principal**: **Plus Jakarta Sans** (Google Fonts)
- Pesos utilizados: `400` (body), `500` (labels/nav), `600` (subtítulos), `700` (métricas grandes, headings)
- Nombre definitivo de la app: KALT (confirmado, sustituye a FitDiet).
