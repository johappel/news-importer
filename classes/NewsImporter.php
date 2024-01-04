<?php
/**
 * @package news-porter
 * @file NewsImporter.php
 */
namespace NewsImporter;

class NewsImporter {
    private $api_handler;

    public function __construct() {
        // Initialisiert die REST API Handler mit den Quell-URLs
        // ToDo: Fügen Sie hier Ihre Quell-URLs ein
        $this->api_handler = new RestAPIHandler([
            'https://example.com/wp-json/wp/v2/posts'
            // Weitere URLs...
        ]);

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
        $news_items = $this->api_handler->fetch_news();
        foreach ($news_items as $item) {
            // ToDo: Implementieren Sie die Logik, um Duplikate zu verhindern oder zu aktualisieren
            if (get_post($item['id'])) {
                continue;
            }

            // Medieninhalte herunterladen und anhängen.
            $media_id = $this->import_media($item['featured_media']);

            // Beitrag erstellen.
            $post_id = wp_insert_post(array(
                'post_author' => 1, // Benutzer ID 1.
                'post_content' => $item['content']['rendered'],
                'post_title' => $item['title']['rendered'],
                'post_status' => 'publish',
                'post_type' => 'post',
                'import_id' => $item['id']
            ));

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
        // ToDo: Implementieren Sie die Logik zur Zuordnung von Term-IDs
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

}
