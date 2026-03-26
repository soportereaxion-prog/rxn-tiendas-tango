# Ajuste Correctivo — Fix Paginación Estricta en Metadata Tango Connect

## Causa Raíz
Los selectores se mostraban con "Valor obsoleto" o su Option inicial "Cód. guardado" pese a que el `process=2941` y `process=984` sí poseían datos (ej: 1 = Casa Central). 
El causante no era un error de parseo en el Mapper ni un error de tipado Javascript, sino un comportamiento estricto y muchas veces silente del REST de Axoft Connect. En numerosos Process (especialmente maestros), si la request `/Api/Get` llega "desnuda" sin los tres parámetros canónicos de grilla (`view`, `pageSize`, `pageIndex`), Tango no responde con un `HTTP 400 Bad Request`, sino con un `HTTP 200 OK` portando un JSON con data en `null` o una `list` vacía `[]`.

Al inyectar ese Array vacío en el Frontend, el JavaScript iteraba 0 veces, asumía que el ID guardado no tenía contraparte ("No Match"), y procedía a imprimir la advertencia de Legacy Fallback ideada en el release anterior.

## Resolución Aplicada
Se re-arquitecturaron ambos getters (`getMaestroDepositos` y `getMaestroListasPrecio`) en `TangoApiClient.php`:
1. **Contrato Total Estricto**: Ahora la request forja ineludiblemente `['view' => '', 'pageSize' => 100, 'pageIndex' => $page]`.
2. **Ciclo Do-While (Paginador Infinito)**: Las catálogos se devoran exhaustivamente. El bucle inicializa en 0 y recorre páginas hasta que `count($data['data']['list']) < $pageSize` advierte el límite real del maestro.
3. **Parseo Robusto**: Para blindar posibles metamorfosis del JSON a futuro (un clásico en las iteraciones Connect), el parseador PHP se construyó agnóstico:
   - Apunta primero a los Keys nominales exactos (`CODIGO`, `ID_STA22`, `NUMERO_DE_LISTA`).
   - Apunta secundariamente a la Heurística si no lo encuentra (buscando la silueta de IDs en arrays).
   - Sanitizado trim+cast inamovible (Pulveriza los `" 1 "`).

## Resultado / Frontend UI
- El Front recibe un catálogo exhaustivo sin lagunas.
- El PHP dibuja el `data-original="1"`. Si se oprime "Validar", el Frontend recibe que `1 = Venta Mayorista`.
- Hay match perfecto Javascript -> Desaparece el texto "Cód Actual guardado" o el Warn, y el Select se auto-asigna visiblemente a "Venta Mayorista", sin alteraciones a nivel Database.
