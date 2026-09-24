<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', 'ldvt_vimeo_tracking_script');

/**
 * Injeta o script de rastreamento do Vimeo no footer.
 *
 * @return void
 */
function ldvt_vimeo_tracking_script()
{
    if (!is_user_logged_in()) {
        return;
    }

    $lesson_id = (int) get_queried_object_id();
    if (!$lesson_id || !is_singular() || !in_array(get_post_type($lesson_id), array('sfwd-lessons', 'sfwd-topic'), true)) {
        return;
    }

    $user_id = get_current_user_id();
    $page_started_at = time();
    wp_enqueue_script('ldvt-vimeo-player', 'https://player.vimeo.com/api/player.js', array(), null, true);
    wp_enqueue_script(
        'ldvt-vimeo-tracking',
        LDVT_PLUGIN_URL . 'includes/frontend/tracking.js',
        array('ldvt-vimeo-player'),
        LDVT_VERSION,
        true
    );
    wp_localize_script('ldvt-vimeo-tracking', 'LDVTTracking', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ldvt_progress'),
        'blogId' => get_current_blog_id(),
        'userId' => $user_id,
        'lessonId' => $lesson_id,
        'pageStartedAt' => $page_started_at,
        'pageSignature' => ldvt_create_page_start_signature($user_id, $lesson_id, $page_started_at),
    ));
}
