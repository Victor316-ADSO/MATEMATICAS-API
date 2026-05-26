# Análisis matemático y gráficas — Analytics de adopción tecnológica

Documentación completa del módulo **Analytics Matemático**: modelo de usuarios `U(t)`, cálculo diferencial, las seis gráficas del dashboard, alertas, predicción y flujo API ↔ frontend.

---

## Tabla de contenidos

1. [Propósito y contexto](#1-propósito-y-contexto)
2. [Modelo matemático U(t)](#2-modelo-matemático-ut)
3. [Arquitectura del sistema](#3-arquitectura-del-sistema)
4. [Variable temporal t](#4-variable-temporal-t)
5. [Análisis en un instante (analyzeAt)](#5-análisis-en-un-instante-analyzeat)
6. [Puntos críticos e inflexión](#6-puntos-críticos-e-inflexión)
7. [Alertas inteligentes](#7-alertas-inteligentes)
8. [Módulo predictivo](#8-módulo-predictivo)
9. [Las seis gráficas (detalle)](#9-las-seis-gráficas-detalle)
10. [API REST](#10-api-rest)
11. [Frontend (React + Chart.js)](#11-frontend-react--chartjs)
12. [Datos reales y sincronización](#12-datos-reales-y-sincronización)
13. [Instalación y verificación](#13-instalación-y-verificación)
14. [Referencias de código](#14-referencias-de-código)

---

## 1. Propósito y contexto

El panel **Analytics Matemático** (`/admin/analytics`) combina:

- **Datos reales** de la plataforma (egresados, intentos de quiz, tabla `usuarios_estadisticas`).
- **Un modelo polinómico** `U(t)` que representa la curva teórica de adopción tecnológica en **semanas** desde el inicio de la plataforma.

El cálculo diferencial (`U'`, `U''`) no sustituye a las métricas reales: sirve para **clasificar tendencia**, **detectar saturación o estancamiento**, **marcar puntos críticos** y **proyectar escenarios** alineados al promedio de usuarios activos.

---

## 2. Modelo matemático U(t)

### Funciones

| Símbolo | Fórmula | Interpretación |
|--------|---------|----------------|
| **U(t)** | `-2t⁴ + 32t³ - 180t² + 432t + 100` | Usuarios modelados (escala pedagógica del polinomio) |
| **U'(t)** | `-8t³ + 96t² - 360t + 432` | **Velocidad** de crecimiento (pendiente de la curva) |
| **U''(t)** | `-24t² + 192t - 360` | **Aceleración** / **concavidad** del crecimiento |

Implementación: `src/Services/MathematicalAnalysisService.php` — métodos `u()`, `uPrime()`, `uDoublePrime()`.

### Dominio interpretable

- En el dashboard, la curva se muestra en **t ∈ [0, 8]** semanas.
- El instante operativo `t_actual` se acota a **[0.5, 6.5]** según actividad real reciente.
- Valores negativos de `U(t)` en gráficas se recortan a **0** (`generateCurveForChart`).

### Significado intuitivo

- **U' > 0**: la adopción **aumenta** en ese instante del modelo.
- **U' ≈ 0** (|U'| < 5): **estancamiento** en la curva.
- **U'' > 0**: crecimiento **acelerado** (curva cóncava hacia arriba).
- **U'' < 0** con U' > 0: crecimiento positivo pero **desacelerado** (se acerca a un techo).

---

## 3. Arquitectura del sistema

```
┌─────────────────────┐     GET /api/analytics/dashboard      ┌──────────────────────────┐
│  React Analytics    │ ──────────────────────────────────────► │  AnalyticsController     │
│  (Chart.js)         │         JWT admin                       │  AnalyticsService        │
└─────────────────────┘                                       │  MathematicalAnalysis    │
        │                                                       └────────────┬─────────────┘
        │                                                                    │
        │  tarjetas, crecimiento, matematico, graficas                       │
        ▼                                                                    ▼
  StatCards, MathPanel, AlertsPanel,                              MySQL: egresados,
  AnalyticsCharts (6 gráficas), PredictivePanel                    usuarios_estadisticas,
                                                                 quiz_adopcion_intentos
```

| Capa | Archivo principal | Rol |
|------|-------------------|-----|
| Cálculo puro | `MathematicalAnalysisService.php` | Polinomio, curvas, críticos, alertas, predict |
| Orquestación + gráficas | `AnalyticsService.php` | BD, series temporales, `buildChartDatasets`, `t_actual` |
| HTTP | `AnalyticsController.php` | `/dashboard`, `/matematico`, `/prediccion` |
| UI | `cuestionario-dev/src/features/analytics/` | Visualización |

---

## 4. Variable temporal t

`t` **no** es la fecha del calendario directamente: es la **posición en semanas dentro del modelo de adopción**.

### Cómo se calcula `t_actual` (`AnalyticsService::getCurrentT`)

1. Promedio de **usuarios activos** últimos **14 días** (`usuarios_estadisticas`).
2. Semanas de calendario desde el primer registro en estadísticas (referencia auxiliar).
3. Reglas por umbral de actividad:

| Promedio activos (14 días) | t asignado |
|---------------------------|------------|
| ≥ 38 | 5.8 |
| ≥ 32 | 5.2 |
| ≥ 26 | 4.5 |
| ≥ 20 | 3.5 |
| < 20 | `2.5 + min(2.5, semanasCalendario/4)` |

4. Resultado final: `round(clamp(t, 0.5, 6.5), 2)`.

**Nota mostrada en UI:** la plataforma puede llevar más tiempo en calendario; el análisis usa el tramo **interpretable** del polinomio (fase 0–8).

### Alineación modelo ↔ eje de fechas (`mapModelToDays`)

Para la gráfica **real vs modelo**:

- Cada día `i` de la serie (índice `0 … n-1`) se mapea a  
  `t = 0.5 + (i / (n-1)) * 6.0` → dominio **[0.5, 6.5]**.
- Se evalúa `U(t)`, se escala linealmente para que el máximo del modelo coincida con el máximo de **activos reales** en la ventana (comparación visual en Chart.js).

---

## 5. Análisis en un instante (`analyzeAt`)

Entrada: `t` (float). Salida (resumen):

| Campo | Descripción |
|-------|-------------|
| `u`, `u_prime`, `u_double_prime` | Valores numéricos redondeados |
| `growth_rate` | Clasificación por U'(t) — ver tabla abajo |
| `acceleration` | Clasificación por U''(t) |
| `is_stagnant` | \|U'(t)\| < 5 |
| `is_growing` | U'(t) > 0 |
| `is_decelerating` | U'' < 0 y U' > 0 |
| `is_accelerating` | U'' > 0 |

### Clasificación de `growth_rate` (U')

| Condición | Etiqueta |
|-----------|----------|
| U' > 50 | `crecimiento_rapido` |
| 0 < U' ≤ 50 | `crecimiento_moderado` |
| \|U'\| < 5 | `estancamiento` |
| U' ≤ 0 (y no estancamiento) | `decrecimiento` |

### Clasificación de `acceleration` (U'')

| Condición | Etiqueta |
|-----------|----------|
| U'' > 5 | `aceleracion` |
| U'' < -5 | `desaceleracion` |
| Resto | `velocidad_constante` |

El panel **MathPanel** en frontend muestra fórmulas, `t_actual`, referencia de activos reales, derivadas y chips de tendencia/aceleración.

---

## 6. Puntos críticos e inflexión

### Puntos críticos (`findCriticalPoints`)

- Barrido de `t` con paso `0.05` (dashboard: `0.08`, máximo t=8).
- Donde **U'(t) cambia de signo** → raíz refinada por **bisección** (`bisectZero`).
- Tipo según **U''(t)** en el crítico:
  - U'' < 0 → `maximo_local`
  - U'' > 0 → `minimo_local`
  - U'' = 0 → `punto_silla`

`findCriticalPointsForDashboard` filtra: `t ≤ 8`, `u ≥ 0`, máximo 5 puntos.

### Puntos de inflexión (`findInflectionPoints`)

- Donde **U''(t) cambia de signo** → bisección en U'' (`bisectZeroSecond`).
- Devuelve `concavidad_antes` / `concavidad_despues`: `concava_arriba` | `concava_abajo`.

---

## 7. Alertas inteligentes

`generateAlerts(t)` evalúa el estado en `t` y proximidad a críticos/inflexiones.

| Tipo | Nivel | Condición (resumen) |
|------|-------|---------------------|
| `aceleracion` | success | U'' > 0 y U' > 0 |
| `desaceleracion` | warning | U'' < 0 y U' > 0 |
| `saturacion` | danger | U'' < -10 y \|U'\| < 15 |
| `maximo_local` | info | Crítico tipo máximo con t ≈ actual (±0.5) |
| `estancamiento` | secondary | \|U'\| < 5 |
| `inflexion` | primary | Inflexión con t ≈ actual (±0.3) |
| `estable` | info | Si no aplica ninguna otra |

Cada alerta incluye `mensaje` legible y `criterio` con la expresión usada (útil en **AlertsPanel**).

---

## 8. Módulo predictivo

`predict(currentT, monthsAhead=6, usuariosBase)`:

1. `currentT` ∈ [0.5, 6.5]; `usuariosBase` = promedio activos reales (mín. 5).
2. Para cada mes `m = 1 … 6`:
   - `futureT = min(8, currentT + m * 0.9)`
   - `usuarios_proyectados = round((U(futureT) / U(currentT)) * usuariosBase)` (mín. 5)
   - `tendencia`: según **delta** entre meses proyectados (≥2 crecimiento, ≤-2 decrecimiento, etc.)
   - `tendencia_modelo`: `growth_rate` de `analyzeAt(futureT)`
3. **Meses de saturación**: U'' < -15 y U' < 40.
4. **Meses de estabilización**: estancamiento o \|U'\| < 8.
5. **Riesgo de abandono** (`bajo` | `medio` | `alto`) según U', U'' y estancamiento en `t` actual.
6. **crecimiento_futuro_estimado**: diferencia mes 1 vs base redondeada.

**PredictivePanel** muestra resumen y lista mes a mes; la gráfica de predicción usa solo `usuarios_proyectados`.

---

## 9. Las seis gráficas (detalle)

Todas se construyen en `AnalyticsService::buildChartDatasets()` y se renderizan en `AnalyticsCharts.tsx` (Chart.js).

### Gráfica 1 — Crecimiento de usuarios (real vs modelo)

| Aspecto | Detalle |
|---------|---------|
| **Tipo** | Línea (`Line`) |
| **Clave API** | `graficas.crecimiento_usuarios` |
| **Eje X** | Fechas diarias últimos ~60 días (`dd/mm`) |
| **Serie 1** | Usuarios activos **reales** (`usuarios_estadisticas`) |
| **Serie 2** | **U(t) modelo** escalado (`mapModelToDays`) — línea discontinua naranja |
| **Objetivo** | Comparar trayectoria empírica con la forma del polinomio en la misma ventana temporal |
| **Lectura** | Si ambas suben juntas, adopción coherente con fase de crecimiento del modelo; divergencias indican que la realidad no sigue la curva teórica (normal en datos reales ruidosos) |

### Gráfica 2 — Concavidad U''(t)

| Aspecto | Detalle |
|---------|---------|
| **Tipo** | Línea con relleno |
| **Clave API** | `graficas.concavidad` |
| **Eje X** | `t` de 0 a 8 (50 puntos de `generateCurveForChart`) |
| **Eje Y** | `u_double_prime` de cada punto |
| **Objetivo** | Visualizar **dónde acelera o desacelera** el crecimiento del modelo |
| **Lectura** | U'' > 0: cóncava arriba (aceleración); U'' < 0: cóncava abajo (desaceleración); cruces por cero ≈ **puntos de inflexión** |

### Gráfica 3 — Comparación semanal

| Aspecto | Detalle |
|---------|---------|
| **Tipo** | Barras agrupadas (`Bar`) |
| **Clave API** | `graficas.comparacion_semanal` |
| **Eje X** | Últimas **8 semanas** (etiqueta inicio de semana `d/m`) |
| **Barras** | Promedio diario de **activos** vs **quizzes** por semana (`YEARWEEK`) |
| **Objetivo** | Ver engagement semanal: ¿los quizzes acompañan a los activos? |
| **Lectura** | Barras de quizzes muy por debajo de activos → muchos usuarios activos completan pocos quizzes |

### Gráfica 4 — Activos vs quizzes (diario)

| Aspecto | Detalle |
|---------|---------|
| **Tipo** | Doble línea |
| **Clave API** | `graficas.activos_vs_quizzes` |
| **Eje X** | Mismas fechas diarias que gráfica 1 |
| **Series** | Activos y quizzes por día |
| **Objetivo** | Correlación **día a día** entre presencia y uso del cuestionario |
| **Lectura** | Líneas paralelas = buena conversión actividad→quiz; quizzes planos con activos altos = oportunidad de retención |

### Gráfica 5 — Predicción de crecimiento

| Aspecto | Detalle |
|---------|---------|
| **Tipo** | Línea con relleno |
| **Clave API** | `graficas.prediccion` |
| **Eje X** | `Mes 1` … `Mes 6` |
| **Eje Y** | `usuarios_proyectados` del módulo `predict()` |
| **Objetivo** | Proyección a 6 meses anclada a usuarios reales, forma guiada por U(t) |
| **Lectura** | No es pronóstico estadístico clásico (ARIMA, etc.): es **escenario pedagógico** derivado del modelo diferencial + escala actual |

### Gráfica 6 — Curva polinómica U(t)

| Aspecto | Detalle |
|---------|---------|
| **Tipo** | Línea suave |
| **Datos** | `matematico.curva` (misma curva que alimenta concavidad, pero solo `u` vs `t`) |
| **Eje X** | Semanas `t` (0–8) |
| **Eje Y** | `U(t)` ≥ 0 |
| **Objetivo** | Ver la **forma global** de adopción: subida, meseta, posible caída |
| **Lectura** | Complementa la gráfica 1: aquí el eje es **t del modelo**, no fechas calendario |

### Resumen visual en dashboard

```
┌────────────────────────────────────┬──────────────────┐
│ 1. Real vs modelo (fechas)         │ 2. U''(t)        │
├──────────────────┬─────────────────┼──────────────────┤
│ 3. Semanal bar   │ 4. Activos/quiz │                  │
├──────────────────┼─────────────────┤                  │
│ 5. Predicción 6m │ 6. U(t) pura    │                  │
└──────────────────┴─────────────────┴──────────────────┘
```

---

## 10. API REST

Requieren **JWT de administrador** (`Authorization: Bearer …`).

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/analytics/dashboard` | Payload completo: `tarjetas`, `crecimiento`, `matematico`, `graficas` |
| GET | `/api/analytics/matematico?t=4` | Análisis en `t` arbitrario [0,12], curva 0–12, críticos/inflexión/alertas |
| GET | `/api/analytics/prediccion` | Solo bloque `prediccion` (toma `t_actual` del dashboard interno) |

### Estructura relevante de `matematico` (dashboard)

```json
{
  "funcion": "U(t) = ...",
  "derivada": "U'(t) = ...",
  "segunda_derivada": "U''(t) = ...",
  "t_actual": 4.5,
  "usuarios_reales_referencia": 32.4,
  "analisis_actual": { "u", "u_prime", "u_double_prime", "growth_rate", ... },
  "curva": [{ "t", "u", "u_prime", "u_double_prime" }, ...],
  "puntos_criticos": [{ "t", "u", "tipo" }, ...],
  "puntos_inflexion": [...],
  "alertas": [...],
  "prediccion": { "proyeccion", "riesgo_abandono", ... }
}
```

### Estructura `graficas`

```json
{
  "crecimiento_usuarios": { "labels": [], "datasets": [{ "label", "data" }, ...] },
  "concavidad": { "labels": [], "data": [] },
  "comparacion_semanal": { "labels": [], "activos": [], "quizzes": [] },
  "activos_vs_quizzes": { "labels": [], "activos": [], "quizzes": [] },
  "prediccion": { "labels": [], "data": [] }
}
```

Rutas registradas en `src/App/Routes/Analytics.php`.

---

## 11. Frontend (React + Chart.js)

| Componente | Función |
|------------|---------|
| `useAnalyticsData` | `GET` dashboard, valida que exista serie `diario` |
| `StatCards` | Tarjetas KPI (registrados, activos, % crecimiento, retención) |
| `AlertsPanel` | Alertas con color por `nivel` |
| `MathPanel` | Fórmulas, métricas en `t_actual`, puntos críticos |
| `AnalyticsCharts` | Las 6 gráficas |
| `PredictivePanel` | Texto de predicción y riesgo |

Tipos TypeScript: `src/features/analytics/types.ts`.

---

## 12. Datos reales y sincronización

### Tabla `usuarios_estadisticas`

Campos: `fecha`, `usuarios_nuevos`, `usuarios_activos`, `quizzes_completados`, `tiempo_promedio`.

`syncTodaySnapshot()`:

- Cuenta egresados y actividad del día desde `quiz_adopcion_intentos` o `tecni_encuesta_realizada`.
- Actualiza/inserta el registro de hoy.
- Si hay &lt; 7 filas, ejecuta `seedHistoricalData()` (30 días sintéticos alineados al modelo).

### Fuentes de actividad

Prioridad: `quiz_adopcion_intentos` → `tecni_encuesta_realizada`.

### Tarjetas resumen (no son gráficas pero alimentan contexto)

- Crecimiento semanal/mensual: % sobre suma de activos en ventanas deslizantes.
- Retención: usuarios distintos semana actual vs semana anterior en tabla de quiz.

---

## 13. Instalación y verificación

```bash
# Poblar estadísticas de ejemplo
php database/seed_analytics_datos.php --reset

# Comprobar coherencia matemático ↔ gráficas
php database/verify_analytics.php
```

Frontend: ruta admin analytics con sesión admin; botón **Actualizar** recarga el dashboard.

Si `crecimiento.diario` está vacío, el hook muestra error indicando ejecutar el seed.

---

## 14. Referencias de código

| Tema | Ubicación |
|------|-----------|
| Polinomio y análisis | `src/Services/MathematicalAnalysisService.php` |
| Series, gráficas, t_actual | `src/Services/AnalyticsService.php` |
| Endpoints | `src/Controllers/AnalyticsController.php` |
| Gráficas UI | `cuestionario-dev/.../components/AnalyticsCharts.tsx` |
| Panel matemático | `.../components/MathPanel.tsx` |

---

## Glosario rápido

| Término | Significado en este módulo |
|---------|----------------------------|
| **Adopción tecnológica** | Uso sostenido de la plataforma de quizzes por egresados |
| **t** | Semana en el modelo polinómico (no necesariamente semana calendario exacta) |
| **Velocidad** | U'(t) — qué tan rápido cambia U |
| **Concavidad / aceleración** | U''(t) — si el crecimiento se acelera o frena |
| **Punto crítico** | Donde U'(t)=0 (máximo, mínimo o silla local) |
| **Punto de inflexión** | Donde U''(t)=0 (cambia la curvatura) |

---

*Documento generado para el proyecto cuestionario-api / cuestionario-dev — módulo Analytics Matemático.*
