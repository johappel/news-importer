<?php

class BulkPostUpdater
{

    static private $pattern = '/\[:([\w]{0,2})\]([^\[[]*)/';


    // Add a menu item to the admin menu
    function __construct()
    {
        add_action('admin_menu', [$this, 'bulk_post_updater_menu']);

    }

    function bulk_post_updater_menu()
    {
        add_submenu_page(
            'options-general.php',
            'Bulk Post Updater',
            'Bulk Post Updater',
            'manage_options',
            'bulk-post-updater',
            [$this, 'bulk_post_updater_page']
        );
    }

// Display the page content

    function bulk_post_updater_page()
    {

        // Handle form submission
        if (isset($_POST['submit'])) {
//            $selected_posts = isset($_POST['posts']) ? $_POST['posts'] : array();
            $post_types = isset($_POST['poststypes']) ? $_POST['poststypes'] : '';

            // Loop through selected posts and update them
            foreach ($post_types as $post_type) {
                $args = [
                    'post_type' => $post_type,
                    'numberposts' => -1
                ];
                $posts = get_posts($args);
                foreach ($posts as $post) {
                    $this->extract_and_create_posts($post);
                    break;
                }


            }
            echo '<div class="updated"><p>Posts updated successfully.</p></div>';
        }

        // Display form
        ?>
        <div class="wrap">
            <h2>Bulk Post Updater</h2>
            <form method="post" action="">
                <label for="import_post_types">Select post type to update:</label>

                <?php

                $post_types = get_post_types();
                foreach ($post_types as $post_type => $post_type_name) {
                    ?>
                    <input type="checkbox" name="poststypes[]"
                           value="<?php echo $post_type; ?>"/> <?php echo $post_type_name; ?><br/>
                    <?php
                }
                ?>
                <br/>
                <input type="submit" name="submit" value="Update Posts" class="button button-primary"/>
            </form>
        </div>
        <?php
    }


    /**
     * Function to extract translated content from a post and create separate posts for each language.
     *
     * @param mixed $post The post object or post ID.
     */
    function extract_and_create_posts($origin_post)
    {
        // If the input is not a WP_Post object, retrieve the post object
        if (!is_a($origin_post, 'WP_Post')) {
            $origin_post = get_post($origin_post);
        }

        // Get post meta data
        $origin_post_meta = get_post_meta($origin_post->ID);

        // Get post taxonomies
        $all_tax = get_post_taxonomies($origin_post->ID);

        $origin_post_taxonomies = wp_get_post_terms($origin_post->ID, $all_tax);

        // Define the elements to search for translations within the post
        $post_search_target = array(
            'post_title' => $origin_post->post_title, // Original post title
            'post_content' => $origin_post->post_content, // Original post content
            'post_excerpt' => $origin_post->post_excerpt, // Original post excerpt
            // Add any other necessary post data
        );

        // Define the regular expression pattern to match the content within brackets


        // Array to store extracted content for each language
        $new_posts = [];

        // Loop through each type of content in the post
        foreach ($post_search_target as $content_type => $content_value) {
            // If the content value is an array (e.g., post meta or taxonomies)

            if (!empty($content_value) && is_string($content_value)) {
                preg_match_all(BulkPostUpdater::$pattern, $content_value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (is_array($match) && count($match) == 3) {
                        // Extract language code and content
                        $lang_code = $match[1];
                        $content = $match[2];
                        // Store the content for the language
                        $new_posts[$lang_code][$content_type] = $content;
                    }
                }
            }

        }

        // Array to store created post IDs for each language
        $post_bundle = [];

        // Loop through extracted content for each language
        foreach ($new_posts as $language => $new_post) {
            // Define new post data
            $new_post_data = array(
                'post_status' => $origin_post->post_status,
                'post_author' => $origin_post->post_author,
                'post_type' => $origin_post->post_type, // Change to the appropriate post type
                // Add any other necessary post data
            );

            // Create a new post with the extracted content
            $new_post_id = wp_insert_post($new_post_data);

            // Set language meta for the post
            update_post_meta($new_post_id, 'language_code', $language);


            $new_post_meta = $this->get_language_array_of_post_meta($origin_post_meta, $language);
            foreach ($new_post_meta as $meta_key => $meta_value) {
                update_post_meta($new_post_id, $meta_key, $meta_value);
            }

            // Set taxonomies for the new post
            foreach ($origin_post_taxonomies as $taxonomy) {
                wp_set_post_terms($new_post_id, $taxonomy->term_id, $taxonomy->taxonomy);
            }

            // Store the created post ID for the language
            $post_bundle[$origin_post->ID][$language] = $new_post_id;
        }

        // Save post translations using Polylang
        pll_save_post_translations($post_bundle);
    }

    function get_language_array_of_post_meta($origin_post_meta, $language)
    {
        $new_post = [];
        foreach ($origin_post_meta as $meta_key => $meta_value) //            // Set metadata for the new post
        {
            if (is_array($meta_value)) {
                $new_post[$meta_key] = $this->get_language_array_of_post_meta($meta_value, $language);

            } else {
                preg_match_all(BulkPostUpdater::$pattern, $meta_value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (is_array($match) && count($match) == 3) {
                        // Extract language code and content
                        $match_lang = $match[1];
                        $content = $match[2];
                        if ($language === $match_lang) {
                            // Store the content for the language
                            $new_post[$meta_key] = $content;
                        }
                    }
                }
            }
        }
        return $new_post;
    }
}
