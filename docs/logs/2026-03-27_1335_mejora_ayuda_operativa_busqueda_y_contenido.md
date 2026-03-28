# [Ayuda] - Mejora fuerte de ayuda operativa

## Que se hizo
- Se reconstruyo la ayuda operativa con mucho mas contenido, preguntas frecuentes y explicaciones orientadas a usuarios no tecnicos.
- Se agrego un buscador interno dentro de la propia ayuda para filtrar temas por palabra clave.
- Se amplio la documentacion fuente para que futuras iteraciones agreguen modulos, estados, botones y dudas comunes con el mismo criterio.

## Por que
- La primera version estaba demasiado resumida y no resolvia la necesidad real de acompañar a usuarios administradores con informacion util y buscable.

## Impacto
- La ayuda ahora sirve como referencia real de uso y no solo como recordatorio breve.
- Los usuarios pueden encontrar temas mas rapido sin leer toda la pagina linealmente.

## Decisiones tomadas
- Se mantuvo una implementacion liviana y compatible con el stack actual: HTML server-rendered + buscador client-side simple.
- Se priorizo lenguaje humano, pasos concretos, dudas comunes y botones que suelen prestarse a confusion.
