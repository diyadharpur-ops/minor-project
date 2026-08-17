<?php

$file = 'routes/web.php';
$content = file_get_contents($file);

// Remove the erroneous clone keyword on strings
$content = str_replace("clone \$dept->name", "\$dept->name", $content);
$content = str_replace("clone \$base->semester", "\$base->semester", $content);

file_put_contents($file, $content);
echo "Patched clone error in $file\n";
