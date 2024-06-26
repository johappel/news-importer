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
    public function fetch_news()
    {
        $all_news = [];

        foreach ($this->base_urls as $url) {
            $url = sanitize_url($url);

            // HTTP request to the API
            $response = wp_remote_get($url);

            // Check if the request was successful
            if (is_wp_error($response)) {
                continue; // Skip to the next URL in case of an error
            }

            // Parse the JSON response
            $posts = json_decode(wp_remote_retrieve_body($response), true);

            // Fetch all pages of results
            $all_news = array_merge($all_news, $this->fetch_all_pages($url, $posts));
        }

        return $all_news;
    }

    public function fetch_all_pages($api_url, $data, $page = 1)
    {
        $per_page = 10; // Number of items per page
        $args = array(
            'per_page' => $per_page,
            'page' => $page,
        );

        $request_url = add_query_arg($args, $api_url);
        $response = wp_remote_get($request_url);

        if (is_wp_error($response)) {
            return $data; // Return existing data if request fails
        }

        $next = json_decode(wp_remote_retrieve_body($response), true);
        $data = array_merge($data, $next);

        // Check if there are more pages to fetch
        if (count($next) == $per_page) {
            $page++;
            $data = $this->fetch_all_pages($api_url, $data, $page); // Recursively fetch next page
        }

        return $data;
    }


}
