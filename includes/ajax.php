<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_ldvt_get_user_courses', 'ldvt_get_user_courses_callback');
add_action('wp_ajax_ldvt_get_tempo_video', 'ldvt_get_tempo_video_callback');
add_action('wp_ajax_ldvt_salvar_tempo_video', 'ldvt_salvar_tempo_video_callback');

/**
 * Callback AJAX para buscar cursos em que o aluno está inscrito.
 *
 * @return void
 */
function ldvt_get_user_courses_callback()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permissão negada.');
    }

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if (empty($email)) {
        wp_send_json_error('Email não fornecido.');
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        wp_send_json_error('Usuário não encontrado.');
    }

    if (!function_exists('learndash_user_get_enrolled_courses')) {
        wp_send_json_error('LearnDash não está ativo.');
    }

    $course_ids = learndash_user_get_enrolled_courses($user->ID);

    if (empty($course_ids)) {
        wp_send_json_success(array());
    }

    $courses = get_posts(array(
        'post_type' => 'sfwd-courses',
        'post__in' => $course_ids,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    $courses_data = array();
    foreach ($courses as $course) {
        $courses_data[] = array(
            'id' => $course->ID,
            'title' => $course->post_title,
        );
    }

    wp_send_json_success($courses_data);
}

/**
 * Callback AJAX para buscar o tempo ja salvo de um video.
 *
 * @return void
 */
function ldvt_get_tempo_video_callback()
{
    $user_id = get_current_user_id();
    $video_id = sanitize_text_field($_POST['video_id'] ?? '');

    if (!$user_id || !$video_id) {
        wp_send_json_error('Dados inválidos.');
    }

    $record = ldvt_get_tempo_video_record($user_id, $video_id);
    $saved_time = $record ? (int) $record->tempo : 0;
    $saved_at = $record ? $record->data_registro : '';

    wp_send_json_success(array(
        'tempo' => $saved_time,
        'tempo_formatado' => ldvt_format_seconds($saved_time),
        'data_registro' => $saved_at,
        'data_registro_formatada' => $saved_at ? date_i18n('d/m/Y H:i', strtotime($saved_at)) : '',
        'has_record' => (bool) $record,
    ));
}

/**
 * Callback AJAX para salvar o tempo assistido no banco de dados.
 *
 * @return void
 */
function ldvt_salvar_tempo_video_callback()
{
    global $wpdb;

    $user_id = get_current_user_id();
    $video_id = sanitize_text_field($_POST['video_id'] ?? '');
    $tempo = (int) ($_POST['tempo'] ?? 0);
    $curso_id = (int) ($_POST['curso_id'] ?? 0);
    $aula_id = (int) ($_POST['aula_id'] ?? 0);
    $duracao_total = (int) ($_POST['duracao_total'] ?? 0);
    $watched_intervals_raw = isset($_POST['watched_intervals']) ? wp_unslash($_POST['watched_intervals']) : '';

    if (!$user_id || !$video_id || !$tempo) {
        wp_send_json_error('Dados inválidos.');
    }

    ldvt_criar_tabela_tempo_video();

    $table = ldvt_get_tempo_video_table_name();
    $now = current_time('mysql');
    $existing_record = ldvt_get_tempo_video_record($user_id, $video_id);
    $existing_intervals = ldvt_get_watched_intervals_from_record($existing_record);
    $new_intervals = ldvt_parse_watched_intervals_json($watched_intervals_raw, $duracao_total);

    if (empty($new_intervals)) {
        $new_intervals = ldvt_get_legacy_watched_interval($tempo, $duracao_total);
    }

    $watched_intervals = ldvt_normalize_watched_intervals(
        array_merge($existing_intervals, $new_intervals),
        $duracao_total
    );
    $saved_time = ldvt_calculate_watched_time_from_intervals($watched_intervals);
    $watched_intervals_json = wp_json_encode($watched_intervals);

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO $table (user_id, video_id, tempo, curso_id, aula_id, duracao_total, watched_intervals, data_registro)
             VALUES (%d, %s, %d, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                 tempo             = VALUES( tempo ),
                 curso_id          = VALUES( curso_id ),
                 aula_id           = VALUES( aula_id ),
                 duracao_total     = VALUES( duracao_total ),
                 watched_intervals = VALUES( watched_intervals ),
                 data_registro     = VALUES( data_registro )",
            $user_id,
            $video_id,
            $saved_time,
            $curso_id,
            $aula_id,
            $duracao_total,
            $watched_intervals_json,
            $now
        )
    );

    $step_completed = ldvt_maybe_mark_step_complete($user_id, $curso_id, $aula_id, $saved_time, $duracao_total);
    $record = ldvt_get_tempo_video_record($user_id, $video_id);
    $saved_time = $record ? (int) $record->tempo : $tempo;
    $saved_at = $record ? $record->data_registro : $now;

    wp_send_json_success(array(
        'message' => $step_completed ? 'Tempo salvo e etapa concluída no LearnDash.' : 'Tempo salvo.',
        'tempo' => $saved_time,
        'tempo_formatado' => ldvt_format_seconds($saved_time),
        'data_registro' => $saved_at,
        'data_registro_formatada' => date_i18n('d/m/Y H:i', strtotime($saved_at)),
        'step_completed' => $step_completed,
    ));
}
