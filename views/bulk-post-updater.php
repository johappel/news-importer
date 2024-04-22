<?php

class BulkPostUpdater
{
    // Add a menu item to the admin menu
    function __construct()
    {
        add_action('admin_menu', 'bulk_post_updater_menu');

    }

    function bulk_post_updater_menu()
    {
        add_submenu_page(
            'edit.php',
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
            $post_types = isset($_POST['posttype']) ? $_POST['posttype'] : '';
            // Loop through selected posts and update them
            foreach ($post_types as $post_type) {
                $args = [
                    'post_type' => $post_type,
                    'numberposts' => -1
                ];
                $posts = get_posts($args);
                foreach ($posts as $post) {
                    $this->extract_and_create_posts($post);
                }


            }
            echo '<div class="updated"><p>Posts updated successfully.</p></div>';
        }

        // Display form
        ?>
        <div class="wrap">
            <h2>Bulk Post Updater</h2>
            <form method="post" action="">
                <h3>Select post type to update:</h3>

                <?php
                $post_types = get_post_types();
                foreach ($post_types as $post_type) {
                    ?>
                    <input type="checkbox" name="poststypes[]"
                           value="<?php echo $post_type->name; ?>"/> <?php echo $post_type->name; ?><br/>
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
    function extract_and_create_posts($post)
    {
        // If the input is not a WP_Post object, retrieve the post object
        if (!is_a($post, 'WP_Post')) {
            $post = get_post($post);
        }

        // Get post meta data
        $post_meta = get_post_meta($post->ID);

        // Get post taxonomies
        $all_tax = get_taxonomies();
        $post_taxonomies = wp_get_post_terms($post->ID, $all_tax);

        // Define the elements to search for translations within the post
        $post_search_target = array(
            'post_title' => $post->post_title, // Original post title
            'post_content' => $post->post_content, // Original post content
            'post_excerpt' => $post->post_excerpt, // Original post excerpt
            'meta' => $post_meta, // Original post meta data
            'taxonomy' => $post_taxonomies, // Original post taxonomies
            // Add any other necessary post data
        );

        // Define the regular expression pattern to match the content within brackets
        $pattern = '/\[:([\w]{0,2})\]([^\[:]*)/g';

        // Array to store extracted content for each language
        $new_posts = [];

        // Loop through each type of content in the post
        foreach ($post_search_target as $content_type => $content_value) {
            // If the content value is an array (e.g., post meta or taxonomies)
            if (is_array($content_value)) {
                foreach ($content_value as $key => $item) {
                    // Search for language codes and content within brackets
                    preg_match_all($pattern, $item, $matches, PREG_SET_ORDER);
                    foreach ($matches as $match) {
                        // Extract language code and content
                        $lang_code = $match[1];
                        $content = $match[2];
                        // Store the content for the language
                        $new_posts[$lang_code][$content_type][$key] = $content;
                    }
                }
            } else {
                // For single content values (e.g., post title, content, excerpt)
                preg_match_all($pattern, $content_value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    // Extract language code and content
                    $lang_code = $match[1];
                    $content = $match[2];
                    // Store the content for the language
                    $new_posts[$lang_code][$content_type] = $content;
                }
            }
        }

        // Array to store created post IDs for each language
        $post_bundle = [];

        // Loop through extracted content for each language
        foreach ($new_posts as $language => $new_post) {
            // Define new post data
            $new_post_data = array(
                'post_status' => 'publish',
                'post_author' => $post->post_author,
                'post_type' => $post->post_type, // Change to the appropriate post type
                // Add any other necessary post data
            );

            // Create a new post with the extracted content
            $new_post_id = wp_insert_post($new_post_data);

            // Set language meta for the post
            update_post_meta($new_post_id, 'language_code', $lang_code);

            // Set metadata for the new post
            foreach ($new_post['meta'] as $meta_key => $meta_value) {
                update_post_meta($new_post_id, $meta_key, $meta_value[0]);
            }

            // Set taxonomies for the new post
            foreach ($post_taxonomies as $taxonomy) {
                wp_set_post_terms($new_post_id, $taxonomy->term_id, $taxonomy->taxonomy);
            }

            // Store the created post ID for the language
            $post_bundle[$post->ID][$language] = $new_post_id;
        }

        // Save post translations using Polylang
        pll_save_post_translations($post_bundle);
    }


}
