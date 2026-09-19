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
<div id="pub" class="pub" hidden>
  <span class="pub__luz"></span>
  <span class="pub__txt">Comprobando la web…</span>
</div>
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
            $clave = $donde . '|' . $camino;
            // Si el guardado ha fallado, no le hagamos perder lo que había escrito:
            // se le devuelve su texto, no el que sigue estando en GitHub.
            $actual = ($aviso[0] ?? '') === 'mal' && isset($_POST['c'][$clave])
                ? (string) $_POST['c'][$clave]
                : valor($datos, $camino); ?>
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

<?php elseif ($seccion === 'fotos'):
  // Nombres de las etapas para rotular las fotos de la ruta.
  $viajes = array_map(fn ($s) => (string) $s['name'], $salidas);
  $docs = ['home' => $home['datos']];
  foreach ($viajes as $v) {
      $docs['viaje:' . $v] = (leer_json('content/salidas/' . $v) ?? [])['datos'] ?? [];
  }
  $etapas = [];
  foreach (($viajes ? ($docs['viaje:' . $viajes[0]]['itinerario'] ?? []) : []) as $it) {
      $etapas[] = trim(explode(',', (string) ($it['titulo'] ?? ''))[0]);
  }
  $huecos = huecos_fotos($viajes, $etapas);
  // Qué archivo hay en cada hueco y en qué otros sitios sale el mismo archivo.
  $usos = [];
  foreach ($huecos as $i => $h) {
      $huecos[$i]['archivo'] = basename(valor($docs[$h['fuente']] ?? [], $h['camino']));
      $usos[$huecos[$i]['archivo']][] = $h['grupo'] . ' · ' . $h['etiqueta'];
  }
  $grupoActual = null; ?>
  <h1>Fotos</h1>
  <p class="guia">Las fotos están en el mismo orden en que aparecen en la web. Para cambiar una, elige la nueva en su recuadro y pulsa <strong>Guardar</strong>: solo cambia en ese sitio. Formatos JPG, PNG o WEBP, hasta 8 MB.</p>
  <p class="guia">Cada foto tiene un <strong>título</strong> y una <strong>descripción</strong>. La descripción es lo que lee Google y lo que oyen las personas que navegan con lector de pantalla: cuenta en una frase qué se ve y dónde, por ejemplo «Amanecer sobre un mar de nubes desde el mirador de Phu Chi Fa». Si cambias la foto, revisa también sus textos.</p>
  <?php foreach ($huecos as $h):
    if ($h['grupo'] !== $grupoActual):
      if ($grupoActual !== null): ?></div><?php endif;
      $grupoActual = $h['grupo']; ?>
      <h2 class="grupo-fotos"><?= e($h['grupo']) ?></h2>
      <div class="fotos">
    <?php endif;
    $tx = $textosFotos[$h['archivo']] ?? [];
    $otros = array_values(array_filter($usos[$h['archivo']] ?? [], fn ($u) => $u !== $h['grupo'] . ' · ' . $h['etiqueta'])); ?>
    <div class="foto">
      <?php if ($h['archivo'] !== ''): ?><img src="?foto=<?= e(rawurlencode($h['archivo'])) ?>" alt="" loading="lazy"><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="pie">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="accion" value="hueco">
        <input type="hidden" name="hueco" value="<?= e($h['id']) ?>">
        <strong class="donde"><?= e($h['etiqueta']) ?></strong>
        <code><?= e($h['archivo']) ?></code>
        <?php if ($otros): ?><p class="compartida">Esta misma foto sale también en: <?= e(implode('; ', $otros)) ?>. El título y la descripción son los mismos en todos esos sitios.</p><?php endif; ?>
        <label><span>Cambiar foto</span><input type="file" name="archivo" accept="image/jpeg,image/png,image/webp"></label>
        <label><span>Título</span><input type="text" name="titulo" maxlength="120" value="<?= e((string) ($tx['titulo'] ?? '')) ?>"></label>
        <label><span>Descripción</span><textarea name="descripcion" maxlength="300" rows="3"><?= e((string) ($tx['descripcion'] ?? '')) ?></textarea></label>
        <button class="btn" type="submit">Guardar</button>
      </form>
    </div>
  <?php endforeach;
  if ($grupoActual !== null): ?></div><?php endif; ?>

<?php elseif ($seccion === 'videos'): ?>
  <h1>Vídeos</h1>
  <p class="guia">Los vídeos están en el mismo orden en que aparecen en la web. Sube un MP4 de hasta 300 MB. Los vídeos se guardan en el propio servidor, así que la subida puede tardar un rato: no cierres la pestaña.</p>
  <p class="guia"><strong>Consejo importante:</strong> el vídeo de la portada se reproduce solo nada más entrar, así que conviene que sea ligero, de unos 10 segundos y por debajo de 10 MB, o la web tardará en cargar en el móvil. El de la ruta solo se descarga cuando alguien le da al play, así que ahí sí puede ser largo y pesado.</p>
  <p class="guia">Igual que las fotos, cada vídeo tiene un <strong>título</strong> y una <strong>descripción</strong>: Google los usa para entender y mostrar el vídeo en sus resultados.</p>
  <?php $subidos = videos_subidos(); if ($subidos): ?>
    <fieldset><legend>Vídeos en el servidor</legend>
      <table class="tabla"><?php foreach ($subidos as $v): ?>
        <tr><td><code><?= e($v['nombre']) ?></code></td><td style="text-align:right;color:var(--muted)"><?= number_format($v['peso'] / 1048576, 1, ',', '.') ?> MB</td></tr>
      <?php endforeach; ?></table>
    </fieldset>
  <?php endif; ?>
  <?php foreach (huecos_videos() as [$donde, $grupo, $etiqueta, $pre, $campoUrl]):
    $actual = valor($home['datos'], $campoUrl); ?>
    <h2 class="grupo-fotos"><?= e($grupo) ?></h2>
    <fieldset>
      <legend><?= e($etiqueta) ?></legend>
      <p class="guia"><?= $actual ? 'Ahora mismo: <code>' . e($actual) . '</code>' : ($donde === 'hero' ? 'Ahora mismo no hay vídeo: se ve la foto de fondo.' : 'Ahora mismo no hay vídeo: la tarjeta muestra solo la foto.') ?></p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="accion" value="video">
        <input type="hidden" name="donde" value="<?= e($donde) ?>">
        <label><span><?= $actual ? 'Cambiar vídeo (MP4)' : 'Subir vídeo (MP4)' ?></span><input type="file" name="archivo" accept="video/mp4"></label>
        <label><span>Título</span><input type="text" name="titulo" maxlength="120" value="<?= e(valor($home['datos'], $pre . 'videoTitulo')) ?>"></label>
        <label><span>Descripción</span><textarea name="descripcion" maxlength="300" rows="3"><?= e(valor($home['datos'], $pre . 'videoDescripcion')) ?></textarea></label>
        <?php if ($donde === 'hero'): ?>
          <label class="check"><input type="checkbox" name="sonido" value="1"<?= valor($home['datos'], 'hero.videoSonido') === '1' ? ' checked' : '' ?>> Dejar que el visitante active el sonido</label>
          <p class="guia">El vídeo de la portada siempre empieza en silencio: los navegadores no permiten que un vídeo arranque solo con sonido y lo bloquearían entero. Con esta casilla marcada aparece un botón de altavoz sobre el vídeo para que quien quiera lo active. Si el vídeo no tiene audio, déjala sin marcar.</p>
        <?php endif; ?>
        <button class="btn" type="submit">Guardar</button>
      </form>
    </fieldset>
  <?php endforeach; ?>

<?php else:
  $archivo = basename((string) ($_GET['a'] ?? ($salidas[0]['name'] ?? '')));
  $salida = $archivo ? leer_json('content/salidas/' . $archivo) : null; ?>
  <h1>Datos del viaje</h1>
  <p class="guia">Duración, plazas, descripción e itinerario día a día. El precio y las fechas no se muestran en la web: se dan por WhatsApp con el dossier.</p>
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
            $actual = ($aviso[0] ?? '') === 'mal' && isset($_POST['v'][$clave])
                ? (string) $_POST['v'][$clave]
                : (string) ($salida['datos'][$clave] ?? ''); ?>
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
              <?php $estadoActual = ($aviso[0] ?? '') === 'mal' && isset($_POST['v']['estado'])
                  ? (string) $_POST['v']['estado']
                  : (string) ($salida['datos']['estado'] ?? ''); ?>
              <option value="<?= e($k) ?>" <?= $estadoActual === $k ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </fieldset>
      <div class="guardar"><button class="btn" type="submit">Guardar cambios</button></div>
    </form>
  <?php endif; ?>
<?php endif; ?>
</main>
<script>
(function () {
  // Tras guardar, Vercel publica la web solo. Aquí solo enseñamos cómo va.
  const caja = document.getElementById('pub');
  const txt  = caja.querySelector('.pub__txt');
  const guardado = <?= json_encode(($aviso[0] ?? '') === 'ok') ?>;

  const pinta = (clase, mensaje) => {
    caja.hidden = false;
    caja.className = 'pub ' + clase;
    txt.textContent = mensaje;
  };
  const estado = () => fetch('index.php?api=estado', { cache: 'no-store' }).then(r => r.json()).catch(() => ({ estado: 'desconocido' }));
  const espera = ms => new Promise(r => setTimeout(r, ms));

  async function seguir() {
    // Hasta 25 minutos: si Vercel tiene cola, puede tardar.
    for (let i = 0; i < 100; i++) {
      const e = await estado();
      if (e.estado === 'al-dia') return pinta('ok', 'Publicado. Ya se ve en explorasiam.com (si no, recarga la web).');
      if (e.estado === 'error')  return pinta('mal', 'Vercel no ha podido publicar el último cambio. Tu cambio está guardado; avisa a Javi.');
      pinta('yendo', 'Guardado. Publicando en la web… suele tardar 2-3 minutos, a veces más si hay cola.');
      await espera(15000);
    }
    pinta('yendo', 'Guardado. La web está tardando más de lo normal en publicarse, pero saldrá sola.');
  }

  if (guardado) { seguir(); return; }
  estado().then(e => {
    if (e.estado === 'publicando') return seguir();
    if (e.estado === 'error') return pinta('mal', 'Vercel no pudo publicar el último cambio. Lo guardado no se pierde; avisa a Javi.');
    caja.hidden = true;
  });
})();
</script>
</body>
</html>
