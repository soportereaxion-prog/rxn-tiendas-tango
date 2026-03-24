# Clientes Web y Mapper de Pedidos — IDs Internos Comerciales (Process 2117)

## Contexto
El ERP Tango fallaba en la ingesta de las órdenes de venta emitiendo un mensaje: "No existe condición de venta para el ID_GVA01 ingresado: 3", el cual dictaminaba que estábamos enviando códigos identificadores UI externos (como Condición Venta = '3' o Transporte = '02') dentro del payload cuando el sistema estricto de process 19845 (Pedidos) requería IDs SQL internos (ID_GVA01, etc).

## Problema
El sistema contaba con los IDs externos en caché gracias a `ClienteTangoLookupService.php` usando la API `GetByFilter`, la cual enmascara los IDs internos y solo retorna los strings crudos legibles. Por ende la base de datos `clientes_web` solo almacenaba los valores string perdiendo acceso permanente a las verdaderas llaves para mapear el pedido de manera lícita.

## Decisión
Se auditaron y probaron endpoints de la API documentada, descartando hacer peticiones independientes masivas (process 952 y 960) a favor de implementar una segunda barrera automática en la validación local al `GetById?process=2117`. Esta petición única engloba e inyecta la configuración completa e interna de los IDs de todas las variables relacionales del cliente atómico desde Tango hacia el backend TiendaIA. 

Se crearon las columnas locales nativas (`id_gva01_tango`, etc.) para poder retener ambos datos y preservar la lectura externa intacta si el front end las presentaba.

## Archivos afectados
- `app/modules/ClientesWeb/Services/ClienteTangoLookupService.php` (Agregada petición secundaria en tiempo real)
- `app/modules/ClientesWeb/ClienteWebRepository.php` (Alteración en inyección de datos actualizados)
- `app/modules/Tango/Mappers/TangoOrderMapper.php` (Apuntando el mapper Order a la nueva ruta nativa)
- Base de Datos `rxn_tiendas_core` (Ejecución de ALTER TABLE para append IDs INT a la entidad clientes)

## Implementación
1. Colocación de columnas INT en MySQL `clientes_web`.
2. Encadenado de proceso 2117 `GetById` justo después de validar el cliente para obtener IDs nativos.
3. Modificación del JSON de pedidos para usar `id_gva*_tango` al compilar el payload.

## Impacto
El enlazador de Tango ya es inmune a las fallas de mapeo referencial de cliente durante los carritos de compra y envío masivo, resolviendo el falso negativo. 

## Riesgos
Agrega sobrecarga de 1 micro-petición extra por validación individual de código de Cliente hacia la API de Axoft.

## Notas
Las nuevas columnas SQL garantizan que los valores string sigan mostrándose intactos en edición de Cliente Web en `views/edit.php`.
