# Documentar y Publicar: El Camino al Éxito del Proyecto

<!--
GUÍA COMENTADA - SALUDWEB
=========================
Este archivo es la versión comentada de la guía "Documentar y Publicar: El
Camino al Éxito del Proyecto". El texto original está completo y sin cambios:
los comentarios van entre marcadores de HTML, así que no se ven cuando el
archivo se renderiza en GitHub ni en ningún visor de Markdown.

Cómo leerlo: cada bloque de comentarios explica la idea que tiene justo debajo
(o arriba, cuando se aclara). Sirve para dos cosas: para publicar ESTE proyecto
(SaludWEB) y para explicar por qué cada paso importa en una defensa o en una
entrevista.
-->

De un repositorio privado a una solución pública, profesional y lista para escalar. Esta guía te llevará paso a paso por el proceso de documentación, preparación y publicación de tu proyecto.

<!--
El subtítulo resume el recorrido completo: pasar de privado a público, con dos
adjetivos que son el estándar de la guía: "profesional" (calidad visible) y
"lista para escalar" (que crecer no obligue a rehacer todo).
-->

**Guía Técnica**

<!--
"Guía Técnica" marca que lo que sigue son pasos concretos y verificables, no
una charla motivacional.
-->

**Documentación y Deploy**

<!--
Los dos ejes del documento: documentación (lo que otros leen para entender y
usar el proyecto) y deploy (lo que hace que el proyecto esté disponible).
Sin el primero nadie lo adopta; sin el segundo nadie lo encuentra.
-->

## Objetivo: Del Código al Valor Real

<!--
Título de la primera sección. "Del código al valor real" es la idea central:
el código solo no vale nada si no llega a alguien que lo use.
-->

Un proyecto que no está documentado ni publicado no existe para el mundo. El objetivo de esta sesión es claro: transformar un repositorio privado en una solución pública, profesional y accesible. Esto implica mucho más que subir archivos a un servidor; implica comunicar valor, garantizar usabilidad y establecer un estándar de calidad que inspire confianza tanto en usuarios finales como en potenciales colaboradores o evaluadores técnicos.

<!--
Párrafo clave: distingue "publicar" (subir archivos) de "comunicar valor".
Hay tres públicos a los que hay que convencer: usuarios finales (que lo usen),
colaboradores (que puedan sumarse) y evaluadores técnicos (que confíen).
-->

### ¿Qué logramos hoy?

<!--
Lista de resultados concretos. Funciona como checklist al final: si no se
cumplen estos tres puntos, la publicación quedó a medias.
-->

- Transformar un repositorio privado en una solución pública y profesional.

<!--
Resultado 1: visibilidad. Un repo privado es invisible; uno público y prolijo
se puede mostrar, compartir y evaluar.
-->

- Establecer estándares de calidad, transparencia y usabilidad para usuarios y colaboradores.

<!--
Resultado 2: estándares. No alcanza con que funcione: tiene que ser claro,
consistente y fácil de usar y de mantener.
-->

- Generar documentación técnica que comunique el valor real del sistema.

<!--
Resultado 3: documentación con foco en el valor. No es inventariar archivos:
es explicar qué problema resuelve el sistema y cómo se usa.
-->

### ¿Por qué importa documentar?

<!--
Sección que justifica el esfuerzo de documentar. Si alguien pregunta "¿para qué
perder tiempo escribiendo?", la respuesta está en los cuatro puntos siguientes.
-->

- Un proyecto sin documentación es invisible para quienes podrían adoptarlo o evaluarlo.

<!--
Sin documentación no hay adopción ni evaluación posible: el interesado no sabe
por dónde empezar y se va.
-->

- La documentación reduce la fricción de onboarding y acelera la adopción.

<!--
"Fricción de onboarding" es todo lo que alguien nuevo tiene que resolver antes
de poder usar o tocar el proyecto. Cuanta menos fricción, más rápido se suma.
-->

- Proyecta madurez técnica, seriedad profesional y visión de largo plazo.

<!--
La documentación es una señal: quien la escribe demuestra que pensó en otros y
en el futuro del proyecto, no solo en que "a mí me funcione hoy".
-->

- La documentación no es el final del proceso de desarrollo: es el inicio de la vida pública del proyecto.

<!--
Cierra la idea: documentar no es un trámite posterior, es el paso que abre la
etapa pública del proyecto.
-->

## Revisión Técnica: Limpieza y Consistencia

<!--
Segunda gran etapa: antes de mostrar el código hay que revisarlo. Un repo
desordenado comunica descuido aunque el sistema funcione.
-->

Antes de exponer el código al público, es fundamental realizar una auditoría técnica profunda. Un repositorio desordenado, con código muerto o inconsistencias de estilo, comunica falta de profesionalismo y dificulta la colaboración futura. Este paso garantiza que lo que publicamos refleje verdaderamente el nivel del equipo.

<!--
Define el propósito de la auditoría: que lo publicado represente el nivel real
del equipo, no una versión apurada.
-->

### Depuración Profunda

<!--
Primera tarea de la revisión: sacar todo lo que estorba. En SaludWEB este paso
se ve en la limpieza de scripts viejos, dependencias que ya no se usan y
comentarios que quedaron de versiones anteriores.
-->

Identificar y eliminar bugs críticos que puedan comprometer la experiencia del usuario o la estabilidad del sistema. Remover código muerto, funciones duplicadas, comentarios obsoletos y ramas no mergeadas. Un repositorio limpio es un repositorio confiable.

<!--
"Un repositorio limpio es un repositorio confiable": la limpieza no es estética,
es una señal de que el equipo controla lo que publica.
-->

### Estandarización de Estilo

<!--
Segunda tarea: que todo el código se parezca. En el proyecto hay convenciones
claras (comentarios didácticos, nombres en español para el dominio, inglés para
lo técnico) y esa coherencia es parte del valor.
-->

Aplicar convenciones de nomenclatura consistentes en variables, funciones, componentes y archivos. Reorganizar la estructura de carpetas para que sea intuitiva y predecible. Incorporar un linter o formatter (ESLint, Prettier, etc.) si aún no está configurado.

<!--
La estructura predecible es la que permite que alguien nuevo encuentre las
cosas sin preguntar: controladores, servicios, persistencia y configuración
tienen que estar siempre en el mismo lugar.
-->

### Optimización de Carga

<!--
Tercera tarea: que el sistema responda bien. La primera impresión del usuario
depende del rendimiento, no de la cantidad de funciones.
-->

Garantizar que el sistema responda de forma fluida y estable antes del lanzamiento. Revisar tiempos de respuesta, eliminar dependencias innecesarias y asegurarse de que el build de producción esté correctamente optimizado. La primera impresión del usuario depende del rendimiento.

<!--
En SaludWEB esto se traduce en: build de Vite optimizado, paginación en los
listados, índices en la base y consultas que no traigan más de lo necesario.
-->

Una revisión técnica bien ejecutada no solo mejora la calidad del código: también protege la reputación del equipo y facilita que nuevos colaboradores puedan contribuir con confianza desde el primer día.

<!--
Cierre de la sección: la revisión técnica protege dos cosas a la vez, la
reputación del equipo y la confianza de quien llega a colaborar.
-->

## README: La Carta de Presentación

<!--
Tercera etapa: el README. Es lo primero que ve cualquier persona y muchas
veces lo único que lee antes de decidir si el proyecto le interesa.
-->

El README es la primera interacción que tiene cualquier persona con tu proyecto. Es, en esencia, la portada y el índice de todo lo que construiste. Un README bien redactado comunica propósito, genera confianza y reduce drásticamente las barreras de entrada para nuevos usuarios y colaboradores. Debe ser claro, conciso y completo.

<!--
Las tres cualidades que pide: claro (se entiende sin contexto), conciso (no
obliga a leer media hora) y completo (no faltan los datos para arrancar).
-->

### Título y Descripción

<!--
Lo primero que se lee. Si el título y la descripción no explican el proyecto
en segundos, el resto no se lee.
-->

Un título claro y una descripción directa que responda: ¿qué hace este proyecto y qué problema resuelve? Incluir badges de estado del proyecto (build, cobertura de tests, versión).

<!--
Los badges (esas etiquetas de color) son información de estado a simple vista:
si el build pasa y los tests están verdes, el proyecto transmite salud técnica.
-->

### Inicio Rápido

<!--
La sección más importante para que alguien pruebe el proyecto: tiene que poder
ejecutarlo en menos de cinco minutos.
-->

Instrucciones mínimas para que el usuario pueda ejecutar el proyecto en menos de 5 minutos. Incluir el comando de instalación, configuración básica y el comando de inicio.

<!--
En SaludWEB el inicio rápido está resuelto: `composer install` y
`composer run dev` en el backend; `npm install` y `npm start` en cada frontend;
y un lanzador `.bat` que levanta todo de una vez.
-->

### Ejemplos de Uso

<!--
No alcanza con enumerar funciones: hay que mostrar el sistema en acción, con
capturas o ejemplos concretos.
-->

Mostrar casos de uso reales con capturas de pantalla o fragmentos de código. Demostrar el valor del sistema en acción, no solo describir sus funcionalidades en abstracto.

<!--
Ejemplo aplicado: mostrar la reserva de un turno de punta a punta (elegir
especialidad, profesional y horario) vale más que una lista de endpoints.
-->

### Enlaces Clave

<!--
El README es el índice del ecosistema: todo lo demás tiene que estar a un clic.
-->

Links a la documentación técnica completa, demo en vivo, guía de contribución (CONTRIBUTING.md), y licencia. Facilitar la navegación al ecosistema completo del proyecto.

<!--
En este proyecto los enlaces clave son: las guías de código, la guía de tiempo
real, el registro de cambios y la guía de producción y defensa.
-->

## Manual de Instalación: El Puente al Usuario

<!--
Cuarta etapa: el manual de instalación. Es el puente entre el código y la
persona que quiere usarlo.
-->

Un sistema brillante que nadie puede instalar no llega a ningún lado. El manual de instalación es el puente entre el código y el usuario final. Su objetivo es eliminar toda fricción posible en el proceso de adopción, anticipando errores frecuentes y guiando paso a paso incluso a quienes no tienen experiencia avanzada con la tecnología utilizada.

<!--
La frase que resume la sección: si la instalación es difícil, el proyecto no
se adopta, por bueno que sea.
-->

### Requisitos Previos del Sistema

<!--
Antes de instalar nada hay que saber qué hace falta. La precisión acá evita
idas y vueltas.
-->

Especificar con precisión las versiones requeridas de cada herramienta:

<!--
"Con precisión" significa versión mínima, no "PHP instalado". Una versión
vieja puede hacer fallar el proyecto por motivos que no se ven en el código.
-->

- Lenguaje de programación y versión mínima (ej. Node.js ≥ 18, Python ≥ 3.10).

<!--
En SaludWEB: PHP 8.3 para el backend y Node.js 18 o superior para la web y la
app móvil.
-->

- Gestores de paquetes: npm, pip, composer, etc.

<!--
Composer para las dependencias PHP y npm para las de JavaScript. Sin esos dos
gestores no se instala nada.
-->

- Servicios externos necesarios: base de datos, API keys, servicios de almacenamiento.

<!--
En este proyecto es una ventaja: no hay servicios externos obligatorios. La
base es SQLite (un archivo) y el envío de correo es opcional.
-->

- Sistema operativo compatible y requerimientos de hardware.

<!--
Windows para los scripts `.bat`; macOS y Linux con los comandos manuales.
Hardware: cualquier PC común, porque el servidor de desarrollo es liviano.
-->

### Pasos de Configuración

<!--
La secuencia exacta de instalación. El orden importa: clonar, instalar,
configurar, migrar y arrancar.
-->

- Clonar el repositorio con el comando exacto.

<!--
Comando exacto, no "cloná el repo": `git clone <url>`. La precisión evita
errores de tipeo y de carpeta equivocada.
-->

- Instalar dependencias con el gestor correspondiente.

<!--
`composer install` en el backend y `npm install` en Web y Mobile. Es el paso
que más falla cuando falta el gestor o la versión de PHP/Node.
-->

- Copiar y configurar el archivo .env con las variables de entorno.

<!--
En el backend: `copy .env.example .env`. Las variables obligatorias son
`JWT_SECRET` (en producción), `DB_DATABASE` y `CORS_ORIGINS`. En los frontends
el `.env` es opcional.
-->

- Ejecutar migraciones de base de datos si aplica.

<!--
En SaludWEB no hay migraciones manuales: `db.php` crea el esquema y los datos
de ejemplo solos en el primer request. Eso elimina un paso clásico de error.
-->

- Iniciar el servidor en modo desarrollo o producción.

<!--
Desarrollo: `composer run dev` + `npm run dev` (o el lanzador).
Producción: build de la web y servidor web con PHP, nunca `php -S`.
-->

### Resolución de Errores Comunes

<!--
La sección que ahorra más tiempo de soporte: los errores que ya sabemos que
van a aparecer, resueltos de antemano.
-->

Anticipar y documentar los problemas más frecuentes ahorra horas de soporte y frustración:

<!--
Cada error documentado es una consulta que no hay que responder y una persona
que no abandona la instalación.
-->

- Error de permisos: Cómo configurar correctamente los permisos de archivos y directorios.

<!--
En Linux/macOS, la carpeta `storage/` del backend necesita permiso de
escritura para PHP. En Windows este error casi no aparece.
-->

- Puerto ocupado: Instrucciones para cambiar el puerto de la aplicación.

<!--
Si el 8000 o el 5173 están ocupados, el servidor no arranca. En el proyecto:
`scripts\detener_saludweb.bat` libera esos puertos.
-->

- Variables de entorno faltantes: Lista de verificación de variables obligatorias.

<!--
La lista mínima del backend: `APP_ENV`, `JWT_SECRET`, `DB_DATABASE` y
`CORS_ORIGINS`. Si falta el secreto en producción, la API se niega a arrancar.
-->

- Conflictos de versiones: Uso de herramientas como nvm o entornos virtuales.

<!--
En un proyecto con PHP y Node conviene fijar versiones: `nvm use` para Node y
la versión de PHP documentada (8.3) para el backend.
-->

- Fallos en la conexión a BD: Verificación de credenciales y disponibilidad del servicio.

<!--
En SaludWEB no hay credenciales de base: si algo falla, casi siempre es que la
carpeta `storage/` no existe o no tiene permiso de escritura.
-->

Incluye un archivo Makefile o script de setup para automatizar la instalación cuando sea posible.

<!--
En este proyecto el equivalente al Makefile es `scripts\iniciar_saludweb.bat`:
un doble clic que levanta la API y la web, espera a que respondan y abre el
navegador.
-->

## Arquitectura: La Columna Vertebral

<!--
Quinta etapa: la arquitectura. Es el mapa que permite entender el sistema sin
leer todo el código.
-->

El diagrama de arquitectura es el mapa del sistema. Permite que cualquier desarrollador, evaluador o stakeholder técnico comprenda de un vistazo cómo están organizados los componentes, cómo se comunican entre sí y cuáles fueron las decisiones de diseño que guiaron su construcción. Una arquitectura bien documentada es señal de madurez técnica y planificación estratégica.

<!--
"Mapa del sistema": componentes, comunicación y decisiones. Las tres cosas
juntas, porque un diagrama sin justificación es solo un dibujo.
-->

El diagrama de arquitectura debe incluir las tres capas principales del sistema y sus interacciones. Cada capa representa una responsabilidad clara y un dominio de decisiones técnicas independiente.

<!--
Tres capas en SaludWEB: presentación (web y app), lógica y API (backend PHP) y
datos (SQLite). Cada una se puede cambiar sin reescribir las otras.
-->

### Diagramas de Alto Nivel

<!--
Lo primero es una vista general: qué hay y cómo se conecta, sin entrar en
detalle de archivos.
-->

Representación visual del sistema completo y sus componentes principales. Incluir servicios externos, integraciones de terceros y los límites entre cada capa. Herramientas recomendadas: draw.io, Lucidchart, Mermaid.js, o Excalidraw.

<!--
Mermaid tiene una ventaja para este proyecto: el diagrama se escribe como
texto dentro del README y se versiona junto con el código.
-->

### Flujo de Datos

<!--
El diagrama de arquitectura dice qué hay; el flujo de datos dice cómo viaja la
información de un extremo al otro.
-->

Documentar cómo viaja la información entre los módulos del sistema: desde la solicitud del usuario hasta la respuesta final. Incluir autenticación, transformaciones de datos y cachés si aplica. La claridad en el flujo de datos reduce bugs en producción.

<!--
En SaludWEB: navegador o celular → petición HTTP con JWT → ruta → controlador
→ servicio → repositorio → SQLite → respuesta JSON (y evento SSE si cambió un
turno).
-->

### Justificación del Stack

<!--
La pregunta que siempre aparece en una defensa: "¿por qué esta tecnología y no
otra?". La respuesta tiene que estar documentada, no improvisada.
-->

Explicar por qué se eligió cada tecnología del stack: framework, base de datos, servicios en la nube, librerías clave. Las decisiones arquitectónicas deben estar respaldadas por criterios de escalabilidad, mantenibilidad, performance y familiaridad del equipo.

<!--
Justificación aplicada: PHP nativo (sin framework) para no depender de XAMPP ni
de un servidor pesado; SQLite porque la base es un archivo y no necesita
servicio; React y Vite por velocidad de desarrollo; React Native con Expo para
reutilizar el mismo backend en el celular.
-->

## Diagrama de Base de Datos: La Estructura de la Información

<!--
Sexta etapa: la base de datos. Es el corazón del sistema: si el modelo está
mal, todo lo demás se complica.
-->

La base de datos es el corazón del sistema. Un diagrama claro y bien estructurado permite entender cómo se almacena, relaciona y accede a la información crítica del proyecto. Este artefacto es indispensable para el onboarding de nuevos desarrolladores, para la auditoría de seguridad y para futuras decisiones de escalabilidad.

<!--
Sirve para tres cosas: onboarding (entender rápido), auditoría (qué datos
sensibles hay) y escalabilidad (qué crece y qué no).
-->

### Diseño Relacional

<!--
Qué tiene que mostrar el diagrama, sin omitir nada importante.
-->

El diagrama debe representar con claridad:

<!--
"Claridad" significa que se entienda sin explicación oral: nombres legibles,
relaciones dibujadas y tipos a la vista.
-->

- Tablas y entidades principales: cada tabla representa un concepto del dominio del negocio.

<!--
En SaludWEB: usuarios, pacientes, médicos, especialidades, disponibilidades,
citas y notificaciones. Cada una es un concepto real, no una tabla técnica.
-->

- Campos y tipos de datos: nombre, tipo (VARCHAR, INT, BOOLEAN, TIMESTAMP) y restricciones (NOT NULL, UNIQUE).

<!--
El tipo y las restricciones son parte de la documentación: explican qué datos
se aceptan y cuáles se rechazan.
-->

- Relaciones clave: uno a uno, uno a muchos, muchos a muchos, con sus claves foráneas explícitas.

<!--
Ejemplo: un médico tiene muchas disponibilidades (uno a muchos); un paciente
tiene muchas citas; una especialidad agrupa muchos médicos.
-->

- Claves primarias: identificadores únicos para cada entidad del sistema.

<!--
Sin clave primaria no hay forma confiable de referenciar una fila, y las
relaciones terminan rotas.
-->

### Integridad y Eficiencia

<!--
Que los datos sean correctos y que las consultas sean rápidas: las dos cosas
se diseñan, no se arreglan después.
-->

- Índices estratégicos: aplicados en campos de alta frecuencia de consulta para mejorar el rendimiento.

<!--
En el proyecto hay índices por fecha, estado, médico y paciente: son los
campos por los que se filtra la agenda todos los días.
-->

- Normalización: aplicar al menos hasta la 3FN para evitar redundancia y anomalías de datos.

<!--
La 3FN evita repetir datos y, con eso, evita que una actualización deje copias
desincronizadas.
-->

- Constraints de integridad: reglas a nivel de base de datos que garantizan la consistencia de los datos.

<!--
Ejemplo real: la clave única de médico, fecha, hora y slot impide que dos
personas reserven el mismo turno aunque las peticiones lleguen juntas.
-->

### Herramientas para Visualizar

<!--
Opciones concretas para dibujar y mantener el diagrama actualizado.
-->

Existen diversas herramientas para crear y mantener el diagrama de base de datos actualizado:

<!--
La herramienta importa menos que el hábito de actualizar el diagrama con cada
cambio de esquema.
-->

- dbdiagram.io: rápido, online y con sintaxis DBML intuitiva.

<!--
Útil para armar el diagrama desde cero y compartirlo por link.
-->

- DBeaver: genera el diagrama ER automáticamente desde la BD real.

<!--
Su ventaja es que refleja la base tal como está, no como creemos que está.
-->

- MySQL Workbench / pgAdmin: soluciones nativas para MySQL y PostgreSQL.

<!--
En este proyecto no aplican directamente porque la base es SQLite, pero son el
estándar cuando el motor es MySQL o PostgreSQL.
-->

- Draw.io: flexible y exportable a múltiples formatos.

<!--
Sirve para diagramas de arquitectura y de base en la misma herramienta, y
exporta PNG o SVG para incluirlo en la documentación.
-->

El diagrama de base de datos debe mantenerse actualizado con cada migración. Considera versionarlo junto al código fuente.

<!--
En SaludWEB las tablas se crean en `db.php`: cualquier cambio de esquema tiene
que reflejarse ahí y en el diagrama, en el mismo commit.
-->

## Preparación para el Deploy

<!--
Séptima etapa: el deploy. Acá se deja de hablar de código y se empieza a
hablar de operación, seguridad y reproducibilidad.
-->

El deploy no es solo subir archivos a un servidor: es un proceso estructurado que garantiza que el sistema llegue al entorno de producción de forma segura, estable y reproducible. Una preparación deficiente puede resultar en tiempo de inactividad, brechas de seguridad o pérdida de datos. Esta etapa exige rigor y una mentalidad de "cero sorpresas".

<!--
Las tres palabras que definen un buen deploy: seguro, estable y reproducible.
Y la mentalidad: "cero sorpresas", es decir, nada se descubre recién en
producción.
-->

### Selección de Entorno

<!--
Elegir dónde va a vivir el sistema. La decisión depende del tipo de proyecto,
no de la moda.
-->

Evaluar y seleccionar la plataforma de hosting más adecuada según las necesidades del proyecto: Vercel o Netlify para frontends estáticos, Railway, Render o Heroku para backends ligeros, AWS, Azure o GCP para soluciones empresariales con mayor control y escalabilidad.

<!--
Para SaludWEB: la web es estática (build de Vite) y puede ir a Vercel,
Netlify o cualquier hosting con PHP; el backend necesita PHP 8.3 con SQLite,
así que conviene un hosting con PHP o un servidor propio.
-->

### Automatización CI/CD

<!--
El pipeline es lo que convierte "publicar" en un proceso repetible y sin
olvidos.
-->

Configurar un pipeline de integración y despliegue continuo (CI/CD) utilizando GitHub Actions, GitLab CI o CircleCI. El pipeline debe incluir: ejecución de tests automatizados, build del proyecto, y despliegue automático al aprobar un merge a la rama principal.

<!--
En este proyecto los comandos ya devuelven código de salida correcto, que es
lo que un pipeline necesita: `composer test`, `npm test`, `npm run build` y las
verificaciones E2E.
-->

### Checklist Final de Seguridad

<!--
La última barrera antes de abrir la puerta. Nada de esto es opcional.
-->

Antes del go-live: verificar que no haya credenciales expuestas en el código, configurar correctamente las variables de entorno en el servidor, revisar las reglas de firewall y puertos abiertos, y confirmar que el dominio y los certificados SSL están activos.

<!--
En SaludWEB: `.env` fuera de git, `JWT_SECRET` largo y propio, HTTPS en
producción, solo los puertos 80 y 443 abiertos, y los scripts de desarrollo
(`listar_usuarios.php`, `resetear_password.php`) fuera del servidor.
-->

## Verificación y Publicación

<!--
Octava etapa: verificar antes de anunciar. Publicar con errores visibles daña
la credibilidad antes de que el proyecto pueda demostrar su valor.
-->

El sistema está preparado, la documentación está lista y el entorno de producción está configurado. Ahora llega el momento de verificar que todo funcione exactamente como se espera antes de anunciarlo al mundo. Este paso es crítico: una publicación con errores visibles daña la credibilidad del proyecto antes de que pueda demostrar su valor.

<!--
La idea central: lo que funcionó en desarrollo no se asume, se valida en el
entorno real.
-->

### Test de Aceptación

<!--
Pruebas funcionales sobre los flujos que el usuario realmente usa.
-->

Ejecutar un conjunto de pruebas funcionales sobre las características core del sistema en el entorno de producción real. Verificar los flujos críticos de usuario: registro, autenticación, operaciones principales y cierre de sesión. No asumir que lo que funcionó en desarrollo funcionará en producción sin validarlo.

<!--
En SaludWEB el test de aceptación mínimo es: registrarse como paciente,
reservar un turno, verlo en el panel como administradora, confirmarlo y cerrar
sesión. Si esos cinco pasos funcionan, el núcleo está sano.
-->

### Monitoreo en Línea

<!--
Una vez publicado, hay que enterarse de los problemas antes que los usuarios.
-->

Confirmar que el sistema está accesible desde distintos navegadores y dispositivos. Configurar una herramienta de monitoreo como UptimeRobot, Sentry o Datadog para recibir alertas inmediatas ante caídas o errores críticos en producción. El monitoreo no es opcional: es la red de seguridad del sistema publicado.

<!--
El proyecto ya expone `GET /api/health`, que es justo lo que un monitor de
disponibilidad necesita para saber si la API está viva.
-->

### Plan de Contingencia

<!--
Qué hacer si algo falla en el peor momento: la presentación o el primer día de
uso real.
-->

Preparar un video demostrativo de alta calidad que muestre el sistema funcionando correctamente. Este video es el plan B ante cualquier fallo inesperado durante una presentación en vivo. Adicionalmente, tener documentados los pasos de rollback en caso de que el deploy deba revertirse urgentemente.

<!--
El video no reemplaza la demo en vivo: la respalda. Y el rollback documentado
convierte un incidente en un procedimiento, no en un improvisación.
-->

¡Sistema verificado y en línea! El proyecto ha superado la etapa de desarrollo privado y está listo para recibir usuarios reales y retroalimentación del mundo real.

<!--
Cierre de la guía: la publicación no es el final, es el comienzo de la etapa
en la que el proyecto recibe usuarios y devuelve aprendizaje.
-->

## Entregable: El Paquete

<!--
El texto original termina acá. "El paquete" es el conjunto que se entrega:
los tres repositorios públicos y prolijos, los README con inicio rápido y
enlaces, las guías (código, tiempo real, producción y defensa, documentar y
publicar), el registro de cambios y las evidencias de las verificaciones.
-->
