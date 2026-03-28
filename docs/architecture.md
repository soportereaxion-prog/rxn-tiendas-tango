# Reglas de Integracion Connect

## Patron Local-First

- Toda pantalla que dependa de Tango Connect debe renderizar primero desde la base local (`MySQL/MariaDB`).
- Los valores guardados localmente son la referencia visual y operativa inicial del formulario.
- Connect solo se consulta por accion explicita del operador, usando el boton o comando de sincronizacion correspondiente.
- Una consulta remota nunca debe pisar automaticamente lo ya persistido localmente al abrir una pantalla.
- Si un valor guardado ya no existe en Connect, la UI debe conservar un fallback visible y no vaciar el dato sin decision del usuario.

## Regla de Persistencia

- Guardar un formulario debe persistir primero en BD local los campos editables del modulo.
- Si el guardado deriva de una sincronizacion remota, esa accion debe estar marcada por una bandera explicita del flujo y no por heuristica de hidden fields precargados.
- Los paneles resumen (`Ver/Editar`) deben leer por defecto desde BD local y solo enriquecer con Connect bajo demanda.

## Aplicacion actual

- `Clientes Web`: vinculo comercial Tango y overrides comerciales para pedidos.
- `EmpresaConfig`: parametros de integracion Connect (empresa, listas y deposito).
