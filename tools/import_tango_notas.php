<?php
/**
 * Importación de Notas de CRM desde SQL Server (Sandbox) hacia MySQL (Módulo CrmNota)
 * Autogenerado vía Planificación de SDD
 */

define('BASE_PATH', dirname(__DIR__));

date_default_timezone_set('America/Argentina/Buenos_Aires');

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

if (is_file(BASE_PATH . '/app/core/helpers.php')) {
    require_once BASE_PATH . '/app/core/helpers.php';
}

use App\Core\Database;
use App\Modules\CrmNotas\CrmNota;
use App\Modules\CrmNotas\CrmNotaRepository;

/**
 * Routine to parse SQL Server RTF blob into plain text cleanly
 */
function parseTangoRtfToText($text) {
    if (str_starts_with($text, '0x')) {
        $text = substr($text, 2);
    }
    
    if (strlen($text) % 2 === 0 && preg_match('/^[0-9a-fA-F]+$/', substr($text, 0, 100))) {
        $bin = hex2bin($text);
        if (str_contains($bin, "\x00")) {
            $text = mb_convert_encoding($bin, 'UTF-8', 'UTF-16LE');
        } else {
            $text = $bin; 
        }
    }
    
    if (!$text || !str_contains($text, '{\rtf')) {
        return trim(mb_convert_encoding($text, 'UTF-8', 'Windows-1252'));
    }
    
    $len = strlen($text);
    $out = "";
    $i = 0;
    while ($i < $len) {
        $c = $text[$i];
        
        if ($c === '{') {
            $nextChunk = substr($text, $i, 30);
            if (
                str_starts_with($nextChunk, '{\\*') ||
                str_starts_with($nextChunk, '{\\fonttbl') ||
                str_starts_with($nextChunk, '{\\colortbl') ||
                str_starts_with($nextChunk, '{\\stylesheet') ||
                str_starts_with($nextChunk, '{\\info')
            ) {
                $depth = 1;
                $i++;
                while ($i < $len && $depth > 0) {
                    if ($text[$i] === '{') $depth++;
                    if ($text[$i] === '}') $depth--;
                    if ($text[$i] === '\\' && $i+1 < $len && str_contains('{}', $text[$i+1])) {
                        $i++;
                    }
                    $i++;
                }
                continue;
            } else {
                $i++;
                continue;
            }
        }
        
        if ($c === '}') {
            $i++;
            continue;
        }
        
        if ($c === '\\') {
            $i++;
            if ($i >= $len) break;
            
            if (str_contains('\\{}~-', $text[$i])) {
                $out .= $text[$i];
                $i++;
                continue;
            }
            
            if ($text[$i] === '\'') {
                $hex = substr($text, $i + 1, 2);
                if (ctype_xdigit($hex)) {
                    $out .= chr(hexdec($hex));
                    $i += 3;
                    continue;
                }
            }
            
            $cmd = "";
            while ($i < $len && ctype_alpha($text[$i])) {
                $cmd .= $text[$i];
                $i++;
            }
            $param = "";
            while ($i < $len && (ctype_digit($text[$i]) || $text[$i] === '-')) {
                $param .= $text[$i];
                $i++;
            }
            if ($i < $len && $text[$i] === ' ') {
                $i++;
            }
            
            if ($cmd === 'par' || $cmd === 'line') {
                $out .= "\n";
            } elseif ($cmd === 'tab') {
                $out .= "\t";
            }
            continue;
        }
        
        if ($c !== "\r" && $c !== "\n") {
            $out .= $c;
        }
        $i++;
    }
    
    $text = mb_convert_encoding($out, 'UTF-8', 'Windows-1252');
    return trim(preg_replace('/\n{3,}/', "\n\n", $text));
}

// 1. Initialize MS SQL Server connection
echo "Conectando a SQL Server Sandbox...\n";
$serverName = "SRVCHARLY\\SQLEXPRESS2019";
$db = "zzz_SDM_1";
$user = "Axoft";
$pass = "Axoft";

try {
    $dsn = "odbc:Driver={SQL Server Native Client 11.0};Server=$serverName;Database=$db;";
    $connSql = new PDO($dsn, $user, $pass);
} catch (Throwable $e) {
    try {
        $dsn2 = "odbc:Driver={SQL Server};Server=$serverName;Database=$db;";
        $connSql = new PDO($dsn2, $user, $pass);
    } catch (Throwable $e2) {
        die("❌ Error conectando a SQL Server: " . $e2->getMessage() . "\n");
    }
}
$connSql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. Query to fetch notes with their client codes
$sql = "
SELECT 
    CRM_CLIENTES.COD_Tango AS [CodigoTango],
    CRM_NOTAS.Titulo,
    CRM_NOTAS.Descripcion
FROM CRM_CLIENTES
INNER JOIN CRM_NOTAS
    ON CRM_NOTAS.ID_CLIENTE = CRM_CLIENTES.ID_CLIENTE
WHERE CRM_NOTAS.Descripcion IS NOT NULL
";
$stmt = $connSql->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ Extraídas " . count($rows) . " notas desde SQL Server.\n";

// 3. Initialize MySQL Local Connection via framework
$dbLocal = Database::getConnection();
$repo = new CrmNotaRepository();

// Stats
$stats = [
    'total' => count($rows),
    'imported' => 0,
    'skipped_duplicate' => 0,
    'matched_client' => 0,
    'unmatched_client' => 0
];

echo "Iniciando importación a MySQL...\n";

foreach ($rows as $row) {
    $codigoTango = trim($row['CodigoTango'] ?? '');
    $tituloRaw = $row['Titulo'] ?? 'Sin Título';
    $titulo = mb_convert_encoding($tituloRaw, 'UTF-8', 'Windows-1252'); // Decode ISO
    
    // Parse description
    $desc = parseTangoRtfToText($row['Descripcion']);
    if (empty($titulo)) $titulo = "Nota Importada";
    
    // Find Client ID dynamically
    $clienteId = null;
    if (!empty($codigoTango)) {
        $stmtClient = $dbLocal->prepare("SELECT id FROM crm_clientes WHERE TRIM(codigo_tango) = :codigo AND deleted_at IS NULL LIMIT 1");
        $stmtClient->execute(['codigo' => $codigoTango]);
        $clientIdRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
        if ($clientIdRow) {
            $clienteId = $clientIdRow['id'];
        }
    }
    
    // Check for duplicates based on titulo and a substring of description
    $checkDuplicateStmt = $dbLocal->prepare("
        SELECT id FROM crm_notas 
        WHERE titulo = :titulo AND SUBSTRING(contenido, 1, 50) = :subdesc
        LIMIT 1
    ");
    $checkDuplicateStmt->execute([
        'titulo' => $titulo,
        'subdesc' => mb_substr($desc, 0, 50)
    ]);
    if ($checkDuplicateStmt->fetch()) {
        $stats['skipped_duplicate']++;
        continue;
    }
    
    // Create new CrmNota
    $nota = new CrmNota();
    $nota->empresa_id = 1; // Default tenant
    $nota->cliente_id = $clienteId;
    $nota->titulo = $titulo;
    $nota->contenido = $desc;
    $nota->tags = ''; // Optional default
    $nota->activo = 1;
    
    $repo->save($nota);
    
    if ($clienteId) {
        $stats['matched_client']++;
    } else {
        $stats['unmatched_client']++;
    }
    $stats['imported']++;
}

echo "✅ Importación completada.\n";
echo "📊 Estadísticas:\n";
echo " - Total extraídas: " . $stats['total'] . "\n";
echo " - Importadas exitosamente: " . $stats['imported'] . "\n";
echo "   ↳ Vinculadas a Clientes (Tango): " . $stats['matched_client'] . "\n";
echo "   ↳ Sin vinculación de Cliente: " . $stats['unmatched_client'] . "\n";
echo " - Omitidas (Duplicadas): " . $stats['skipped_duplicate'] . "\n";
