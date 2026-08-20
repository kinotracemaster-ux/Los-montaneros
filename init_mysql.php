<?php
/**
 * init_mysql.php
 * Crea las tablas en MySQL si no existen.
 * Ejecutar UNA VEZ después de agregar el servicio MySQL en Railway:
 *   php init_mysql.php
 */

$host   = getenv('MYSQL_HOST')     ?: 'localhost';
$port   = getenv('MYSQL_PORT')     ?: '3306';
$dbname = getenv('MYSQL_DATABASE') ?: '';
$user   = getenv('MYSQL_USER')     ?: 'root';
$pass   = getenv('MYSQL_PASSWORD') ?: '';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "✅ Conexión exitosa a MySQL\n";
} catch (PDOException $e) {
    die("❌ Error de conexión: " . $e->getMessage() . "\n");
}

$db->exec("CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(500) NOT NULL,
    date VARCHAR(20) NOT NULL,
    path VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
echo "✅ Tabla 'documents' lista\n";

$db->exec("CREATE TABLE IF NOT EXISTS codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    code VARCHAR(200) NOT NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
echo "✅ Tabla 'codes' lista\n";

$db->exec("CREATE INDEX IF NOT EXISTS idx_codes_code ON codes(code)");
echo "✅ Índice en 'codes.code' listo\n";

echo "\n🎉 Base de datos inicializada correctamente.\n";
