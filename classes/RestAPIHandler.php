<?php
namespace NewsImporter;

class RestAPIHandler {
    private $base_urls = [];

    public function __construct($urls) {
        $this->base_urls = $urls;
    }

    // Holt die News-Daten von den angegebenen URLs
    public function fetch_news() {
        // ToDo: Implementieren Sie die Logik zum Abrufen der Daten von der REST API
    }
}
