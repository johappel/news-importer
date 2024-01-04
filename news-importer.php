<?php
/*
Plugin Name: News Importer
Description: Importiert News-Beiträge von verschiedenen Quellen unter Verwendung der WP REST API.
Version: 1.0
Author: WP Plugin Lab
*/

// Sicherstellen, dass das Skript nicht direkt aufgerufen wird.
if (!defined('ABSPATH')) {
    exit;
}

// Autoloader für Klassen.
spl_autoload_register(function ($class) {
    if (strpos($class, 'NewsImporter') === 0) {
        include 'classes/' . $class . '.php';
    }
});

// Initialisieren des Plugins.
function news_importer_init() {
    new NewsImporter\NewsImporter();
}

add_action('plugins_loaded', 'news_importer_init');
