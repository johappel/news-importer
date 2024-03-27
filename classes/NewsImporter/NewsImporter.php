<?php
/**
 * @package news-porter
 * @file NewsImporter.php
 */

namespace NewsImporter;

class NewsImporter
{
    private $api_handler;
    private $posts_to_link = [];

    public function __construct()
    {
        // Admin-Einstellungen speichern
        if (!empty($_POST['api_urls'])) {
            update_option('news_importer_api_urls', sanitize_textarea_field($_POST['api_urls']));
        }

        // URLs aus den Einstellungen holen
        $urls = explode("\n", get_option('news_importer_api_urls'));

        // Leere und ungültige URLs entfernen
        $urls = array_filter($urls, function ($url) {
            return filter_var(trim($url), FILTER_VALIDATE_URL);
        });

        $this->api_handler = new RestAPIHandler($urls);

        add_action('admin_menu', array($this, 'add_admin_menu'));

//        add_action('init', array($this, 'translate_all_posts'));
    }

    // Fügt einen Menüpunkt im Admin-Bereich hinzu
    public function add_admin_menu()
    {
        add_menu_page(
            'News Importer',
            'News Importer',
            'manage_options',
            'news-importer',
            array($this, 'admin_page'),
            'dashicons-admin-site-alt3'
        );
    }

    // Admin-Seite zum Auslösen des Imports
    public function admin_page()
    {
        if (!empty($_POST['import_news'])) {
            $this->import_news();
        }
        include dirname(dirname(__DIR__)) . '/views/admin-page.php';
    }

    // Importiert die News-Beiträge
    private function import_news()
    {
        // Überprüfen, ob Polylang-Funktionen vorhanden sind
        if (!function_exists('pll_save_post_translations') || !function_exists('pll_set_post_language')) {
            // Fehlermeldung oder Logging, falls Polylang nicht installiert ist
            error_log('Polylang-Plugin ist nicht installiert oder aktiviert.');
            return;
        }
        $this->log('Start des Importvorgangs.');
        $news_items = $this->api_handler->fetch_news();
        foreach ($news_items as $item) {

            $lang = $this->derive_language_from_url($item['link']);

            // Erstellen eines neuen Beitrags für jede Sprache.
            $post_id = $this->create_post($item, $lang);

            // Speichern der Post-ID für spätere Verknüpfung mit Übersetzungen
            $this->posts_to_link[$lang][$item['id']] = $post_id;

        }
        $this->link_translations();
        $this->log('Importvorgang abgeschlossen.');
    }

    // Lädt und speichert Medieninhalte

    private function log($message)
    {
        $log_file = WP_CONTENT_DIR . '/news_importer_log.txt'; // Pfad zur Log-Datei
        $timestamp = current_time('mysql');
        $entry = "{$timestamp}: {$message}\n";

        file_put_contents($log_file, $entry, FILE_APPEND);
    }


    // Weist Kategorien und Tags einem Beitrag zu

    private function derive_language_from_url($url)
    {
        // Standard-Sprachcode definieren
        $default_lang = 'en'; // oder eine andere Standard-Sprache

        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        if (count($segments) > 1 && strlen($segments[0]) == 2) { // Einfache Überprüfung auf Sprachkürzel
            return $segments[0];
        }

        return $default_lang; // Rückgabe der Standard-Sprache, wenn kein Kürzel gefunden wird

    }

    // Übersetzt Term-IDs zwischen Quell- und Zielblog

    private function create_post($item, $lang)
    {
        // Annahme: $item enthält alle notwendigen Informationen
        $post_data = array(
            'post_author' => 1, // oder einen dynamischen Autor
            'post_content' => $item['content']['rendered'],
            'post_title' => $item['title']['rendered'],
            'post_status' => 'publish',
            'post_type' => 'news',
            'meta_input' => array(
                'import_id' => $item['id'], // oder eine andere eindeutige ID
                'import_lang' => $lang,
            ),
        );

        // Erstellen des Beitrags
        $post_id = wp_insert_post($post_data);

        // Überprüfen, ob der Beitrag erfolgreich erstellt wurde.
        if ($post_id && !is_wp_error($post_id)) {

            // Medieninhalte hinzufügen
            if (!empty($item['featured_media'])) {
                $this->import_media($item['featured_media'], $post_id);
            }

            // Kategorien und Tags hinzufügen
            if (!empty($item['categories'])) {
                $this->assign_terms($post_id, $item['categories'], 'category');
            }
            if (!empty($item['tags'])) {
                $this->assign_terms($post_id, $item['tags'], 'post_tag');
            }

            // Polylang-Sprache zuweisen
            pll_set_post_language($post_id, $lang);

            $this->log("Beitrag erstellt: '{$post_data['post_title']}' in Sprache: '{$lang}'");

        }

        return $post_id;
    }

    private function import_media($media_url)
    {
        if (!$media_url) {
            return null;
        }

        // Der Dateiname wird aus der URL extrahiert.
        $file_name = basename($media_url);

        // Temporärdatei erstellen.
        $temp_file = download_url($media_url);
        if (is_wp_error($temp_file)) {
            return null;
        }

        // Dateiinformationen vorbereiten.
        $file = array(
            'name' => $file_name,
            'type' => mime_content_type($temp_file),
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        );

        // Die Datei wird der Medienbibliothek hinzugefügt.
        $media_id = media_handle_sideload($file, 0);
        if (is_wp_error($media_id)) {
            @unlink($temp_file);
            return null;
        }

        return $media_id;
    }

    private function assign_terms($post_id, $term_ids, $taxonomy)
    {
        foreach ($term_ids as $term_id) {
            // Namen des Terms aus der Quelle holen
            $term_name = get_term_by('id', $term_id, $taxonomy)->name;

            // Term-ID im Zielblog übersetzen
            $translated_term_id = $this->translate_term_id($term_name, $taxonomy);
            if ($translated_term_id) {
                wp_set_object_terms($post_id, $translated_term_id, $taxonomy, true);
            }
        }
    }

    private function translate_term_id($source_term_name, $taxonomy)
    {
        // Überprüfen, ob ein Term mit diesem Namen im Zielblog existiert.
        $term = get_term_by('name', $source_term_name, $taxonomy);

        // Wenn der Term existiert, gibt die ID zurück.
        if ($term) {
            return $term->term_id;
        }

        // Wenn der Term nicht existiert, erstelle einen neuen Term.
        $new_term = wp_insert_term($source_term_name, $taxonomy);

        // Überprüfen, ob die Erstellung erfolgreich war.
        if (is_wp_error($new_term)) {
            // Fehlerbehandlung, eventuell Logging.
            return null;
        }

        // Gibt die ID des neu erstellten Terms zurück.
        return $new_term['term_id'];
    }

    private function link_translations()
    {
        $translations = [];
        foreach ($this->posts_to_link as $lang => $posts) {
            foreach ($posts as $original_id => $post_id) {
                $translations[$original_id][$lang] = $post_id;
            }
        }
        foreach ($translations as $translation)
            pll_save_post_translations($translation);
    }

}
