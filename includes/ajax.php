<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_ldvt_get_user_courses', 'ldvt_get_user_courses_callback');
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

    if (!$user_id || !$video_id || !$tempo) {
        wp_send_json_error('Dados inválidos.');
    }

    ldvt_criar_tabela_tempo_video();

    $table = ldvt_get_tempo_video_table_name();
    $now = current_time('mysql');

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO $table (user_id, video_id, tempo, curso_id, aula_id, duracao_total, data_registro)
             VALUES (%d, %s, %d, %d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE
                 tempo         = GREATEST( tempo, VALUES( tempo ) ),
                 curso_id      = VALUES( curso_id ),
                 aula_id       = VALUES( aula_id ),
                 duracao_total = VALUES( duracao_total ),
                 data_registro = VALUES( data_registro )",
            $user_id,
            $video_id,
            $tempo,
            $curso_id,
            $aula_id,
            $duracao_total,
            $now
        )
    );

    $step_completed = ldvt_maybe_mark_step_complete($user_id, $curso_id, $aula_id, $tempo, $duracao_total);

    wp_send_json_success($step_completed ? 'Tempo salvo e etapa concluída no LearnDash.' : 'Tempo salvo.');
}
