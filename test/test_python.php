<?php
// Ejecuta "python3 --version" y muestra la salida
exec('python3 --version 2>&1', $output, $returnCode);

// Si no existe python3, prueba con "python --version"
if ($returnCode !== 0) {
    exec('python --version 2>&1', $output, $returnCode);
}

echo "<pre>";
echo "Código de salida: $returnCode\n";
echo "Salida:\n";
echo htmlspecialchars(implode("\n", $output));
echo "</pre>";
