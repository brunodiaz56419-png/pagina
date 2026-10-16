<?php
// ============================================================
//  Configuración — SIN contraseñas escritas en el código.
//  Los datos de la base de datos se leen de las variables de entorno
//  que configuras en el panel de Render (Environment Variables):
//    DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME
//  Opcional: ORS_API_KEY (OpenRouteService) para el botón de ruta del mapa.
// ============================================================
define('DB_HOST', (string) getenv('DB_HOST'));
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_NAME', (string) getenv('DB_NAME'));
define('DB_USER', (string) getenv('DB_USER'));
define('DB_PASS', (string) getenv('DB_PASS'));

const TITULO_PANEL = 'Sistema de ventas';
const NOMBRE_PERFIL = 'Administrador';       // aparece bajo tu foto

// ---------- Mapas ----------
const MAPA_NOMBRE = 'Universidad Politécnica del Valle de México';
const MAPA_LAT    = 19.64078;
const MAPA_LNG    = -99.12778;
const MAPA_ZOOM   = 16;

// Etapa 1 · iframe de Google Maps (NO necesita API key).
const MAPA_IFRAME_SRC = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2858.2233490563667!2d-99.12777556532178!3d19.64078403283855!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x85d1f43e59c00a9f%3A0xe3c992b6aa422e2c!2sUniversidad%20Polit%C3%A9cnica%20del%20Valle%20de%20M%C3%A9xico!5e0!3m2!1ses-419!2smx!4v1790863623769!5m2!1ses-419!2smx';

// Etapa 2 · Leaflet + OpenRouteService. La API key es un dato privado:
// se lee de la variable de entorno ORS_API_KEY. Si no existe, el mapa y la
// búsqueda funcionan igual; solo se desactiva el botón de ruta.
define('ORS_API_KEY', (string) getenv('ORS_API_KEY'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ] 
        );
    }
    return $pdo;
}
