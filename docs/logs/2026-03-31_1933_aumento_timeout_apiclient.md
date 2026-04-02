# Aumento de timeout en ApiClient

## Qué se hizo
Se incrementó el timeout del cliente HTTP (pp/Infrastructure/Http/ApiClient.php) de 30 a 120 segundos.

## Por qué
Las peticiones masivas a la API de Tango (sincronización de artículos y clientes), en entornos con alta cantidad de registros, superaban el tiempo de respuesta predeterminado de 30 segundos (Operation timed out after 30010 milliseconds), dejando fallido el proceso de actualización del CRM.

## Impacto
El sistema admitirá respuestas más demoradas para operaciones pesadas. Afecta transversalmente a todas las llamadas externas hechas pasando por ApiClient (pedidos POST, sincronizaciones GET), dándoles un margen máximo de 2 min.

## Decisiones tomadas
Se repone esta funcionalidad luego de haberse perdido en un git reset reciente.