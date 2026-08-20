<?php
$host   = 'sql200.infinityfree.com';
$dbname = 'if0_39064130_buscador';
$user   = 'if0_39064130';
$pass   = 'POQ2ODdvhG';
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $count = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    echo "Conexión OK, documentos en DB: $count";
} catch (PDOException $e) {
    echo "Fallo conexión: ".$e->getMessage();
}
