Preparar el Sistema para Producción y Defensa

<!-- Documento transversal del proyecto SaludWEB. Explica cómo llevar un sistema
desde el desarrollo hasta la producción con garantías de calidad, y cómo defenderlo
con solidez. Está publicado, comentado línea por línea, en la raíz de los tres
repositorios: Backend, Web y Mobile. -->

Una guía práctica para equipos de desarrollo que buscan llevar sus proyectos al entorno de producción con garantías de calidad, estabilidad y documentación profesional. Desde la configuración de ambientes hasta la redacción de documentación técnica y funcional, este material cubre todos los pasos necesarios para presentar y defender un proyecto con solidez.

<!-- Párrafo de presentación: define el alcance (calidad, estabilidad, documentación)
y el destinatario (equipos que van a producir y defender un proyecto). -->

Guía Técnica

<!-- Encabezado de la serie documental a la que pertenece esta guía. -->

Producción & Defensa

<!-- Subtítulo del capítulo: el tema es cómo pasar a producción y cómo defender el
trabajo ante un evaluador. -->

Ambientes: Desarrollo, Prueba y Producción

<!-- Sección 1. Motiva la separación en ambientes: es la base de todo lo demás
(variables de entorno, deploy y backups tienen sentido recién cuando se entiende
que el sistema corre en más de un lugar distinto. -->

La separación en ambientes es una práctica fundamental en el ciclo de vida del software. Cada ambiente cumple un rol específico y permite que el equipo trabaje con seguridad sin comprometer la estabilidad del sistema en producción.

 Desarrollo (Development)

<!-- 1.1 El ambiente donde vive el código mientras se escribe. En SaludWEB este es
el entorno diario: backend con `composer run dev` (servidor nativo de PHP en el
puerto 8000) y frontend con `npm run dev` (Vite en el puerto 5173). -->

Es el ambiente local de cada desarrollador. Aquí se escriben nuevas funcionalidades, se corrigen errores y se experimentan cambios. Los errores en este entorno son esperados y no afectan al usuario final. Se trabaja con datos de prueba, bases de datos locales y servidores embebidos.

<!-- Puntos fuertes del ambiente de desarrollo, pensados para la velocidad: -->

Libertad para probar cambios sin consecuencias

Hot reload y herramientas de depuración activas

Variables de entorno específicas del desarrollador

<!-- 1.2 El ambiente de prueba (staging / QA): la copia de producción donde se
valida antes de afectar a usuarios reales. En SaludWEB corresponde a un servidor
con varios workers (Apache o nginx + PHP-FPM) porque ahí sí debe funcionar el
canal SSE real, que en desarrollo se simula con sondeo. -->

 Prueba (Staging / QA)

Replica el entorno de producción lo más fielmente posible. Es el ambiente donde se validan los cambios antes de desplegarlos a los usuarios reales. Se ejecutan pruebas de integración, pruebas de aceptación y revisiones por parte del equipo de QA o del cliente.

<!-- Tres pilares del staging: -->

Misma configuración que producción

Datos anonimizados o de prueba realistas

Revisión por parte de QA y stakeholders

<!-- 1.3 Producción: el único ambiente que ve el usuario final. Acá no se
"improvisa": todo llega por el flujo Desarrollo → Prueba → Producción. -->

 Producción (Production)

El ambiente al que acceden los usuarios reales. Requiere la mayor estabilidad, rendimiento y seguridad. Todo cambio que llegue aquí debe haber pasado previamente por los ambientes anteriores. Los errores aquí tienen impacto directo en el negocio y en la experiencia del usuario.

<!-- Requisitos mínimos de un entorno de producción: -->

Alta disponibilidad y monitorización activa

Certificados SSL, HTTPS y firewalls configurados

Backups automáticos y políticas de recuperación

<!-- Regla de oro del capítulo: el cambio directo en producción es la causa más
común de incidentes evitables. -->

Nunca realices cambios directamente en producción. Todo debe pasar por el flujo: Desarrollo → Prueba → Producción.

Variables de Configuración

<!-- Sección 2. Explica cómo hace una misma base de código para funcionar en los
tres ambientes sin edits repetidos: variables de entorno (.env). En SaludWEB, el
Backend usa core/Env.php, la Web usa variables VITE_ y Mobile usa EXPO_PUBLIC_;
los tres son opcionales en desarrollo porque el código trae defaults seguros. -->

Las variables de configuración permiten que una misma base de código funcione correctamente en diferentes ambientes sin necesidad de modificar el código fuente. Son un componente esencial de cualquier aplicación preparada para producción.

¿Qué son las variables de entorno?

<!-- Definición breve para quien llega nuevo al tema. -->

Son pares clave-valor definidos fuera del código fuente que almacenan información sensible o dependiente del ambiente: credenciales de bases de datos, claves de API, URLs de servicios externos, modos de ejecución (debug, producción), etc.

<!-- El estándar de facto: archivos .env, con librerías que los cargan al arrancar. -->

El estándar más extendido es el uso de archivos .env gestionados con librerías como dotenv (Node.js), python-dotenv (Python) o mecanismos equivalentes en otros frameworks.

Buenas prácticas esenciales

<!-- Lista implícita que acompaña el título anterior. La regla más importante
aparece sola, casi sin desarrollo, porque no necesita explicación: -->

Nunca subir el archivo

<!-- Conexión con SaludWEB: el .env real de los tres repos está en .gitignore;
lo que se versiona es .env.example, sin secretos. Es exactamente la práctica que
describe este punto. -->

Deploy en Hosting

<!-- Sección 3. El proceso de llevar el código al servidor de producción.
Describe el ciclo completo: preparar, configurar, desplegar y verificar. -->

El proceso de despliegue (deploy) consiste en llevar el código desde el repositorio hasta el servidor donde correrá en producción. Un buen proceso de deploy es repetible, automatizable y reversible.

<!-- 3.1 Primer paso: el repositorio tiene que estar listo y limpio. -->

Preparar el Repositorio

Asegúrate de que la rama principal (main o master) contiene el código listo para producción. Revisa que no haya credenciales expuestas ni archivos innecesarios.

<!-- 3.2 Segundo paso: el servidor de destino. -->

Configurar el Servidor

Elige el proveedor de hosting adecuado (Railway, Render, Heroku, VPS, etc.). Configura las variables de entorno, el servidor web (Nginx, Apache) y los servicios necesarios.

<!-- 3.3 Tercer paso: el despliegue en sí. -->

Ejecutar el Deploy

Conecta el repositorio al proveedor de hosting. Ejecuta los comandos de build y arranque. Verifica logs y estado del servicio tras el despliegue.

<!-- 3.4 Paso final e imprescindible: verificar que lo recién publicado funciona. -->

Verificar en Producción

Comprueba que la aplicación funciona correctamente: rutas, autenticación, base de datos, integraciones externas. Realiza un smoke test básico.

Opciones de hosting más comunes

<!-- Panorama breve de proveedores, con el tipo de proyecto al que conviene cada uno. -->

Railway / Render / Fly.io: Plataformas PaaS modernas, ideales para proyectos de estudiantes y startups. Despliegue desde GitHub con un clic.

Heroku: Clásico del sector, fácil de configurar y con buena documentación.

VPS (DigitalOcean, Linode, Hetzner): Mayor control, requiere configuración manual de servidor.

Vercel / Netlify: Óptimos para frontends estáticos o aplicaciones serverless.

CI/CD: Automatización del deploy

<!-- Subsección sobre automatización: el pipeline que corre solo en cada push. -->

Un pipeline de integración y entrega continua (CI/CD) automatiza las pruebas y el despliegue cada vez que se hace push al repositorio. Herramientas como GitHub Actions, GitLab CI o CircleCI permiten definir estos flujos como código. Un pipeline básico incluye: ejecutar tests → construir la imagen → desplegar en el servidor.

<!-- Conexión con SaludWEB: las suites de pruebas (tests/run.php en el Backend y
npm test en Web/Mobile) devuelven código de salida 0 o 1, que es exactamente lo
que un pipeline necesita para decidir si continúa o corta. -->

Backups: Copias de Seguridad

<!-- Sección 4. Los backups como responsabilidad, no como opción. -->

Un backup es una copia de seguridad de los datos y/o el código del sistema que permite restaurar el funcionamiento ante un fallo, error humano o ataque. En producción, los backups no son opcionales: son un requisito mínimo de responsabilidad.

¿Qué se debe respaldar?

<!-- Lista de qué incluye un backup completo. -->

Base de datos completa (dump SQL o equivalente)

Archivos subidos por usuarios (imágenes, documentos)

Ficheros de configuración del servidor

Código fuente (el repositorio Git ya actúa como backup del código)

<!-- Conexión con SaludWEB: la base es un único archivo SQLite
(storage/saludweb.sqlite), así que "dumpear la base" es copiar ese archivo.
Git ya respalda el código en los tres repos de GitHub. -->

Frecuencia y retención

<!-- Cuán seguido copiar y cuánto conservar. -->

Diario: Para proyectos con actividad constante

Semanal: Para proyectos con menor volumen de cambios

Retención mínima: Conservar al menos los últimos 7 días

Regla 3-2-1: 3 copias, en 2 soportes distintos, 1 fuera del sitio

Herramientas y dónde almacenar

<!-- Dónde y con qué hacer los backups. -->

pg_dump / mysqldump: Comandos estándar para bases de datos relacionales

Almacenamiento: Amazon S3, Google Cloud Storage, Backblaze B2

Automatización: Cron jobs, scripts de shell o servicios managed del proveedor

Verificación: Probar periódicamente la restauración del backup

<!-- La advertencia más importante de la sección: un backup sin prueba de
restauración puede fallar justo cuando más se necesita. -->

Un backup que nunca se ha probado no es un backup fiable. Programa restauraciones de prueba periódicas para verificar que tus copias son funcionales.

README Profesional

<!-- Sección 5. El README como primera impresión del repositorio. En SaludWEB,
los tres repos tienen un README largo que cumple este estándar: qué hace, cómo se
instala, cómo se usa, credenciales de demo y enlaces al resto de la documentación. -->

El archivo README.md es la primera impresión que cualquier persona tiene de tu proyecto. Es la puerta de entrada al repositorio y debe responder a las preguntas fundamentales: ¿qué hace este proyecto?, ¿cómo se instala?, ¿cómo se usa? Un README bien escrito refleja el nivel de profesionalidad del equipo.

Título y descripción

<!-- Qué debe tener el encabezado del README. -->

Nombre del proyecto, logo si existe, y una descripción clara y concisa de qué hace la aplicación y para quién está pensada. Incluir badges de estado (build passing, cobertura de tests, versión).

Tecnologías utilizadas

<!-- El stack, con versiones, para poder replicar el entorno. -->

Stack tecnológico con versiones: lenguaje, framework, base de datos, servicios externos. Esto facilita la replicación del entorno y deja claro el contexto técnico del proyecto.

Instalación y configuración

<!-- Los pasos para clonar y levantar el proyecto en local, copiables y pegables. -->

Pasos detallados para clonar el repositorio, instalar dependencias, configurar variables de entorno y ejecutar el proyecto en local. Cada comando debe poder copiarse y ejecutarse directamente.

Uso y funcionalidades principales

<!-- Qué puede hacer el evaluador con la aplicación, sin tener que ejecutarla. -->

Ejemplos de uso, capturas de pantalla o GIFs animados que muestren las funcionalidades clave. El evaluador debe entender qué puede hacer con la aplicación sin tener que ejecutarla.

Estructura del proyecto y contribución

<!-- Mapa de carpetas y, si el equipo es colaborativo, el flujo de contribución. -->

Árbol de directorios con una breve explicación de cada carpeta relevante. Si el proyecto es colaborativo, incluir guía de contribución, código de conducta y proceso de pull requests.

Documentación Funcional

<!-- Sección 6. Qué hace el sistema, desde el punto de vista del usuario, sin
hablar de implementación. Es el documento que lee un cliente o un tribunal. -->

La documentación funcional describe qué hace el sistema desde el punto de vista del usuario. No explica cómo está implementado, sino qué problemas resuelve, cómo se usa y cuáles son sus reglas de negocio. Es el documento que debe poder leer un cliente, un evaluador o un nuevo miembro no técnico del equipo.

Contenido esencial

<!-- Los bloques mínimos de una documentación funcional. -->

Objetivo del sistema: Qué problema resuelve y a quién va dirigido.

Roles de usuario: Tipos de usuarios (administrador, cliente, editor) y qué puede hacer cada uno.

Casos de uso: Descripción de las acciones principales que puede realizar cada rol.

Flujos de trabajo: Secuencia de pasos para completar tareas clave (registro, compra, publicación, etc.).

Reglas de negocio: Condiciones, validaciones y restricciones que rigen el comportamiento del sistema.

Glosario: Definición de términos específicos del dominio del proyecto.

<!-- Conexión con SaludWEB: los roles (admin, médico, paciente), las reglas de la
turnera y el flujo de reserva/cancelación están descritos en los README y en
AGENDA_DE_TRABAJO.md; ese es el material funcional del proyecto. -->

Formato y herramientas recomendadas

<!-- Posibles formatos del entregable funcional. -->

La documentación funcional puede adoptar distintos formatos según el contexto del proyecto:

Documento Word / PDF: Formato clásico, fácil de entregar y revisar.

Notion / Confluence: Wikis colaborativas online, ideales para equipos.

Markdown en el repositorio: Documentación junto al código, fácil de versionar.

Historias de usuario: Formato ágil: "Como [rol], quiero [acción] para [beneficio]".

Errores frecuentes

<!-- Los tres errores más comunes al documentar, en orden inverso de aparición. -->

Documentar lo que se pensó hacer, no lo que realmente se implementó.

Usar jerga técnica en un documento dirigido a usuarios no técnicos.

No actualizar la documentación cuando el sistema cambia.

<!-- Cierre de la sección: por qué la documentación funcional pesa en la defensa. -->

La documentación funcional es especialmente valiosa en la defensa del proyecto: permite al tribunal entender el valor y el alcance del sistema sin necesidad de ejecutarlo.

Documentación Técnica

<!-- Sección 7. Cómo está construido el sistema, para desarrolladores y
evaluadores técnicos. A diferencia de la funcional, sí habla de arquitectura,
datos e implementación. -->

La documentación técnica describe cómo está construido el sistema internamente. Su audiencia son otros desarrolladores, el equipo de operaciones y los evaluadores técnicos. Debe permitir que alguien externo entienda la arquitectura, instale el entorno y mantenga el código sin necesidad de preguntar.

Arquitectura del sistema

<!-- Componentes principales y patrón arquitectónico. -->

Descripción de los componentes principales: frontend, backend, base de datos, servicios externos. Incluir el patrón arquitectónico utilizado (MVC, microservicios, monolito, etc.) y cómo se comunican entre sí.

<!-- Conexión con SaludWEB: la API es un monolito en capas
(routes → controllers → services → persistence) y las dos frontends (SPA web y
app móvil) la consumen por REST. Esa descripción vive en GUIA_DE_CODIGO.md. -->

Modelo de datos

<!-- El diagrama ER y la descripción de tablas: la base del entendimiento de datos. -->

Diagrama Entidad-Relación (ER) y descripción de las tablas o colecciones principales: campos, tipos, relaciones, restricciones e índices. Fundamental para entender cómo se almacena y organiza la información.

Documentación de la API

<!-- El contrato del backend: endpoints, parámetros, respuestas y códigos. -->

Listado de endpoints con método HTTP, URL, parámetros, cuerpo de la petición, respuestas posibles y códigos de estado. Herramientas como Swagger/OpenAPI o Postman Collections facilitan enormemente esta tarea.

Estructura del código

<!-- Organización de carpetas, convenciones y decisiones de diseño. -->

Explicación de la organización de carpetas y ficheros: dónde viven los modelos, controladores, servicios, tests, etc. Convenciones de nombrado, patrones de diseño aplicados y decisiones de arquitectura relevantes.

<!-- Complementos de una documentación técnica completa, más allá de lo anterior: -->

Además del contenido anterior, una documentación técnica completa debe incluir: instrucciones de instalación del entorno de desarrollo, guía de ejecución de tests, descripción del proceso de deploy, changelog de versiones y cualquier deuda técnica conocida con su justificación.

Diagramas Necesarios

<!-- Sección 8. Los diagramas como herramienta de comunicación en la defensa. -->

Los diagramas son herramientas de comunicación visual que permiten transmitir información compleja de forma clara y eficiente. En la defensa de un proyecto, un buen conjunto de diagramas puede marcar la diferencia entre una presentación confusa y una exposición brillante.

 Diagrama Entidad-Relación (ER)

<!-- 8.1 El diagrama de datos: tablas, columnas y relaciones. -->

Representa la estructura de la base de datos: entidades (tablas), atributos (columnas) y relaciones entre ellas (uno a uno, uno a muchos, muchos a muchos). Es uno de los diagramas más valorados en evaluaciones técnicas. Herramientas: dbdiagram.io, Draw.io, MySQL Workbench.

 Diagrama de Casos de Uso

<!-- 8.2 El diagrama funcional: qué puede hacer cada actor con el sistema. -->

Muestra qué puede hacer cada tipo de actor (usuario, administrador, sistema externo) con la aplicación. Es el puente entre la documentación funcional y la técnica. Parte del estándar UML. Herramientas: Lucidchart, Draw.io, PlantUML.

 Diagrama de Arquitectura

<!-- 8.3 El diagrama de componentes: piezas del sistema y sus conexiones. -->

Representa los componentes principales del sistema y cómo se comunican: cliente, servidor, base de datos, servicios externos, CDN, etc. Puede ser un diagrama de componentes UML o un esquema informal pero claro. Fundamental para defender decisiones de diseño.

 Diagrama de Flujo / Secuencia

<!-- 8.4 El diagrama dinámico: cómo transcurre un proceso concreto paso a paso. -->

Ilustra cómo fluye la información o las acciones a lo largo del sistema para un proceso concreto (login, pago, registro). Los diagramas de secuencia UML son especialmente útiles para mostrar la interacción entre capas (frontend  API  base de datos).

<!-- Conjunto mínimo recomendado para la defensa, y por qué estos tres: -->

Un conjunto mínimo recomendado para la defensa incluye: diagrama ER, diagrama de casos de uso y diagrama de arquitectura. Estos tres, bien elaborados, cubren las dimensiones de datos, funcionalidad y tecnología del sistema.

Cierre: La Documentación como Parte del Producto

<!-- Sección 9. Reflexión final: la documentación como parte del software, no
como trámite burocrático posterior. -->

"Un proyecto sin documentación es difícil de evaluar y mantener."

<!-- Desarrollo de la idea anterior, con la distinción clave entre "funciona hoy"
y "funciona mañana": -->

La documentación no es un trámite burocrático que se completa al final del proyecto: es una parte integral del software profesional. Un sistema perfectamente codificado pero sin documentar es un sistema incompleto. La documentación comunica intención, facilita la colaboración, permite la evolución del código y demuestra madurez técnica.

 Lista de verificación para la defensa

<!-- Checklist final: los ocho puntos que un proyecto debe tener listos antes de
la defensa. En SaludWEB, los ocho están cubiertos y son verificables en los
repositorios de GitHub. -->

Ambientes correctamente separados y configurados (dev, staging, prod)

Variables de entorno gestionadas de forma segura

Aplicación desplegada y accesible en producción

Sistema de backups configurado y probado

README completo y actualizado en el repositorio

Documentación funcional redactada para usuarios y evaluadores

Documentación técnica disponible para desarrolladores

Diagramas (ER, casos de uso, arquitectura) incluidos

 El objetivo final

<!-- El estándar que se persigue: que el evaluador no necesite preguntar nada. -->

Un evaluador que acceda a tu proyecto debe poder entender qué hace, cómo funciona, cómo instalarlo y cómo usarlo sin necesidad de preguntarte nada. Ese es el estándar de un proyecto profesional. La documentación no solo facilita la evaluación: demuestra que el equipo es capaz de trabajar de forma ordenada, comunicarse con claridad y entregar un producto completo y mantenible a largo plazo.

<!-- Frase de cierre del documento, con la dualidad código / documentación: -->

El código es lo que hace que funcione hoy. La documentación es lo que hace que funcione mañana.
