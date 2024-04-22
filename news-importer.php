<?php
/*
Plugin Name: News Importer
Description: Importiert News-Beiträge von verschiedenen Quellen unter Verwendung der WP REST API.
Version: 1.0
Author: WP Plugin Lab
*/

// Sicherstellen, dass das Skript nicht direkt aufgerufen wird.
require_once 'news-importer.php';
require_once 'views/bulk-post-updater.php';
if (!defined('ABSPATH')) {
    exit;
}

// Autoloader für Klassen.
spl_autoload_register(function ($class) {
    // Ersetzen Sie die Backslashes im Namespace durch normale Slashes
    $class = str_replace('\\', '/', $class);

    // Bilden Sie den vollständigen Pfad zur Klassendatei
    $path = __DIR__ . '/classes/' . $class . '.php';

    // Überprüfen Sie, ob die Datei existiert, und binden Sie sie ein
    if (file_exists($path)) {
        require_once $path;

    }

});

// Initialisieren des Plugins.
function news_importer_init()
{
    new \NewsImporter\NewsImporter();
//    new BulkPostUpdater();
}

add_action('plugins_loaded', 'news_importer_init');
