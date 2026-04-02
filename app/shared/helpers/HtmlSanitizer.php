<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

class HtmlSanitizer
{
    /**
     * Sanitiza el string con htmlspecialchars y luego deshace el encoding 
     * SOLO de etiquetas específicas permitidas y atributos seguros.
     * Permitido: <b>, <strong>, <i>, <em>, <u>, <br>, y <span style="..."> con reglas seguras.
     *
     * @param string $content
     * @return string
     */
    public static function allowSafeInlineHtml(string $content): string
    {
        // 1. Sanitizar todo el contenido base por seguridad (protege variables interpoladas)
        $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // 2. Decode etiquetas simples en bloque directo
        $allowedSimpleTags = ['b', 'strong', 'i', 'em', 'u', 'br'];
        foreach ($allowedSimpleTags as $tag) {
            // Apertura: &lt;b&gt;, &lt;br&gt;, &lt;br/&gt;, &lt;br /&gt;
            $safeContent = preg_replace('/&lt;(' . $tag . ')\s*\/?&gt;/i', '<$1>', $safeContent);
            // Cierre: &lt;/b&gt;
            $safeContent = preg_replace('/&lt;\/(' . $tag . ')&gt;/i', '</$1>', $safeContent);
        }

        // 3. Decode <span style="...">
        // Buscamos algo con la forma: &lt;span style=&quot; ALGO &quot;&gt;
        $safeContent = preg_replace_callback('/&lt;span\s+style=&quot;([^&quot;]+)&quot;&gt;/i', function ($matches) {
            $styleRaw = $matches[1];
            
            // Evaluamos que el style sea seguro (que solo contenga reglas inofensivas y no payloads XSS)
            $safeStyle = self::sanitizeStyleAttribute($styleRaw);
            
            if (!empty($safeStyle)) {
                return '<span style="' . $safeStyle . '">';
            }
            
            // Si el estilo era inseguro o vacío, devolvemos un span limpio
            return '<span>';
        }, $safeContent);

        // Permitimos cerrar el span
        $safeContent = preg_replace('/&lt;\/span&gt;/i', '</span>', $safeContent);

        return $safeContent;
    }

    /**
     * Extrae un style string y filtra solo las propiedades listadas.
     * Remueve llamadas funcionales extrañas (ej. url(), javascript:).
     */
    private static function sanitizeStyleAttribute(string $styleRaw): string
    {
        $allowedProperties = ['color', 'font-weight', 'font-style', 'text-decoration', 'font-size', 'background-color'];
        
        $rules = explode(';', $styleRaw);
        $safeRules = [];

        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            if (count($parts) === 2) {
                $prop = strtolower(trim($parts[0]));
                $value = trim($parts[1]);

                // Verificamos si la propiedad está en la whitelist
                if (in_array($prop, $allowedProperties, true)) {
                    // Prevenir inyecciones en el value como `url(...)` o comillas dobles saltadas
                    if (!preg_match('/(expression|url|javascript|vbscript|data):/i', $value) && !str_contains($value, '"') && !str_contains($value, "'")) {
                        $safeRules[] = "$prop: $value";
                    }
                }
            }
        }

        return implode('; ', $safeRules) . (!empty($safeRules) ? ';' : '');
    }
}
