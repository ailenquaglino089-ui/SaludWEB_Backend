# Organización empresarial aplicada a SaludWEB

**Materia:** Organización Empresarial<br>
**Profesora:** Kranevitter, María Cecilia<br>
**Carrera:** Tecnicatura en Programación<br>
**Institución:** UTN Facultad Regional Paraná<br>
**Autora:** Ailen Quaglino<br>
**Proyecto:** SaludWEB (Backend PHP/SQLite, Web React y Mobile React Native/Expo)<br>
**Fecha de la aplicación:** 25/10/2026

---

## Índice

0. [Introducción y alcance](#0-introducción-y-alcance)
1. [La administración: conceptos que ordenan el análisis](#1-la-administración-conceptos-que-ordenan-el-análisis)
2. [Unidad 1 - Actividad 1](#2-unidad-1---actividad-1)
3. [Las organizaciones y SaludWEB](#3-las-organizaciones-y-saludweb)
4. [Unidad 1 - Actividad 2: organigramas](#4-unidad-1---actividad-2-organigramas)
5. [Documentos comerciales aplicados a SaludWEB](#5-documentos-comerciales-aplicados-a-saludweb)
6. [El administrador](#6-el-administrador)
7. [El emprendedor](#7-el-emprendedor)
8. [Eficacia, eficiencia y economicidad](#8-eficacia-eficiencia-y-economicidad)
9. [Diseño de la estructura organizativa](#9-diseño-de-la-estructura-organizativa)
10. [Trabajo Integrador Final aplicado](#10-trabajo-integrador-final-aplicado)
11. [Plan de negocios aplicado](#11-plan-de-negocios-aplicado)
12. [Conclusión](#12-conclusión)
13. [Trazabilidad, límites y bibliografía](#13-trazabilidad-límites-y-bibliografía)

---

## 0. Introducción y alcance

Este documento aplica al proyecto **SaludWEB** el material de la cátedra de Organización Empresarial. No reemplaza la entrega: la complementa y la ordena. Cada concepto se explica y después se vincula con una decisión, un rol, un proceso o una tabla del sistema.

Se trabajan:

- la administración como ciencia, técnica y arte;
- el proceso administrativo: planificación, organización, dirección y control;
- la previsión dentro de la planificación;
- las organizaciones, sus características, recursos y clasificación;
- la estructura, los organigramas y los niveles de decisión;
- la Actividad 1 y la Actividad 2 de la Unidad 1;
- los documentos comerciales y su circuito;
- los roles y habilidades del administrador;
- el emprendedor y sus diferencias con el empresario;
- eficacia, eficiencia y economicidad;
- el diseño de estructuras según Mintzberg;
- el Trabajo Integrador Final y el plan de negocios.

### Cómo leerlo

Hay tres niveles de afirmación:

1. **Datos del proyecto:** surgen del repositorio, del README, de `db.php` o de `routes.php`.
2. **Propuestas de organización:** son decisiones sugeridas para SaludWEB, no hechos consumados. Se marcan como “propuesta”.
3. **Ejemplos didácticos:** son números o documentos armados para practicar. Se marcan como “ejemplo” y no deben tomarse como datos reales de mercado, contables o legales.

> **Aclaración importante.** SaludWEB es hoy un proyecto académico y un sistema funcional. No es una empresa constituida con dotación, facturación y estructura formal. Por eso, cuando se habla de “la organización SaludWEB”, se analiza como **emprendimiento en etapa inicial**. Cuando se propone un organigrama, se aclara que es un diseño deseable para esa etapa, no un reflejo de puestos que ya existan.

### Fuentes usadas

| Fuente | Uso |
|---|---|
| Material de cátedra de Organización Empresarial (Moodle FRP) | Conceptos, actividades y guías |
| Actividad 1: proceso administrativo | Preguntas 1 a 3 |
| Actividad 2: Mercería SA y Design & Harmony | Organigramas y detección de errores |
| Capítulo II: Documentos Comerciales | Circuito y aplicación de comprobantes |
| El administrador y el emprendedor | Roles, habilidades y tipos |
| Diseño de la estructura organizativa | Mintzberg, variables y configuraciones |
| Trabajo Integrador Final 2025 | Organización, proceso, entorno, macro y FODA |
| Guía del plan de negocios | Estructura y proyecciones |
| Repositorio SaludWEB | Evidencia real del sistema y de sus procesos |

---

## 1. La administración: conceptos que ordenan el análisis

### 1.1 Qué es administrar

Administrar es **coordinar recursos y personas para alcanzar propósitos**. La administración estudia cómo se dirigen las organizaciones y cómo se combinan recursos humanos, materiales, financieros, tecnológicos y de información para lograr objetivos.

La definición tiene tres consecuencias prácticas:

- no se administra “en abstracto”: se administra una organización concreta, con un propósito;
- administrar no es solo decidir: también es organizar, comunicar, dirigir y controlar;
- los recursos son limitados, por lo que siempre hay que elegir entre alternativas.

En SaludWEB, administrar significa decidir qué problema se resuelve primero (por ejemplo, la reserva de turnos), cómo se organiza el equipo, cómo se comunica el producto y cómo se comprueba que lo hecho funciona. El sistema ya tiene una API, una web y una app móvil; la administración aparece cuando se decide **qué construir, para quién, con qué recursos y cómo medir el resultado**.

### 1.2 Universalidad de la administración

La administración es universal porque se necesita en organizaciones de todo tipo y tamaño, en todos los niveles y en todas las áreas. No es exclusiva de las empresas grandes ni de los gerentes de alto nivel.

La diferencia está en la **profundidad y el énfasis** con que se aplican las funciones, no en la existencia de esas funciones. Un supervisor de pruebas de software y el CEO de una empresa tecnológica planifican, organizan, dirigen y controlan; lo hacen con distinto alcance y distinta proporción de tareas.

En SaludWEB se ve así:

- la persona que define el rumbo del producto planifica;
- quien ordena los módulos y asigna tareas organiza;
- quien acompaña al equipo, resuelve dudas y comunica prioridades dirige;
- quien revisa pruebas, errores, tiempos y métricas controla.

### 1.3 Administración como ciencia, técnica y arte

| Dimensión | Qué aporta | Cómo se ve en SaludWEB |
|---|---|---|
| Ciencia | Conocimiento sistemático, hipótesis, leyes y explicaciones | Analizar por qué un turno se cancela, formular hipótesis y medir |
| Técnica | Procedimientos, normas, herramientas y métodos | Organigramas, presupuestos, casos de prueba, manuales, tableros |
| Arte | Percepción, creatividad, liderazgo y experiencia | Decidir un rumbo con información incompleta y sostener al equipo |

La administración no es una ciencia exacta: es una **ciencia social** porque su objeto son las organizaciones y las personas. Sus conclusiones no son universales ni eternas; dependen del contexto, de la cultura y de la situación.

### 1.4 El proceso administrativo

El enfoque clásico de Fayol hablaba de cinco funciones: **planear, organizar, mandar, coordinar y controlar**. Hoy se resumen en cuatro:

1. **Planificación:** definir misión, visión, valores, objetivos, estrategias y planes.
2. **Organización:** dividir el trabajo, agrupar tareas, asignar responsabilidades y establecer autoridad.
3. **Dirección:** motivar, comunicar, liderar, resolver conflictos y acompañar a las personas.
4. **Control:** comparar lo realizado con lo planificado, detectar desvíos y corregir.

El proceso no es una línea recta. El control alimenta nuevamente la planificación: si los resultados no coinciden con los objetivos, se corrige el plan, la organización o la ejecución.

### 1.5 La previsión dentro del proceso

La **previsión** es el esfuerzo por anticipar lo que puede ocurrir: demanda, costos, riesgos, necesidades de personal o comportamiento del entorno. No es lo mismo que planificar:

- **prever** es estimar un escenario futuro;
- **planificar** es decidir qué se va a hacer para alcanzar un objetivo en ese escenario.

En el esquema moderno de cuatro funciones, la previsión no aparece como una quinta función separada: es una **etapa intelectual dentro de la planificación**. En cambio, en una lectura amplia del proceso administrativo, sí se la puede considerar parte de él porque sin anticipación no se puede planificar con realismo.

En SaludWEB, prever significa estimar:

- cuántos pacientes van a reservar en una semana;
- cuántos profesionales y bloques de agenda se necesitan;
- cuánto costará el hosting y el envío de notificaciones;
- qué pasa si un profesional cancela o si falla el servidor;
- cuánto tiempo llevará incorporar una clínica nueva.

---

## 2. Unidad 1 - Actividad 1

### 2.1 Pregunta 1: ¿cuál es la función final de la administración? Explique su importancia

La función final del proceso administrativo es el **control**. Controlar es comparar el rendimiento real con los objetivos y planes establecidos, identificar desvíos y adoptar medidas correctivas.

El control no es solo “vigilar” o “castigar”. Es un proceso de aprendizaje y de dirección:

1. se fijan estándares o indicadores;
2. se mide el resultado real;
3. se compara con el estándar;
4. se analiza la causa del desvío;
5. se corrige el plan, el proceso o la ejecución;
6. se vuelve a medir.

Su importancia es alta por cinco razones:

- **Cierra el ciclo administrativo:** sin control no se sabe si lo planificado funcionó.
- **Detecta desvíos a tiempo:** permite corregir antes de que el problema crezca.
- **Asigna responsabilidades:** cada área sabe qué se espera de ella y cómo se mide.
- **Mejora la calidad:** los errores se convierten en información para mejorar.
- **Reduce riesgos:** en salud, un turno mal asignado, una notificación fallida o un dato expuesto pueden tener consecuencias graves.

El control debe ser **concomitante con la ejecución**, no solo posterior. Esperar al final para controlar puede significar perder tiempo, recursos o confianza. En SaludWEB esto se traduce en:

- tablero de indicadores y estadísticas (`GET /api/estadisticas`, `routes.php:726`);
- auditoría de operaciones (`auditoria`, `db.php:275`);
- pruebas automatizadas y suites de roles;
- logs y `X-Correlation-Id` para seguir un pedido;
- revisión de citas, cancelaciones, notificaciones y ocupación;
- control de acceso por rol en el backend y en la interfaz.

**Conclusión:** el control es la función final en la secuencia, pero no es el “último paso y listo”. Es el mecanismo que devuelve información al sistema para que la organización pueda corregirse.

### 2.2 Pregunta 2: ¿se considera a la previsión parte del proceso administrativo?

Sí, aunque con una precisión importante: **la previsión es parte de la planificación**, no una quinta función independiente en el esquema moderno de cuatro funciones.

Fundamentos:

- Fayol incluía la previsión en su función de “prever/planear”; luego el proceso se resumió en planificación, organización, dirección y control.
- Prever es anticipar escenarios; planificar es decidir objetivos, estrategias y cursos de acción. La previsión alimenta la planificación, pero no la reemplaza.
- Sin previsión, la planificación se vuelve reactiva: se decide sobre el pasado y no sobre lo que puede pasar.
- En organizaciones pequeñas, la previsión suele ser informal; en organizaciones grandes, se formaliza con presupuestos, estudios de demanda y planificación estratégica.

En SaludWEB, la previsión aparece cuando se estima la demanda de turnos, la cantidad de profesionales necesarios, el costo de infraestructura o el volumen de notificaciones. Esa estimación no es todavía un plan: es la base para decidir cuántos recursos asignar y qué metas fijar.

### 2.3 Pregunta 3: elegir una organización y aplicar el proceso administrativo

**Organización elegida:** SaludWEB, plataforma de gestión de turnos médicos en línea.

#### Planificación

La planificación define el rumbo. Para SaludWEB se propone:

- **Misión:** facilitar el acceso a turnos médicos y mejorar la gestión de la agenda de profesionales y establecimientos de salud.
- **Visión:** ser una plataforma accesible y confiable para la gestión de turnos en Argentina, integrada con profesionales, consultorios y obras sociales.
- **Valores:** privacidad de los datos, transparencia, accesibilidad, calidad, mejora continua y respeto por el trabajo profesional.
- **Objetivos iniciales:**
  - permitir que un paciente reserve un turno en menos de tres minutos;
  - reducir las llamadas telefónicas y los turnos duplicados;
  - dar al profesional una agenda actualizada;
  - generar información confiable para la gestión;
  - proteger los datos personales y clínicos.
- **Estrategias:**
  - comenzar con un piloto en consultorios pequeños;
  - ofrecer una experiencia web y móvil simple;
  - integrar notificaciones y recordatorios;
  - construir confianza con seguridad y trazabilidad;
  - medir resultados antes de ampliar.

#### Organización

La organización divide el trabajo y asigna responsabilidades. En una etapa inicial, la estructura debe ser simple:

- **Dirección general:** rumbo, alianzas, decisiones estratégicas.
- **Producto y tecnología:** backend, web, mobile, pruebas, seguridad e infraestructura.
- **Operaciones y experiencia:** alta de profesionales, agendas, soporte y notificaciones.
- **Comercial y alianzas:** consultorios, clínicas y obras sociales.
- **Administración y finanzas:** facturación, cobros, pagos y obligaciones.
- **Asesorías:** legal, contable y protección de datos.

Los **roles del sistema** (`admin`, `medico`, `paciente`) no son puestos de trabajo. Son permisos de uso. Un administrador del sistema puede ser una persona del equipo o un responsable de la clínica; un médico es un usuario profesional; un paciente es un usuario final.

#### Dirección

La dirección consiste en lograr que las personas trabajen hacia los objetivos. En SaludWEB implica:

- comunicar prioridades y criterios de aceptación;
- acordar reuniones breves de avance;
- documentar decisiones para que no dependan de la memoria;
- resolver conflictos entre velocidad y calidad;
- capacitar a los profesionales en el uso de la agenda;
- acompañar a los pacientes con mensajes claros;
- delegar sin perder el control.

#### Control

El control compara resultados con objetivos. Indicadores propuestos:

| Indicador | Qué mide | Fuente posible |
|---|---|---|
| Tiempo medio de reserva | Minutos desde que entra hasta que confirma | Web/Mobile y logs |
| Tasa de conversión | Reservas / personas que iniciaron el flujo | Analítica del frontend |
| Ocupación de agenda | Bloques usados / bloques publicados | `disponibilidades`, `citas` |
| Cancelaciones | Citas canceladas / citas totales | `citas.estado` |
| Ausentismo | Pacientes que no asisten / citas confirmadas | Estado de cita y registro |
| Notificaciones entregadas | Enviadas con éxito / generadas | `notificaciones` |
| Errores por pedido | Respuestas 4xx y 5xx / total | Logs y API |
| Incidentes de seguridad | Accesos indebidos o intentos bloqueados | Auditoría y pruebas |

Hoy la base de desarrollo tiene muy pocos datos operativos: una sola cita y cero notificaciones. Por eso estos indicadores se pueden definir y probar, pero **todavía no alcanzan para conclusiones estadísticas sólidas**. Esa limitación es parte del control: reconocer qué se puede medir y qué no.

#### Retroalimentación

El control produce información que vuelve a la planificación:

- si la reserva es lenta, se simplifica el flujo;
- si hay cancelaciones, se mejora el recordatorio;
- si un profesional no carga su agenda, se ajusta el onboarding;
- si hay errores de permisos, se revisa la seguridad;
- si el costo de infraestructura sube, se revisa el modelo de precios.

**Conclusión de la actividad:** el proceso administrativo no es un trámite teórico. En SaludWEB se puede observar en la definición del producto, la división de tareas, la comunicación con usuarios y la medición de resultados.

---

## 3. Las organizaciones y SaludWEB

### 3.1 Qué es una organización

Una organización es un **sistema social integrado por personas y grupos** que, bajo una estructura y dentro de un contexto, desarrolla actividades aplicando recursos para lograr fines propuestos.

Tiene tres ideas centrales:

- **propósito común:** no es un grupo cualquiera, sino uno que persigue un fin;
- **estructura:** divide el trabajo, asigna autoridad y coordina;
- **contexto:** existe en un tiempo y un espacio, y recibe influencia del entorno.

Peter Drucker insiste en que la organización se define por su labor. Una organización es eficaz cuando se concentra en una labor principal. SaludWEB no debe intentar ser al mismo tiempo una clínica, una farmacia, una red social y una empresa de hardware. Su labor principal es **gestionar turnos y la relación entre pacientes y profesionales de la salud**.

### 3.2 Características de SaludWEB como organización

| Característica | Aplicación a SaludWEB |
|---|---|
| Conjunto de voluntades | Personas que quieren mejorar el acceso a la salud y construir un producto útil |
| Sistema estructurado | Roles, módulos, API, permisos, procesos de atención y soporte |
| Temporal y espacial | Nace en un contexto académico y local; puede crecer a otros ámbitos |
| Fines específicos | Reducir la fricción para reservar y gestionar turnos |
| Acto deliberado | No es un grupo espontáneo: se crea para resolver un problema |
| Recursos | Personas, conocimiento, tecnología, infraestructura, datos y eventualmente dinero |

### 3.3 Recursos

| Tipo de recurso | En SaludWEB |
|---|---|
| Humanos | Fundadora, desarrollo, diseño, pruebas, soporte, asesorías |
| Materiales | Computadoras, servidores o hosting, dispositivos de prueba, oficina o espacio de trabajo |
| Financieros | Aporte propio, ahorro, eventual crédito o inversión |
| Naturales y energéticos | Electricidad, internet, infraestructura de nube |
| Ideas, conocimiento e información | Código, documentación, datos, conocimiento del dominio de salud |
| Tecnológicos | PHP, SQLite, React, React Native, Expo, Git, API REST, SSE, JWT |

En una empresa de software, los recursos más importantes no son los edificios: son **las personas, el conocimiento, la calidad del código y la confianza de los usuarios**. Un error de seguridad puede destruir más valor que una oficina pequeña.

### 3.4 Clasificación de SaludWEB

Se clasifica según los criterios del material:

| Criterio | Clasificación propuesta | Fundamento |
|---|---|---|
| Finalidad | Empresa lucrativa de servicios, si se constituye como empresa | Busca sostenerse económicamente brindando un servicio |
| Tipo jurídico | A definir: SAS, SRL o unipersonal | No hay constitución formal en el repositorio |
| Ámbito | Local/regional, con proyección nacional | Nace en Paraná y puede operar online |
| Cantidad de dueños | Unipersonal o societaria, según constitución | Depende de la decisión legal |
| Sector económico | Terciario/servicios | Gestiona información y servicios, no fabrica bienes |
| Origen del capital | Privado | No hay evidencia de capital estatal |
| Tamaño | Microemprendimiento o pequeña empresa | Etapa inicial, equipo reducido |

> **Matiz.** Si SaludWEB se pensara como cooperativa de profesionales o como organización sin fines de lucro, cambiarían la finalidad y el tipo jurídico. El análisis toma el escenario más habitual para un emprendimiento de software: una empresa privada de servicios.

### 3.5 Estructura y niveles

La estructura es la forma en que se divide el trabajo y se coordina. Incluye:

- división del trabajo;
- autoridad y jerarquía;
- comunicación;
- procedimientos y normas;
- mecanismos de control.

Los niveles organizacionales se pueden leer en tres planos:

1. **Estratégico:** define rumbos, alianzas y supervivencia.
2. **Táctico:** traduce el rumbo en planes de área y coordina recursos.
3. **Operativo:** ejecuta tareas concretas de atención, desarrollo o soporte.

En SaludWEB, el nivel estratégico decide si el producto se orienta a consultorios o a clínicas; el táctico organiza el roadmap y la agenda; el operativo atiende un turno, corrige un error o responde un reclamo.

### 3.6 Propuesta de organigrama para SaludWEB

La siguiente estructura es una **propuesta para una etapa inicial**. No supone que todos los puestos existan hoy. En un equipo pequeño, una misma persona puede cubrir varias funciones.

```text
Asamblea de socios / Dirección
├── Asesoría legal y protección de datos (staff)
├── Asesoría contable e impositiva (staff)
└── Gerencia General
    ├── Producto y Tecnología
    │   ├── Backend y API
    │   ├── Aplicación web
    │   ├── Aplicación móvil
    │   └── Calidad, seguridad e infraestructura
    ├── Operaciones y Experiencia
    │   ├── Alta de profesionales y consultorios
    │   ├── Agenda, turnos y notificaciones
    │   └── Soporte y reclamos
    ├── Comercial y Alianzas
    │   ├── Ventas a consultorios y clínicas
    │   ├── Obras sociales y convenios
    │   └── Marketing y comunicación
    └── Administración y Finanzas
        ├── Facturación y cobranzas
        ├── Pagos y tesorería
        └── Personas y legajo (puede ser compartido o tercerizado)
```

Criterios del diseño:

- **Simplicidad:** no crear áreas que no tengan trabajo real.
- **Claridad:** cada área tiene un objetivo y un responsable.
- **Flexibilidad:** el equipo puede reorganizarse sin cambiar toda la estructura.
- **Separación de funciones:** quien cobra no debería ser la misma persona que concilia sin control.
- **Asesorías:** legal y contable no mandan sobre la línea; aconsejan.
- **Tecnología como área de producto:** no es solo “soporte técnico”; construye el servicio.

---

## 4. Unidad 1 - Actividad 2: organigramas

### 4.1 Ejercicio N° 1: Mercería SA

**Datos del enunciado:**

- Empresa dedicada a la comercialización de hilos y agujas.
- Vende en Capital Federal y Gran Buenos Aires, a mayoristas y minoristas.
- Ricardo Enrico selecciona a los mejores vendedores.
- Se hacen campañas de publicidad en medios masivos y promoción en la vía pública.
- Gerencia de Comercialización a cargo de Mariano Negroni.
- Gerente General: Andrés Blanco.
- Gerencia de Personal: Walter Méndez, departamentalizada por funciones; Esteban Quito se encarga de liquidación de sueldos; las funciones se distribuyen en varias oficinas.
- Gerencia de Producción: A. Giuliani, departamentalizada por productos; posee una oficina de almacenamiento de materias primas.

**Organigrama propuesto:**

```text
Gerente General
Andrés Blanco
├── Gerencia de Comercialización
│   Mariano Negroni
│   ├── Ventas Capital Federal y GBA
│   │   ├── Selección de vendedores: Ricardo Enrico
│   │   ├── Canal mayoristas
│   │   └── Canal minoristas
│   ├── Publicidad en medios masivos
│   └── Promoción en la vía pública
├── Gerencia de Personal
│   Walter Méndez
│   └── Departamentalización por funciones
│       ├── Liquidación de sueldos: Esteban Quito
│       └── Otras oficinas de personal
└── Gerencia de Producción
    A. Giuliani
    ├── Productos (líneas de producción)
    └── Oficina de almacenamiento de materias primas
```

**Lectura del organigrama:**

- La empresa combina una **estructura funcional** en Comercialización y Personal con una **departamentalización por productos** en Producción.
- La comercialización se organiza por canal y por zona; la publicidad y la promoción son actividades de apoyo dentro de la gerencia comercial.
- La producción tiene una oficina de almacenamiento de materias primas, que es una función de apoyo logístico.
- Ricardo Enrico no aparece como gerente: el enunciado lo ubica seleccionando vendedores, por lo que se lo representa como responsable de ventas o jefe de ventas dentro de Comercialización.

**Observaciones y sugerencias:**

1. **Precisar el cargo de Ricardo Enrico.** “Selecciona vendedores” puede ser jefe de ventas, jefe de selección comercial o responsable de recursos humanos comerciales. El organigrama debe evitar cargos ambiguos.
2. **Aclarar “las mismas en varias oficinas”** en Personal. No se sabe si son oficinas por función, por zona o por establecimiento. Conviene definirlo antes de dibujar.
3. **Revisar el nombre “A gusto Giuliani”.** Parece un error de tipeo; habría que confirmar el nombre real.
4. **Definir las líneas de producto.** La empresa vende hilos y agujas; si Producción se departamentaliza por productos, conviene nombrar esas líneas.
5. **Separar publicidad de ventas.** Publicidad y promoción pueden ser staff o apoyo, no necesariamente una sección comercial operativa.

### 4.2 Ejercicio N° 2: Design & Harmony

**Datos del enunciado:**

- Presidente: Germán Vázquez.
- Fabricación y comercialización de muebles para el hogar.
- Opera en Capital Federal, Gran Buenos Aires y capitales del interior.
- Gerente General: Andrés Steinberg.
- Gerencia de Comercialización: departamentalizada por productos; tiene Oficina de Publicidad y una sección para la zona geográfica de Gran Buenos Aires.
- Gerencia de Producción: departamentalizada por funciones.
- Gerencia Administrativa: dos secciones, Cobranzas y Pagos, y Liquidaciones de Sueldos; asesoría contable a cargo de Carolina Martínez.
- Gerencia de Sistemas: sección de programación y oficina de investigación; el gerente es asesorado por Esteban Ponce, arquitecto de desarrollo de software.
- De la Gerencia General dependen una sección de seguridad y una sección de control de calidad.
- La sección de seguridad tiene a su cargo una oficina de mantenimiento.
- Germán Vázquez es asesorado en materia legal por Agustín Fernández.
- La consigna pide agregar dos asesorías.

**Organigrama propuesto:**

```text
Presidencia
Germán Vázquez
├── Asesoría Legal (staff)
│   Agustín Fernández
└── Gerencia General
    Andrés Steinberg
    ├── Asesoría Contable (staff)
    │   Carolina Martínez
    ├── Gerencia de Comercialización
    │   ├── Productos: sillones, mesas, poltronas, escritorios
    │   ├── Oficina de Publicidad
    │   └── Sección Zona Gran Buenos Aires
    ├── Gerencia de Producción
    │   └── Departamentalización por funciones
    ├── Gerencia Administrativa
    │   ├── Sección Cobranzas y Pagos
    │   └── Sección Liquidaciones de Sueldos
    ├── Gerencia de Sistemas
    │   ├── Sección de Programación
    │   ├── Oficina de Investigación
    │   └── Asesoría técnica: Esteban Ponce (arquitecto de software)
    ├── Sección de Control de Calidad
    └── Sección de Seguridad
        └── Oficina de Mantenimiento
```

Las **dos asesorías** que pide la consigna quedan formalizadas como:

1. **Asesoría Legal**, en staff de Presidencia, a cargo de Agustín Fernández.
2. **Asesoría Contable**, en staff de Gerencia General, a cargo de Carolina Martínez.

La relación con Esteban Ponce se representa como **asesoría técnica del área de Sistemas**, no como una tercera asesoría de la máxima dirección.

**Errores, tensiones y sugerencias:**

1. **Doble criterio en Comercialización.** Se agrupa por productos y, al mismo tiempo, se crea una sección por zona geográfica (GBA). Eso puede generar duplicación de responsabilidades. Sugerencia: definir si la estructura es por producto, por zona o matriz, y aclarar quién decide el precio y la prioridad en cada territorio.
2. **Mantenimiento dentro de Seguridad.** La oficina de mantenimiento no tiene relación natural con seguridad. Sugerencia: ubicarla en Producción, en Administración o en una gerencia de servicios generales. Seguridad debe ocuparse de protección de personas y bienes, no de reparaciones.
3. **Control de Calidad depende de Gerencia General.** Es aceptable para preservar independencia, pero debe tener autoridad para detener un lote o rechazar una entrega. Si depende de Producción, pierde independencia; si depende de la Gerencia General, necesita un procedimiento claro.
4. **Asesoría contable y Administración.** Carolina Martínez asesora a la Gerencia General, pero la sección de Liquidaciones de Sueldos está en Administración. Hay que aclarar si la asesoría es staff o si forma parte de la línea administrativa.
5. **Oficina de Investigación en Sistemas.** “Investigación” puede significar investigación de mercado, investigación técnica o I+D. Conviene separar I+D de soporte y de programación.
6. **Sección de Seguridad y personal.** Si la empresa tiene seguridad interna, debe definir si es seguridad patrimonial, seguridad informática o ambas. Son funciones distintas.
7. **Falta de descripciones de puestos.** El organigrama muestra cajas, pero no dice qué hace cada una, qué decide y cómo se mide. Debe acompañarse con un manual de organización.

**Organigrama corregido sugerido:**

```text
Presidencia
├── Asesoría Legal
└── Gerencia General
    ├── Asesoría Contable
    ├── Comercialización
    │   ├── Producto: sillones
    │   ├── Producto: mesas
    │   ├── Producto: poltronas
    │   ├── Producto: escritorios
    │   ├── Publicidad
    │   └── Ventas por zona (GBA e interior)
    ├── Producción
    │   ├── Fabricación
    │   ├── Planificación
    │   └── Mantenimiento
    ├── Administración
    │   ├── Cobranzas y Pagos
    │   └── Liquidaciones de Sueldos
    ├── Sistemas
    │   ├── Programación
    │   └── I+D / Investigación
    ├── Control de Calidad
    └── Seguridad patrimonial
```

---

## 5. Documentos comerciales aplicados a SaludWEB

### 5.1 Para qué sirven

Los documentos comerciales son **comprobantes o constancias escritas** de operaciones comerciales. Cumplen cuatro funciones:

1. **Contable:** dejan constancia y respaldan la registración.
2. **Jurídica:** sirven como prueba de la relación entre las partes.
3. **De control:** identifican a las personas que intervinieron y sus responsabilidades.
4. **Impositiva:** respaldan lo declarado y facilitan la fiscalización.

Deben conservarse, por ley, durante **10 años** desde la fecha de emisión, salvo normas especiales. El archivo debe ser ordenado, seguro y recuperable.

### 5.2 Negociables y no negociables

- **Negociables:** representan dinero o una promesa de pago; pueden transferirse o cobrarse. Ejemplos: cheque, pagaré.
- **No negociables:** sirven de soporte o información de una operación. Ejemplos: orden de compra, remito, resumen de cuenta.

### 5.3 El circuito de compraventa

En toda operación hay al menos:

- una parte que compra y otra que vende;
- una parte que paga y otra que cobra;
- un comprobante para cada parte.

La documentación se emite, como mínimo, por duplicado. El original y el duplicado no son “copias decorativas”: cada uno tiene una función distinta.

### 5.4 Documentos en particular

#### Orden de compra o nota de pedido

**Qué es:** documento que emite el comprador para solicitar mercaderías o servicios.

**Aplicación a SaludWEB:** solicitar hosting, servidores, licencias, equipos o servicios de diseño. También puede usarse para pedir insumos de oficina.

**Requisitos principales:** lugar y fecha, número de orden, datos del comprador y vendedor, CUIT y condición frente al IVA, detalle, condiciones de pago y entrega, firma y datos de imprenta. Debe llevar la leyenda “no válido como factura” y la letra “X”.

**Control:** comparar lo pedido con lo recibido y con la factura antes de pagar.

#### Remito

**Qué es:** acredita el envío de mercaderías. El receptor firma una copia.

**Aplicación a SaludWEB:** entrega de equipos, routers, impresoras o material para un consultorio. No se usa para una consulta médica ni para un servicio digital.

**Emisión:** por duplicado o triplicado. El original queda para el comprador, el duplicado para el vendedor y el triplicado para el transportista.

**Control:** verificar cantidad, descripción y estado; comparar con la orden de compra.

#### Factura

**Qué es:** comprobante obligatorio de la operación de venta o prestación de servicios. Otorga derechos al comprador y obligaciones al vendedor.

**Aplicación a SaludWEB:** la plataforma factura su **servicio de suscripción o licencia** a consultorios, clínicas o profesionales. La consulta médica la factura el profesional o la institución que la presta, según corresponda. No hay que confundir ambos actos.

**Tipos:**

| Tipo | Quién la emite | A quién se emite | IVA |
|---|---|---|---|
| A | Responsable inscripto | Otro responsable inscripto | Discriminado |
| B | Responsable inscripto | Monotributista, consumidor final o exento | Incluido, no discriminado |
| C | Monotributista o exento | Todas las categorías | No discriminado |
| E | Exportador | Operaciones de exportación | Según régimen |
| M | Responsable inscripto | Otro responsable inscripto, con retención | Con leyenda y retención |

**Requisitos principales:** fecha, número, tipo, datos de las partes, detalle, condiciones de venta, remito, neto, IVA, total, CUIT, ingresos brutos, CAI, código de barras y datos de imprenta.

**Control:** verificar que la factura coincida con el pedido y el remito; controlar CUIT y condición fiscal; archivar por orden de emisión.

#### Factura electrónica y CAI

La factura electrónica reemplaza al papel y se emite desde el sitio de AFIP con clave fiscal. Para emitir comprobantes de papel se necesita el **CAI** (Código de Autorización de Impresión), que tiene vigencia de 10 días y se usa, por ejemplo, si hay un corte de luz o el sistema no está disponible.

**Aplicación a SaludWEB:** el sistema debería integrarse con un proveedor de facturación electrónica o con los servicios de AFIP. No conviene improvisar la numeración ni el CAI. La facturación debe quedar registrada y conciliada con el cobro.

#### Ticket y controlador fiscal

El ticket es el comprobante que emite un controlador fiscal homologado. Se usa para ventas masivas a consumidores finales. Incluye logotipo fiscal, memoria fiscal, cinta testigo, cierre “Z” y comprobante de auditoría.

**Aplicación a SaludWEB:** no es el comprobante típico de un servicio B2B vendido a una clínica. Sí podría aparecer si la empresa vendiera productos o servicios presenciales a consumidores finales. No se debe usar un ticket para reemplazar una factura cuando corresponde factura.

#### Recibo

**Qué es:** constancia de que se recibió dinero o un bien. Generalmente no tiene carácter impositivo y no reemplaza a la factura.

**Aplicación a SaludWEB:** recibir el pago de una suscripción, una seña o un servicio de implementación. Puede ser “recibo factura” si cumple la función impositiva.

**Control:** emitir por duplicado; original para quien paga, duplicado para quien cobra; detallar monto en letras y números, concepto y forma de pago.

#### Nota de crédito

**Qué es:** documento que disminuye el importe de una factura ya emitida. No se “borra” la factura: se corrige con una nota de crédito.

**Aplicación a SaludWEB:**

- devolución de una suscripción;
- bonificación por un mes sin servicio;
- error de facturación por un usuario adicional;
- rescisión de un contrato.

**Control:** referenciar la factura original, el motivo y el importe; emitir por duplicado.

#### Nota de débito

**Qué es:** documento que aumenta el importe de una factura ya emitida.

**Aplicación a SaludWEB:**

- intereses por pago tardío;
- gastos de implementación no incluidos;
- corrección de una factura emitida por menos;
- servicios adicionales solicitados por el cliente.

**Control:** referenciar la factura, el motivo y el nuevo saldo.

#### Resumen de cuenta

**Qué es:** documento que muestra el movimiento de la cuenta de un cliente: saldo anterior, facturas, notas de débito, notas de crédito y saldo final.

**Aplicación a SaludWEB:** enviar mensualmente a una clínica con varias sedes o profesionales, para que pueda conciliar sus pagos.

#### Pagaré

**Qué es:** promesa de pago firmada por el deudor. Es un título ejecutivo, abstracto y transferible por endoso.

**Aplicación a SaludWEB:** no es el documento habitual de una suscripción mensual. Puede aparecer si se financia un equipo, una implementación grande o un servicio con pago diferido. No conviene usarlo para cobrar un servicio pequeño y recurrente.

**Requisitos esenciales:** lugar y fecha, vencimiento, importe en letras y números, palabra “pagaré” y firma del librador.

#### Cheque

**Qué es:** orden de pago librada contra una cuenta corriente bancaria. Está regulado por la Ley 24.452.

**Clases:**

- **Común:** una sola fecha, la de emisión; se usa como instrumento de pago.
- **De pago diferido:** tiene fecha de emisión y fecha de pago; el plazo máximo entre ambas es de 360 días.

**Aplicación a SaludWEB:** una clínica puede pagar una implementación o una suscripción anual con cheque. Hay que registrar el cheque, controlar fondos, endosos y plazos. Un cheque rechazado tiene un plazo de un año para iniciar el juicio ejecutivo.

#### Ficha de caja

**Qué es:** registro de entradas y salidas de dinero de caja, con saldo inicial, ingresos, egresos y saldo final.

**Aplicación a SaludWEB:** si se cobra en efectivo en una oficina o en un stand. En un modelo 100 % digital, la tesorería se lleva con el banco y el sistema de facturación; la ficha de caja sigue siendo útil para fondos fijos y gastos menores.

#### Fichas de clientes, proveedores y stock

| Ficha | Qué registra | Equivalente en SaludWEB |
|---|---|---|
| Clientes | Ventas a crédito, cobros y saldo | Cuenta corriente de consultorios y obras sociales |
| Proveedores | Compras a crédito, pagos y saldo | Hosting, licencias, servicios profesionales |
| Stock | Entradas, salidas, costo y existencia | Solo si se venden equipos o insumos; no para el servicio de software |

**Métodos de stock:** PEPS (primero entrado, primero salido), UEPS (último entrado, primero salido) y PPP (precio promedio ponderado). En SaludWEB no hay stock de turnos: los turnos son una capacidad, no un bien almacenado. Si se vendieran equipos, habría que elegir un método y registrarlo.

### 5.5 Tabla resumen de aplicación

| Documento | Uso típico en SaludWEB | Control clave |
|---|---|---|
| Orden de compra | Comprar hosting, equipos o servicios | Comparar con remito y factura |
| Remito | Recibir equipos o materiales | Verificar cantidad y estado |
| Factura | Cobrar la suscripción o implementación | Coincidir con el contrato |
| Factura electrónica | Emitir y archivar comprobantes | CAI, numeración y AFIP |
| Ticket | Venta presencial a consumidor final | Solo si corresponde |
| Recibo | Registrar un pago | Monto, concepto y firma |
| Nota de crédito | Bonificar o devolver | Referenciar factura original |
| Nota de débito | Cobrar intereses o adicionales | Referenciar factura original |
| Resumen de cuenta | Conciliar cuenta corriente | Saldo anterior y movimientos |
| Pagaré | Financiar una operación grande | Vencimiento y firma |
| Cheque | Cobrar a una clínica | Fondos, endoso y plazo |
| Ficha de caja | Fondo fijo o caja presencial | Arqueo y saldo |
| Fichas de clientes/proveedores | Cuenta corriente | Conciliación |
| Ficha de stock | Solo si hay equipos | PEPS, UEPS o PPP |

> **Advertencia profesional.** Los modelos de factura, notas, pagaré y cheque deben ser revisados por un contador o abogado antes de usarse. La normativa impositiva y cambiaria cambia. Este documento explica el circuito, no reemplaza el asesoramiento profesional.

---

## 6. El administrador

### 6.1 Quién es un gerente

Un gerente es un miembro de la organización que **integra y coordina el trabajo de otras personas**. Puede tener responsabilidad directa sobre un equipo, supervisar a una sola persona o coordinar un equipo interdisciplinario.

La distinción entre gerente y operario se volvió difusa: muchos equipos de trabajo planifican, deciden y controlan su propio desempeño. Un desarrollador senior puede coordinar un proyecto sin tener el título formal de gerente.

En SaludWEB, el administrador no es solo quien “mira el panel”. Es quien:

- define prioridades;
- asigna recursos;
- coordina desarrollo, operaciones y comercial;
- resuelve conflictos;
- comunica hacia adentro y hacia afuera;
- controla resultados.

### 6.2 Roles gerenciales de Mintzberg

| Grupo | Rol | Qué hace | En SaludWEB |
|---|---|---|---|
| Interpersonal | Representante | Representa a la organización | Presenta el proyecto ante clínicas, universidad o inversores |
| Interpersonal | Líder | Motiva y dirige | Acompaña al equipo y sostiene el rumbo |
| Interpersonal | Enlace | Conecta con otros | Habla con profesionales, obras sociales y proveedores |
| Informativo | Monitor | Busca y recibe información | Lee métricas, reclamos, logs y novedades regulatorias |
| Informativo | Difusor | Comparte información | Comunica prioridades y cambios al equipo |
| Informativo | Vocero | Informa hacia afuera | Explica el producto a clientes y aliados |
| Decisorio | Emprendedor | Impulsa mejoras e innovación | Propone nuevas funciones o mercados |
| Decisorio | Solucionador de conflictos | Resuelve problemas | Media entre calidad, plazo y alcance |
| Decisorio | Asignador de recursos | Distribuye tiempo, dinero y personas | Decide en qué se trabaja primero |
| Decisorio | Negociador | Negocia acuerdos | Acuerda precios, convenios y condiciones |

### 6.3 Habilidades de Katz

| Habilidad | Qué es | Nivel donde pesa más | En SaludWEB |
|---|---|---|---|
| Técnica | Conocimiento del trabajo y de las herramientas | Primera línea | Saber leer un error, probar una API o revisar una consulta SQL |
| Humana | Trabajar con personas, comunicar y motivar | Todos los niveles | Escuchar al profesional, explicar un cambio, contener un conflicto |
| Conceptual | Ver el sistema como un todo y anticipar | Alta dirección | Entender cómo se relacionan producto, regulación, costos y confianza |

### 6.4 Niveles de gerencia

- **Primera línea:** supervisores o líderes de equipo. En SaludWEB: líder de desarrollo, responsable de soporte, coordinador de onboarding.
- **Nivel medio:** gerentes de área. En SaludWEB: Producto y Tecnología, Operaciones, Comercial, Administración.
- **Alto nivel:** dirección general o presidencia. En SaludWEB: fundadora/socios, responsables de estrategia y alianzas.

En una startup pequeña, los niveles se superponen. La misma persona puede ser alta dirección y primera línea. El riesgo es la **sobrecarga**: todo pasa por una sola persona y la organización no escala.

### 6.5 Flujos de trabajo del administrador

| Flujo | Qué implica | Aplicación a SaludWEB |
|---|---|---|
| Trabajo operativo | Insumo, tarea, procesamiento y producto | Un pedido de turno entra, se valida, se agenda y se responde |
| Organización del trabajo | Ordenar tareas propias y ajenas | Roadmap, tablero de tareas, prioridades |
| Comunicación | Sistemas formales e informales | Reuniones, documentación, chat, correo |
| Resolución de problemas | Detectar y decidir | Error de reserva, caída del servidor, reclamo |
| Asignación de recursos | Personas, tiempo, dinero | Elegir entre mejorar mobile o facturación |
| Conflictos | Mediar y acordar | Discusión por alcance o calidad |
| Negociación | Acordar condiciones | Precio con una clínica, plazo con un proveedor |
| Relaciones | Dentro y fuera | Obras sociales, profesionales, universidad |
| Tiempo | Priorizar y administrar agendas | Evitar urgencias que tapan lo importante |
| Delegación | Dar responsabilidad y autoridad | Permitir que operaciones resuelva sin consultar todo |
| Liderazgo estratégico | Sostener el rumbo | Coherencia entre misión y decisiones |
| Evaluación y control | Medir y corregir | Indicadores, pruebas, auditoría |

### 6.6 El administrador como “hombre de acción”

El administrador genera capacidad de gestión para lograr objetivos con eficacia, eficiencia, productividad, lucro, beneficio social o competitividad. Debe adaptarse al cambio social y tecnológico.

En SaludWEB, eso significa:

- no enamorarse de la tecnología por la tecnología;
- entender que la salud exige confianza y cuidado;
- medir el impacto real en pacientes y profesionales;
- mantener la seguridad como prioridad;
- aprender de los errores sin ocultarlos.

---

## 7. El emprendedor

### 7.1 Qué es un emprendedor

Un emprendedor es una persona que convierte una idea en un proyecto concreto, con innovación y generación de valor económico, social o ambiental. Es un agente de cambio.

No todo emprendedor es empresario y no todo empresario es emprendedor. El empresario administra y asume riesgo; el emprendedor, además, introduce novedad y transforma una idea en una organización.

### 7.2 Tipos de emprendedor

| Criterio | Tipo | Descripción | Ejemplo posible |
|---|---|---|---|
| Lugar | Intrapreneur | Emprende dentro de una organización | Empleado que crea un nuevo servicio interno |
| Lugar | Entrepreneur | Crea su propia empresa o emprendimiento | Fundadora de SaludWEB |
| Finalidad | Económico | Busca rentabilidad | SaaS de turnos |
| Finalidad | Social | Busca impacto social | Plataforma para mejorar acceso a salud |
| Finalidad | Tecnológico | Basado en innovación técnica | API, app móvil, automatización |
| Motivación | Oportunidad | Detecta una necesidad o nicho | Digitalizar agendas médicas |
| Motivación | Necesidad | Busca ingresos ante una situación desfavorable | Emprender tras una pérdida de empleo |

### 7.3 Características

- **Pasión:** sostiene el esfuerzo cuando los resultados tardan.
- **Visión:** permite anticipar oportunidades.
- **Confianza:** ayuda a decidir con incertidumbre.
- **Liderazgo:** inspira y orienta al equipo.
- **Honestidad:** protege la reputación y la confianza.
- **Innovación:** busca nuevas formas de resolver problemas.

### 7.4 Diferencia entre emprendedor y empresario

El emprendedor busca transformar una realidad y suele asumir más incertidumbre. El empresario o comerciante tiende a administrar un negocio existente y a preservarlo. La frontera no es absoluta: un empresario puede innovar y un emprendedor puede volverse administrador de su propia empresa.

### 7.5 SaludWEB como emprendimiento

SaludWEB se puede clasificar como:

- **emprendimiento tecnológico**, porque su soporte es software;
- **con impacto social**, porque mejora el acceso y la organización de turnos de salud;
- **de oportunidad**, porque responde a un problema concreto: agendas telefónicas, turnos duplicados y falta de información;
- **en etapa inicial**, porque todavía no tiene operación comercial validada.

No es necesariamente una “empresa social” en sentido estricto: para eso debería tener una misión social prioritaria y un modelo de excedentes reinvertido. Puede ser una empresa lucrativa con impacto positivo. La clasificación debe hacerse con precisión, no por moda.

**Desafíos del emprendedor SaludWEB:**

1. Validar que los consultorios paguen por el servicio.
2. Demostrar que reduce el trabajo administrativo.
3. Proteger datos sensibles.
4. Integrarse con obras sociales y sistemas existentes.
5. Escalar sin perder calidad ni confianza.
6. Sostener el foco en un problema acotado.

---

## 8. Eficacia, eficiencia y economicidad

### 8.1 Definiciones

- **Eficacia:** hacer las cosas correctas; alcanzar los objetivos. Relación entre resultados y objetivos.
- **Eficiencia:** hacer correctamente las cosas; obtener resultados con la menor cantidad de recursos. Relación entre resultados y recursos.
- **Economicidad:** lograr los objetivos con el menor costo o valor económico posible, sin sacrificar la calidad necesaria.

> **Precisión sobre las fórmulas del material.** El material presenta `Eficiencia = Recursos / Resultados` y `Eficacia = Objetivos / Resultados`. Para evitar ambigüedad, en este documento se usan las formas habituales `Eficiencia = Resultados / Recursos` y `Eficacia = Resultados / Objetivos`, donde **más alto es mejor**. Si se usa la forma del material, el indicador se lee al revés: menos recursos por resultado es mejor. Lo importante es definir el sentido y usarlo siempre igual.

### 8.2 Aplicación a SaludWEB

| Concepto | Pregunta | Ejemplo de indicador |
|---|---|---|
| Eficacia | ¿Se logró que el paciente reserve? | Reservas confirmadas / intentos de reserva |
| Eficiencia | ¿Se logró con pocos recursos? | Costo de infraestructura por cita atendida |
| Economicidad | ¿Se logró al menor costo razonable? | Costo total por turno sin perder calidad |
| Productividad | ¿Cuánto se produce por recurso? | Turnos gestionados por hora de soporte |

**Ejemplos didácticos:**

- Si 100 personas inician una reserva y 40 la completan, la eficacia del flujo es 40 %. No dice si el sistema es eficiente; solo dice si alcanzó el objetivo.
- Si el sistema atiende 1.000 turnos con un servidor de 20 USD al mes, el costo de infraestructura por turno es 0,02 USD. Ese dato sirve para comparar alternativas.
- Si se reducen los costos de hosting pero el sistema se cae y se pierden turnos, no hay economicidad: se sacrificó calidad y confianza.

### 8.3 Eficiencia no es lo mismo que eficacia

Se puede ser eficiente en lo que no hay que hacer. Por ejemplo, automatizar un trámite que nadie necesita puede ser muy eficiente y completamente ineficaz. Primero hay que elegir bien el objetivo; después, optimizar los recursos.

En SaludWEB:

- **eficacia:** que el turno quede reservado y el paciente reciba confirmación;
- **eficiencia:** que eso ocurra con la menor intervención manual y el menor costo técnico;
- **economicidad:** que el costo total sea sostenible sin comprometer seguridad ni calidad.

### 8.4 Indicadores y límites de datos

La base de desarrollo tiene pocos datos operativos (una cita, cero notificaciones). Por eso:

- se pueden definir indicadores y probar el circuito;
- no se pueden sacar conclusiones de mercado;
- no se debe confundir “dato disponible” con “muestra representativa”;
- antes de mostrar métricas a un cliente, hay que explicar el período y la fuente.

---

## 9. Diseño de la estructura organizativa

### 9.1 Mecanismos de coordinación

| Mecanismo | Qué es | En SaludWEB |
|---|---|---|
| Ajuste mutuo | Comunicación informal cara a cara | Reuniones breves, chat, resolución conjunta de un bug |
| Supervisión directa | Un jefe coordina y controla | Líder de desarrollo revisa y orienta |
| Estandarización del proceso | Procedimientos definidos | Guía de despliegue, flujo de reserva, checklist de pruebas |
| Estandarización del producto | Especificaciones del resultado | Contrato de API, formato JSON, diseño de pantallas |
| Estandarización de habilidades | Formación y criterios profesionales | Conocimientos de PHP, React, seguridad, salud |

En una etapa inicial, el **ajuste mutuo** y la **supervisión directa** son los mecanismos más usados. A medida que crece el equipo, hay que estandarizar procesos y productos para no depender de la memoria de una persona.

### 9.2 Los nueve parámetros de diseño

| Parámetro | Pregunta | Propuesta para SaludWEB |
|---|---|---|
| Especialización | ¿Cuánto se divide el trabajo? | Alta en tecnología; moderada en operaciones |
| Formalización | ¿Cuánto se documenta y norma? | Media: documentar lo crítico, no burocratizar todo |
| Entrenamiento | ¿Qué formación se necesita? | Técnica, seguridad, trato con usuarios y salud |
| Agrupamiento | ¿Por qué criterio se agrupa? | Por función en la etapa inicial; luego por producto o mercado |
| Tamaño de unidad | ¿Cuántos reportan a cada jefe? | Equipos pequeños, con alcance de control claro |
| Planeamiento y control | ¿Cómo se planifica y mide? | Roadmap, tablero, métricas, pruebas |
| Recursos de enlace | ¿Cómo se comunican las partes? | Reuniones, documentación, canales formales e informales |
| Descentralización vertical | ¿Cuánto se delega hacia abajo? | Delegar decisiones operativas; retener estrategia |
| Descentralización horizontal | ¿Cuánto poder tienen los expertos? | Alta en tecnología y seguridad |

### 9.3 Variables contingentes

| Variable | Efecto | En SaludWEB |
|---|---|---|
| Edad y tamaño | Más edad y tamaño suelen traer más formalización | Proyecto joven: estructura simple y flexible |
| Tecnología | A mayor complejidad, más planificación y coordinación | Stack heterogéneo: API, web, mobile, base, SSE |
| Entorno | Más dinámico, más adaptación; más hostil, más centralización | Regulación de salud y tecnología cambiantes |
| Poder | Control externo y modas influyen en la estructura | Obras sociales, AFIP, usuarios, proveedores |

### 9.4 Variables intermedias

- **Comprensión del trabajo:** a mayor complejidad técnica, más formación y documentación.
- **Conocimiento técnico:** a mayor conocimiento, más posibilidad de estandarizar y delegar.
- **Diversidad del trabajo:** a mayor variedad, más criterios de agrupamiento.
- **Velocidad de respuesta:** a mayor necesidad de reacción, más autonomía en los equipos.

### 9.5 Partes de la organización

| Parte | Función | En SaludWEB |
|---|---|---|
| Cabeza estratégica | Define rumbo y políticas | Dirección/fundadora y socios |
| Tecnoestructura | Diseña normas y sistemas | Responsables de producto, calidad y procesos |
| Asesoría de apoyo | Servicios especializados | Legal, contable, seguridad, marketing |
| Gerencia intermedia | Conecta estrategia con operación | Líderes de tecnología, operaciones y comercial |
| Núcleo operativo | Ejecuta el trabajo principal | Desarrollo, soporte, onboarding, atención |
| Zona de supervisión | Coordina el trabajo operativo | Líderes de equipo y responsables de turnos |

### 9.6 Configuraciones de Mintzberg aplicadas

| Configuración | Predomina | ¿Aplica a SaludWEB? |
|---|---|---|
| Estructura simple | Cabeza estratégica | Sí, en la etapa inicial |
| Máquina burocrática | Tecnoestructura | No como modelo principal; solo procesos críticos |
| Burocracia profesional | Núcleo operativo profesional | Parcial si se trabaja con profesionales de salud, pero el producto es software |
| Forma divisionalizada | Gerencia intermedia y resultados | Posible si se separa por clínicas, obras sociales o regiones |
| Adhocracia/matricial | Equipos de proyecto | Sí, para desarrollo y proyectos de integración |
| Estructura en red | Colaboración entre nodos | Sí, con socios, proveedores y profesionales |

**Diagnóstico:** SaludWEB no debe copiar una estructura burocrática grande. En su etapa actual necesita una **estructura simple con equipos de proyecto y una red de alianzas**. Cuando crezca, puede evolucionar a una forma divisional por mercado (consultorios, clínicas, obras sociales) o a una matriz para proyectos de integración.

### 9.7 Etapas de crecimiento y crisis

| Etapa | Punto de ruptura aproximado | Crisis | Solución |
|---|---|---|---|
| 1. Conformación | 10 a 20 miembros | Centralización excesiva | Dividir tareas y especializar |
| 2. Transformación en mediana empresa | 25 a 50 | Falta de criterios uniformes | Delegar, departamentalizar y descentralizar |
| 3. Coordinación y control | Más de 50, se agudiza en 75 | Problemas de coordinación | Redefinir control, staff y procesos |
| 4. Exceso de concentración | 100 a 150 | Todo pasa por la cúpula | Descentralizar y formar equipos |
| 5. Integración | Más de 1.000 | Falta de formalización y comprensión | Formalizar, integrar y consolidar la tecnoestructura |
| 6. Exceso de formalización | Más de 10.000 | Burocratización | Dirección por objetivos y divisionalización |

> **Aplicación prudente.** No se conoce la dotación real de SaludWEB. Si el equipo es de pocas personas, está en la primera etapa. Lo importante no es el número exacto, sino reconocer la crisis típica: cuando todo depende de una persona, hay que dividir tareas, documentar y delegar.

### 9.8 La organización del futuro

El material destaca:

- trabajo basado en conocimiento;
- irrupción de la tecnología;
- equipos de proyecto;
- era de la información;
- reorganización permanente;
- estructuras más planas, flexibles y en red.

SaludWEB ya muestra varios de esos rasgos:

- el producto es digital y se actualiza por versiones;
- el equipo puede trabajar en forma remota;
- la API permite integrar socios y clientes;
- las decisiones técnicas requieren conocimiento especializado;
- la estructura no puede ser rígida si el entorno cambia.

El riesgo es confundir “flexibilidad” con “falta de orden”. Una estructura en red necesita **contratos claros, documentación, seguridad y acuerdos de servicio**.

---

## 10. Trabajo Integrador Final aplicado

### 10.1 Organización elegida

**SaludWEB**, plataforma de gestión de turnos médicos en línea. El análisis se hace como organización local con proyección regional/nacional y como emprendimiento en etapa inicial.

### 10.2 Historia y estado actual

SaludWEB nace como proyecto de la Tecnicatura en Programación. Con el tiempo se convirtió en un sistema con:

- API REST en PHP con autenticación JWT y roles;
- base SQLite con creación automática;
- aplicación web en React;
- aplicación móvil en React Native/Expo;
- módulo de turnos, especialidades, disponibilidades, notificaciones y estadísticas;
- tiempo real con SSE y sondeo en desarrollo;
- documentación, pruebas y guías de uso.

El proyecto ya no es solo una idea: tiene un producto técnico. Lo que falta es la **operación comercial y organizativa** propia de una empresa: clientes reales, facturación, soporte formal, métricas de uso y acuerdos.

### 10.3 Clasificación

| Criterio | Clasificación |
|---|---|
| Finalidad | Empresa de servicios con fines de lucro, si se constituye |
| Actividad | Sector terciario: servicios de software y gestión |
| Ámbito | Local/regional, con posibilidad nacional |
| Dueños | Unipersonal o societaria, a definir |
| Capital | Privado |
| Tamaño | Micro o pequeña empresa |
| Tipo jurídico | A definir: SAS, SRL o unipersonal |
| Grado de formalización | Bajo/medio: producto desarrollado, empresa en formación |

### 10.4 Características y recursos

**Características:**

- sistema social con un propósito: mejorar la gestión de turnos;
- estructura técnica y roles definidos;
- contexto sanitario, tecnológico y regulatorio;
- fines específicos y medibles;
- recursos limitados;
- creación deliberada, no espontánea.

**Recursos:**

- humanos: conocimiento de desarrollo, diseño, pruebas, operación y salud;
- materiales: equipos, hosting, conectividad;
- financieros: aportes, ahorros, posibles inversores;
- información: código, documentación, datos, conocimiento del usuario;
- tecnológicos: stack web/mobile, API, base de datos, SSE, JWT;
- naturales/energéticos: electricidad e infraestructura de nube.

### 10.5 Estructura organizacional

La estructura propuesta es la del apartado 3.6:

- Dirección general.
- Producto y Tecnología.
- Operaciones y Experiencia.
- Comercial y Alianzas.
- Administración y Finanzas.
- Asesorías legal, contable y de protección de datos.

En la etapa inicial, varias funciones pueden estar en una misma persona. La estructura debe servir para **tomar decisiones y evitar que todo dependa de la fundadora**.

### 10.6 Procedimientos sistemáticos: input, conversión, output y retroalimentación

| Etapa | Qué ocurre | Evidencia en SaludWEB |
|---|---|---|
| Input | Paciente se registra, elige especialidad, profesional y horario | Formularios web/mobile; `GET /api/especialidades`, `/api/disponibilidades` |
| Conversión | Se autentica, se valida el rol, se controla disponibilidad y superposición | `POST /api/citas` (`routes.php:625`), servicios y repositorios |
| Output | Turno confirmado, agenda actualizada, notificación, evento en vivo | `citas`, `notificaciones`, `eventos_realtime` |
| Retroalimentación | Cancelaciones, ausencias, reclamos, métricas, logs | `GET /api/estadisticas` (`routes.php:726`), `auditoria` (`db.php:275`) |

**Flujo simplificado:**

```text
Paciente
  → elige especialidad y profesional
  → consulta disponibilidad
  → selecciona fecha y hora
  → el sistema valida identidad, rol y choque de horarios
  → guarda la cita
  → actualiza la agenda
  → genera notificación y evento
  → el paciente y el médico ven el resultado
  → cancelación, asistencia o ausencia alimentan el control
```

La **restricción única** de la base evita turnos duplicados en el mismo médico, fecha y hora. Es un ejemplo de control preventivo: no se corrige después, se impide al momento de guardar.

### 10.7 Entorno y agentes directos

| Agente | Cómo interviene |
|---|---|
| Pacientes | Demandan turnos, cargan datos, cancelan, asisten o faltan |
| Médicos | Publican disponibilidad, atienden, recetan, cancelan |
| Consultorios y clínicas | Contratan el servicio, definen agendas y políticas |
| Obras sociales | Cobertura, autorizaciones y eventual integración |
| Bancos y medios de pago | Cobros, transferencias y conciliación |
| AFIP | Facturación, impuestos y comprobantes |
| Proveedores de hosting | Disponibilidad, seguridad y costo de infraestructura |
| App stores | Distribución de la app móvil y actualizaciones |
| Competidores | Plataformas de turnos, agendas y sistemas de gestión |
| Universidad/incubadora | Formación, mentoría y red de contactos |
| Organismos de protección de datos | Regulación y control del tratamiento de datos personales |

### 10.8 Aspectos macroeconómicos

| Aspecto | Beneficio posible | Riesgo o dificultad |
|---|---|---|
| Inflación | Permite ajustar precios con frecuencia | Aumenta costos y complica presupuestos |
| Tipo de cambio | Compra de servicios en dólares | Encarece hosting y licencias |
| Tasas de interés | Acceso a financiamiento | Encarece crédito y capital de trabajo |
| Salarios | Disponibilidad de talento | Aumento de costos y competencia por profesionales |
| Digitalización | Mayor aceptación de turnos online | Brecha digital en algunos usuarios |
| Conectividad | Más usuarios conectados | Zonas con internet inestable |
| Regulación de salud | Marco para operar con confianza | Cambios y requisitos de habilitación |
| Impuestos | Formalización y acceso a clientes | Carga administrativa y costo de cumplimiento |

### 10.9 Análisis FODA

#### Fortalezas

1. Producto completo: API, web y mobile con roles y permisos.
2. Documentación y pruebas que permiten mantener y explicar el sistema.
3. Enfoque en un problema concreto y frecuente: la gestión de turnos.
4. Seguridad y trazabilidad como parte del diseño (JWT, roles, auditoría).

#### Oportunidades

1. Digitalización de consultorios pequeños y medianos.
2. Integración con obras sociales y sistemas de historia clínica.
3. Crecimiento de la demanda de atención ambulatoria y telemedicina.
4. Alianzas con universidades, colegios profesionales y cámaras de salud.

#### Debilidades

1. Base de desarrollo con pocos datos operativos: una cita y cero notificaciones.
2. Falta de operación comercial, facturación integrada y soporte formal.
3. Equipo reducido y dependencia de pocas personas.
4. Producto aún no validado con clientes reales a escala.

#### Amenazas

1. Competencia de plataformas consolidadas de turnos y gestión.
2. Riesgos de seguridad y protección de datos de salud.
3. Cambios regulatorios y requisitos de habilitación.
4. Crisis económica que reduce la inversión de consultorios.
5. Dependencia de conectividad, app stores y proveedores de nube.

#### Estrategias

- **FO:** usar el producto completo para un piloto con consultorios y universidad.
- **DO:** aprovechar la documentación para cerrar brechas de operación y soporte.
- **FA:** convertir la seguridad y la trazabilidad en argumento de confianza.
- **DA:** enfocarse en un nicho acotado y de bajo costo antes de escalar.

### 10.10 Conclusión del Trabajo Integrador

SaludWEB tiene una base técnica sólida, pero su desafío principal no es solo tecnológico: es **organizacional**. Para pasar de proyecto a empresa necesita:

- definir una propuesta de valor y un cliente inicial;
- ordenar roles y procesos;
- formalizar facturación, contratos y protección de datos;
- medir resultados con datos reales;
- construir una estructura simple que pueda crecer sin burocratizarse.

La teoría de la administración no reemplaza la práctica, pero ayuda a evitar errores costosos: confundir producto con empresa, centralizar todo, crecer sin controles o prometer más de lo que se puede sostener.

---

## 11. Plan de negocios aplicado

### 11.1 Para qué sirve

Un plan de negocios describe objetivos, acciones y recursos para reducir riesgos. Sirve para:

- ordenar la idea;
- detectar vacíos de información;
- estimar costos e ingresos;
- convencer a socios, clientes o financistas;
- hacer seguimiento y corregir.

No es un documento rígido: se actualiza cuando cambian los supuestos.

### 11.2 Resumen ejecutivo

**SaludWEB** es una plataforma de gestión de turnos médicos que permite a pacientes reservar, cancelar y consultar turnos, y a profesionales administrar su agenda. Ofrece una API central, una aplicación web y una app móvil, con roles diferenciados y notificaciones.

**Problema:** llamadas telefónicas, agendas en papel o planillas, turnos duplicados y falta de información.

**Solución:** autogestión de turnos, agenda actualizada, recordatorios y métricas de gestión.

**Modelo de ingresos propuesto:** suscripción mensual por profesional o por establecimiento, con un servicio de implementación inicial.

**Público inicial:** consultorios pequeños y medianos de Paraná y la región.

**Ventaja:** producto completo, simple, con foco en seguridad y trazabilidad.

### 11.3 Descripción de la empresa

- **Nombre:** SaludWEB.
- **Tipo de negocio:** software como servicio (SaaS) para gestión de turnos.
- **Ubicación:** operación digital; domicilio legal en Paraná, Argentina.
- **Forma jurídica:** a definir (SAS, SRL o unipersonal).
- **Estado:** producto desarrollado, en etapa de validación comercial.
- **Propietarios/directivos:** a definir según constitución.

### 11.4 Producto y servicio

**Funciones actuales:**

- registro e inicio de sesión;
- roles `admin`, `medico` y `paciente`;
- catálogo de especialidades y profesionales;
- publicación de disponibilidad;
- reserva, cancelación y cambio de estado de turnos;
- agenda del profesional;
- notificaciones con token;
- estadísticas de gestión;
- panel en tiempo real (SSE en producción; sondeo en desarrollo).

**Funciones a desarrollar:**

- facturación electrónica integrada;
- pagos online;
- integración con obras sociales;
- historia clínica o vínculo con sistemas existentes;
- aplicación móvil publicada en tiendas;
- panel para clínicas con varias sedes;
- reportes exportables.

**Diferenciación:** simplicidad, trazabilidad, seguridad por roles y enfoque en la experiencia del paciente y del profesional.

### 11.5 Mercado y plan de marketing

**Mercado meta inicial:** consultorios y clínicas pequeñas/medianas de Paraná y alrededores.

**Clientes:**

- profesionales independientes;
- consultorios con varios profesionales;
- clínicas ambulatorias;
- obras sociales o centros de atención.

**Competencia:** agendas telefónicas, planillas, sistemas de gestión clínica, plataformas de turnos.

**Estrategia de entrada:**

1. piloto con 3 a 5 consultorios;
2. acompañamiento en la carga de agendas;
3. medición de tiempo de reserva y ausentismo;
4. testimonio y caso de éxito;
5. expansión a clínicas y obras sociales.

**Canales:** venta directa, referidos, colegios profesionales, universidad, redes y contenido educativo.

**Precio:** suscripción mensual por profesional, con descuento por volumen y pago anual. El precio debe cubrir infraestructura, soporte, desarrollo y margen.

### 11.6 Administración y equipo

| Tarea | Responsable inicial |
|---|---|
| Definir estrategia y alianzas | Dirección |
| Desarrollar y mantener el producto | Producto y Tecnología |
| Alta de profesionales y soporte | Operaciones |
| Ventas y convenios | Comercial |
| Facturación y cobros | Administración |
| Asesoría legal y datos | Asesoría externa |
| Asesoría contable | Asesoría externa |

En la etapa inicial, una persona puede cubrir más de una tarea. Lo importante es que esté claro **quién decide** y **quién responde** por cada resultado.

### 11.7 Operaciones

**Proceso de alta de un consultorio:**

1. relevamiento de profesionales y agendas;
2. configuración de especialidades y disponibilidades;
3. capacitación;
4. prueba con turnos ficticios;
5. salida a producción;
6. seguimiento de primeras reservas.

**Proceso de soporte:**

- canal de contacto;
- registro del reclamo;
- priorización;
- solución y comunicación;
- registro en base de conocimiento.

**Capacidad:** depende de la infraestructura y del soporte. Al inicio, un servidor pequeño y un equipo reducido pueden atender pocos consultorios; el crecimiento debe planificarse.

### 11.8 Plan financiero

> **Ejemplo didáctico.** Los valores siguientes son ficticios y sirven para mostrar el cálculo. No son una proyección real de mercado ni cotización de SaludWEB.

**Supuestos mensuales:**

| Variable | Valor de ejemplo |
|---|---|
| Precio por profesional activo | ARS 18.000 |
| Costo variable por profesional | ARS 4.000 |
| Margen de contribución | ARS 14.000 |
| Costos fijos mensuales | ARS 900.000 |

**Punto de equilibrio:**

```text
Punto de equilibrio = Costos fijos / Margen de contribución
Punto de equilibrio = 900.000 / 14.000 = 64,29
→ se necesitan 65 profesionales activos para cubrir los costos fijos
```

**Escenarios:**

| Profesionales | Ingresos | Costos variables | Contribución | Costos fijos | Resultado |
|---:|---:|---:|---:|---:|---:|
| 50 | 900.000 | 200.000 | 700.000 | 900.000 | -200.000 |
| 100 | 1.800.000 | 400.000 | 1.400.000 | 900.000 | 500.000 |
| 200 | 3.600.000 | 800.000 | 2.800.000 | 900.000 | 1.900.000 |

**Lectura:** con 100 profesionales, el margen de seguridad es del 35 % respecto del punto de equilibrio. Con 50, el negocio pierde. El ejercicio muestra por qué hay que validar el precio y la cantidad de clientes antes de escalar.

**Costos a incluir:** hosting, dominio, correo, notificaciones, herramientas de desarrollo, publicidad, contabilidad, legales, soporte y tiempo del equipo.

### 11.9 Estructura legal

Puntos a definir con asesoramiento:

- forma jurídica: SAS, SRL o unipersonal;
- inscripción en AFIP e Ingresos Brutos;
- facturación electrónica;
- contratos con consultorios y profesionales;
- términos y condiciones de uso;
- política de privacidad y tratamiento de datos;
- consentimiento informado para datos sensibles;
- responsabilidad por el servicio;
- propiedad intelectual del software;
- acuerdos de confidencialidad.

**Datos de salud:** son datos sensibles. Hay que aplicar minimización, seguridad, control de acceso, registro de auditoría y plazos de conservación. No alcanza con “tener contraseña”.

### 11.10 Ubicación

La operación puede ser digital y remota. El domicilio legal y fiscal debe existir, pero no es necesario alquilar una oficina grande al inicio. La ubicación física puede ser un espacio de coworking o el domicilio de la fundadora, según la constitución y las habilitaciones.

### 11.11 Riesgos y mitigación

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Baja adopción | Ingresos insuficientes | Piloto, capacitación y ajuste del producto |
| Falla de seguridad | Pérdida de confianza y sanción | Auditoría, cifrado, roles y respaldo |
| Dependencia de una persona | Continuidad | Documentación, delegación y respaldo |
| Costos de infraestructura | Margen bajo | Medición y optimización |
| Cambio regulatorio | Obligaciones nuevas | Asesoría legal y seguimiento |
| Competencia | Pérdida de mercado | Nicho, servicio y diferenciación |

### 11.12 Conclusión del plan

SaludWEB tiene una oportunidad razonable en un mercado con dolor concreto, pero el plan debe validarse con clientes reales. El error sería asumir que un buen producto técnico se vende solo. La empresa necesita **propuesta de valor, precio, canal, soporte, contratos y control**. El plan de negocios es la herramienta para probar esas hipótesis antes de invertir de más.

---

## 12. Conclusión

Organización Empresarial aporta a SaludWEB una forma de mirar el proyecto que va más allá del código. El sistema ya resuelve circuitos concretos, pero el análisis muestra que una aplicación no es una organización por sí sola: necesita propósito, recursos, división del trabajo, coordinación, autoridad y control. Aplicar el proceso administrativo permitió ordenar esos elementos y distinguir lo que existe de lo que se proyecta.

La Actividad 1 y la Actividad 2 obligaron a pasar de la definición a la representación. Los organigramas de Mercería SA y Design & Harmony muestran que un dibujo puede ocultar problemas de dependencia, amplitud, especialización o unidades sin función. En SaludWEB, la propuesta de estructura para la etapa inicial busca evitar esos errores: separar conducción, operación del producto, administración y comercialización, sin inventar dotación ni jerarquías que el proyecto todavía no tiene. La estructura queda abierta para crecer hacia una configuración profesional o divisional cuando aparezcan volumen, especialización y mercados distintos.

El plan de negocios completa la mirada con mercado, operaciones, riesgos y números. Sus resultados no son un pronóstico: son un ejercicio didáctico que muestra cómo se relacionan precio, costo variable, costos fijos y punto de equilibrio. El valor está en el razonamiento y en las preguntas que deja abiertas, no en tomar los importes como datos reales. Lo mismo vale para los documentos comerciales: se aplican para entender el circuito administrativo, no para afirmar que SaludWEB ya factura.

El aprendizaje central es que eficacia, eficiencia y economicidad no son sinónimos, y que una estructura no se diseña por gusto sino en función de la estrategia, el entorno, la tecnología y el tamaño. SaludWEB queda mejor documentado y mejor preparado para explicar sus decisiones, pero también con límites explícitos: es un emprendimiento académico en etapa inicial, sin empresa constituida, sin dotación real y con pocos datos operativos. Reconocer esos límites es parte del análisis y evita convertir una propuesta razonable en una descripción falsa.

## 13. Trazabilidad, límites y bibliografía

### 13.1 Evidencia del proyecto

| Tema | Archivo o referencia |
|---|---|
| Roles y permisos | `README.md:14`, `routes.php:271`, `routes.php:403`, `routes.php:799` |
| Turnera y disponibilidad | `routes.php:543`, `routes.php:551`, `routes.php:625` |
| Agenda y cancelación | `routes.php:560`, `routes.php:631` |
| Notificaciones | `routes.php:586`, `routes.php:686`, `db.php:458` |
| Estadísticas | `routes.php:726` |
| Tiempo real | `routes.php:859`, `db.php:510` |
| Auditoría | `db.php:275` |
| Pacientes | `db.php:211` |
| Médicos | `db.php:235` |
| Citas | `db.php:392` |
| Especialidades | `db.php:331` |
| Disponibilidades | `db.php:357` |
| Prescripciones | `db.php:261` |
| Obras sociales | `db.php:202` |

### 13.2 Límites del análisis

- SaludWEB no es una empresa constituida: la estructura y el plan son propuestas.
- No hay dotación real informada; las etapas de crecimiento se aplican como escenario.
- No hay datos financieros reales; los números del plan son ejemplos didácticos.
- La base de desarrollo tiene pocos datos operativos; no sirve para inferencia de mercado.
- La aplicación no reemplaza asesoramiento legal, contable ni médico.
- Las normas impositivas y cambiarias deben verificarse al momento de usarse.
- Los organigramas de Mercería SA y Design & Harmony son interpretaciones del enunciado; los datos ambiguos se señalaron.

### 13.3 Bibliografía y fuentes

- Material de cátedra **Organización Empresarial**, profesora Kranevitter, María Cecilia. Unidad 1, actividades 1 y 2; capítulo de documentos comerciales; el administrador; el emprendedor; diseño de la estructura organizativa; Trabajo Integrador Final; guía del plan de negocios.
- Fayol, Henri. **Administración industrial y general**.
- Mintzberg, Henry. **La estructuración de las organizaciones**. Editorial Ariel.
- Mintzberg, Henry. **El trabajo del gerente**: roles gerenciales.
- Katz, Robert L. **Habilidades de un administrador eficaz**.
- Drucker, Peter. **La sociedad poscapitalista** y **La gerencia**.
- Hermida, Jorge. Etapas críticas del crecimiento de las organizaciones.
- Código Civil y Comercial de la Nación, artículo 1123 (compraventa).
- Ley 24.452 (cheques).
- AFIP. Facturación electrónica, CAI y controladores fiscales.
- Repositorio SaludWEB. README, `db.php`, `routes.php`, documentación de arquitectura y pruebas.

---

**Fin del documento.**
