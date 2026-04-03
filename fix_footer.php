<?php
$dirs = ['admin', 'customer', 'employee', 'salesman', 'Delivery'];
$count = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        $replaced = preg_replace("/<\?php\s+include\s+'(\.\.\/)*config\/footer\.php';\s*\?" . ">/", "", $content);
        if ($replaced !== null && $replaced !== $content) {
            // Means we found and removed it
            $content = $replaced;
            
            // Insert exactly 1 footer before the final </div> that encloses <!-- Bootstrap
            $footerCode = "    <?php include '../config/footer.php'; ?" . ">\n</div>\n\n<!-- Bootstrap";
            $content = preg_replace("/<\/div>\s*<!-- Bootstrap/s", $footerCode, $content, 1);
            
            file_put_contents($file, $content);
            echo "Fixed $file\n";
            $count++;
        }
    }
}
echo "Total fixed: $count\n";
?>
