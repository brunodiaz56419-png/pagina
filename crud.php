<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

/* ---------- Utilidades ---------- */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function money($v): string { return '$' . number_format((float)$v, 2); }
function flash(string $msg, string $tipo = 'ok'): void { $_SESSION['flash'] = [$msg, $tipo]; }
function ir(string $p): void { header('Location: crud.php?p=' . $p); exit; }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$paginas = ['inicio', 'usuarios', 'productos', 'ventas'];
$p = $_GET['p'] ?? 'inicio';
if (!in_array($p, $paginas, true)) { $p = 'inicio'; }

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flash('La sesión expiró. Vuelve a intentarlo.', 'error');
        ir($p);
    }
    $pdo = db();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        switch ($accion) {

            /* ----- USUARIOS ----- */
            case 'guardar_usuario':
                $nombre = trim($_POST['nombre'] ?? '');
                $correo = trim($_POST['correo'] ?? '');
                $tel    = trim($_POST['telefono'] ?? '');
                $activo = isset($_POST['activo']) ? 1 : 0;
                if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    flash('Escribe un nombre y un correo válido.', 'error');
                    ir('usuarios');
                }
                if ($id > 0) {
                    $pdo->prepare('UPDATE usuarios SET nombre=?, correo=?, telefono=?, activo=? WHERE id=?')
                        ->execute([$nombre, $correo, $tel ?: null, $activo, $id]);
                    flash('Usuario actualizado.');
                } else {
                    $pdo->prepare('INSERT INTO usuarios (nombre, correo, telefono, activo) VALUES (?,?,?,?)')
                        ->execute([$nombre, $correo, $tel ?: null, $activo]);
                    flash('Usuario creado.');
                }
                ir('usuarios');

            case 'eliminar_usuario':
                $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
                flash('Usuario eliminado.');
                ir('usuarios');

            /* ----- PRODUCTOS ----- */
            case 'guardar_producto':
                $nombre = trim($_POST['nombre'] ?? '');
                $desc   = trim($_POST['descripcion'] ?? '');
                $precio = max(0, (float)($_POST['precio'] ?? 0));
                $stock  = max(0, (int)($_POST['stock'] ?? 0));
                $img    = trim($_POST['imagen'] ?? '');
                $activo = isset($_POST['activo']) ? 1 : 0;
                if ($nombre === '') {
                    flash('El producto necesita un nombre.', 'error');
                    ir('productos');
                }
                if ($id > 0) {
                    $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=?, activo=? WHERE id=?')
                        ->execute([$nombre, $desc ?: null, $precio, $stock, $img ?: null, $activo, $id]);
                    flash('Producto actualizado.');
                } else {
                    $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, stock, imagen, activo) VALUES (?,?,?,?,?,?)')
                        ->execute([$nombre, $desc ?: null, $precio, $stock, $img ?: null, $activo]);
                    flash('Producto creado.');
                }
                ir('productos');

            case 'eliminar_producto':
                $pdo->prepare('DELETE FROM productos WHERE id=?')->execute([$id]);
                flash('Producto eliminado.');
                ir('productos');

            /* ----- VENTAS ----- */
            case 'guardar_venta':
                $uid   = (int)($_POST['usuario_id'] ?? 0);
                $pid   = (int)($_POST['producto_id'] ?? 0);
                $cant  = (int)($_POST['cantidad'] ?? 0);
                $pu    = (float)($_POST['precio_unitario'] ?? 0);
                $desc  = min(100, max(0, (float)($_POST['descuento'] ?? 0)));
                $tasa  = min(100, max(0, (float)($_POST['tasa_impuesto'] ?? 0)));
                if ($uid <= 0 || $pid <= 0 || $cant <= 0) {
                    flash('Elige usuario, producto y una cantidad mayor a 0.', 'error');
                    ir('ventas');
                }

                $pdo->beginTransaction();

                // Si se edita, devolver primero el stock de la venta anterior.
                if ($id > 0) {
                    $old = $pdo->prepare('SELECT producto_id, cantidad FROM ventas WHERE id=? FOR UPDATE');
                    $old->execute([$id]);
                    $o = $old->fetch();
                    if (!$o) { $pdo->rollBack(); flash('La venta no existe.', 'error'); ir('ventas'); }
                    $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id=?')
                        ->execute([$o['cantidad'], $o['producto_id']]);
                }

                $s = $pdo->prepare('SELECT precio, stock FROM productos WHERE id=? FOR UPDATE');
                $s->execute([$pid]);
                $prod = $s->fetch();
                if (!$prod) { $pdo->rollBack(); flash('El producto no existe.', 'error'); ir('ventas'); }
                if ($cant > (int)$prod['stock']) {
                    $pdo->rollBack();
                    flash('Stock insuficiente: quedan ' . (int)$prod['stock'] . ' unidades.', 'error');
                    ir('ventas');
                }
                if ($pu <= 0) { $pu = (float)$prod['precio']; }

                $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id=?')->execute([$cant, $pid]);

                if ($id > 0) {
                    $pdo->prepare('UPDATE ventas SET usuario_id=?, producto_id=?, cantidad=?, precio_unitario=?, descuento=?, tasa_impuesto=? WHERE id=?')
                        ->execute([$uid, $pid, $cant, $pu, $desc, $tasa, $id]);
                    $ventaId = $id;
                    flash('Venta actualizada. MySQL recalculó los totales.');
                } else {
                    $pdo->prepare('INSERT INTO ventas (usuario_id, producto_id, cantidad, precio_unitario, descuento, tasa_impuesto) VALUES (?,?,?,?,?,?)')
                        ->execute([$uid, $pid, $cant, $pu, $desc, $tasa]);
                    $ventaId = (int)$pdo->lastInsertId();
                    flash('Venta creada. MySQL calculó los totales.');
                }

                // Los totales los calcula MySQL (se evalúa de izquierda a derecha).
                $pdo->prepare(
                    'UPDATE ventas SET
                        subtotal        = ROUND(cantidad * precio_unitario, 2),
                        monto_descuento = ROUND(subtotal * descuento / 100, 2),
                        monto_impuesto  = ROUND((subtotal - monto_descuento) * tasa_impuesto / 100, 2),
                        total_final     = subtotal - monto_descuento + monto_impuesto
                     WHERE id=?'
                )->execute([$ventaId]);

                $pdo->commit();
                ir('ventas');

            case 'eliminar_venta':
                $pdo->beginTransaction();
                $old = $pdo->prepare('SELECT producto_id, cantidad FROM ventas WHERE id=? FOR UPDATE');
                $old->execute([$id]);
                if ($o = $old->fetch()) {
                    $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id=?')
                        ->execute([$o['cantidad'], $o['producto_id']]);
                    $pdo->prepare('DELETE FROM ventas WHERE id=?')->execute([$id]);
                }
                $pdo->commit();
                flash('Venta eliminada y stock devuelto.');
                ir('ventas');
        }
    } catch (PDOException $ex) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        $code = $ex->getCode();
        if ($code === '23000' && str_contains($ex->getMessage(), 'Duplicate')) {
            flash('Ese correo ya está registrado.', 'error');
        } elseif ($code === '23000') {
            flash('No se puede eliminar: tiene ventas registradas.', 'error');
        } else {
            flash('Error de base de datos. Revisa las variables de entorno y que importaste base_datos.sql.', 'error');
        }
        ir($p);
    }
}

/* ---------- Datos para las vistas (GET) ---------- */
$errorConexion = null;
$datos = [];
try {
    $pdo = db();
    $datos['n_usuarios']  = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $datos['n_productos'] = (int)$pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
    $datos['n_ventas']    = (int)$pdo->query('SELECT COUNT(*) FROM ventas')->fetchColumn();
    $datos['ingresos']    = (float)$pdo->query('SELECT COALESCE(SUM(total_final),0) FROM ventas')->fetchColumn();

    $editId = (int)($_GET['editar'] ?? 0);
    $editar = null;

    if ($p === 'usuarios') {
        $datos['lista'] = $pdo->query('SELECT * FROM usuarios ORDER BY id DESC')->fetchAll();
        if ($editId) { $q = $pdo->prepare('SELECT * FROM usuarios WHERE id=?'); $q->execute([$editId]); $editar = $q->fetch() ?: null; }
    }
    if ($p === 'productos') {
        $datos['lista'] = $pdo->query('SELECT * FROM productos ORDER BY id DESC')->fetchAll();
        if ($editId) { $q = $pdo->prepare('SELECT * FROM productos WHERE id=?'); $q->execute([$editId]); $editar = $q->fetch() ?: null; }
    }
    if ($p === 'ventas') {
        $datos['lista'] = $pdo->query(
            'SELECT v.*, u.nombre AS cliente, pr.nombre AS producto
               FROM ventas v
               JOIN usuarios u  ON u.id  = v.usuario_id
               JOIN productos pr ON pr.id = v.producto_id
              ORDER BY v.id DESC'
        )->fetchAll();
        $datos['usuarios']  = $pdo->query('SELECT id, nombre FROM usuarios WHERE activo=1 ORDER BY nombre')->fetchAll();
        $datos['productos'] = $pdo->query('SELECT id, nombre, precio, stock FROM productos WHERE activo=1 ORDER BY nombre')->fetchAll();
        if ($editId) { $q = $pdo->prepare('SELECT * FROM ventas WHERE id=?'); $q->execute([$editId]); $editar = $q->fetch() ?: null; }
    }
} catch (PDOException $ex) {
    $errorConexion = 'No se pudo conectar a la base de datos. Revisa las variables de entorno DB_* y que hayas importado base_datos.sql.';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ---------- Componentes ---------- */
function form_eliminar(string $accion, int $id, string $csrf, string $p): string {
    return '<form method="post" action="crud.php?p=' . e($p) . '" class="inline" onsubmit="return confirm(\'¿Eliminar este registro? Esta acción no se puede deshacer.\');">'
         . '<input type="hidden" name="csrf" value="' . e($csrf) . '">'
         . '<input type="hidden" name="accion" value="' . e($accion) . '">'
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<button class="btn btn-peligro btn-sm" type="submit">Eliminar</button></form>';
}
$titulos = ['inicio' => 'Panel de control', 'usuarios' => 'Usuarios', 'productos' => 'Productos', 'ventas' => 'Ventas'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulos[$p]) ?> · <?= e(TITULO_PANEL) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<?php if ($p === 'inicio'): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>
<link rel="stylesheet" href="style.css?v=<?= (int)@filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>

<aside class="lateral">
  <div class="perfil">
    <img src="perfil.jpg" alt="Foto de perfil">
    <strong><?= e(NOMBRE_PERFIL) ?></strong>
  </div>
  <nav>
    <a href="crud.php?p=inicio"    <?= $p === 'inicio'    ? 'class="activo"' : '' ?>>Inicio</a>
    <a href="crud.php?p=usuarios"  <?= $p === 'usuarios'  ? 'class="activo"' : '' ?>>Usuarios</a>
    <a href="crud.php?p=productos" <?= $p === 'productos' ? 'class="activo"' : '' ?>>Productos</a>
    <a href="crud.php?p=ventas"    <?= $p === 'ventas'    ? 'class="activo"' : '' ?>>Ventas</a>
  </nav>
  <nav class="nav-extra">
    <a href="index.php">&larr; Menú principal</a>
    <a href="encuesta.html">Ir a la encuesta</a>
  </nav>
  <p class="marca"><?= e(TITULO_PANEL) ?></p>
</aside>

<main class="contenido">
  <header class="cabecera">
    <h1><?= e($titulos[$p]) ?></h1>
    <?php if ($p !== 'inicio'): ?>
      <a class="btn btn-primario" href="crud.php?p=<?= e($p) ?>#formulario">+ Nuevo</a>
    <?php endif; ?>
  </header>

  <?php if ($flash): ?>
    <div class="aviso aviso-<?= e($flash[1]) ?>" role="status"><?= e($flash[0]) ?></div>
  <?php endif; ?>
  <?php if ($errorConexion): ?>
    <div class="aviso aviso-error"><?= e($errorConexion) ?></div>
  <?php endif; ?>

<?php if ($p === 'inicio'): ?>
  <section class="portada">
    <div class="portada-texto">
      <h2>Controla tu negocio<br>desde un solo panel.</h2>
      <p>Administra usuarios, productos y ventas. El stock se ajusta solo en cada venta y MySQL calcula los totales.</p>
      <a class="btn btn-primario" href="crud.php?p=ventas#formulario">Registrar venta</a>
    </div>
    <img class="portada-foto" src="perfil.jpg" alt="Foto de perfil">
  </section>

  <section class="cifras">
    <div><b><?= $datos['n_usuarios']  ?? 0 ?></b><span>Usuarios registrados</span></div>
    <div><b><?= $datos['n_productos'] ?? 0 ?></b><span>Productos registrados</span></div>
    <div><b><?= $datos['n_ventas']    ?? 0 ?></b><span>Ventas registradas</span></div>
    <div><b><?= money($datos['ingresos'] ?? 0) ?></b><span>Ingresos totales</span></div>
  </section>

  <section class="panel">
    <h3>Ubicación</h3>
    <p class="nota-mapa">Cómo llegar a <?= e(MAPA_NOMBRE) ?>. A la izquierda el mapa incrustado con iframe; a la derecha el mapa interactivo con Leaflet.</p>
    <div class="mapas">
      <div class="mapa-col">
        <h4>Mapa de Google</h4>
        <iframe class="mapa-iframe" style="width:100%;height:420px;border:0" title="Mapa de ubicación"
          src="<?= e(MAPA_IFRAME_SRC) ?>"
          allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
      </div>
      <div class="mapa-col">
        <h4>Mapa interactivo (Leaflet)</h4>
        <div class="mapa-barra">
          <input type="search" id="mapa-buscar" placeholder="Buscar un lugar" value="<?= e(MAPA_NOMBRE) ?>" aria-label="Buscar un lugar">
          <button class="btn btn-primario btn-sm" type="button" id="mapa-btn-buscar">Buscar</button>
          <select id="mapa-modo" aria-label="Medio de transporte">
            <option value="driving-car">En auto</option>
            <option value="foot-walking">A pie</option>
            <option value="cycling-regular">En bici</option>
          </select>
        </div>
        <div class="mapa-barra">
          <button class="btn btn-sec btn-sm" type="button" id="mapa-btn-ruta">Ruta entre los 2 últimos puntos</button>
          <button class="btn btn-sec btn-sm" type="button" id="mapa-btn-limpiar">Limpiar</button>
        </div>
        <p class="mapa-info" id="mapa-info" role="status">Haz clic en el mapa para marcar puntos.</p>
        <div id="mapa-leaflet" class="mapa-leaflet" style="width:100%;height:340px"></div>
      </div>
    </div>
  </section>

  <section class="panel">
    <h3>Operaciones CRUD</h3>
    <div class="crud-guia">
      <div><span class="tag tag-crear">Crear</span><p>Registra usuarios, productos y ventas con el botón <b>Nuevo</b> de cada pantalla.</p></div>
      <div><span class="tag tag-leer">Leer</span><p>Consulta los listados con sus relaciones usuario-producto y totales.</p></div>
      <div><span class="tag tag-act">Actualizar</span><p>Modifica cualquier registro con <b>Editar</b>; MySQL recalcula los totales.</p></div>
      <div><span class="tag tag-elim">Eliminar</span><p>Borra un registro con confirmación previa desde su fila.</p></div>
    </div>
  </section>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  (function () {
    var cfg = <?= json_encode([
        'lat' => MAPA_LAT, 'lng' => MAPA_LNG, 'zoom' => MAPA_ZOOM,
        'nombre' => MAPA_NOMBRE, 'key' => ORS_API_KEY,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

    var info = document.getElementById('mapa-info');
    var puntos = [], marcadores = [], capaRuta = null;

    function esc(t) {
      return String(t).replace(/[&<>"']/g, function (c) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
      });
    }

    var map = L.map('mapa-leaflet').setView([cfg.lat, cfg.lng], cfg.zoom);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; Colaboradores de <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> | Rutas: <a href="https://openrouteservice.org">openrouteservice.org</a>'
    }).addTo(map);

    function agregar(lat, lng, titulo) {
      var n = puntos.length + 1;
      var texto = 'Punto ' + n + '<br>' + lat.toFixed(5) + ', ' + lng.toFixed(5) + (titulo ? '<br>' + esc(titulo) : '');
      var m = L.marker([lat, lng]).addTo(map).bindPopup(texto);
      puntos.push([lat, lng]);
      marcadores.push(m);
      return m;
    }

    function quitarRuta() {
      if (capaRuta) { map.removeLayer(capaRuta); capaRuta = null; }
    }

    agregar(cfg.lat, cfg.lng, cfg.nombre);

    map.on('click', function (ev) {
      agregar(ev.latlng.lat, ev.latlng.lng);
      info.textContent = 'Puntos marcados: ' + puntos.length + '.';
    });

    // Búsqueda de lugares (Nominatim / OpenStreetMap, no requiere key)
    function buscar() {
      var q = document.getElementById('mapa-buscar').value.trim();
      if (!q) { info.textContent = 'Escribe un lugar para buscar.'; return; }
      info.textContent = 'Buscando…';
      fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(q), {
        headers: {'Accept': 'application/json'}
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.length) { info.textContent = 'No se encontró ese lugar. Prueba con otro nombre.'; return; }
          var lat = parseFloat(res[0].lat), lng = parseFloat(res[0].lon);
          map.setView([lat, lng], 16);
          agregar(lat, lng, res[0].display_name).openPopup();
          info.textContent = 'Lugar encontrado. Puntos marcados: ' + puntos.length + '.';
        })
        .catch(function () { info.textContent = 'No se pudo buscar. Revisa tu conexión.'; });
    }

    // Ruta entre los 2 últimos puntos (OpenRouteService, requiere key)
    function ruta() {
      if (puntos.length < 2) { info.textContent = 'Marca al menos 2 puntos en el mapa.'; return; }
      if (!cfg.key) { info.textContent = 'Falta la API key de OpenRouteService en las variables de entorno de Render (ORS_API_KEY).'; return; }
      var a = puntos[puntos.length - 2], b = puntos[puntos.length - 1];
      var modo = document.getElementById('mapa-modo').value;
      info.textContent = 'Calculando ruta…';
      fetch('https://api.openrouteservice.org/v2/directions/' + modo + '/geojson', {
        method: 'POST',
        headers: {'Authorization': cfg.key, 'Content-Type': 'application/json', 'Accept': 'application/geo+json'},
        body: JSON.stringify({coordinates: [[a[1], a[0]], [b[1], b[0]]]})
      })
        .then(function (r) {
          if (!r.ok) { throw new Error(r.status); }
          return r.json();
        })
        .then(function (gj) {
          quitarRuta();
          capaRuta = L.geoJSON(gj, {style: {color: '#B8903A', weight: 5}}).addTo(map);
          map.fitBounds(capaRuta.getBounds(), {padding: [24, 24]});
          var s = gj.features[0].properties.summary;
          info.textContent = 'Ruta: ' + (s.distance / 1000).toFixed(1) + ' km, ' + Math.round(s.duration / 60) + ' min aprox.';
        })
        .catch(function (err) {
          var m = String(err.message);
          info.textContent = (m === '401' || m === '403')
            ? 'La API key no es válida. Revisa la variable ORS_API_KEY en Render.'
            : (m === '429' ? 'Llegaste al límite de consultas de hoy.' : 'No se pudo calcular la ruta entre esos puntos.');
        });
    }

    function limpiar() {
      marcadores.forEach(function (m) { map.removeLayer(m); });
      marcadores = []; puntos = [];
      quitarRuta();
      map.setView([cfg.lat, cfg.lng], cfg.zoom);
      info.textContent = 'Mapa limpio. Haz clic para marcar puntos.';
    }

    document.getElementById('mapa-btn-buscar').addEventListener('click', buscar);
    document.getElementById('mapa-buscar').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); buscar(); }
    });
    document.getElementById('mapa-btn-ruta').addEventListener('click', ruta);
    document.getElementById('mapa-btn-limpiar').addEventListener('click', limpiar);
  })();
  </script>

<?php elseif ($p === 'usuarios'): ?>
  <section class="panel">
    <h3><span class="tag tag-leer">Leer</span> Usuarios registrados</h3>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($datos['lista'] ?? [] as $r): ?>
        <tr>
          <td><?= e($r['nombre']) ?></td><td><?= e($r['correo']) ?></td><td><?= e($r['telefono'] ?: '—') ?></td>
          <td><span class="estado <?= $r['activo'] ? 'on' : 'off' ?>"><?= $r['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
          <td class="acciones">
            <a class="btn btn-sec btn-sm" href="crud.php?p=usuarios&amp;editar=<?= (int)$r['id'] ?>#formulario">Editar</a>
            <?= form_eliminar('eliminar_usuario', (int)$r['id'], $csrf, 'usuarios') ?>
          </td>
        </tr>
      <?php endforeach; if (empty($datos['lista'])): ?>
        <tr><td colspan="5" class="vacio">Aún no hay usuarios. Crea el primero con el formulario de abajo.</td></tr>
      <?php endif; ?>
      </tbody></table></div>
  </section>

  <section class="panel" id="formulario">
    <h3><span class="tag <?= $editar ? 'tag-act' : 'tag-crear' ?>"><?= $editar ? 'Actualizar' : 'Crear' ?></span> <?= $editar ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
    <form method="post" action="crud.php?p=usuarios" class="rejilla">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="accion" value="guardar_usuario">
      <input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
      <label>Nombre<input name="nombre" required maxlength="120" value="<?= e($editar['nombre'] ?? '') ?>"></label>
      <label>Correo<input type="email" name="correo" required maxlength="160" value="<?= e($editar['correo'] ?? '') ?>"></label>
      <label>Teléfono<input name="telefono" maxlength="30" value="<?= e($editar['telefono'] ?? '') ?>"></label>
      <label class="check"><input type="checkbox" name="activo" <?= !isset($editar) || ($editar['activo'] ?? 1) ? 'checked' : '' ?>> Activo</label>
      <div class="fila-botones">
        <button class="btn btn-primario" type="submit"><?= $editar ? 'Guardar cambios' : 'Guardar usuario' ?></button>
        <?php if ($editar): ?><a class="btn btn-sec" href="crud.php?p=usuarios">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>

<?php elseif ($p === 'productos'): ?>
  <section class="panel">
    <h3><span class="tag tag-leer">Leer</span> Catálogo de productos</h3>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Producto</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($datos['lista'] ?? [] as $r): ?>
        <tr>
          <td class="prod">
            <?php if ($r['imagen']): ?><img src="<?= e($r['imagen']) ?>" alt="" onerror="this.style.display='none'"><?php endif; ?>
            <div><b><?= e($r['nombre']) ?></b><small><?= e($r['descripcion'] ?? '') ?></small></div>
          </td>
          <td><?= money($r['precio']) ?></td>
          <td><span class="<?= (int)$r['stock'] <= 3 ? 'bajo' : '' ?>"><?= (int)$r['stock'] ?></span></td>
          <td><span class="estado <?= $r['activo'] ? 'on' : 'off' ?>"><?= $r['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
          <td class="acciones">
            <a class="btn btn-sec btn-sm" href="crud.php?p=productos&amp;editar=<?= (int)$r['id'] ?>#formulario">Editar</a>
            <?= form_eliminar('eliminar_producto', (int)$r['id'], $csrf, 'productos') ?>
          </td>
        </tr>
      <?php endforeach; if (empty($datos['lista'])): ?>
        <tr><td colspan="5" class="vacio">Aún no hay productos. Agrega el primero con el formulario de abajo.</td></tr>
      <?php endif; ?>
      </tbody></table></div>
  </section>

  <section class="panel" id="formulario">
    <h3><span class="tag <?= $editar ? 'tag-act' : 'tag-crear' ?>"><?= $editar ? 'Actualizar' : 'Crear' ?></span> <?= $editar ? 'Editar producto' : 'Nuevo producto' ?></h3>
    <form method="post" action="crud.php?p=productos" class="rejilla">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="accion" value="guardar_producto">
      <input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
      <label>Nombre<input name="nombre" required maxlength="120" value="<?= e($editar['nombre'] ?? '') ?>"></label>
      <label>Precio<input type="number" name="precio" step="0.01" min="0" value="<?= e($editar['precio'] ?? '0.00') ?>"></label>
      <label>Stock<input type="number" name="stock" min="0" value="<?= e($editar['stock'] ?? '0') ?>"></label>
      <label>Imagen (ruta o URL)<input name="imagen" maxlength="255" placeholder="imagenes_productos/foto.jpg" value="<?= e($editar['imagen'] ?? '') ?>"></label>
      <label class="ancho">Descripción<textarea name="descripcion" rows="3"><?= e($editar['descripcion'] ?? '') ?></textarea></label>
      <label class="check"><input type="checkbox" name="activo" <?= !isset($editar) || ($editar['activo'] ?? 1) ? 'checked' : '' ?>> Activo</label>
      <div class="fila-botones">
        <button class="btn btn-primario" type="submit"><?= $editar ? 'Guardar cambios' : 'Guardar producto' ?></button>
        <?php if ($editar): ?><a class="btn btn-sec" href="crud.php?p=productos">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>

<?php elseif ($p === 'ventas'): ?>
  <section class="panel">
    <h3><span class="tag tag-leer">Leer</span> Historial de ventas</h3>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Cliente</th><th>Producto</th><th>Cant.</th><th>Subtotal</th><th>Total final</th><th>Fecha</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($datos['lista'] ?? [] as $r): ?>
        <tr>
          <td><?= e($r['cliente']) ?></td><td><?= e($r['producto']) ?></td><td><?= (int)$r['cantidad'] ?></td>
          <td><?= money($r['subtotal']) ?></td><td><b><?= money($r['total_final']) ?></b></td>
          <td><?= e($r['fecha']) ?></td>
          <td class="acciones">
            <a class="btn btn-sec btn-sm" href="crud.php?p=ventas&amp;editar=<?= (int)$r['id'] ?>#formulario">Editar</a>
            <?= form_eliminar('eliminar_venta', (int)$r['id'], $csrf, 'ventas') ?>
          </td>
        </tr>
      <?php endforeach; if (empty($datos['lista'])): ?>
        <tr><td colspan="7" class="vacio">Aún no hay ventas. Registra la primera con el formulario de abajo.</td></tr>
      <?php endif; ?>
      </tbody></table></div>
  </section>

  <section class="panel" id="formulario">
    <h3><span class="tag <?= $editar ? 'tag-act' : 'tag-crear' ?>"><?= $editar ? 'Actualizar' : 'Crear' ?></span> <?= $editar ? 'Editar venta' : 'Nueva venta' ?></h3>
    <form method="post" action="crud.php?p=ventas" class="rejilla" id="form-venta">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="accion" value="guardar_venta">
      <input type="hidden" name="id" value="<?= (int)($editar['id'] ?? 0) ?>">
      <label>Usuario
        <select name="usuario_id" required>
          <option value="">Selecciona un usuario</option>
          <?php foreach ($datos['usuarios'] ?? [] as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($editar['usuario_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Producto
        <select name="producto_id" id="producto" required>
          <option value="">Selecciona un producto</option>
          <?php foreach ($datos['productos'] ?? [] as $pr): ?>
            <option value="<?= (int)$pr['id'] ?>" data-precio="<?= e($pr['precio']) ?>" <?= (int)($editar['producto_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>>
              <?= e($pr['nombre']) ?> (stock <?= (int)$pr['stock'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Cantidad<input type="number" name="cantidad" id="cantidad" min="1" value="<?= e($editar['cantidad'] ?? '1') ?>" required></label>
      <label>Precio unitario<input type="number" name="precio_unitario" id="precio" step="0.01" min="0" value="<?= e($editar['precio_unitario'] ?? '0.00') ?>"></label>
      <label>Descuento (%)<input type="number" name="descuento" id="descuento" step="0.01" min="0" max="100" value="<?= e($editar['descuento'] ?? '0') ?>"></label>
      <label>Tasa de impuesto (%)<input type="number" name="tasa_impuesto" id="tasa" step="0.01" min="0" max="100" value="<?= e($editar['tasa_impuesto'] ?? '16') ?>"></label>
      <div class="estimado ancho">Total estimado: <b id="estimado">$0.00</b> <small>· el total final lo calcula MySQL al guardar</small></div>
      <div class="fila-botones">
        <button class="btn btn-primario" type="submit"><?= $editar ? 'Guardar cambios' : 'Guardar venta' ?></button>
        <?php if ($editar): ?><a class="btn btn-sec" href="crud.php?p=ventas">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>
  <script>
  (function () {
    var prod = document.getElementById('producto'), precio = document.getElementById('precio'),
        cant = document.getElementById('cantidad'), desc = document.getElementById('descuento'),
        tasa = document.getElementById('tasa'), out = document.getElementById('estimado');
    function calc() {
      var s = (parseFloat(cant.value) || 0) * (parseFloat(precio.value) || 0);
      var d = s * (parseFloat(desc.value) || 0) / 100;
      var t = (s - d) * (parseFloat(tasa.value) || 0) / 100;
      out.textContent = '$' + (s - d + t).toFixed(2);
    }
    prod.addEventListener('change', function () {
      var o = prod.options[prod.selectedIndex];
      if (o && o.dataset.precio) { precio.value = o.dataset.precio; }
      calc();
    });
    [cant, precio, desc, tasa].forEach(function (el) { el.addEventListener('input', calc); });
    calc();
  })();
  </script>
<?php endif; ?>

</main>
</body>
</html>
