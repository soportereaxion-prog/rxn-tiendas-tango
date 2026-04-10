<?php

declare(strict_types=1);

namespace App\Core;

class AdvancedQueryFilter
{
    /**
     * Construye un array de fragmentos SQL y parámetros a partir del request.
     * 
     * @param array $filters El array asociativo proveniente de $_GET['f'] ej: ['cliente' => ['op'=>'contiene', 'val'=>'pepe']]
     * @param array $columnMap El mapa de campos seguros y sus columnas SQL reales ej: ['cliente' => 'c.razon_social']
     * @return array [0 => string con sentencias AND puestas para concatenar, 1 => array de parametros bind variables]
     */
    public static function build(array $filters, array $columnMap): array
    {
        $sqlFragments = [];
        $params = [];

        $paramCounter = 0;
        foreach ($filters as $field => $data) {
            if (!isset($columnMap[$field])) {
                continue; // Prevent injection: solo permitimos campos listados en el WHILELIST.
            }

            $op = $data['op'] ?? '';
            $val = $data['val'] ?? '';
            $val2 = $data['val2'] ?? '';

            if ($val === '') {
                continue;
            }

            $realColumn = $columnMap[$field];
            $pName = ':adv_' . $paramCounter++;

            switch ($op) {
                case 'entre':
                    if ($val2 !== '') {
                        $pName2 = ':adv_' . $paramCounter++;
                        $sqlFragments[] = "$realColumn BETWEEN $pName AND $pName2";
                        $params[$pName] = $val;
                        $params[$pName2] = $val2;
                    } else {
                        // Fallback a mayor o igual si falta límite superior (robusto)
                        $sqlFragments[] = "$realColumn >= $pName";
                        $params[$pName] = $val;
                    }
                    break;
                case 'contiene':
                    $sqlFragments[] = "$realColumn LIKE $pName";
                    $params[$pName] = '%' . $val . '%';
                    break;
                case 'no_contiene':
                    $sqlFragments[] = "$realColumn NOT LIKE $pName";
                    $params[$pName] = '%' . $val . '%';
                    break;
                case 'empieza_con':
                    $sqlFragments[] = "$realColumn LIKE $pName";
                    $params[$pName] = $val . '%';
                    break;
                case 'termina_con':
                    $sqlFragments[] = "$realColumn LIKE $pName";
                    $params[$pName] = '%' . $val;
                    break;
                case 'igual':
                    $sqlFragments[] = "$realColumn = $pName";
                    $params[$pName] = $val;
                    break;
                case 'distinto':
                    $sqlFragments[] = "$realColumn != $pName";
                    $params[$pName] = $val;
                    break;
                case 'mayor_que':
                    $sqlFragments[] = "$realColumn > $pName";
                    $params[$pName] = $val;
                    break;
                case 'menor_que':
                    $sqlFragments[] = "$realColumn < $pName";
                    $params[$pName] = $val;
                    break;
            }
        }

        $whereSql = '';
        if (count($sqlFragments) > 0) {
            $whereSql = implode(' AND ', $sqlFragments);
        }

        return [$whereSql, $params];
    }
}
