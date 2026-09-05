<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Panel · Explora Siam</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<?php require __DIR__ . '/estilo.php'; ?>
</head>
<body>
<header class="barra">
  <div class="marca"><img src="/logo.png" alt="">Panel de Explora Siam</div>
  <nav>
    <a href="?s=textos" class="<?= $seccion === 'textos' ? 'on' : '' ?>">Textos</a>
    <a href="?s=fotos" class="<?= $seccion === 'fotos' ? 'on' : '' ?>">Fotos</a>
    <a href="?s=videos" class="<?= $seccion === 'videos' ? 'on' : '' ?>">Vídeos</a>
    <a href="?s=viaje" class="<?= $seccion === 'viaje' ? 'on' : '' ?>">Viaje</a>
  </nav>
  <div><a href="https://explorasiam.com" target="_blank" rel="noopener" class="salir">Ver la web</a> · <a href="?salir=1" class="salir">Salir</a></div>
</header>

<main>
<?php if ($aviso): ?><p class="aviso <?= e($aviso[0]) ?>"><?= e($aviso[1]) ?></p><?php endif; ?>
<?php if ($sinConexion): ?>
  <h1>No se puede leer el contenido</h1>
  <p class="guia">Revisa que el token de GitHub del archivo <code>config.php</code> sigue siendo válido y tiene permiso de escritura sobre el repositorio.</p>
<?php elseif ($seccion === 'textos'): ?>
  <h1>Textos de la web</h1>
  <p class="guia">Cambia lo que quieras y pulsa Guardar. La web se actualiza sola en unos <?= (int) MINUTOS_PUBLICACION ?> minutos.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="accion" value="textos">
    <?php foreach ($CAMPOS as $grupo => $campos): ?>
      <fieldset>
        <legend><?= e($grupo) ?></legend>
        <?php foreach ($campos as [$donde, $camino, $etiqueta, $tipo]):
            $datos = $donde === 'home' ? $home['datos'] : $sitio['datos'];
            $actual = valor($datos, $camino);
            $clave = $donde . '|' . $camino; ?>
          <label>
            <span><?= e($etiqueta) ?></span>
            <?php if ($tipo === 'parrafo'): ?>
              <textarea name="c[<?= e($clave) ?>]"><?= e($actual) ?></textarea>
            <?php else: ?>
              <input type="text" name="c[<?= e($clave) ?>]" value="<?= e($actual) ?>">
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
    <div class="guardar"><button class="btn" type="submit">Guardar cambios</button></div>
  </form>

<?php elseif ($seccion === 'fotos'): ?>
  <h1>Fotos</h1>
  <p class="guia">Para cambiar una foto, elige la nueva debajo de la que quieres sustituir. Se queda en el mismo sitio de la web. Formatos JPG, PNG o WEBP, hasta 8 MB.</p>
  <div class="fotos">
    <?php foreach ($fotos as $f): ?>
      <div class="foto">
        <img src="?foto=<?= e(rawurlencode($f['name'])) ?>" alt="" loading="lazy">
        <div class="pie">
          <code><?= e($f['name']) ?></code>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="accion" value="foto">
            <input type="hidden" name="sustituye" value="<?= e($f['name']) ?>">
            <input type="file" name="archivo" accept="image/jpeg,image/png,image/webp" required onchange="this.form.submit()">
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($seccion === 'videos'): ?>
  <h1>Vídeos</h1>
  <p class="guia">Sube un MP4 de hasta 25 MB. Para la portada, lo ideal son 10 segundos en bucle y sin sonido; para la banda del centro, unos 40 segundos.</p>
  <?php foreach ([['hero', 'Vídeo de la portada', valor($home['datos'], 'hero.videoMp4')], ['banda', 'Vídeo de la banda central', valor($home['datos'], 'video.mp4')]] as [$donde, $etiqueta, $actual]): ?>
    <fieldset>
      <legend><?= e($etiqueta) ?></legend>
      <p class="guia"><?= $actual ? 'Ahora mismo: <code>' . e($actual) . '</code>' : 'Ahora mismo no hay vídeo: se ve la foto de fondo.' ?></p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="accion" value="video">
        <input type="hidden" name="donde" value="<?= e($donde) ?>">
        <label><span>Archivo MP4</span><input type="file" name="archivo" accept="video/mp4" required></label>
        <button class="btn" type="submit">Subir vídeo</button>
      </form>
    </fieldset>
  <?php endforeach; ?>

<?php else:
  $archivo = basename((string) ($_GET['a'] ?? ($salidas[0]['name'] ?? '')));
  $salida = $archivo ? leer_json('content/salidas/' . $archivo) : null; ?>
  <h1>Datos del viaje</h1>
  <p class="guia">Fechas, plazas y precio. Cuando el grupo se llene, cambia el estado a «Grupo completo» y la web lo muestra tachado con un botón para avisar de la próxima edición.</p>
  <?php if (count($salidas) > 1): ?>
    <p><?php foreach ($salidas as $s): ?>
      <a class="btn claro" href="?s=viaje&a=<?= e(rawurlencode($s['name'])) ?>"><?= e(str_replace('.json', '', $s['name'])) ?></a>
    <?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($salida): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="accion" value="viaje">
      <input type="hidden" name="archivo" value="<?= e($archivo) ?>">
      <fieldset>
        <legend><?= e((string) ($salida['datos']['nombre'] ?? 'Viaje')) ?></legend>
        <?php foreach ($VIAJE as [$clave, $etiqueta, $tipo]):
            $actual = (string) ($salida['datos'][$clave] ?? ''); ?>
          <label>
            <span><?= e($etiqueta) ?></span>
            <?php if ($tipo === 'parrafo'): ?>
              <textarea name="v[<?= e($clave) ?>]"><?= e($actual) ?></textarea>
            <?php elseif ($tipo === 'numero'): ?>
              <input type="number" name="v[<?= e($clave) ?>]" value="<?= e($actual) ?>" min="0">
            <?php else: ?>
              <input type="text" name="v[<?= e($clave) ?>]" value="<?= e($actual) ?>">
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
        <label><span>Estado</span>
          <select name="v[estado]">
            <?php foreach (['abierta' => 'Abierta', 'ultimas' => 'Últimas plazas', 'completa' => 'Grupo completo', 'proximamente' => 'Próximamente'] as $k => $t): ?>
              <option value="<?= e($k) ?>" <?= ($salida['datos']['estado'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </fieldset>
      <div class="guardar"><button class="btn" type="submit">Guardar cambios</button></div>
    </form>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
