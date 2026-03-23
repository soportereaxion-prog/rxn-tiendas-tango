# ESTADO ACTUAL

## módulos tocados

* módulo: empresas (Fase 1 - Alta y Listado)
* módulo: core (rutas base y DB temporal)

## decisiones

* Se implementó el módulo Empresas siguiendo la arquitectura definida por `AGENTS.md` y `APP` actual.
* Las capas creadas fueron Entidad (`Empresa.php`), Repositorio (`EmpresaRepository.php`), Servicio (`EmpresaService.php`), Controlador (`EmpresaController.php`) y Vistas.
* Se estructuraron las vistas usando un CDN de Bootstrap 5 para el render inicial.

## riesgos

* Las variables de entorno en `.env` deben ser idénticas en producción.
* La ruta base de `/rxnTiendasIA/public` está "hardcodeada" en la clase `Request`. Se debe evaluar migrar a dinámico a futuro.

## próximo paso

* Avanzar a modulo Empresas (Fase 2): Edición, Baja Lógica, Validación de CUIT o multiempresa contextual.
