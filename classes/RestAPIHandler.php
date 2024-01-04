<?php
/**
 * @package news-porter
 * @file RestAPIHandler.php
 */

namespace NewsImporter;

class RestAPIHandler {
    private $base_urls = [];

    public function __construct($urls) {
        $this->base_urls = $urls;
    }

    // Holt die News-Daten von den angegebenen URLs
    public function fetch_news() {
        $all_news = [];

        foreach ($this->base_urls as $url) {
            // HTTP-Anfrage an die API senden
            $response = wp_remote_get($url);

            // Prüfen, ob die Anfrage erfolgreich war
            if (is_wp_error($response)) {
                continue; // Bei einem Fehler mit der nächsten URL fortfahren
            }

            // Antwortkörper extrahieren und in ein Array umwandeln
            $data = json_decode(wp_remote_retrieve_body($response), true);

            // Überprüfen, ob die Daten gültig sind
            if (!empty($data) && is_array($data)) {
                $all_news = array_merge($all_news, $data);
            }
        }

        return $all_news;
    }
}
