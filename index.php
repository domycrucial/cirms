<?php
// CIRMS lives under public/; send browsers to the real front controller.
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
header('Location: ' . ($base === '' ? '' : $base) . '/public/', true, 302);
exit;
