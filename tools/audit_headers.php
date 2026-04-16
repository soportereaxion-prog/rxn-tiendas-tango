<?php
$dirs = ['d:/RXNAPP/3.3/www/rxn_suite/app/modules', 'd:/RXNAPP/3.3/www/rxn_suite/app/shared'];
$results = [];

foreach($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $ite = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($ite) as $filename => $cur) {
        if(pathinfo($filename, PATHINFO_EXTENSION) == 'php' && strpos($filename, 'views') !== false) {
            $content = file_get_contents($filename);
            
            // Extract typical headers and topbars blocks
            $extracted = [];
            
            // 1. D-flex header (common in admin/dashboard)
            if(preg_match('/<div class="d-flex\s+(?:flex-column\s+flex-[^"]+|justify-content-[^"]+)*\s*mb-4[^"]*".*?<\/div>\s*<\/div>/is', $content, $m)) {
                $extracted['d_flex_header'] = trim($m[0]);
            } elseif(preg_match('/<div class="d-flex[^"]*justify-content-between[^"]*mb-4[^"]*".*?<\/div>/is', $content, $m)) {
                $extracted['d_flex_header'] = trim($m[0]);
            }

            // 2. breadcrumb
            if(preg_match('/<nav aria-label="breadcrumb"[^>]*>.*?<\/nav>/is', $content, $m)) {
                $extracted['breadcrumb_nav'] = trim($m[0]);
            } elseif(preg_match('/<ol class="breadcrumb"[^>]*>.*?<\/ol>/is', $content, $m)) {
                $extracted['breadcrumb_ol'] = trim($m[0]);
            }

            // 3. Header tags
            if(preg_match('/<header[^>]*>.*?<\/header>/is', $content, $m)) {
                $extracted['header_tag'] = trim($m[0]);
            }
            
            // 4. Nav navbar tags
            if(preg_match('/<nav class="navbar[^>]*>.*?<\/nav>/is', $content, $m)) {
                $extracted['navbar_tag'] = trim($m[0]);
            }

            // 5. topbar class
            if(preg_match('/<div class="[^"]*topbar[^"]*"[^>]*>.*?<\/div>/is', $content, $m)) {
                $extracted['topbar_div'] = trim($m[0]);
            }

            if (!empty($extracted)) {
                // Truncate length
                foreach($extracted as $k => $v) {
                    $extracted[$k] = substr(strip_tags($v, '<nav><ul><li><a><button><div><h2><h3><h4><span><i>'), 0, 500); 
                }
                
                $relPath = str_replace(['d:/RXNAPP/3.3/www/rxn_suite/', '\\'], ['', '/'], $filename);
                $results[$relPath] = $extracted;
            }
        }
    }
}

file_put_contents('d:/RXNAPP/3.3/www/rxn_suite/temp_headers.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Done! Found " . count($results) . " files with headers.\n";
