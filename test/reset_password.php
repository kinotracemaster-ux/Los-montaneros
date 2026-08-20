<?php
// test/reset_password.php

// Rutas a tus ficheros de datos (ajusta según tu estructura)
define('TOKENS_FILE', __DIR__ . '/tokens.json');
define('USERS_FILE',  __DIR__ . '/users.json');

// Carga tokens existentes
$tokens = file_exists(TOKENS_FILE)
    ? json_decode(file_get_contents(TOKENS_FILE), true)
    : [];

// Carga usuarios existentes (email => ['password' => hash])
$users = file_exists(USERS_FILE)
    ? json_decode(file_get_contents(USERS_FILE), true)
    : [];

$error = '';
$success = false;

// Si es POST, procesamos el cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validaciones básicas
    if (!$token || !isset($tokens[$token])) {
        $error = 'Token inválido o expirado.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $email = $tokens[$token];

        // Hashear la contraseña nueva
        $users[$email]['password'] = password_hash($password, PASSWORD_DEFAULT);

        // Guardar nuevo estado de usuarios
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));

        // Eliminar el token usado
        unset($tokens[$token]);
        file_put_contents(TOKENS_FILE, json_encode($tokens, JSON_PRETTY_PRINT));

        $success = true;
    }
}

// Si viene de GET, tomamos token de la URL
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Restablecer Contraseña</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100 p-6">
  <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
    <?php if ($success): ?>
      <h2 class="text-2xl font-semibold mb-4 text-green-600">¡Contraseña actualizada!</h2>
      <p>Puedes <a href="../index.html" class="text-blue-600 underline">iniciar sesión</a> con tu nueva contraseña.</p>

    <?php else: ?>
      <h2 class="text-xl font-semibold mb-4">Restablecer contraseña</h2>

      <?php if ($error): ?>
        <div class="mb-4 p-2 bg-red-100 text-red-700 rounded"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!$token || !isset($tokens[$token])): ?>
        <p class="text-gray-700">Enlace inválido o ya expiró.</p>
      <?php else: ?>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
          <div>
            <label class="block mb-1">Nueva contraseña:</label>
            <input type="password" name="password" required minlength="6"
                   class="w-full border rounded p-2" placeholder="Mínimo 6 caracteres" />
          </div>
          <button type="submit"
                  class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700">
            Cambiar contraseña
          </button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
