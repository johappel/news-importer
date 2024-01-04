<?php
// Sicherstellen, dass das Skript nicht direkt aufgerufen wird.
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>News Importer</h1>
    <form method="post">
        <input type="hidden" name="import_news" value="1">
        <button type="submit" class="button button-primary">Import Starten</button>
    </form>
</div>
