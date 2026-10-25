# Probabilidad y Estadística aplicadas a SaludWEB

**Materia:** Probabilidad y Estadística (2025)<br>
**Carrera:** Tecnicatura en Programación<br>
**Institución:** UTN Facultad Regional Paraná<br>
**Autora:** Ailen Quaglino<br>
**Proyecto:** SaludWEB (Backend PHP/SQLite, Web React y Mobile React Native/Expo)<br>
**Fecha de la aplicación:** 25/10/2026<br>
**Corte de datos del proyecto:** 25/10/2026, base `SaludWEB_Backend/storage/saludweb.sqlite`

---

## 0. Para qué sirve este documento

Este documento aplica al proyecto **SaludWEB** el material de la cátedra de Probabilidad y Estadística:

- estadística descriptiva e inferencial;
- población, muestra y variables;
- tablas de frecuencia, medidas de tendencia central y de dispersión;
- gráficos y boxplot;
- probabilidad, espacio muestral, sucesos y axiomas;
- unión, intersección, complemento y sucesos mutuamente excluyentes;
- probabilidad condicional, independencia, probabilidad total y Bayes;
- variables aleatorias, binomial, normal y Poisson.

Además:

1. **verifica y corrige** la Guía N°1 de 21 ejercicios;
2. **resuelve completa** la Guía N°2 con las 500 respuestas reales del formulario;
3. usa la **base real de SaludWEB** para calcular frecuencias, medidas y probabilidades;
4. resuelve el **segundo recuperatorio** (drones, sensores y streaming);
5. deja trazabilidad de las consultas y de las limitaciones de los datos.

> **Importante:** la base de SaludWEB es una base de desarrollo. Sus 223 pacientes y sus 25 bloques de agenda sirven para aprender y para describir ese conjunto de datos, pero **no forman una muestra aleatoria** de todos los pacientes reales. Por eso no se calculan intervalos de confianza ni pruebas de hipótesis como si fueran conclusiones poblacionales. La Guía N°2 también se describe, no se infiere a toda la carrera, porque no se conoce el método de muestreo.

### Fuentes usadas

| Fuente | Uso |
|---|---|
| Material de cátedra de Probabilidad y Estadística 2025 | Teoría, fórmulas y ejercicios |
| Guía N°1 (21 ejercicios con soluciones) | Verificación y corrección |
| [Guía N°2 - `estudiantes prog 500.xlsx`](https://docs.google.com/spreadsheets/d/1ckn8P-ggoVHInUX7M8vFP0VCR0m6TNEscgmm68Mp4iI/edit?usp=sharing) | Resolución con 500 respuestas |
| `SaludWEB_Backend/storage/saludweb.sqlite` | Frecuencias, probabilidades y medidas del proyecto |
| `SaludWEB_Backend/db.php` y `routes.php` | Trazabilidad del esquema y de los endpoints |
| Segundo recuperatorio (drones, sensores y streaming) | Resolución de distribuciones |

Los archivos `.xlsx` de Moodle del primer parcial y del recuperatorio de `CentroBienestar` no se pudieron incorporar al repositorio: los enlaces requieren sesión y no vienen con el material versionado. Por eso esos puntos se resuelven como **método de trabajo**, sin inventar resultados.

---

## 1. Conceptos que ordenan todo

### 1.1 Estadística descriptiva e inferencial

La **estadística descriptiva** resume los datos que se tienen: cuenta, ordena, promedia, mide dispersión y grafica. La **estadística inferencial** usa una muestra para sacar conclusiones sobre una población, con una medida de incertidumbre.

En este documento se hace principalmente estadística descriptiva:

- de los 500 estudiantes de la Guía N°2;
- de los 223 pacientes, 8 médicos, 25 bloques de agenda y 17 triages de SaludWEB.

No se hace inferencia formal porque no hay muestreo aleatorio documentado. Una proporción calculada sobre los datos disponibles es una **frecuencia relativa observada**, no una probabilidad poblacional garantizada.

### 1.2 Población, muestra, parámetro y estadístico

| Concepto | Definición | Ejemplo en la Guía N°2 | Ejemplo en SaludWEB |
|---|---|---|---|
| Población | Todos los individuos que interesan | Todos los estudiantes de la Tecnicatura | Todos los pacientes que podrían usar el sistema |
| Muestra | Subconjunto observado | 500 respuestas del formulario | 223 fichas cargadas en la base de desarrollo |
| Parámetro | Medida de la población, normalmente desconocida | Edad media de todos los estudiantes | Proporción real de pacientes OSDE |
| Estadístico | Medida calculada en la muestra | Edad media de los 500 encuestados | Proporción observada 88/223 |

### 1.3 Variables y escalas de medición

| Tipo | Subtipo | Qué permite | Ejemplos del material | Ejemplos de SaludWEB |
|---|---|---|---|---|
| Cualitativa | Nominal | Clasificar, contar, moda | SO, sexo, lenguaje, modalidad | Obra social, especialidad, estado de cita |
| Cualitativa | Ordinal | Clasificar y ordenar | Nivel de inglés | Nivel de triage, si se define una escala |
| Cuantitativa | Discreta | Contar, sumar, promediar | Horas de estudio, nota | Cantidad de citas por paciente, bloques por médico |
| Cuantitativa | Continua | Medir, promediar, desviar | Asistencia %, años de experiencia | Minutos semanales de agenda |

Una variable numérica no siempre es continua: la nota del formulario está registrada como entero (5, 6, 7, 8), aunque conceptualmente podría tener decimales. La distinción importa para elegir el gráfico y para saber si tiene sentido usar la media.

---

## 2. Guía N°2 resuelta con los 500 estudiantes

### 2.1 Origen y forma de los datos

Se descargó la hoja pública de la Guía N°2 en formato CSV el 25/10/2026. Tiene **500 filas** y estas columnas:

`ID`, `Edad`, `Sexo`, `SO`, `LenguajeFav`, `HorasEstudio`, `CursaProgramacion`, `CursaProbEstad`, `Modalidad`, `Dispositivo`, `Trabaja`, `AsistenciaPct`, `ExpProgAnios`, `NivelIngles`, `UsaGit`, `Nota`.

La hoja trae además columnas vacías al final. No se usaron. Los decimales del CSV vienen con coma (`"2,6"`, `"62,1"`); se convirtieron a punto para calcular.

### 2.2 Consigna 1: población y muestra

- **Población:** todos los estudiantes de la Tecnicatura en Programación que podrían haber respondido el formulario.
- **Muestra:** los **500 estudiantes** que efectivamente respondieron.
- **Unidad de análisis:** cada estudiante encuestado.
- **Alcance:** la muestra permite describir a esos 500 estudiantes. Para generalizar a toda la carrera haría falta conocer el marco muestral y el método de selección. Si el formulario se difundió por conveniencia, la generalización es limitada.

### 2.3 Consigna 2: clasificación de variables

| Variable | Tipo | Escala | Observación |
|---|---|---|---|
| `ID` | Cualitativa | Nominal | Identificador; no se analiza como magnitud |
| `Edad` | Cuantitativa | Discreta/continua | Registrada en años enteros |
| `Sexo` | Cualitativa | Nominal | Valores `F`, `M`, `X` |
| `SO` | Cualitativa | Nominal | Windows, Linux, MacOS |
| `LenguajeFav` | Cualitativa | Nominal | Python, JavaScript, Java, C++, Otro |
| `HorasEstudio` | Cuantitativa | Discreta | Horas semanales, registradas como enteros |
| `CursaProgramacion` | Cualitativa | Nominal binaria | Sí/No |
| `CursaProbEstad` | Cualitativa | Nominal binaria | Sí/No |
| `Modalidad` | Cualitativa | Nominal | Presencial, Híbrida, Virtual |
| `Dispositivo` | Cualitativa | Nominal | Notebook, PC Escritorio, Netbook, Otro |
| `Trabaja` | Cualitativa | Nominal | No, Part-time, Full-time |
| `AsistenciaPct` | Cuantitativa | Continua | Porcentaje de asistencia |
| `ExpProgAnios` | Cuantitativa | Continua | Años de experiencia, con decimales |
| `NivelIngles` | Cualitativa | Ordinal | Bajo, Intermedio, Avanzado |
| `UsaGit` | Cualitativa | Nominal binaria | Sí/No |
| `Nota` | Cuantitativa | Discreta | Nota entera de 5 a 8 |

### 2.4 Consigna 3: tabla de frecuencias de `SO`

| SO | Frecuencia absoluta | Frecuencia relativa | Porcentaje | Frecuencia acumulada | Porcentaje acumulado |
|---|---:|---:|---:|---:|---:|
| Windows | 263 | 0,5260 | 52,60 % | 263 | 52,60 % |
| Linux | 180 | 0,3600 | 36,00 % | 443 | 88,60 % |
| MacOS | 57 | 0,1140 | 11,40 % | 500 | 100,00 % |
| **Total** | **500** | **1,0000** | **100,00 %** | | |

Interpretación: más de la mitad de los encuestados usa Windows; Linux llega al 36 % y MacOS al 11,4 %. La moda es **Windows**.

### 2.5 Consigna 4: gráfico para `SO`

`SO` es cualitativa nominal, así que corresponde un **gráfico de barras** (o un gráfico circular si solo se quiere mostrar la composición). No corresponde un histograma, porque no hay intervalos numéricos.

Representación aproximada, con cada `#` equivalente a unos 10 estudiantes:

```text
Windows | ########################## 263
Linux   | ################## 180
MacOS   | ###### 57
```

Un gráfico circular también sería válido, pero las barras permiten comparar mejor las tres categorías.

### 2.6 Consigna 5: media, mediana y moda de `Edad`

Con los 500 valores:

- suma de edades: **13.344**;
- media: `13.344 / 500 = 26,688` años;
- mediana: **26** años;
- moda: **19** años, con **44** repeticiones (8,8 % de la muestra);
- mínimo: 18; máximo: 45; rango: 27;
- Q1: 21; Q3: 30; IQR: 9;
- desvío muestral: 6,7208 años;
- coeficiente de variación: 25,18 %.

Interpretación: la edad media es 26,69 años; la mitad de los encuestados tiene 26 años o menos; la edad más frecuente es 19. La media y la mediana son parecidas, así que la distribución no está fuertemente corrida por valores extremos, aunque hay edades de 44 y 45 años que quedan por encima del límite de Tukey (43,5).

### 2.7 Consigna 6: varianza y desvío de `HorasEstudio`

Datos:

- `n = 500`;
- suma de horas: `Σx = 7.006`;
- suma de cuadrados: `Σx² = 115.062`;
- media: `x̄ = 7.006 / 500 = 14,012` horas.

Varianza muestral:

```text
s² = [Σx² - n·x̄²] / (n - 1)
   = [115.062 - 500 · (14,012)²] / 499
   = [115.062 - 98.168,072] / 499
   = 16.893,928 / 499
   = 33,8556
```

Desvío muestral:

```text
s = √33,8556 = 5,8186 horas
```

Si se tratara a los 500 estudiantes como población completa:

```text
σ² = 16.893,928 / 500 = 33,7879
σ  = √33,7879 = 5,8127 horas
```

Interpretación: en promedio, los estudiantes dedican 14,01 horas semanales al estudio, con una dispersión de 5,82 horas. El coeficiente de variación es `5,8186 / 14,012 · 100 = 41,53 %`, alto: la cantidad de horas de estudio varía bastante entre estudiantes. La moda es 14 horas (47 casos).

### 2.8 Consigna 7: boxplot de `AsistenciaPct`

Resumen de cinco números:

| Medida | Valor |
|---|---:|
| Mínimo | 48,00 % |
| Q1 | 74,85 % |
| Mediana | 81,65 % |
| Q3 | 90,725 % |
| Máximo | 100,00 % |
| IQR | 15,875 % |
| Valla inferior de Tukey | 51,0375 % |
| Valla superior de Tukey | 114,5375 % |
| Bigote inferior | 52,00 % |
| Bigote superior | 100,00 % |
| Outliers bajos | 48; 49,9; 50; 50,7 |

El boxplot tendría la caja entre 74,85 y 90,725, con la mediana en 81,65. El bigote superior llega a 100 y el inferior a 52. Quedan **cuatro valores atípicos bajos**: 48, 49,9, 50 y 50,7. Son asistencias mucho menores que el resto, no necesariamente errores: pueden ser estudiantes que faltaron por trabajo, enfermedad o problemas personales.

La media es 82,08 %, la mediana 81,65 % y el desvío 11,26 %. La cercanía entre media y mediana indica una distribución bastante simétrica en el centro, con una cola hacia los valores bajos.

### 2.9 Consigna 8: comparar `Nota` según `Trabaja`

| Situación laboral | n | Media | Mediana | Moda | Desvío muestral | Mínimo | Máximo |
|---|---:|---:|---:|---:|---:|---:|---:|
| No trabaja | 224 | 6,5938 | 7 | 7 | 0,5839 | 5 | 8 |
| Part-time | 173 | 6,5260 | 7 | 7 | 0,5662 | 5 | 8 |
| Full-time | 103 | 5,9709 | 6 | 6 | 0,5680 | 5 | 7 |

Lectura descriptiva:

- quienes no trabajan y quienes trabajan part-time tienen mediana 7 y media cercana a 6,5;
- quienes trabajan full-time tienen mediana 6 y media 5,97, alrededor de **0,62 puntos menos** que el grupo que no trabaja;
- la dispersión es parecida en los tres grupos (desvío cercano a 0,57);
- las notas están muy concentradas en pocos valores (5, 6, 7 y 8), así que la media resume pero pierde detalle.

No se puede afirmar que trabajar full-time **cause** notas más bajas. Solo se observa una asociación descriptiva. Para una conclusión causal haría falta un diseño de estudio distinto, con control de otras variables (horas de estudio, asistencia, experiencia previa, etc.).

### 2.10 Consigna 9: tabla de contingencia `SO` × `UsaGit`

Observados:

| SO | Usa Git: Sí | Usa Git: No | Total | P(Sí \| SO) |
|---|---:|---:|---:|---:|
| Windows | 176 | 87 | 263 | 66,92 % |
| Linux | 121 | 59 | 180 | 67,22 % |
| MacOS | 40 | 17 | 57 | 70,18 % |
| **Total** | **337** | **163** | **500** | **67,40 %** |

Esperados si `SO` y `UsaGit` fueran independientes:

| SO | Esperado Sí | Esperado No |
|---|---:|---:|
| Windows | 177,262 | 85,738 |
| Linux | 121,320 | 58,680 |
| MacOS | 38,418 | 18,582 |

Ejemplo de cálculo del esperado de Windows-Sí:

```text
E = (total de fila · total de columna) / total general
  = (263 · 337) / 500
  = 177,262
```

Chi-cuadrado:

```text
χ² = Σ (O - E)² / E = 0,23
gl = (3 - 1)(2 - 1) = 2
V de Cramér = √(0,23 / (500 · 1)) = 0,0214
```

Con `χ² = 0,23` y 2 grados de libertad, el valor es muy pequeño; el valor-p aproximado es 0,891. La asociación es prácticamente nula. Las proporciones condicionales (66,9 %, 67,2 %, 70,2 %) son muy parecidas a la proporción global (67,4 %). **No hay evidencia descriptiva de asociación entre el sistema operativo y el uso de Git.**

### 2.11 Consigna 10: conclusión de la Guía N°2

- El sistema operativo más usado es **Windows (52,6 %)**, seguido por Linux (36 %) y MacOS (11,4 %).
- La edad media es **26,69 años**, con mediana 26 y moda 19.
- Los estudiantes dedican en promedio **14,01 horas semanales** al estudio, con desvío 5,82 y alta variabilidad relativa (CV 41,53 %).
- La asistencia media es **82,08 %**, con mediana 81,65 % y cuatro valores atípicos bajos.
- La nota media es **6,44**; quienes trabajan full-time muestran una media y una mediana algo menores, pero es una diferencia descriptiva, no causal.
- El uso de Git alcanza al **67,4 %** y no se asocia con el sistema operativo en esta muestra.

---

## 3. Estadística descriptiva con los datos de SaludWEB

### 3.1 Qué se puede y qué no se puede analizar

La base de desarrollo tiene:

| Tabla | Registros | Uso estadístico |
|---|---:|---|
| `pacientes` | 223 | Frecuencias, proporciones, tablas cruzadas |
| `medicos` | 8 | Frecuencias y agenda |
| `disponibilidades` | 25 | Medidas de dispersión y boxplot |
| `triages` | 17 | Distribución ordinal, con advertencia de escala |
| `citas` | 1 | No alcanza para estimar ausentismo ni demanda |
| `notificaciones` | 0 | No se puede analizar recordatorios |
| `usuarios` | 10 | Composición por rol |
| `prescripciones` | 2 | Solo contexto, no muestra |
| `eventos_realtime` | 8 | Provienen de pruebas, no de producción |

La tabla `citas` tiene **una sola fila** (2026-09-29, 10:00, Dermatología, estado `pendiente`, 30 minutos). Con una sola observación no se puede estimar la probabilidad de que una cita se cancele, se complete o el paciente no asista. Cualquier cálculo de ausentismo con `1/1` sería descriptivamente correcto para esa fila, pero estadísticamente inútil. En este documento se evita ese error.

### 3.2 Pacientes por obra social

| Obra social | Frecuencia | Porcentaje | Activos | Inactivos |
|---|---:|---:|---:|---:|
| OSDE | 88 | 39,46 % | 43 | 45 |
| PAMI | 67 | 30,04 % | 35 | 32 |
| Particular | 68 | 30,49 % | 36 | 32 |
| **Total** | **223** | **100,00 %** | **114** | **109** |

La distribución está bastante equilibrada: OSDE tiene algo más de peso, pero las tres categorías están entre 30 % y 39 %. La cantidad de activos e inactivos es casi la mitad en cada grupo.

### 3.3 Médicos y especialidades

Hay 8 médicos, de los cuales 5 están activos. Al normalizar la especialidad (unificando `Cardiología` y `Cardiologia`):

| Especialidad | Frecuencia | Porcentaje |
|---|---:|---:|
| Cardiología | 3 | 37,50 % |
| Medicina General | 2 | 25,00 % |
| Dermatología | 1 | 12,50 % |
| Pediatría | 1 | 12,50 % |
| Neurología | 1 | 12,50 % |

**Hallazgo de calidad:** en la base conviven `Cardiología` (con tilde) y `Cardiologia` (sin tilde). Si no se normaliza, la categoría "Cardiología" parece tener solo 2 médicos (25 %) y "Cardiologia" 1 (12,5 %). Es un caso típico de dato categórico que necesita normalización antes de contar.

### 3.4 Agenda: 25 bloques, 5 médicos y 20 horas semanales

Las 25 disponibilidades se distribuyen así:

- 5 bloques por cada día hábil: lunes, martes, miércoles, jueves y viernes;
- 0 bloques sábado y domingo;
- todos comienzan a las 08:00;
- todos duran 30 minutos;
- 5 médicos tienen 5 bloques cada uno: 1.200 minutos semanales = **20 horas**;
- 3 médicos no tienen agenda: 0 minutos;
- total: **6.000 minutos semanales = 100 horas** de atención publicada.

La variable `minutos_semanales_por_medico` toma solo dos valores: 0 (3 médicos) y 1.200 (5 médicos).

| Medida | Valor |
|---|---:|
| n | 8 |
| Media | 750 minutos |
| Mediana | 1.200 minutos |
| Moda | 1.200 minutos |
| Mínimo | 0 |
| Q1 | 0 |
| Q3 | 1.200 |
| Máximo | 1.200 |
| Rango | 1.200 |
| IQR | 1.200 |
| Varianza muestral | 385.714,2857 |
| Desvío muestral | 621,059 |
| Coeficiente de variación | 82,81 % |

Cálculo de la varianza muestral:

```text
Valores: 1200, 1200, 1200, 1200, 1200, 0, 0, 0
Media: 6000 / 8 = 750
Desviaciones: cinco de +450 y tres de -750
Suma de cuadrados:
  5 · 450² + 3 · 750²
  = 5 · 202.500 + 3 · 562.500
  = 1.012.500 + 1.687.500
  = 2.700.000
s² = 2.700.000 / (8 - 1) = 385.714,2857
s  = √385.714,2857 = 621,059
```

Boxplot de `minutos_semanales_por_medico`:

- mínimo 0; Q1 0; mediana 1.200; Q3 1.200; máximo 1.200;
- IQR 1.200; vallas de Tukey: -1.800 y 3.000;
- no hay outliers;
- la caja va de 0 a 1.200 con la mediana pegada al borde superior.

Es un boxplot **degenerado y bimodal**: no describe una distribución normal, sino dos grupos. La media de 750 minutos no representa a ningún médico típico: o tiene 0 horas o tiene 20 horas. En este caso, la mediana y la moda describen mejor la realidad que la media.

### 3.5 Triages: escala y calidad del dato

Hay 17 triages con estos niveles:

| Nivel | Frecuencia |
|---:|---:|
| 1 | 2 |
| 2 | 1 |
| 3 | 2 |
| 4 | 1 |
| 5 | 4 |
| 6 | 1 |
| 7 | 1 |
| 8 | 2 |
| 9 | 1 |
| 10 | 2 |
| **Total** | **17** |

Medidas:

- media: `92 / 17 = 5,4118`;
- mediana: 5;
- moda: 5 (4 casos);
- mínimo: 1; máximo: 10; rango: 9;
- Q1: 3; Q3: 8; IQR: 5;
- varianza muestral: 8,5074; desvío: 2,9167; CV: 53,90 %.

**Advertencia:** el material de la cátedra usa una escala de triage de 1 a 5. En la base aparecen valores 6, 7, 8, 9 y 10. Si la escala de negocio fuera 1 a 5, **7 de los 17 registros (41,18 %)** estarían fuera de rango. Si la escala real es 1 a 10, no hay error, pero la convención debe documentarse. El esquema `db.php:229` solo declara `nivel_gravedad INTEGER NOT NULL`; no tiene un `CHECK` que limite los valores. Es una mejora posible para el proyecto.

Además, tratándose de una variable ordinal, la media aritmética no es la medida más adecuada: la mediana y la moda son más informativas. Se calcula la media solo para comparar con el material, no para tomar decisiones clínicas.

### 3.6 Gráficos recomendados para el proyecto

| Variable | Gráfico | Motivo |
|---|---|---|
| Obra social | Barras | Cualitativa nominal |
| Estado de cita | Barras | Cualitativa nominal |
| Especialidad | Barras | Cualitativa nominal |
| Minutos semanales por médico | Boxplot o histograma | Cuantitativa con dos grupos |
| Nivel de triage | Barras ordenadas | Ordinal |
| Citas por día | Barras o línea | Serie temporal corta |
| Edad | Histograma | Cuantitativa, si existiera la fecha de nacimiento |
| Relación entre dos variables numéricas | Dispersión | Solo si hay dos variables numéricas comparables |

No se hace un gráfico de dispersión con los datos actuales porque no hay dos variables numéricas continuas con suficientes observaciones. Tampoco se hace un gráfico circular de obras sociales: con tres categorías es aceptable, pero las barras comparan mejor.

---

## 4. Probabilidad aplicada a los pacientes de SaludWEB

### 4.1 Experimento, espacio muestral y sucesos

Se elige **una ficha de paciente al azar** entre las 223 de la base. Para el ejercicio se consideran las 223 fichas igualmente probables (definición clásica o de Laplace).

- **Espacio muestral** `Ω`: las 223 fichas.
- **Suceso A:** la ficha elegida es de OSDE.
- **Suceso B:** la ficha elegida es de PAMI.
- **Suceso C:** la ficha elegida es Particular.
- **Suceso D:** la ficha elegida está activa.
- **Suceso Dᶜ:** la ficha elegida está inactiva.

Como las tres obras sociales son las únicas categorías y no se superponen:

- `A`, `B` y `C` son **mutuamente excluyentes**;
- `A ∪ B ∪ C = Ω`;
- `C = (A ∪ B)ᶜ`.

### 4.2 Probabilidades simples

```text
P(A) = 88/223  = 0,394619  = 39,46 %
P(B) = 67/223  = 0,300448  = 30,04 %
P(C) = 68/223  = 0,304933  = 30,49 %
P(D) = 114/223 = 0,511211  = 51,12 %
P(Dᶜ)= 109/223 = 0,488789  = 48,88 %
```

Verificación:

```text
P(A) + P(B) + P(C) = (88 + 67 + 68)/223 = 223/223 = 1
P(D) + P(Dᶜ) = 1
```

### 4.3 Unión e intersección

`A` y `B` son mutuamente excluyentes:

```text
P(A ∩ B) = 0
P(A ∪ B) = P(A) + P(B) = 155/223 = 0,695067 = 69,51 %
P(C) = 1 - P(A ∪ B) = 68/223 = 0,304933 = 30,49 %
```

Tabla cruzada entre OSDE y estar activo:

| | Activo (D) | Inactivo (Dᶜ) | Total |
|---|---:|---:|---:|
| OSDE (A) | 43 | 45 | 88 |
| No OSDE (Aᶜ) | 71 | 64 | 135 |
| **Total** | **114** | **109** | **223** |

De ahí:

```text
P(A ∩ D)   = 43/223 = 0,192825 = 19,28 %
P(A ∩ Dᶜ)  = 45/223 = 0,201794 = 20,18 %
P(Aᶜ ∩ D)  = 71/223 = 0,318386 = 31,84 %
P(Aᶜ ∩ Dᶜ) = 64/223 = 0,286996 = 28,70 %
```

Regla de la suma:

```text
P(A ∪ D) = P(A) + P(D) - P(A ∩ D)
         = 0,394619 + 0,511211 - 0,192825
         = 0,713005
         = 71,30 %
```

El complemento es `P(Aᶜ ∩ Dᶜ) = 64/223 = 0,286996 = 28,70 %`, que coincide con `1 - 0,713005`.

### 4.4 Probabilidad condicional

```text
P(D | A)  = P(A ∩ D) / P(A) = 43/88  = 0,488636 = 48,86 %
P(D | Aᶜ) = 71/135            = 0,525926 = 52,59 %
P(D)      = 114/223           = 0,511211 = 51,12 %
```

Dirección inversa:

```text
P(A | D) = P(A ∩ D) / P(D) = 43/114 = 0,377193 = 37,72 %
```

`P(D | A)` y `P(A | D)` son distintas: la primera pregunta "si es OSDE, ¿qué probabilidad hay de que esté activo?"; la segunda, "si está activo, ¿qué probabilidad hay de que sea OSDE?". Confundirlas es uno de los errores más frecuentes.

### 4.5 Independencia

Dos sucesos son independientes si:

```text
P(A ∩ B) = P(A) · P(B)
```

Para OSDE y activo:

```text
P(A ∩ D) = 0,192825
P(A) · P(D) = 0,394619 · 0,511211 = 0,201733
```

No son exactamente iguales: la diferencia es `-0,008908` (un 4,4 % relativo). La asociación es débil, pero en sentido estricto **no hay independencia exacta**. Para PAMI y activo:

```text
P(B ∩ D) = 35/223 = 0,156951
P(B) · P(D) = 0,300448 · 0,511211 = 0,153592
```

La diferencia es `+0,003359`; también es una asociación débil. Para el proyecto, se puede decir que las variables están **aproximadamente** equilibradas entre sí, pero no se debe afirmar independencia exacta sin un contraste formal.

Para OSDE y PAMI:

```text
P(A ∩ B) = 0
P(A) · P(B) = 0,394619 · 0,300448 = 0,1185
```

No son independientes. De hecho, **dos sucesos mutuamente excluyentes con probabilidad positiva son dependientes**, porque saber que ocurrió uno descarta al otro. "Mutuamente excluyente" no significa "independiente".

### 4.6 Probabilidad total

Las obras sociales forman una partición de `Ω`. Entonces:

```text
P(D) = P(D | A)·P(A) + P(D | B)·P(B) + P(D | C)·P(C)
     = (43/88)(88/223) + (35/67)(67/223) + (36/68)(68/223)
     = 43/223 + 35/223 + 36/223
     = 114/223
     = 0,511211
```

Es un ejemplo perfecto de por qué la probabilidad total funciona: cada rama aporta la parte de activos de su obra social, y la suma de esas partes es el total de activos.

### 4.7 Bayes

Si se sabe que la ficha elegida está activa, ¿cómo se reparte por obra social?

```text
P(A | D) = P(D | A)·P(A) / P(D) = (43/223) / (114/223) = 43/114 = 37,72 %
P(B | D) = 35/114 = 30,70 %
P(C | D) = 36/114 = 31,58 %
```

La suma es 100 %. Entre los activos hay algo más de OSDE y algo menos de PAMI/Particular que en el total, pero las diferencias son pequeñas.

### 4.8 Otros experimentos del proyecto

**Elegir un médico al azar (Ω = 8):**

```text
P(Cardiología) = 3/8 = 0,375 = 37,50 %   (normalizando la tilde)
P(activo)      = 5/8 = 0,625 = 62,50 %
```

Si no se normaliza el texto, `Cardiología` exacta tiene 2/8 = 25 % y `Cardiologia` 1/8 = 12,5 %. La diferencia no es estadística, es de calidad de datos.

**Elegir un bloque de agenda al azar (Ω = 25):**

```text
P(lunes) = 5/25 = 0,20 = 20 %
P(empieza a las 08:00) = 25/25 = 1
P(dura 30 minutos) = 25/25 = 1
```

Los dos últimos no son hallazgos interesantes: son consecuencias de cómo se cargaron los datos. Un buen análisis debe preguntarse si una probabilidad de 1 aporta información o solo describe una restricción del sistema.

---

## 5. Variables aleatorias y distribuciones

### 5.1 Variable aleatoria discreta con los datos del proyecto

Sea `X` = minutos semanales de agenda de un médico elegido al azar. Los valores posibles son 0 y 1.200:

```text
P(X = 0)    = 3/8 = 0,375
P(X = 1200) = 5/8 = 0,625

E[X] = 0·0,375 + 1200·0,625 = 750
E[X²] = 0²·0,375 + 1200²·0,625 = 900.000
Var(X) = E[X²] - (E[X])² = 900.000 - 750² = 337.500
σ = √337.500 = 580,95
```

La varianza poblacional es 337.500 y el desvío 580,95. La varianza muestral calculada antes (385.714,29) usa el divisor `n - 1` y es mayor. Las dos son correctas según el contexto: población completa versus muestra.

### 5.2 Binomial aplicada a los pacientes

Se eligen 10 fichas al azar. Sea `X` = cantidad de fichas OSDE entre las 10. Si se muestrea con reposición (o se aproxima la población finita), `X ~ Bi(n = 10, p = 0,394619)`.

```text
P(X = 4) = C(10,4)·0,394619⁴·(1-0,394619)⁶ = 0,250671
P(X ≤ 3) = 0,395866
P(X ≥ 1) = 1 - P(X = 0) = 1 - 0,006611 = 0,993389
P(X ≥ 5) = 1 - P(X ≤ 4) = 0,353463

E[X] = n·p = 3,9462
Var(X) = n·p·(1-p) = 2,3889
σ = 1,5456
```

La muestra es de 10 sobre 223 (4,48 % de la población), así que la aproximación binomial es razonable. Si se quisiera exactitud sin reposición, correspondería una hipergeométrica; el material de la cátedra trabaja con binomial, así que se deja explícita la simplificación.

### 5.3 Segundo recuperatorio: drones

**Enunciado:** un dron falla con probabilidad 0,03. Se prueban 80 drones. Si fallan más de 3, se rechaza el lote.

Sea `X` = cantidad de drones que fallan. `X ~ Bi(80, 0,03)`.

```text
P(X = 0) = 0,087446
P(X = 1) = 0,216361
P(X = 2) = 0,264317
P(X = 3) = 0,212543
P(X ≤ 3) = 0,780667
P(X > 3) = 1 - 0,780667 = 0,219333
```

**Resultado:** la probabilidad de rechazar el lote es **21,93 %**.

```text
E[X] = 80 · 0,03 = 2,4 fallas
Var(X) = 80 · 0,03 · 0,97 = 2,328
σ = 1,5258
```

No conviene aproximar por normal: `n·p = 2,4 < 5`. La binomial exacta es la herramienta adecuada.

### 5.4 Segundo recuperatorio: sensores de temperatura

**Enunciado:** la temperatura `X` se distribuye normal con `μ = 80 °C` y `σ = 4 °C`.

Estandarización:

```text
Z = (X - 80) / 4
```

Cálculos:

```text
P(X < 75) = P(Z < -1,25) = 0,1056 = 10,56 %
P(75 < X < 85) = P(-1,25 < Z < 1,25) = 0,7887 = 78,87 %
P(X > 90) = P(Z > 2,5) = 0,0062 = 0,62 %
```

Si se considera fuera de rango a todo valor que se aleje más de 5 °C de la media, el rango aceptado es `[75, 85]`:

```text
P(fuera de rango) = P(X < 75) + P(X > 85)
                  = 0,1056 + 0,1056
                  = 0,2113
                  = 21,13 %
```

**Resultado:** el 78,87 % de las mediciones cae dentro de ±5 °C y el 21,13 % queda fuera.

### 5.5 Segundo recuperatorio: streaming

**Enunciado:** llegan en promedio 5 accesos por segundo. Sea `X` = accesos en un segundo. `X ~ Poisson(λ = 5)`.

```text
P(X = 0) = e⁻⁵·5⁰/0! = 0,006738 = 0,67 %
P(X = 1) = 0,033690 = 3,37 %
P(X = 2) = 0,084224 = 8,42 %
P(X = 3) = 0,140374 = 14,04 %
P(X ≤ 3) = 0,265026 = 26,50 %
P(4 ≤ X ≤ 6) = 0,497158 = 49,72 %
P(X ≥ 10) = 1 - P(X ≤ 9) = 0,031828 = 3,18 %
```

Si la pregunta fuera "más de 10" y no "al menos 10":

```text
P(X > 10) = P(X ≥ 11) = 0,013695 = 1,37 %
```

Propiedades:

```text
E[X] = λ = 5
Var(X) = λ = 5
σ = √5 = 2,236
```

La distribución de Poisson es adecuada para contar eventos raros en un intervalo fijo, con eventos independientes y tasa constante. En el proyecto no se midió la tasa real de accesos: los 8 eventos de `eventos_realtime` son de pruebas y ocurrieron en un segundo, así que no sirven para estimar λ de producción.

### 5.6 Cuándo usar cada distribución

| Distribución | Cuándo | Ejemplo |
|---|---|---|
| Binomial | Cantidad de éxitos en `n` ensayos independientes con probabilidad `p` constante | Drones que fallan, pacientes OSDE en una muestra |
| Poisson | Cantidad de eventos en un intervalo fijo, con tasa `λ` | Accesos por segundo |
| Normal | Variable continua con distribución simétrica alrededor de una media | Temperatura de un sensor |
| Hipergeométrica | Éxitos en muestreo sin reposición de una población finita | 10 pacientes OSDE sacados de 223 sin reposición |

---

## 6. Guía N°1: verificación de los 21 ejercicios

La Guía N°1 ya trae soluciones. Se revisaron una por una. La mayoría está bien; hay **dos errores de resultado**, una **justificación conceptual incorrecta** y un ejercicio que **no se puede verificar porque falta la tabla**.

### 6.1 Tabla de resultados

| N° | Resultado correcto | Estado |
|---:|---|---|
| 1 | `Ω = {CC, CS, SC, SS}`; `P(al menos una cara) = 3/4 = 0,75` | Correcto |
| 2 | `P(2)=P(4)=P(6)=2/9`; `P(1)=P(3)=P(5)=1/9`; `P(<4)=4/9 ≈ 0,444` | Correcto |
| 3 | a) `25/53 ≈ 0,472`; b) `18/53 ≈ 0,340` | Correcto |
| 4 | `8/36 = 2/9 ≈ 0,222` | Correcto |
| 5 | `P(A∪B) = 0,8 + 0,6 - 0,5 = 0,9` | Correcto |
| 6 | `0,09 + 0,15 + 0,21 + 0,23 = 0,68` | Correcto |
| 7 | a) 0,15; b) 0,30; c) 0,55; d) `425/500 = 0,85` | Correcto |
| 8 | a) `88/500 = 0,176`; b) `31/500 = 0,062`; c) **`171/500 = 0,342`** | **Corregido** |
| 9 | `P(S∩B) = 0,3`; `P(ninguno) = 0,2` | Correcto |
| 10 | `P(A∪B) = 0,75`; `P(ninguno) = 0,25` | Correcto |
| 11 | `P(F∪C) = 0,27`; `P(no F y no C) = 0,73`; son dependientes | Resultado correcto, **justificación corregida** |
| 12 | a) `5/36 ≈ 0,139`; b) `10/36 = 5/18 ≈ 0,278` | Correcto |
| 13 | a) `28/84 = 1/3 ≈ 0,333`; b) `30/84 = 5/14 ≈ 0,357` | Correcto; el 0,43 inicial es un error de tipeo |
| 14 | `P(M∪H) = 0,88`; `P(ninguno) = 0,12`; `P(solo H) = 0,34` | Correcto |
| 15 | `1/9 ≈ 0,111` | Correcto |
| 16 | a) `0,32`; b) **`0,68`** | **Corregido** |
| 17 | a) `0,58`; b) `0,96` | Correcto |
| 18 | a) `0,80`; b) `0,45`; c) `0,55` | Correcto |
| 19 | `P(N)=0,32`; `P(FH)=0,87`; `P(CI)=0,13`; `P(V∪N)=0,63` | **No verificable: falta la tabla** |
| 20 | a) `0,60`; b) `0,889` | Correcto |
| 21 | `P(B)=0,001`; `P(C)=0,009`; `P(B∪C)=0,010` | Correcto |

### 6.2 Corrección del ejercicio 8c

Datos: 500 estudiantes; `F = 210` fuman, `A = 258` consumen alcohol, `C = 216` comen entre comidas; `F∩A = 122`, `C∩A = 83`, `F∩C = 97`, `F∩A∩C = 52`.

La pregunta es "no fume **ni** coma entre comidas", es decir, el complemento de `F ∪ C`:

```text
|F ∪ C| = |F| + |C| - |F ∩ C|
        = 210 + 216 - 97
        = 329

|(F ∪ C)ᶜ| = 500 - 329 = 171
P = 171/500 = 0,342 = 34,2 %
```

El valor `105/500` que aparece en el material es otra cosa: es la cantidad de estudiantes que **solo consumen alcohol**:

```text
|A ∩ Fᶜ ∩ Cᶜ| = 258 - 122 - 83 + 52 = 105
```

Por eso `105/500` no responde a la pregunta formulada. La respuesta correcta es **171/500**.

### 6.3 Corrección del ejercicio 16b

La tabla de la oficina suma:

```text
P(dormitorio) = 0,03 + 0,15 + 0,14 = 0,32
```

El complemento es:

```text
P(no dormitorio) = 1 - 0,32 = 0,68
```

El material dice 0,88, que no surge de la tabla. La respuesta correcta es **0,68**.

### 6.4 Corrección conceptual del ejercicio 11

El material concluye que `F` y `C` no son independientes "porque su intersección no es vacía". Eso no es correcto: dos sucesos pueden tener intersección no vacía y ser independientes.

La razón correcta es:

```text
P(F) = 0,25
P(C) = 0,17
P(F ∩ C) = 0,15

P(F) · P(C) = 0,25 · 0,17 = 0,0425
P(F ∩ C) = 0,15 ≠ 0,0425
```

Como el producto no coincide con la intersección, son **dependientes**. La intersección no vacía solo dice que pueden ocurrir juntos; no dice si la ocurrencia de uno cambia la probabilidad del otro.

### 6.5 Ejercicio 19: falta la tabla

El texto recibido no incluye la tabla de accidentes por turno y causa. Por eso no se pueden verificar los valores `P(N)=0,32`, `P(FH)=0,87`, `P(CI)=0,13` y `P(V∪N)=0,63`. Las fórmulas usadas en el material son correctas:

```text
P(V ∪ N) = P(V) + P(N) - P(V ∩ N)
```

Si los turnos son mutuamente excluyentes, `P(V∩N)=0` y la unión es la suma. Pero sin la tabla no se puede confirmar si los datos la cumplen. También hay una inconsistencia en el texto: dice "2 % + 32 % = 32 %", cuando 2 + 32 = 34. Puede ser un error de tipeo, pero no se corrige sin la tabla original.

### 6.6 Ejercicio 13a: el 0,43 inicial

El material primero escribe 0,43 y después corrige a 0,33. El valor exacto es:

```text
P(el diccionario esté entre los 3) = C(8,2) / C(9,3)
                                     = 28 / 84
                                     = 1/3
                                     ≈ 0,3333
```

La respuesta correcta es **1/3**, no 0,43.

### 6.7 Ejercicio 8a: por qué 88/500 sí es correcto

"Fume pero no consuma bebidas alcohólicas" no excluye comer entre comidas. Por eso:

```text
|F ∩ Aᶜ| = |F| - |F ∩ A| = 210 - 122 = 88
P = 88/500 = 0,176
```

Si la pregunta hubiera sido "fuma, no consume alcohol **y** no come entre comidas", el resultado sería `43/500`, no `88/500`. La diferencia está en el alcance de la conjunción.

---

## 7. Guía integradora y parciales: método de resolución

### 7.1 Guía integradora con variables de hogares

La guía integradora trabaja con `edad`, `ingreso_familiar`, `sexo` y `hacinamiento`. No se recibió la base de hogares, así que no se inventan resultados. El procedimiento correcto sería:

1. **Población y muestra:** definir todos los hogares relevados y la muestra efectiva.
2. **Clasificación:**
   - `edad`: cuantitativa;
   - `ingreso_familiar`: cuantitativa continua;
   - `sexo`: cualitativa nominal;
   - `hacinamiento`: cuantitativa discreta u ordinal, según cómo se mida.
3. **Descriptiva:** tabla de frecuencias de `sexo`; media, mediana y desvío de `edad` e `ingreso`; boxplot de ingreso; gráfico de barras de hacinamiento.
4. **Comparación:** ingreso medio/mediano por sexo o por categoría de hacinamiento, con boxplots paralelos.
5. **Asociación:** tabla de contingencia si se categoriza el ingreso; correlación solo si ambas variables son cuantitativas.
6. **Conclusión:** describir la muestra y aclarar que no se puede generalizar sin muestreo aleatorio.

En SaludWEB no existen edad, ingreso ni hacinamiento. La adaptación posible es usar obra social, estado activo y minutos de agenda, como se hizo en las secciones 3 y 4. No se fuerza una equivalencia que el sistema no tiene.

### 7.2 Primer parcial y recuperatorio de `CentroBienestar`

Los archivos `AILENQUAGLINOFAIL.xlsx`, `CentroBienestar_recuperatorio.xlsx` y `USUARIOS_APP. AilenQuaglinofail.xlsx` no están versionados. Los enlaces de Moodle requieren sesión. Sin esos datos no se pueden dar resultados numéricos. El protocolo de resolución sería:

1. abrir la planilla y verificar tipos de columnas y valores faltantes;
2. separar variables cualitativas, ordinales y cuantitativas;
3. hacer tablas de frecuencia y medidas de resumen;
4. elegir gráficos según el tipo de variable;
5. si hay dos cualitativas, tabla de contingencia y comparación de proporciones;
6. si hay una cuantitativa y una cualitativa, comparar medias/medianas por grupo;
7. si hay dos cuantitativas, dispersión y correlación;
8. cerrar con una conclusión descriptiva y una advertencia de muestreo.

La parte que sí se pudo resolver con enunciado completo es el segundo recuperatorio, en la sección 5.

---

## 8. Inferencia: por qué este documento no da conclusiones poblacionales

La estadística inferencial requiere:

- una población definida;
- un marco muestral;
- un método de selección aleatorio o cuasi-aleatorio;
- un tamaño de muestra justificado;
- un nivel de confianza y un error admitido.

En la Guía N°2 se desconoce cómo se seleccionaron los 500 estudiantes. En SaludWEB, la base es de desarrollo y no representa a todos los pacientes. Por eso:

- las proporciones son **frecuencias observadas**;
- las medias son **estadísticos descriptivos**;
- no se calculan intervalos de confianza;
- no se hacen pruebas de hipótesis;
- no se afirma causalidad.

Esto no le quita valor al trabajo: describe con precisión lo que hay y deja claro hasta dónde se puede afirmar. En una defensa, decir "no puedo inferir porque la muestra no es aleatoria" es más correcto que inventar un intervalo de confianza.

---

## 9. Errores frecuentes que este trabajo ayuda a evitar

| Error | Por qué está mal | Cómo se corrige |
|---|---|---|
| Confundir mutuamente excluyente con independiente | Dos sucesos disjuntos con probabilidad positiva son dependientes | Verificar `P(A∩B) = P(A)P(B)` |
| Usar `P(A|B)` como `P(B|A)` | La condicional no es simétrica | Escribir explícitamente el condicionante |
| Decir que una intersección no vacía implica dependencia | La dependencia se mide con el producto | Comparar `P(A∩B)` con `P(A)P(B)` |
| Usar `n` en la varianza muestral | La muestra usa `n-1` para corregir el sesgo | Población: `n`; muestra: `n-1` |
| Comparar medias de variables ordinales | La media de una escala ordinal no siempre tiene sentido | Usar mediana y moda |
| Usar la media con distribución bimodal | La media cae entre dos grupos y no representa a ninguno | Usar mediana, moda o mostrar los grupos |
| Estimar ausentismo con una sola cita | `1/1` no es una tasa estable | Recolectar más citas o no estimar |
| Generalizar los 500 estudiantes a toda la carrera | No hay muestreo aleatorio documentado | Describir la muestra y aclarar el alcance |
| Contar `Cardiología` y `Cardiologia` como categorías distintas | Es el mismo valor con distinta tilde | Normalizar antes de contar |
| Tratar niveles 6-10 como válidos en una escala 1-5 | Puede haber error de carga | Definir la escala y agregar validación |

---

## 10. Trazabilidad con el código y la base

### 10.1 Tablas usadas

| Tabla | Línea del esquema | Campos usados |
|---|---|---|
| `pacientes` | `db.php:211` | `id`, `id_obra_social`, `activo` |
| `triages` | `db.php:226` | `nivel_gravedad` |
| `medicos` | `db.php:235` | `especialidad`, `activo` |
| `usuarios` | `db.php:285` | `tipo_usuario`, `activo` |
| `disponibilidades` | `db.php:357` | `dia_semana`, `hora_inicio`, `hora_fin`, `duracion_minutos` |
| `citas` | `db.php:392` | `estado`, `duracion_minutos`, `creado_por_paciente`, `recordatorio_enviado` |
| `notificaciones` | `db.php:458` | `tipo`, `canal`, `estado`, `intentos` |
| `eventos_realtime` | `db.php:510` | `canal`, `tipo`, `creado_at` |

Endpoints relacionados:

- `GET /api/medicos` en `routes.php:125`;
- `GET /api/pacientes` en `routes.php:148`;
- `GET /api/disponibilidades` en `routes.php:546`;
- `GET /api/citas` en `routes.php:611`;
- `GET /api/estadisticas` en `routes.php:726`;
- `GET /api/eventos` en `routes.php:872`.

### 10.2 Consultas reproducibles

Pacientes por obra social:

```sql
SELECT os.nombre_obra, COUNT(*) AS cantidad
FROM pacientes p
JOIN obras_sociales os ON os.id = p.id_obra_social
GROUP BY os.nombre_obra
ORDER BY cantidad DESC;
```

Pacientes por obra social y estado:

```sql
SELECT os.nombre_obra, p.activo, COUNT(*) AS cantidad
FROM pacientes p
JOIN obras_sociales os ON os.id = p.id_obra_social
GROUP BY os.nombre_obra, p.activo
ORDER BY os.nombre_obra, p.activo;
```

Disponibilidades por médico:

```sql
SELECT m.nombre, COUNT(*) AS bloques,
       SUM((strftime('%s', '1970-01-01 ' || d.hora_fin) -
            strftime('%s', '1970-01-01 ' || d.hora_inicio)) / 60) AS minutos_semanales
FROM disponibilidades d
JOIN medicos m ON m.id = d.id_medico
GROUP BY m.id, m.nombre
ORDER BY minutos_semanales DESC;
```

Triages por nivel:

```sql
SELECT nivel_gravedad, COUNT(*) AS cantidad
FROM triages
GROUP BY nivel_gravedad
ORDER BY nivel_gravedad;
```

Citas por estado:

```sql
SELECT estado, COUNT(*) AS cantidad
FROM citas
GROUP BY estado
ORDER BY cantidad DESC;
```

### 10.3 Fuente de la Guía N°2

La hoja pública se descargó desde:

<https://docs.google.com/spreadsheets/d/1ckn8P-ggoVHInUX7M8vFP0VCR0m6TNEscgmm68Mp4iI/edit?usp=sharing>

Se trabajó con los agregados. El CSV completo no se versionó en el repositorio para no mezclar datos de estudiantes con el código del proyecto.

---

## 11. Resultados principales en una tabla

| Indicador | Valor |
|---|---:|
| Pacientes en la base | 223 |
| Pacientes activos | 114 (51,12 %) |
| Pacientes OSDE | 88 (39,46 %) |
| Pacientes PAMI | 67 (30,04 %) |
| Pacientes Particular | 68 (30,49 %) |
| P(activo \| OSDE) | 48,86 % |
| P(OSDE \| activo) | 37,72 % |
| Médicos | 8 |
| Médicos activos | 5 (62,50 %) |
| Bloques de agenda | 25 |
| Médicos con agenda | 5 |
| Horas semanales de agenda | 100 |
| Media de minutos semanales por médico | 750 |
| Mediana de minutos semanales por médico | 1.200 |
| Desvío de minutos semanales por médico | 621,06 |
| Triages | 17 |
| Mediana de nivel de triage | 5 |
| Citas | 1 |
| Guía N°2: estudiantes | 500 |
| Guía N°2: edad media | 26,688 |
| Guía N°2: horas de estudio medias | 14,012 |
| Guía N°2: asistencia media | 82,0762 % |
| Guía N°2: nota media | 6,442 |
| Guía N°2: uso de Git | 67,4 % |
| Guía N°2: chi² SO-Git | 0,23 (gl 2; p ≈ 0,891) |

---

## 12. Conclusión

El material de Probabilidad y Estadística se puede aplicar a SaludWEB de tres maneras concretas:

1. **Para describir la base:** obras sociales, estado de pacientes, especialidades, agenda y triages, con la precaución de que es una base de desarrollo.
2. **Para modelar probabilidades:** elegir una ficha al azar, construir sucesos, calcular uniones, intersecciones, complementos, condicionales, probabilidad total y Bayes con datos reales.
3. **Para resolver las guías:** la Guía N°1 queda verificada y corregida; la Guía N°2 queda resuelta con sus 500 respuestas; el segundo recuperatorio queda resuelto con binomial, normal y Poisson.

La conclusión más importante es metodológica: **antes de calcular hay que mirar los datos**. Una tabla con una sola cita no permite estimar ausentismo; una especialidad escrita de dos formas rompe un conteo; una escala de triage sin convención documentada produce medias que no significan lo mismo para todos. La estadística es útil cuando el dato está bien definido y cuando se explicita qué se puede y qué no se puede concluir.

No se modificó el código de la aplicación. Este documento es material de estudio y de defensa, con las consultas y los cálculos reproducibles.

---

## Anexo A. Fórmulas usadas

```text
P(A) = casos favorables / casos posibles          (Laplace)
P(Aᶜ) = 1 - P(A)
P(A ∪ B) = P(A) + P(B) - P(A ∩ B)
P(A ∩ B) = 0                                      (si A y B son excluyentes)
P(A | B) = P(A ∩ B) / P(B),  P(B) > 0
P(A ∩ B) = P(A | B) · P(B)
P(A ∩ B) = P(A) · P(B)                            (si A y B son independientes)
P(B) = Σ P(B | Aᵢ) · P(Aᵢ)                        (probabilidad total)
P(Aᵢ | B) = P(B | Aᵢ) · P(Aᵢ) / P(B)              (Bayes)

Media muestral: x̄ = Σx / n
Varianza muestral: s² = Σ(x - x̄)² / (n - 1)
Varianza poblacional: σ² = Σ(x - x̄)² / n
Desvío: s = √s²
Coeficiente de variación: CV = s / x̄ · 100
Rango: máx - mín
IQR = Q3 - Q1
Valla inferior de Tukey = Q1 - 1,5 · IQR
Valla superior de Tukey = Q3 + 1,5 · IQR

Binomial: P(X = k) = C(n,k) · pᵏ · (1-p)ⁿ⁻ᵏ
E[X] = n·p;  Var(X) = n·p·(1-p)
Poisson: P(X = k) = e⁻λ · λᵏ / k!
E[X] = Var(X) = λ
Normal: Z = (X - μ) / σ
```

## Anexo B. Glosario breve

- **Población:** conjunto completo de interés.
- **Muestra:** subconjunto observado de la población.
- **Parámetro:** medida de la población.
- **Estadístico:** medida calculada en la muestra.
- **Variable cualitativa:** describe una categoría.
- **Variable cuantitativa:** describe una cantidad numérica.
- **Frecuencia absoluta:** cantidad de veces que aparece un valor.
- **Frecuencia relativa:** proporción respecto del total.
- **Media:** suma dividida por la cantidad de datos.
- **Mediana:** valor central cuando los datos se ordenan.
- **Moda:** valor más frecuente.
- **Varianza:** promedio de las distancias al cuadrado respecto de la media.
- **Desvío estándar:** raíz cuadrada de la varianza.
- **Cuartil:** valor que deja un porcentaje de datos por debajo.
- **IQR:** distancia entre el tercer y el primer cuartil.
- **Outlier:** valor alejado del resto según un criterio, por ejemplo Tukey.
- **Experimento aleatorio:** experimento cuyo resultado no se puede predecir con certeza.
- **Espacio muestral:** conjunto de todos los resultados posibles.
- **Suceso:** subconjunto del espacio muestral.
- **Sucesos mutuamente excluyentes:** no pueden ocurrir juntos.
- **Sucesos independientes:** la ocurrencia de uno no cambia la probabilidad del otro.
- **Probabilidad condicional:** probabilidad de un suceso dado que ocurrió otro.
- **Probabilidad total:** probabilidad de un suceso a partir de una partición.
- **Bayes:** actualización de una probabilidad a partir de información nueva.
- **Binomial:** cuenta éxitos en `n` ensayos independientes.
- **Poisson:** cuenta eventos en un intervalo con tasa promedio.
- **Normal:** distribución continua simétrica en forma de campana.
