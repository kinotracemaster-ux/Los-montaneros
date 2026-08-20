<?php
// api.php - Gestión completa de documentos y ZIP
// Asegúrate de no incluir espacios o BOM antes de <?php

// Cabecera JSON por defecto (se sobreescribe para ZIP)
header('Content-Type: application/json; charset=UTF-8');

// Configuración MySQL
$host   = 'sql200.infinityfree.com';
$dbname = 'if0_39064130_buscador';
$user   = 'if0_39064130';
$pass   = 'POQ2ODdvhG';

// Conexión PDO
$dsn = "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8";
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

// Ruta uploads
$uploadsDir = __DIR__ . '/uploads';

$action = $_REQUEST['action'] ?? '';
switch ($action) {

  // 1) Autocomplete de códigos
  case 'suggest':
    $term = trim($_GET['term'] ?? '');
    if ($term === '') {
      echo json_encode([]);
      exit;
    }
    $stmt = $db->prepare("SELECT DISTINCT code FROM codes WHERE code LIKE ? LIMIT 10");
    $stmt->execute(["{$term}%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;

  // 2) Búsqueda Inteligente
  case 'search':
    $codes = explode("\n", trim($_POST['codes'] ?? ''));
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $db->prepare("
      SELECT d.id, d.name, d.date, d.path, GROUP_CONCAT(c.code SEPARATOR ',') AS codes
      FROM documents d
      LEFT JOIN codes c ON c.document_id = d.id
      WHERE c.code IN ($placeholders)
      GROUP BY d.id
    ");
    $stmt->execute($codes);
    $res = $stmt->fetchAll();
    foreach ($res as &$row) {
      $row['codes'] = explode(',', $row['codes']);
    }
    echo json_encode($res);
    exit;

  // 3) Listar todos (para Consultar y Búsqueda por Código)
  case 'list':
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(0, (int)($_GET['per_page'] ?? 10));
    $offset   = ($page - 1) * $per_page;
    $sql = "SELECT id, name, date, path FROM documents";
    if ($per_page) $sql .= " LIMIT $per_page OFFSET $offset";
    $stmt = $db->query($sql);
    $data = $stmt->fetchAll();
    // Obtener códigos asociados
    foreach ($data as &$d) {
      $stmt2 = $db->prepare("SELECT code FROM codes WHERE document_id=?");
      $stmt2->execute([$d['id']]);
      $d['codes'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    }
    echo json_encode(['data' => $data]);
    exit;

  // 4) Subir documento
  case 'upload':
    $name = trim($_POST['name'] ?? '');
    $date = $_POST['date'] ?? '';
    $codes = explode("\n", trim($_POST['codes'] ?? ''));
    if (!$name || strlen($name) < 3) { echo json_encode(['error'=>'Nombre inválido']); exit; }
    if (!$date) { echo json_encode(['error'=>'Fecha inválida']); exit; }
    if (empty($codes)) { echo json_encode(['error'=>'Ingresa al menos un código']); exit; }
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error']) { echo json_encode(['error'=>'PDF inválido']); exit; }
    $ext = pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'pdf') { echo json_encode(['error'=>'Solo PDF']); exit; }
    $target = $uploadsDir . '/' . time() . '_' . basename($_FILES['pdf']['name']);
    if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $target)) { echo json_encode(['error'=>'Error al subir']); exit; }
    $db->prepare("INSERT INTO documents(name,date,path) VALUES(?,?,?)")->execute([$name,$date,basename($target)]);
    $docId = $db->lastInsertId();
    $stmt = $db->prepare("INSERT INTO codes(document_id,code) VALUES(?,?)");
    foreach ($codes as $c) { $stmt->execute([$docId,trim($c)]); }
    echo json_encode(['message'=>'Documento subido']);
    exit;

  // 5) ZIP de todos los PDFs
  case 'download_pdfs':
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="uploads_' . date('Ymd_His') . '.zip"');
    set_time_limit(0);
    ignore_user_abort(true);

    $tmp = tempnam(sys_get_temp_dir(), 'zip_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE) !== TRUE) {
      echo json_encode(['error'=>'No se pudo crear ZIP']); exit;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $f) {
      if (!$f->isDir()) {
        $filePath     = $f->getRealPath();
        $relativePath = substr($filePath, strlen($uploadsDir) + 1);
        $zip->addFile($filePath, $relativePath);
      }
    }
    $zip->close();
    if (ob_get_level()) ob_end_clean();
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    // vaciar buffers y forzar envío inmediato
    if (ob_get_level()) {
      ob_flush();
      flush();
    }
    unlink($tmp);
    exit;

  // 6) Editar documento
  case 'edit':
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $date  = $_POST['date'] ?? '';
    $codes = explode("\n", trim($_POST['codes'] ?? ''));
    if (!$id || !$name || strlen($name) < 3) { echo json_encode(['error'=>'Datos inválidos']); exit; }
    $stmt = $db->prepare("UPDATE documents SET name=?,date=? WHERE id=?");
    $stmt->execute([$name,$date,$id]);
    // Reemplazar PDF si subieron uno nuevo
    if (isset($_FILES['pdf']) && !$_FILES['pdf']['error']) {
      $ext = pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION);
      if (strtolower($ext)==='pdf') {
        $old = $db->prepare("SELECT path FROM documents WHERE id=?");
        $old->execute([$id]);
        @unlink("$uploadsDir/" . $old->fetchColumn());
        $target = $uploadsDir . '/' . time() . '_' . basename($_FILES['pdf']['name']);
        move_uploaded_file($_FILES['pdf']['tmp_name'], $target);
        $db->prepare("UPDATE documents SET path=? WHERE id=?")->execute([basename($target),$id]);
      }
    }
    // Actualizar códigos
    $db->prepare("DELETE FROM codes WHERE document_id=?")->execute([$id]);
    $stmt = $db->prepare("INSERT INTO codes(document_id,code) VALUES(?,?)");
    foreach ($codes as $c) { $stmt->execute([$id,trim($c)]); }
    echo json_encode(['message'=>'Documento editado']);
    exit;

  // 7) Eliminar documento (modal de confirmación)
  case 'delete':
    $id = (int)($_GET['id'] ?? 0);
    $pwd = $_GET['pwd'] ?? '';
    define('CLAVE_ELIMINACION','565');
    if ($pwd !== CLAVE_ELIMINACION) { echo json_encode(['error'=>'Clave incorrecta']); exit; }
    $old = $db->prepare("SELECT path FROM documents WHERE id=?");
    $old->execute([$id]);
    @unlink("$uploadsDir/" . $old->fetchColumn());
    $db->prepare("DELETE FROM codes WHERE document_id=?")->execute([$id]);
    $db->prepare("DELETE FROM documents WHERE id=?")->execute([$id]);
    echo json_encode(['message'=>'Documento eliminado']);
    exit;

  // Default
  default:
    echo json_encode(['error'=>'Acción inválida']);
    exit;
}
