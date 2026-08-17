<?php

$file = 'routes/web.php';
$content = file_get_contents($file);

// Replace the specific error message
$oldMsg = "'auto' => 'No active classes found in the users table.'";
$newMsg = "'auto' => 'No active classes/divisions found. Please add classes/divisions before generating allocation.'";

$content = str_replace($oldMsg, $newMsg, $content);

file_put_contents($file, $content);
echo "Patched error message in $file\n";
