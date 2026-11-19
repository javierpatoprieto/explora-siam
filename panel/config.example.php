<?php
// Copia este archivo como config.php y rellena los tres valores.
// config.php no se sube al repositorio y el .htaccess impide leerlo desde fuera.

// Contraseña que usará el cliente para entrar al panel.
define('PANEL_PASSWORD', 'cambia-esta-contrasena');

// Token de GitHub con permiso de escritura solo en este repositorio.
// GitHub → Settings → Developer settings → Personal access tokens → Fine-grained
// → Repository access: solo explora-siam → Permissions: Contents = Read and write.
define('GITHUB_TOKEN', 'github_pat_...');

define('GITHUB_REPO', 'javierpatoprieto/explora-siam');
define('GITHUB_BRANCH', 'main');

// Aviso que se muestra tras guardar.
define('MINUTOS_PUBLICACION', 2);
