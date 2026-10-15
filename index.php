<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menú principal · UPVM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=3">
</head>
<body>

<aside class="lateral">
  <div class="perfil">
    <img src="perfil.jpg" alt="Mi foto">
    <strong>Encuesta UPVM</strong>
  </div>
  <nav aria-label="Navegación principal">
    <a href="index.php" class="activo">Menú principal</a>
    <a href="encuesta.html">Encuesta</a>
    <a href="crud.php">CRUD de ventas</a>
  </nav>
  <p class="marca">Aplicaciones web orientadas a servicios</p>
</aside>

<main class="contenido">
  <header class="cabecera"><h1>Menú principal</h1></header>

  <section class="portada">
    <div class="portada-texto">
      <h2>¿A dónde<br>quieres ir?</h2>
      <p>Contesta la encuesta sobre la UPVM o entra al panel CRUD para administrar usuarios, productos y ventas.</p>
    </div>
    <img class="portada-foto" src="perfil.jpg" alt="Mi foto">
  </section>

  <section class="opciones" aria-label="Opciones del menú">
    <article class="panel">
      <h3>Encuesta UPVM <span class="tag tag-crear">5 preguntas</span></h3>
      <p>Responde preguntas rápidas sobre profesores e instalaciones. Es anónima y tarda menos de un minuto.</p>
      <a class="btn btn-primario" href="encuesta.html">Contestar la encuesta</a>
    </article>
    <article class="panel crud">
      <h3>CRUD · Sistema de ventas <span class="tag tag-act">Panel</span></h3>
      <p>Administra usuarios, productos y ventas: crear, leer, actualizar y eliminar registros.</p>
      <a class="btn btn-sec" href="crud.php">Abrir el CRUD</a>
    </article>
  </section>
</main>

</body>
</html>
