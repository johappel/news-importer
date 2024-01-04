# News Importer Plugin für WordPress

Das News Importer Plugin ermöglicht es, Beiträge automatisiert von externen WordPress-Seiten über die WP REST API zu importieren. Das Plugin unterstützt das Herunterladen und Speichern von Medien sowie das Zuweisen von Kategorien und Tags zu den importierten Beiträgen.

## Features

- Automatischer Import von Beiträgen über WP REST API.
- Herunterladen und Speichern von Medieninhalten.
- Zuweisung von Kategorien und Tags zu importierten Beiträgen.
- Benutzerfreundliche Admin-Oberfläche zur Steuerung des Importvorgangs.

## Voraussetzungen

- WordPress 5.0 oder höher.
- PHP 7.3 oder höher.

## Installation

1. Laden Sie das Plugin in das Verzeichnis `/wp-content/plugins/` Ihrer WordPress-Installation hoch.
2. Aktivieren Sie das Plugin über das 'Plugins'-Menü in WordPress.

## Verwendung von Composer

Das Plugin verwendet Composer für das Autoloading von Klassen. Führen Sie die folgenden Schritte aus, um Composer in Ihrem Plugin einzurichten:

1. Installieren Sie Composer, falls noch nicht geschehen. Anweisungen finden Sie unter [getcomposer.org](https://getcomposer.org/download/).

2. Navigieren Sie im Terminal oder in der Kommandozeile zum Wurzelverzeichnis des Plugins und führen Sie den folgenden Befehl aus, um die Abhängigkeiten zu installieren:

 `composer install`


3. Um das Autoloading einzurichten, führen Sie den folgenden Befehl aus:

 `composer dump-autoload`

## Konfiguration

Nach der Installation finden Sie die Plugin-Einstellungen im Admin-Bereich von WordPress unter 'News Importer'. Dort können Sie den Importprozess manuell auslösen.


## Weiterentwicklung

Das Plugin wurde mit der Erweiterbarkeit im Hinterkopf entwickelt. Entwickler können die Funktionalität anpassen oder erweitern, indem sie den bereitgestellten Code als Grundlage verwenden.

## Lizenz

Das News Importer Plugin ist unter der GPL v2 oder später lizenziert. Weitere Informationen finden Sie in der Datei LICENSE. 
