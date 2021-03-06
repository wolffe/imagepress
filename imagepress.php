<?php
/*
Plugin Name: ImagePress
Plugin URI: https://getbutterfly.com/wordpress-plugins/imagepress/
Description: Create a user-powered image gallery or an image upload site, using nothing but WordPress custom posts. Moderate image submissions and integrate the plugin into any theme.
Version: 8.1.2
Author: Ciprian Popescu
Author URI: https://getbutterfly.com/
GitHub Plugin URI: getbutterfly/imagepress
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: imagepress

ImagePress       (c) 2013-2021 Ciprian Popescu (https://getbutterfly.com/)
RoarJS           (c) 2018-2021 Ciprian Popescu (https://getbutterfly.com/)
Linearicons Free (c) 2014-2015 Perxis (https://linearicons.com/free)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if (!function_exists('add_filter')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

define('IMAGEPRESS_PLUGIN_URL', WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)));
define('IMAGEPRESS_PLUGIN_PATH', WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)));
define('IMAGEPRESS_PLUGIN_FILE_PATH', WP_PLUGIN_DIR . '/' . plugin_basename(__FILE__));



// Plugin initialization
function imagepress_init() {
    load_plugin_textdomain('imagepress', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    $imagepress_slug = imagepress_get_option('ip_slug');

    if (empty($imagepress_slug)) {
        $optionArray = [
            'ip_slug' => 'image'
        ];
        imagepress_update_option($optionArray);
    }
}
add_action('plugins_loaded', 'imagepress_init');



include_once IMAGEPRESS_PLUGIN_PATH . '/includes/imagepress-install.php';
include_once IMAGEPRESS_PLUGIN_PATH . '/includes/functions.php';

include IMAGEPRESS_PLUGIN_PATH . '/includes/alpha-functions.php';
include IMAGEPRESS_PLUGIN_PATH . '/includes/page-settings.php';
include IMAGEPRESS_PLUGIN_PATH . '/includes/cinnamon-users.php';

// user modules
include IMAGEPRESS_PLUGIN_PATH . '/modules/mod-awards.php';
include IMAGEPRESS_PLUGIN_PATH . '/modules/mod-user-following.php';
include IMAGEPRESS_PLUGIN_PATH . '/modules/mod-likes.php';
include IMAGEPRESS_PLUGIN_PATH . '/modules/mod-notifications.php';

include IMAGEPRESS_PLUGIN_PATH . '/modules/mod-collections.php';

add_action('init', 'imagepress_registration');

add_action('admin_menu', 'imagepress_menu'); // settings menu

add_action('wp_ajax_nopriv_post-like', 'post_like');
add_action('wp_ajax_post-like', 'post_like');

add_filter('transition_post_status', 'imagepress_notify_status', 10, 3); // email notifications
add_filter('widget_text', 'do_shortcode');



/*
 * Add ImagePress CPT to singular template
 */
function imagepress_content_filter($content) {
    $ipSlug = imagepress_get_option('ip_slug');

    if (is_singular() && is_main_query() && get_post_type() == $ipSlug) {
        $new_content = imagepress_main_return(get_the_ID());
        $new_content .= imagepress_related();

        $content = $new_content;
    }

    return $content;
}
add_filter('the_content', 'imagepress_content_filter');



function imagepress_menu() {
    global $menu, $submenu;

    add_submenu_page('edit.php?post_type=' . imagepress_get_option('ip_slug'), 'ImagePress Settings', 'ImagePress Settings', 'manage_options', 'imagepress_admin_page', 'imagepress_admin_page');

    $url = 'https://getbutterfly.com/support/documentation/imagepress/';
    $submenu['edit.php?post_type=' . imagepress_get_option('ip_slug')][] = ['<span class="imagepress-highlight">' . __('Documentation', 'imagepress') . '</span>', 'manage_options', $url];

    $args = [
        'post_type' => imagepress_get_option('ip_slug'),
        'post_status' => 'pending',
        'showposts' => -1
    ];
    $draft_ip_links = get_posts($args) ? count(get_posts($args)) : 0;

    if ($draft_ip_links) {
        foreach ($menu as $key => $value) {
            if ($menu[$key][2] == 'edit.php?post_type=' . imagepress_get_option('ip_slug')) {
                $menu[$key][0] .= ' <span class="update-plugins count-' . $draft_ip_links . '"><span class="plugin-count">' . $draft_ip_links . '</span></span>';
                return;
            }
        }
    }
    if ($draft_ip_links) {
        foreach ($submenu as $key => $value) {
            if ($submenu[$key][2] == 'edit.php?post_type=' . imagepress_get_option('ip_slug')) {
                $submenu[$key][0] .= ' <span class="update-plugins count-' . $draft_ip_links . '"><span class="plugin-count">' . $draft_ip_links . '</span></span>';
                return;
            }
        }
    }
}

add_shortcode('imagepress-add', 'imagepress_add');
add_shortcode('imagepress-collection', 'imagepress_collection');
add_shortcode('imagepress-search', 'imagepress_search');
add_shortcode('imagepress-top', 'imagepress_top');

add_shortcode('imagepress', 'imagepress_widget');

add_shortcode('imagepress-collections', 'imagepress_collections_display_custom');

add_image_size('imagepress_sq_std', 250, 250, true);
add_image_size('imagepress_pt_std', 250, 375, true);
add_image_size('imagepress_ls_std', 375, 250, true);

// show admin bar only for admins
if (imagepress_get_option('cinnamon_hide_admin') == 1) {
    add_action('after_setup_theme', 'imagepress_remove_admin_bar');
    function imagepress_remove_admin_bar() {
        if (!current_user_can('administrator') && !is_admin()) {
            show_admin_bar(false);
        }
    }
}
//

/* CINNAMON ACTIONS */
add_action('init', 'imagepress_author_base');

add_action('personal_options_update', 'imagepress_save_profile_fields');
add_action('edit_user_profile_update', 'imagepress_save_profile_fields');

/* CINNAMON SHORTCODES */
add_shortcode('cinnamon-card', 'imagepress_card');
add_shortcode('cinnamon-profile', 'imagepress_profile');
add_shortcode('cinnamon-profile-edit', 'imagepress_profile_edit');
add_shortcode('cinnamon-awards', 'imagepress_awards');

/* CINNAMON FILTERS */
add_filter('get_avatar', 'imagepress_hub_gravatar_filter', 1, 5);
add_filter('user_contactmethods', 'imagepress_extra_contact_info');







// custom thumbnail column
$ip_column_slug = imagepress_get_option('ip_slug');

add_filter('manage_edit-' . $ip_column_slug . '_columns', 'imagepress_columns_filter', 10, 1);
function imagepress_columns_filter($columns) {
    $column_thumbnail = ['thumbnail' => 'Thumbnail'];
    $columns = array_slice($columns, 0, 1, true) + $column_thumbnail + array_slice($columns, 1, NULL, true);

    return $columns;
}
add_action('manage_posts_custom_column', 'imagepress_column_action', 10, 1);
function imagepress_column_action($column) {
    global $post;
    switch($column) {
        case 'thumbnail':
            echo get_the_post_thumbnail($post->ID, 'thumbnail');
        break;
    }
}
//

function imagepress_manage_users_custom_column($output = '', $column_name, $user_id) {
    if ($column_name === 'post_type_quota') {
        // get current user uploads
        $userUploads = imagepress_count_user_posts_by_type($user_id);

        if ($userUploads > 0) {
            $userUploads = '<a href="' . admin_url('edit.php?post_type=' . imagepress_get_option('ip_slug') . '&author=' . $user_id) . '">' . $userUploads . '</a>';
        }

        return $userUploads;
    }
}
add_filter('manage_users_custom_column', 'imagepress_manage_users_custom_column', 10, 3);

function imagepress_manage_users_columns($columns) {
    $columns['post_type_quota'] = __('Images', 'imagepress');

    return $columns;
}
add_filter('manage_users_columns', 'imagepress_manage_users_columns');

// Main upload function
function imagepress_add($atts) {
    extract(shortcode_atts([
        'category' => ''
    ], $atts));

    global $wpdb, $current_user;

    $out = '';
    $ipModerate = (int) imagepress_get_option('ip_moderate');

    if (isset($_POST['imagepress_upload_image_form_submitted']) && wp_verify_nonce($_POST['imagepress_upload_image_form_submitted'], 'imagepress_upload_image_form')) {
        $ip_status = ($ipModerate === 0) ? 'pending' : 'publish';

        $ip_image_author = $current_user->ID;
        $ipImageCaption = uniqid();

        if (!empty($_POST['imagepress_image_caption']))
            $ipImageCaption = sanitize_text_field($_POST['imagepress_image_caption']);

        $user_image_data = [
            'post_title' => $ipImageCaption,
            'post_content' => sanitize_text_field($_POST['imagepress_image_description']),
            'post_status' => $ip_status,
            'post_author' => $ip_image_author,
            'post_type' => imagepress_get_option('ip_slug')
        ];

        // send notification email to administrator
        $notificationEmail = imagepress_get_option('ip_notification_email');
        $notificationSubject = __('New image uploaded!', 'imagepress') . ' | ' . get_bloginfo('name');
        $notificationMessage = __('New image uploaded!', 'imagepress') . ' | ' . get_bloginfo('name');

        if (!empty($_FILES['imagepress_image_file'])) {
            $post_id = wp_insert_post($user_image_data);
            imagepress_process_image('imagepress_image_file', $post_id);

            // Multiple images
            imagepress_upload_secondary($_FILES['imagepress_image_additional'], $post_id);

            if (isset($_POST['imagepress_image_category']))
                wp_set_object_terms($post_id, (int) $_POST['imagepress_image_category'], 'imagepress_image_category');

            // always moderate this category
            $moderatedCategory = imagepress_get_option('ip_cat_moderation_include');
            if (!empty($moderatedCategory)) {
                if ($_POST['imagepress_image_category'] == $moderatedCategory) {
                    $ip_post = [];
                    $ip_post['ID'] = $post_id;
                    $ip_post['post_status'] = 'pending';

                    wp_update_post($ip_post);
                }
            }
            //

            // collections
            $ip_collections = (int) ($_POST['ip_collections']);

            if (!empty($_POST['ip_collections_new'])) {
                $ip_collections_new = sanitize_text_field($_POST['ip_collections_new']);
                $ip_collection_status = (int) ($_POST['collection_status']);

                $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "ip_collections (collection_title, collection_status, collection_author_ID) VALUES (%s, %d, %d)", $ip_collections_new, $ip_collection_status, $ip_image_author));
                $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "ip_collectionmeta (image_ID, image_collection_ID, image_collection_author_ID) VALUES (%d,  %d,  %d)", $post_id, $wpdb->insert_id, $ip_image_author));
            } else {
                $wpdb->query($wpdb->prepare("INSERT INTO " . $wpdb->prefix . "ip_collectionmeta (image_ID, image_collection_ID, image_collection_author_ID) VALUES (%d,  %d,  %d)", $post_id, $ip_collections, $ip_image_author));
            }
            //

            imagepress_post_add_custom($post_id, $ip_image_author);

            $headers[] = "MIME-Version: 1.0\r\n";
            $headers[] = "Content-Type: text/html; charset=\"" . get_option('blog_charset') . "\"\r\n";
            wp_mail($notificationEmail, $notificationSubject, $notificationMessage, $headers);

            $ipUploadRedirection = imagepress_get_option('ip_upload_redirection');
            if (!empty($ipUploadRedirection)) {
                wp_redirect(imagepress_get_option('ip_upload_redirection'));
                exit;
            }
        }

        $out .= '<p class="message noir-success">' . imagepress_get_option('ip_upload_success_title') . '</p>';
        $out .= '<p class="message"><a href="' . get_permalink($post_id) . '">' . imagepress_get_option('ip_upload_success') . '</a></p>';
    }

    if(imagepress_get_option('ip_registration') == 0 && !is_user_logged_in()) {
        $out .= '<p>' . __('You need to be logged in to upload an image.', 'imagepress') . '</p>';
    }
    if((imagepress_get_option('ip_registration') == 0 && is_user_logged_in()) || imagepress_get_option('ip_registration') == 1) {
        if(isset($_POST['imagepress_image_caption']) && isset($_POST['imagepress_image_category']))
            $out .= imagepress_get_upload_image_form($ipImageCaption = $_POST['imagepress_image_caption'], $ipImageCategory = $_POST['imagepress_image_category'], $imagepress_image_description = $_POST['imagepress_image_description'], $category);
        else
            $out .= imagepress_get_upload_image_form($ipImageCaption = '', $ipImageCategory = '', $imagepress_image_description = '', $category);
    }

    return $out;
}



function imagepress_jpeg_quality($quality, $context) {
    $ip_quality = (int) imagepress_get_option('ip_max_quality');

	return $ip_quality;
}
add_filter('jpeg_quality', 'imagepress_jpeg_quality', 10, 2);



function imagepress_process_image($file, $post_id, $feature = 1) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload($file, $post_id);

    if ($feature == 1) {
        set_post_thumbnail($post_id, $attachment_id);
    }

    return $attachment_id;
}

function imagepress_get_upload_image_form($ipImageCaption = '', $ipImageCategory = 0, $imagepress_image_description = '', $imagepress_hardcoded_category) {
    global $wpdb, $wp_roles;

    $current_user = wp_get_current_user();

    // upload form // customize

    // labels
    $imagepress_slug = imagepress_get_option('ip_slug');

    $ip_caption_label = imagepress_get_option('ip_caption_label');
    $ip_description_label = imagepress_get_option('ip_description_label');
    $ip_upload_label = imagepress_get_option('ip_upload_label');

    $ip_upload_tos = imagepress_get_option('ip_upload_tos');
    $ip_upload_tos_url = imagepress_get_option('ip_upload_tos_url');
    $ipUploadTosContent = imagepress_get_option('ip_upload_tos_content');

    $ip_upload_size = imagepress_get_option('ip_upload_size');
    $ip_upload_secondary = imagepress_get_option('ip_upload_secondary');

    // get current user uploads
    $user_uploads = imagepress_count_user_posts_by_type($current_user->ID);

    $out = '<div class="ip-uploader" id="fileuploads" data-user-uploads="' . $user_uploads . '">
        <form id="imagepress_upload_image_form" method="post" action="" enctype="multipart/form-data" class="imagepress-form imagepress-upload-form">';
            $out .= wp_nonce_field('imagepress_upload_image_form', 'imagepress_upload_image_form_submitted');

            if (!empty($ip_caption_label))
                $out .= '<p>
                    <label>' . $ip_caption_label . '</label>
                    <input type="text" id="imagepress_image_caption" name="imagepress_image_caption" placeholder="' . $ip_caption_label . '" required>
                </p>';

            if (!empty($ip_description_label)) {
                $out .= '<p>
                    <label>' . imagepress_get_option('ip_description_label') . '</label>
                    <textarea id="imagepress_image_description" name="imagepress_image_description" placeholder="' . imagepress_get_option('ip_description_label') . '" rows="6"></textarea>
                </p>';
            }

            $out .= '<p>';
                if ('' != $imagepress_hardcoded_category) {
                    $iphcc = get_term_by('slug', $imagepress_hardcoded_category, 'imagepress_image_category'); // ImagePress hard-coded category
                    $out .= '<input type="hidden" id="imagepress_image_category" name="imagepress_image_category" value="' . $iphcc->term_id . '">';
                } else {
                    $out .= imagepress_get_image_categories_dropdown('imagepress_image_category', '');
                }
            $out .= '</p>';

            // Add to collection on upload
            $out .= imagepress_collection_dropdown();

            $uploadsize = number_format((($ip_upload_size * 1024)/1024000), 0, '.', '');
            $datauploadsize = $uploadsize * 1024000;

            $out .= '<hr>

            <label for="imagepress_image_file" id="dropContainer" class="dropSelector">
                <b>' . __('Drop files here<br><small>or</small>', 'imagepress') . '</b><br>
                <input type="file" accept="image/*" data-max-size="' . $datauploadsize . '" name="imagepress_image_file" id="imagepress_image_file" required>
                <br><small>' . $uploadsize . 'MB ' . __('maximum', 'imagepress') . '</small>
            </label>

            <hr>';

            if ((int) $ip_upload_secondary === 1) {
                $out .= '<p><label for="imagepress_image_additional">' . __('Select file(s)', 'imagepress') . ' (' . $uploadsize . 'MB ' . __('maximum', 'imagepress') . ')...</label><input type="file" name="imagepress_image_additional[]" id="imagepress_image_additional" multiple><br><small>' . __('Additional images (variants, making of, progress shots)', 'imagepress') . '</small></p><hr>';
            }

            if ((int) $ip_upload_tos === 1 && !empty($ipUploadTosContent)) {
                $oninvalid = imagepress_get_option('ip_upload_tos_error');

                $out .= '<p><input type="checkbox" id="imagepress_agree" name="imagepress_agree" value="1" class="imagepress-custom-validity" data-custom-validity="' . $oninvalid . '" required> ';

                    if (!empty($ip_upload_tos_url)) {
                        $out .= '<a href="' . $ip_upload_tos_url . '" target="_blank">' . $ipUploadTosContent . '</a>';
                    } else {
                        $out .= $ipUploadTosContent;
                    }

                $out .= '</p>';
            }

            $out .= '<p>
                <input type="submit" id="imagepress_submit" name="imagepress_submit" value="' . $ip_upload_label . '" class="button noir-secondary"> <span id="ipload"></span>
            </p>
        </form>
    </div>';

    return $out;
}



function imagepress_get_image_categories_dropdown($taxonomy, $selected) {
    return wp_dropdown_categories([
        'taxonomy' => $taxonomy,
        'name' => 'imagepress_image_category',
        'selected' => $selected,
        'exclude' => imagepress_get_option('ip_cat_exclude'),
        'hide_empty' => 0,
        'echo' => 0,
        'orderby' => 'name',
        'show_option_all' => imagepress_get_option('ip_category_label'),
        'required' => true
    ]);
}

function imagepress_activate() {
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // notifications table
    $table_name = $wpdb->prefix . 'notifications';
    if ($wpdb->get_var("SHOW TABLES LIKE `$table_name`") != $table_name) {
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            `ID` int(11) NOT NULL AUTO_INCREMENT,
            `userID` int(11) NOT NULL,
            `postID` int(11) NOT NULL,
            `postKeyID` int(11) NOT NULL,
            `actionType` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `actionIcon` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `actionTime` datetime NOT NULL,
            `status` tinyint(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        dbDelta($sql);
        maybe_convert_table_to_utf8mb4($table_name);
    }

    // collections table
    $table_name = $wpdb->prefix . 'ip_collections';
    if ($wpdb->get_var("SHOW TABLES LIKE `$table_name`") != $table_name) {
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            `collection_ID` int(11) NOT NULL AUTO_INCREMENT,
            `collection_title` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `collection_title_slug` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `collection_status` tinyint(4) NOT NULL DEFAULT '1',
            `collection_views` int(11) NOT NULL,
            `collection_author_ID` int(11) NOT NULL,
            PRIMARY KEY (`collection_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        dbDelta($sql);
        maybe_convert_table_to_utf8mb4($table_name);
    }
    $table_name = $wpdb->prefix . 'ip_collectionmeta';
    if ($wpdb->get_var("SHOW TABLES LIKE `$table_name`") != $table_name) {
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            `image_meta_ID` int(11) NOT NULL AUTO_INCREMENT,
            `image_ID` int(11) NOT NULL,
            `image_collection_ID` int(11) NOT NULL,
            `image_collection_author_ID` int(11) NOT NULL,
            PRIMARY KEY (`image_meta_ID`),
            UNIQUE KEY `image_meta_ID` (`image_meta_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        dbDelta($sql);
        maybe_convert_table_to_utf8mb4($table_name);
    }
}

function imagepress_deactivate() {
    flush_rewrite_rules();
}
function imagepress_uninstall() {
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'imagepress_activate');
register_deactivation_hook(__FILE__, 'imagepress_deactivate');
register_uninstall_hook( __FILE__, 'imagepress_uninstall');



// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'imagepress_enqueue_color_picker');
function imagepress_enqueue_color_picker() {
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_style('imagepress', plugins_url('css/ip-admin.css', __FILE__));

    wp_enqueue_script('imagepress-functions', plugins_url('js/functions.js', __FILE__), ['wp-color-picker'], false, true);
    wp_add_inline_script('imagepress-functions', 'const ajax_var = ' . json_encode([
        'ajaxurl' => admin_url('admin-ajax.php')
    ]), 'before');
}



add_action('wp_enqueue_scripts', 'imagepress_enqueue_scripts');
function imagepress_enqueue_scripts() {
    wp_enqueue_style('imagepress-bootstrap', plugins_url('css/ip-bootstrap.css', __FILE__));

	$accountPageUri = get_option('cinnamon_account_page');

    wp_enqueue_script('fa5', 'https://use.fontawesome.com/releases/v5.15.3/js/all.js', [], '5.15.3', true);

    if (is_page(imagepress_get_option('cinnamon_edit_page'))) {
        wp_enqueue_script('imagepress-sortable', plugins_url('js/Sortable.min.js', __FILE__), [], '1.13.0', true);
        wp_enqueue_script('imagepress-main', plugins_url('js/jquery.main.js', __FILE__), ['jquery', 'imagepress-sortable'], '8.1.2', true);
    } else {
        wp_enqueue_script('imagepress-main', plugins_url('js/jquery.main.js', __FILE__), ['jquery'], '8.1.2', true);
    }

    wp_add_inline_script('imagepress-main', 'const ipAjaxVar = ' . json_encode([
        'imagesperpage' => imagepress_get_option('ip_ipp'),
        'authorsperpage' => imagepress_get_option('ip_app'),
        'likelabel' => imagepress_get_option('ip_vote_like'),
        'unlikelabel' => imagepress_get_option('ip_vote_unlike'),
        'processing_error' => __('There was a problem processing your request.', 'imagepress'),
        'login_required' => __('Oops, you must be logged-in to follow users.', 'imagepress'),
        'logged_in' => is_user_logged_in() ? 'true' : 'false',
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ajax-nonce'),
        'ip_url' => IMAGEPRESS_PLUGIN_URL,

        'redirecturl' => apply_filters('fum_redirect_to', $accountPageUri),
		'loadingmessage' => __('Checking credentials...', 'imagepress'),
		'registrationloadingmessage' => __('Processing registration...', 'imagepress'),

        'swal_confirm_operation' => __("Are you sure? You won't be able to revert this!", 'imagepress'),
        'swal_confirm_button' => __('Yes', 'imagepress'),
        'swal_cancel_button' => __('No', 'imagepress')
    ]), 'before');
}
// end

function imagepress_search($atts) {
    extract(shortcode_atts([
        'type' => ''
    ], $atts));

    $display = '<form role="search" method="get" action="' . home_url() . '" class="imagepress-form">
            <div>
                <input type="search" name="s" id="s" placeholder="' . __('Search images...', 'imagepress') . '">
                <input type="submit" id="searchsubmit" value="' . __('Search', 'imagepress') . '">
                <input type="hidden" name="post_type" value="' . imagepress_get_option('ip_slug') . '">
            </div>
        </form>';

    return $display;
}



function imagepress_notify_status($new_status, $old_status, $post) {
    global $current_user;

    $contributor = get_userdata($post->post_author);

    $headers[] = "MIME-Version: 1.0\r\n";
    $headers[] = "Content-Type: text/html; charset=\"" . get_option('blog_charset') . "\"\r\n";

    if ((string) $old_status !== 'pending' && (string) $new_status === 'pending' && (string) imagepress_get_option('ip_notification_email') !== '') {
        $subject = '[' . get_option('blogname') . '] "' . $post->post_title . '" ' . __('pending review', 'imagepress');
        $message = '<p>' . __('A new post is pending review.', 'imagepress') . '</p>';
        $message .= "<p>" . __('Author:', 'imagepress') . " {$contributor->display_name} ({$contributor->user_login}, {$contributor->user_email})</p>";
        $message .= "<p>" . __('Title:', 'imagepress') . " {$post->post_title}</p>";
        $category = get_the_category($post->ID);
        if (isset($category[0])) {
            $message .= "<p>" . __('Category:', 'imagepress') . " {$category[0]->name}</p>";
        }

        wp_mail(imagepress_get_option('ip_notification_email'), $subject, $message, $headers);
    } else if ($old_status == 'pending' && $new_status == 'publish' && imagepress_get_option('approvednotification') == 'yes') {
        $subject = '[' . get_option('blogname') . '] "' . $post->post_title . '" ' . __('approved', 'imagepress') . '';
        $message = "<p>{$contributor->display_name}, " . __('your post has been approved and published at', 'imagepress') . " " . get_permalink($post->ID) . ".</p>";

        wp_mail($contributor->user_email, $subject, $message, $headers);
    } else if ($old_status == 'pending' && $new_status == 'draft' && $current_user->ID != $contributor->ID && imagepress_get_option('declinednotification') == 'yes') {
        $subject = '[' . get_option('blogname') . '] "' . $post->post_title . '" declined';
        $message = "<p>{$contributor->display_name}, " . __('your post has not been approved.', 'imagepress') . "</p>";

        wp_mail($contributor->user_email, $subject, $message, $headers);
    }
}

/*
 * Main shortcode function [imagepress]
 *
 */
function imagepress_widget($atts) {
    extract(shortcode_atts([
        'type' => 'list', // list, top
        'mode' => 'views', // views, likes
        'count' => 5
    ], $atts));

    $display = '';
    $ip_comments = '';

    $imagepress_meta_key = ((string) $mode === 'likes') ? '_like_count' : 'post_views_count';

    if ($type === 'top') {
        $count = 1;
    }

    $args = [
        'post_type' => imagepress_get_option('ip_slug'),
        'posts_per_page' => $count,
        'orderby' => 'meta_value_num',
        'meta_key' => $imagepress_meta_key,
        'meta_query' => [
            [
                'key' => $imagepress_meta_key,
                'type' => 'numeric'
            ]
        ]
    ];

    $getImages = get_posts($args);

    if ($getImages) {
        if ((string) $type === 'list') {
            $display .= '<ul>';
                foreach ($getImages as $image) {
                    if ($mode == 'likes') {
                        $ip_link_value = imagepress_get_like_count($image->ID);
                    } else if ($mode == 'views') {
                        $ip_link_value = imagepress_get_post_views($image);
                    }
                    if (empty($ip_link_value)) {
                        $ip_link_value = 0;
                    }

                    $display .= '<li><a href="' . get_permalink($image->ID) . '">' . get_the_title($image->ID) . '</a> <small>(' . $ip_link_value . ')</small></li>';
                }
            $display .= '</ul>';
        } else if ((string) $type === 'top') {
            foreach ($getImages as $image) {
                if ((int) imagepress_get_option('ip_comments') === 1) {
                    $ip_comments = '<i class="fas fa-comments"></i> ' . get_comments_number($image->ID);
                }

                $post_thumbnail_id = get_post_thumbnail_id($image);
                $image_attributes = wp_get_attachment_image_src($post_thumbnail_id, 'full');
                $ip_image_link = get_permalink($image->ID);

                if ((string) imagepress_get_option('ip_click_behaviour') === 'media') {
                    $ip_image_link = $image_attributes[0];
                }

                $display .= '<div id="ip_container_2">
                    <div class="ip_icon_hover">
                        <div><strong>' . get_the_title($image->ID) . '</strong></div>
                        <div><small><i class="far fa-eye"></i> ' . imagepress_get_post_views($image->ID) . ' ' . $ip_comments . ' <i class="fas fa-heart"></i> ' . imagepress_get_like_count($image->ID) . '</small></div>
                    </div><a href="' . $ip_image_link . '" class="ip-link">' . wp_get_attachment_image($post_thumbnail_id, 'full') . '</a>
                </div>';
            }
        }
    }

    return $display;
}



if ((int) get_option('use_bulk_upload') === 1) {
    include 'classes/ImagePress_Bulk_Upload.php';

    new ImagePress_Bulk_Upload();
}

function imagepress_lightbox_load_scripts() {
    wp_enqueue_script('imagepress-halka', plugin_dir_url(__FILE__) . 'assets/halkabox/halkaBox.min.js', [], '1.6.0', true);
    wp_enqueue_script('imagepress-halka-init', plugin_dir_url(__FILE__) . 'assets/halkabox/halkaBox.init.js', ['imagepress-halka'], '1.6.0', true);

    wp_enqueue_style('imagepress-halka-style', plugin_dir_url(__FILE__) . 'assets/halkabox/halkaBox.min.css');
}

if ((int) get_option('imagepress_use_lightbox') === 1 && (string) imagepress_get_option('ip_click_behaviour') === 'media') {
    add_action('wp_enqueue_scripts', 'imagepress_lightbox_load_scripts');
}


add_shortcode('imagepress-categories', 'imagepress_element_categories');
add_shortcode('imagepress-member-directory', 'imagepress_element_member_directory');

function imagepress_element_categories($atts) {
    extract(shortcode_atts([
        'gallery' => '',
        'columns' => 3
    ], $atts));

    $display = '<ul class="ip-element-categories">';

        $args = [
            'taxonomy' => 'imagepress_image_category',
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => 0
        ];
        $categories = get_categories($args);
        foreach ($categories as $category) {
            if (!empty($gallery)) {
                $link = $gallery . '?sort=newest&range=alltime&t=' . $category->slug . '&q=';
            }

            $display .= '<li><a href="' . $link . '">' . $category->name . '</a></li>';
        }

    $display .= '</ul>';


    return $display;
}

function imagepress_member_directory_user_query($args) {
    $imagepress_slug = imagepress_get_option('ip_slug');
    $args->query_from = str_replace("post_type = post AND", "post_type IN ('$imagepress_slug') AND ", $args->query_from);
}

function imagepress_element_member_directory() {
    global $wpdb;

    $out = '';

    $query = "SELECT id, display_name FROM {$wpdb->prefix}users";
    $members = $wpdb->get_results($query);

    add_action('pre_user_query', 'imagepress_member_directory_user_query');
    $members = get_users([
        'fields' => ['ID', 'display_name'],
        'orderby' => 'post_count',
        'who' => 'authors',
        'has_published_posts' => get_post_types(['public' => true])
    ]);
    remove_action('pre_user_query', 'imagepress_member_directory_user_query');

    $ipProfilePageId = (int) imagepress_get_option('ip_profile_page');
    $ipProfilePageUri = get_permalink($ipProfilePageId);
    $ipProfileSlug = (string) imagepress_get_option('cinnamon_author_slug');

    $out .= '<ul class="ip-element-member-directory">';
        foreach ($members as $group) {
            $ipProfileUri = $ipProfilePageUri . '?' . $ipProfileSlug . '=' . get_the_author_meta('user_login', $group->ID);
            $out .= '<li><a href="' . $ipProfileUri . '">' . $group->display_name . '</a></li>';
        }
    $out .= '</ul>';

    return $out;
}
