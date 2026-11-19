<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<?php require __DIR__ . '/estilo.php'; ?>
</head>
<body>
  <div class="entrada">
    <img src="/logo.png" alt="">
    <h1>Panel de Explora Siam</h1>
    <p class="guia">Para cambiar textos, fotos y vídeos de la web.</p>
    <?php if ($aviso): ?><p class="aviso <?= e($aviso[0]) ?>"><?= e($aviso[1]) ?></p><?php endif; ?>
    <form method="post">
      <label><span>Contraseña</span><input type="password" name="clave" required autofocus autocomplete="current-password"></label>
      <button class="btn" type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
