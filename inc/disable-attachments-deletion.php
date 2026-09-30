<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

define('WPU_ATTACHMENTS_ENHANCED__PROTECTED_META', 'wpu_attachments_enhanced__protected');
function wpu_attachments_enhanced__can_toggle_protection() {
    return current_user_can(apply_filters('wpu_attachments_enhanced__protected_min_cap', 'manage_options'));
}

/* Media views (grid + modal) or the list mode screen : nothing below is useful anywhere else */
function wpu_attachments_enhanced__is_media_screen() {
    return did_action('wp_enqueue_media') || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'upload.php');
}

/* ----------------------------------------------------------
  Block deletion for everyone while protected
---------------------------------------------------------- */

add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    if ($cap !== 'delete_post' || empty($args[0])) {
        return $caps;
    }
    if (get_post_type($args[0]) !== 'attachment') {
        return $caps;
    }
    if (!get_post_meta($args[0], WPU_ATTACHMENTS_ENHANCED__PROTECTED_META, true)) {
        return $caps;
    }
    return array('do_not_allow');
}, 10, 4);

/* ----------------------------------------------------------
  Field : edit page + media modal
---------------------------------------------------------- */

add_filter('attachment_fields_to_edit', function ($form_fields, $post) {
    $key = WPU_ATTACHMENTS_ENHANCED__PROTECTED_META;
    $is_protected = (bool) get_post_meta($post->ID, $key, true);
    $label = __('Protected against deletion', 'wpu_attachments_enhanced');

    if (!wpu_attachments_enhanced__can_toggle_protection()) {
        if ($is_protected) {
            $form_fields[$key] = array(
                'label' => __('Protection', 'wpu_attachments_enhanced'),
                'input' => 'html',
                'html' => '<em>' . esc_html($label) . '</em>'
            );
        }
        return $form_fields;
    }

    /* Hidden input ensures the key is always sent, so an unchecked box can be told apart from a missing field */
    $name = 'attachments[' . $post->ID . '][' . $key . ']';
    $form_fields[$key] = array(
        'label' => __('Protection', 'wpu_attachments_enhanced'),
        'input' => 'html',
        'html' => sprintf(
            '<input type="hidden" name="%1$s" value="0" /><label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr($name),
            checked($is_protected, true, false),
            esc_html($label)
        )
    );
    return $form_fields;
}, 10, 2);

add_filter('attachment_fields_to_save', function ($post, $attachment) {
    $key = WPU_ATTACHMENTS_ENHANCED__PROTECTED_META;
    if (!isset($attachment[$key]) || !wpu_attachments_enhanced__can_toggle_protection()) {
        return $post;
    }
    if ($attachment[$key] === '1') {
        update_post_meta($post['ID'], $key, '1');
    } else {
        delete_post_meta($post['ID'], $key);
    }
    return $post;
}, 10, 2);

/* ----------------------------------------------------------
  Indicator : media library list mode
---------------------------------------------------------- */

add_filter('manage_media_columns', function ($columns) {
    $columns[WPU_ATTACHMENTS_ENHANCED__PROTECTED_META] = '<span class="dashicons dashicons-lock" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Protection', 'wpu_attachments_enhanced') . '</span>';
    return $columns;
});

add_action('manage_media_custom_column', function ($column_name, $post_id) {
    if ($column_name !== WPU_ATTACHMENTS_ENHANCED__PROTECTED_META || !get_post_meta($post_id, WPU_ATTACHMENTS_ENHANCED__PROTECTED_META, true)) {
        return;
    }
    $label = __('Protected against deletion', 'wpu_attachments_enhanced');
    echo '<span class="dashicons dashicons-lock" title="' . esc_attr($label) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html($label) . '</span>';
}, 10, 2);

add_action('admin_head-upload.php', function () {
    echo '<style>.fixed .column-' . esc_attr(WPU_ATTACHMENTS_ENHANCED__PROTECTED_META) . '{width:2.5em;}</style>';
});

/* ----------------------------------------------------------
  Indicator : media library grid mode + modal
---------------------------------------------------------- */

add_filter('wp_prepare_attachment_for_js', function ($response, $attachment) {
    $response['wpuDisableDeletion'] = (bool) get_post_meta($attachment->ID, WPU_ATTACHMENTS_ENHANCED__PROTECTED_META, true);
    return $response;
}, 10, 2);

/* Same approach as the dominant-color-images plugin : patch the attachment template to inject a lock icon */
add_action('admin_print_footer_scripts', function () {
    if (!wpu_attachments_enhanced__is_media_screen()) {
        return;
    }
    $label = __('Protected against deletion', 'wpu_attachments_enhanced');
    $icon = '<# if ( data.wpuDisableDeletion ) { #><span class="wpu-protected-icon dashicons dashicons-lock" title="' . esc_attr($label) . '"><span class="screen-reader-text">' . esc_html($label) . '</span></span><# } #>';
    ?>
    <script>
    (function() {
        var tmpl = document.getElementById('tmpl-attachment');
        if (!tmpl) {
            return;
        }
        tmpl.textContent = tmpl.textContent.replace(/class="attachment-preview[^>]*>/, function(match) {
            return match + <?php echo wp_json_encode($icon); ?>;
        });
    }());
    </script>
    <style>
    .wp-core-ui .attachment-preview .wpu-protected-icon {
        z-index: 2;
        position: absolute;
        top: 4px;
        left: 4px;
        padding: 3px;
        border-radius: 3px;
        background: rgba(255, 255, 255, 0.9);
        color: #1d2327;
    }
    </style>
    <?php
});

/* ----------------------------------------------------------
  Filter : media library grid mode + modal + list mode
---------------------------------------------------------- */

function wpu_attachments_enhanced__protected_meta_query($query_args) {
    $meta_query = isset($query_args['meta_query']) && is_array($query_args['meta_query']) ? $query_args['meta_query'] : array();
    $meta_query[] = array(
        'key' => WPU_ATTACHMENTS_ENHANCED__PROTECTED_META,
        'value' => '1'
    );
    return $meta_query;
}

/* Grid + modal : core whitelists query keys, so the custom prop is read from the raw request */
add_filter('ajax_query_attachments_args', function ($query) {
    if (empty($_REQUEST['query']['wpuDisableDeletion'])) {
        return $query;
    }
    $query['meta_query'] = wpu_attachments_enhanced__protected_meta_query($query);
    return $query;
});

/* List mode */
add_action('pre_get_posts', function ($query) {
    global $pagenow;
    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'upload.php') {
        return;
    }
    if (!isset($_GET['attachment-filter']) || $_GET['attachment-filter'] !== 'wpu_protected') {
        return;
    }
    $query->set('meta_query', wpu_attachments_enhanced__protected_meta_query($query->query_vars));
});

/* Runs after footer scripts (priority 10) so media-views is loaded */
add_action('admin_print_footer_scripts', function () {
    if (!wpu_attachments_enhanced__is_media_screen()) {
        return;
    }
    $label = __('Protected', 'wpu_attachments_enhanced');
    $is_list_filter_active = isset($_GET['attachment-filter']) && $_GET['attachment-filter'] === 'wpu_protected';
    ?>
    <script>
    (function() {
        var label = <?php echo wp_json_encode($label); ?>;

        /* List mode : core offers no hook for this select, the form reloads the page on change */
        var select = document.getElementById('attachment-filter');
        if (select) {
            var option = new Option(label, 'wpu_protected', false, <?php echo $is_list_filter_active ? 'true' : 'false'; ?>);
            select.appendChild(option);
        }

        /* Grid + modal */
        if (!window.wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentFilters) {
            return;
        }
        var All = wp.media.view.AttachmentFilters.All;
        var createFilters = All.prototype.createFilters;
        All.prototype.createFilters = function() {
            createFilters.apply(this, arguments);
            /* Other filters must reset the prop, same as core does with author */
            _.each(this.filters, function(filter) {
                filter.props.wpuDisableDeletion = null;
            });
            this.filters.wpuDisableDeletion = {
                text: label,
                props: {
                    status: null,
                    type: null,
                    uploadedTo: null,
                    orderby: 'date',
                    order: 'DESC',
                    author: null,
                    wpuDisableDeletion: 1
                },
                priority: 70
            };
        };
    }());
    </script>
    <?php
}, 20);
