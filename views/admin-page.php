<?php
// Sicherstellen, dass das Skript nicht direkt aufgerufen wird.
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>News Importer</h1>
    <form method="post">
        <textarea name="api_urls" rows="10" cols="50"><?php echo esc_textarea(get_option('news_importer_api_urls')); ?></textarea>
        <br>
        <input type="hidden" name="import_news" value="1">
        <button type="submit" class="button button-primary">Import Starten</button>
    </form>
</div>

<a href="<?php echo content_url().'/news_importer_log.txt' ?>Log-Datei</a>
