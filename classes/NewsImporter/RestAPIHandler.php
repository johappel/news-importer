<?php
/**
 * @package news-porter
 * @file RestAPIHandler.php
 */

namespace NewsImporter;

use core_reportbuilder\local\aggregation\count;

class RestAPIHandler
{
    private $base_urls = [];

    public function __construct($urls)
    {
        $this->base_urls = $urls;
    }

    // Holt die News-Daten von den angegebenen URLs
    public function fetch_news()
    {
        $all_news = [];
        foreach ($this->base_urls as $url) {
            $url = sanitize_url($url);
            // HTTP-Anfrage an die API senden
            $response = wp_remote_get($url);
            // Prüfen, ob die Anfrage erfolgreich war
            if (is_wp_error($response)) {
                continue; // Bei einem Fehler mit der nächsten URL fortfahren
            }

            // Durch alle Seiten der response iterieren
            $posts = json_decode(wp_remote_retrieve_body($response));
            $data = $this->iterate_through_pages($url, $posts, true);

            // Überprüfen, ob die Daten gültig sind
            if (!empty($data) && is_array($data)) {
                $all_news = array_merge($all_news, $data);
            }
        }

        return $all_news;
    }

    public function iterate_through_pages($api_url, $data, $page = 1)

    {
        $next = [];
        $per_page = 10; // Number of items per page
        $args = array(
            'per_page' => $per_page,
            'page' => $page,
        );
// Add query parameters to the URL
        $request_url = add_query_arg($args, $api_url);

// Make the request
        $response = wp_remote_get($request_url);
        if (is_wp_error($response)) {
            return $data;
        }
        $next = json_decode(wp_remote_retrieve_body($response), true);
        $data = array_merge($data, $next);

        if (count($next) == $per_page) {
            $page++;
            $this->iterate_through_pages($api_url, $data, $page);
        }
        return $data;

    }

}
