<?php
/**
 * @package news-porter
 * @file NewsImporter.php
 */
namespace NewsImporter;

class NewsImporter {
    private $api_handler;

    public function __construct() {
        // Admin-Einstellungen speichern
        if (!empty($_POST['api_urls'])) {
            update_option('news_importer_api_urls', sanitize_textarea_field($_POST['api_urls']));
        }

        // URLs aus den Einstellungen holen
        $urls = explode("\n", get_option('news_importer_api_urls'));

        // Leere und ungültige URLs entfernen
        $urls = array_filter($urls, function($url) {
            return filter_var(trim($url), FILTER_VALIDATE_URL);
        });

        $this->api_handler = new RestAPIHandler($urls);

        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    // Fügt einen Menüpunkt im Admin-Bereich hinzu
    public function add_admin_menu() {
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
    public function admin_page() {
        if (!empty($_POST['import_news'])) {
            $this->import_news();
        }
        include dirname(__FILE__) . '/../views/admin-page.php';
    }

    // Importiert die News-Beiträge
    private function import_news() {
        $this->log('Start des Importvorgangs.');
        $news_items = $this->api_handler->fetch_news();
        foreach ($news_items as $item) {

            $this->log("Verarbeite Beitrag: {$item['id']}");

            // Medieninhalte herunterladen und anhängen.
            $media_id = $this->import_media($item['featured_media']);

            // Ableiten der Sprache aus der URL
            $lang = $this->derive_language_from_url($item['source_url']);

            // Kombinierte ID aus Post-ID und Sprache erstellen
            $combined_id = $item['id'] . '_' . $lang;

            // Überprüfen, ob ein Beitrag mit der kombinierten ID bereits existiert.
            $existing_post_id = $this->find_existing_post($combined_id);

            if ($existing_post_id) {
                // Aktualisieren des bestehenden Beitrags.
                $post_id = wp_update_post(array(
                    'ID'           => $existing_post_id,
                    'post_content' => $item['content']['rendered'],
                    'post_title'   => $item['title']['rendered'],
                    // Weitere Felder...
                ));
            } else {
                // Erstellen eines neuen Beitrags.
                $post_id = wp_insert_post(array(
                    'post_author' => 1, // Benutzer ID 1.
                    'post_content' => $item['content']['rendered'],
                    'post_title' => $item['title']['rendered'],
                    'post_status' => 'publish',
                    'post_type' => 'post',
                    // Meta-Felder hinzufügen
                    'meta_input' => array(
                        'import_id' => $combined_id,
                        'import_lang' => $lang,
                    ),
                ));
                $this->log("neuen Beitrag erstellt: $post_id, {$item['title']['rendered']} ");
            }
            // Überprüfen, ob der Beitrag erfolgreich erstellt wurde.
            if ($post_id && !is_wp_error($post_id)) {
                // Setzt das Beitragsbild, falls ein Media-Element vorhanden ist.
                if ($media_id && !is_wp_error($media_id)) {
                    set_post_thumbnail($post_id, $media_id);
                }

                // Kategorien und Tags hinzufügen.
                if (!empty($item['categories'])) {
                    $this->assign_terms($post_id, $item['categories'], 'category');
                }
                if (!empty($item['tags'])) {
                    $this->assign_terms($post_id, $item['tags'], 'post_tag');
                }
            }
        }
        $this->log('Importvorgang abgeschlossen.');
    }

    private function find_existing_post($import_id) {
        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => 'import_id',
                    'value'   => $import_id,
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);
        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }

        return false;
    }

    // Lädt und speichert Medieninhalte
    private function import_media($media_url) {
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
            'name'     => $file_name,
            'type'     => mime_content_type($temp_file),
            'tmp_name' => $temp_file,
            'error'    => 0,
            'size'     => filesize($temp_file),
        );

        // Die Datei wird der Medienbibliothek hinzugefügt.
        $media_id = media_handle_sideload($file, 0);
        if (is_wp_error($media_id)) {
            @unlink($temp_file);
            return null;
        }

        return $media_id;
    }


    // Weist Kategorien und Tags einem Beitrag zu
    private function assign_terms($post_id, $term_ids, $taxonomy) {
        foreach ($term_ids as $term_id) {
            // Namen des Terms aus der Quelle holen
            $term_name = get_term_by( 'id', $term_id,$taxonomy)->name;

            // Term-ID im Zielblog übersetzen
            $translated_term_id = $this->translate_term_id($term_name, $taxonomy);
            if ($translated_term_id) {
                wp_set_object_terms($post_id, $translated_term_id, $taxonomy, true);
            }
        }
    }

    // Übersetzt Term-IDs zwischen Quell- und Zielblog
    private function translate_term_id($source_term_name, $taxonomy) {
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

    private function derive_language_from_url($url) {
        // Verwenden von PHPs parse_url, um den Pfad aus der URL zu extrahieren
        $path = parse_url($url, PHP_URL_PATH);

        // Aufteilen des Pfads in Segmente
        $segments = explode('/', trim($path, '/'));

        // Das erste Segment als Sprachkennzeichnung annehmen
        $lang = $segments[0];

        // Rückgabe der Sprachkennzeichnung
        return $lang;
    }
    private function log($message) {
        $log_file = WP_CONTENT_DIR . '/news_importer_log.txt'; // Pfad zur Log-Datei
        $timestamp = current_time('mysql');
        $entry = "{$timestamp}: {$message}\n";

        file_put_contents($log_file, $entry, FILE_APPEND);
    }

}
