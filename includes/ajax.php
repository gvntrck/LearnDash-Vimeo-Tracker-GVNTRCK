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

function ldvt_ajax_verify_progress_nonce()
{
    $nonce_raw = wp_unslash($_POST['nonce'] ?? '');
    if (!is_string($nonce_raw)) {
        return false;
    }

    $nonce = sanitize_text_field($nonce_raw);

    return $nonce !== '' && wp_verify_nonce($nonce, 'ldvt_progress');
}

function ldvt_is_valid_vimeo_video_id($video_id)
{
    return is_string($video_id) && preg_match('/^[1-9][0-9]{0,19}$/', $video_id);
}

function ldvt_create_page_start_signature($user_id, $lesson_id, $timestamp)
{
    $payload = implode('|', array((int) get_current_blog_id(), (int) $user_id, (int) $lesson_id, (int) $timestamp));

    return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function ldvt_get_verified_page_start($user_id, $lesson_id, $timestamp, $signature, $now)
{
    $timestamp = is_scalar($timestamp) && preg_match('/^[0-9]+$/', (string) $timestamp) ? (int) $timestamp : 0;
    if (!$timestamp || $timestamp > $now || ($now - $timestamp) > DAY_IN_SECONDS || !is_string($signature)) {
        return 0;
    }

    $expected = ldvt_create_page_start_signature($user_id, $lesson_id, $timestamp);

    return hash_equals($expected, $signature) ? $timestamp : 0;
}

function ldvt_get_vimeo_duration($video_id)
{
    if (!ldvt_is_valid_vimeo_video_id($video_id)) {
        return 0;
    }

    $cache_key = 'ldvt_vimeo_duration_' . $video_id;
    $cached_duration = (int) get_transient($cache_key);
    if ($cached_duration > 0) {
        return $cached_duration;
    }

    $url = 'https://vimeo.com/api/oembed.json?url=' . rawurlencode('https://vimeo.com/' . $video_id);
    $response = wp_remote_get($url, array('timeout' => 3, 'redirection' => 0));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return 0;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || !isset($data['duration'], $data['video_id']) || !is_numeric($data['duration']) || !is_finite((float) $data['duration']) || (float) $data['duration'] <= 0 || (string) $data['video_id'] !== $video_id) {
        return 0;
    }

    $duration = max(1, (int) round((float) $data['duration']));
    set_transient($cache_key, $duration, DAY_IN_SECONDS);

    return $duration;
}

function ldvt_get_progress_lock_name($user_id, $video_id)
{
    return 'ldvt_' . substr(hash('sha256', ldvt_get_tempo_video_table_name() . ':' . (int) $user_id . ':' . $video_id), 0, 58);
}

function ldvt_write_progress_row($user_id, $video_id, $tempo, $tempo_verificado, $ultima_confirmacao, $curso_id, $aula_id, $duracao_total, $watched_intervals, $data_registro)
{
    global $wpdb;

    $table = ldvt_get_tempo_video_table_name();

    return $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO $table (user_id, video_id, tempo, tempo_verificado, ultima_confirmacao, curso_id, aula_id, duracao_total, watched_intervals, data_registro)
             VALUES (%d, %s, %d, %d, %s, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE tempo = VALUES(tempo), tempo_verificado = VALUES(tempo_verificado), ultima_confirmacao = VALUES(ultima_confirmacao), curso_id = VALUES(curso_id), aula_id = VALUES(aula_id), duracao_total = VALUES(duracao_total), watched_intervals = VALUES(watched_intervals), data_registro = VALUES(data_registro)",
            $user_id,
            $video_id,
            $tempo,
            $tempo_verificado,
            $ultima_confirmacao,
            $curso_id,
            $aula_id,
            $duracao_total,
            $watched_intervals,
            $data_registro
        )
    );
}

function ldvt_resolve_video_lesson($video_id, $lesson_id)
{
    $lesson_id = (int) $lesson_id;
    $post_type = $lesson_id ? get_post_type($lesson_id) : '';
    $video_ids = $lesson_id ? ldvt_get_post_vimeo_video_ids($lesson_id) : array();
    $valid = in_array($post_type, array('sfwd-lessons', 'sfwd-topic'), true) && in_array($video_id, $video_ids, true);
    $course_id = $valid && function_exists('learndash_get_course_id') ? (int) learndash_get_course_id($lesson_id) : 0;

    return array(
        'valid' => $valid,
        'lesson_id' => $valid ? $lesson_id : 0,
        'course_id' => $course_id,
    );
}

function ldvt_is_user_enrolled_in_course($user_id, $course_id)
{
    if (!$user_id || !$course_id) {
        return false;
    }

    if (function_exists('sfwd_lms_has_access')) {
        return (bool) sfwd_lms_has_access((int) $course_id, (int) $user_id);
    }

    if (!function_exists('learndash_user_get_enrolled_courses')) {
        return false;
    }

    $courses = learndash_user_get_enrolled_courses($user_id);

    return is_array($courses) && in_array((int) $course_id, array_map('intval', $courses), true);
}

function ldvt_save_video_progress($user_id, $video_id, $new_intervals, $mapping, $page_started_at, $page_signature)
{
    global $wpdb;

    ldvt_criar_tabela_tempo_video();
    $lock_name = ldvt_get_progress_lock_name($user_id, $video_id);
    $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name));
    if ((string) $lock_acquired !== '1') {
        return array('success' => false, 'message' => 'Não foi possível bloquear o progresso; tente novamente.');
    }

    $release_ok = false;
    $result = array('success' => false, 'message' => 'Falha ao salvar o progresso.');
    try {
        $record = ldvt_get_tempo_video_record($user_id, $video_id);
        if (!ldvt_tempo_video_table_exists()) {
            $result['message'] = 'Tabela de progresso indisponível.';
        } else {
            $existing_intervals = ldvt_get_watched_intervals_from_record($record);
            $existing_interval_time = ldvt_calculate_watched_time_from_intervals($existing_intervals);
            $watched_intervals = ldvt_normalize_watched_intervals(array_merge($existing_intervals, $new_intervals));
            $interval_time = ldvt_calculate_watched_time_from_intervals($watched_intervals);
            if (empty($watched_intervals)) {
                $result['message'] = 'Nenhum intervalo válido para salvar.';
            } else {
                $saved_time = max($record ? (int) $record->tempo : 0, $interval_time);
                $new_seconds = max(0, $interval_time - $existing_interval_time);
                $now = time();
                $now_utc = gmdate('Y-m-d H:i:s', $now);
                $old_verified = $record ? max(0, (int) $record->tempo_verificado) : 0;
                $verified_time = $old_verified;
                $last_confirmation = $record && !empty($record->ultima_confirmacao) ? strtotime($record->ultima_confirmacao . ' UTC') : 0;

                if ($record && !$last_confirmation && (int) $record->tempo > 0) {
                    $verified_time = max($old_verified, (int) $record->tempo);
                } else {
                    if ($last_confirmation) {
                        $elapsed = max(0, $now - $last_confirmation);
                    } else {
                        $signed_start = ldvt_get_verified_page_start($user_id, (int) $mapping['requested_lesson_id'], $page_started_at, $page_signature, $now);
                        $elapsed = $signed_start ? max(0, $now - $signed_start) : 0;
                    }
                    $verified_time += min($new_seconds, 2 * min(30, $elapsed));
                }

                $lesson_id = $mapping['valid'] ? (int) $mapping['lesson_id'] : ($record ? (int) $record->aula_id : 0);
                $course_id = $mapping['valid'] ? (int) $mapping['course_id'] : ($record ? (int) $record->curso_id : 0);
                $duration = $record ? max(0, (int) $record->duracao_total) : 0;
                $intervals_json = wp_json_encode($watched_intervals);
                $write_result = ldvt_write_progress_row(
                    $user_id,
                    $video_id,
                    $saved_time,
                    $verified_time,
                    $now_utc,
                    $course_id,
                    $lesson_id,
                    $duration,
                    $intervals_json,
                    current_time('mysql')
                );

                if ($write_result === false) {
                    $result['message'] = 'Banco de dados recusou o progresso.';
                } else {
                    $result = array('success' => true, 'message' => '', 'tempo' => $saved_time, 'aula_id' => $lesson_id, 'curso_id' => $course_id);
                }
            }
        }
    } catch (Throwable $error) {
        $result = array('success' => false, 'message' => 'Falha ao salvar o progresso.');
    } finally {
        $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        $release_ok = (string) $released === '1';
    }

    if (!$release_ok) {
        return array('success' => false, 'message' => 'Não foi possível liberar o bloqueio do progresso; tente novamente.');
    }

    return $result;
}

function ldvt_update_progress_duration($user_id, $video_id, $duration)
{
    global $wpdb;

    $lock_name = ldvt_get_progress_lock_name($user_id, $video_id);
    $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name));
    if ((string) $lock_acquired !== '1') {
        return false;
    }

    $success = false;
    try {
        $record = ldvt_get_tempo_video_record($user_id, $video_id);
        if ($record) {
            $query_result = ldvt_write_progress_row(
                $user_id,
                $video_id,
                (int) $record->tempo,
                (int) $record->tempo_verificado,
                $record->ultima_confirmacao,
                (int) $record->curso_id,
                (int) $record->aula_id,
                (int) $duration,
                wp_json_encode(ldvt_get_watched_intervals_from_record($record)),
                $record->data_registro
            );
            $success = $query_result !== false;
        }
    } catch (Throwable $error) {
        $success = false;
    } finally {
        $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        if ((string) $released !== '1') {
            $success = false;
        }
    }

    return $success;
}

/**
 * Callback AJAX para buscar o tempo ja salvo de um video.
 *
 * @return void
 */
function ldvt_get_tempo_video_callback()
{
    if (!ldvt_ajax_verify_progress_nonce()) {
        wp_send_json_error('Verificação de segurança inválida.', 403);
    }

    $user_id = get_current_user_id();
    $video_id_raw = wp_unslash($_POST['video_id'] ?? '');
    $lesson_id_raw = wp_unslash($_POST['aula_id'] ?? 0);
    $video_id = is_string($video_id_raw) ? sanitize_text_field($video_id_raw) : '';
    $lesson_id = is_scalar($lesson_id_raw) ? absint($lesson_id_raw) : 0;

    if (!$user_id || !ldvt_is_valid_vimeo_video_id($video_id)) {
        wp_send_json_error('Dados inválidos.');
    }

    $record = ldvt_get_tempo_video_record($user_id, $video_id);
    $duration = 0;
    $step_completed = false;
    $completion_pending = false;
    if ($record && ldvt_is_auto_completion_enabled() && (int) $record->tempo_verificado > 0) {
        $stored_duration = max(0, (int) $record->duracao_total);
        $known_duration = max(0, (int) get_transient('ldvt_vimeo_duration_' . $video_id));
        $known_below_threshold = $known_duration > 0 && !ldvt_has_completion_progress((int) $record->tempo_verificado, $known_duration);
        $mapping = $lesson_id ? ldvt_resolve_video_lesson($video_id, $lesson_id) : array('valid' => false, 'lesson_id' => 0, 'course_id' => 0);

        if ($known_below_threshold) {
            $completion_pending = false;
        } elseif ($mapping['valid']) {
            $duration = ldvt_get_vimeo_duration($video_id);
            if ($duration > 0) {
                $duration_saved = $duration === $stored_duration || ldvt_update_progress_duration($user_id, $video_id, $duration);
                if ($duration_saved) {
                    $has_course_access = ldvt_is_user_enrolled_in_course($user_id, (int) $mapping['course_id']);
                    if ($has_course_access) {
                        $step_completed = ldvt_maybe_mark_step_complete(
                            $user_id,
                            (int) $mapping['course_id'],
                            (int) $mapping['lesson_id'],
                            (int) $record->tempo_verificado,
                            $duration
                        );
                    }
                    $completion_pending = ldvt_has_completion_progress((int) $record->tempo_verificado, $duration) && !$step_completed;
                } else {
                    $completion_pending = true;
                }
            } else {
                $completion_pending = true;
            }
        } else {
            $completion_pending = true;
        }
    }

    $saved_time = $record ? (int) $record->tempo : 0;
    $saved_at = $record ? $record->data_registro : '';

    wp_send_json_success(array(
        'tempo' => $saved_time,
        'tempo_formatado' => ldvt_format_seconds($saved_time),
        'data_registro' => $saved_at,
        'data_registro_formatada' => $saved_at ? date_i18n('d/m/Y H:i', strtotime($saved_at)) : '',
        'has_record' => (bool) $record,
        'step_completed' => $step_completed,
        'completion_pending' => $completion_pending,
        'duracao_total' => $duration > 0 ? $duration : ($record ? (int) $record->duracao_total : 0),
    ));
}

/**
 * Callback AJAX para salvar o tempo assistido no banco de dados.
 *
 * @return void
 */
function ldvt_salvar_tempo_video_callback()
{
    if (!ldvt_ajax_verify_progress_nonce()) {
        wp_send_json_error('Verificação de segurança inválida.', 403);
    }

    $user_id = get_current_user_id();
    $video_id_raw = wp_unslash($_POST['video_id'] ?? '');
    $intervals_raw = wp_unslash($_POST['watched_intervals'] ?? '');
    $video_id = is_string($video_id_raw) ? sanitize_text_field($video_id_raw) : '';
    $lesson_id_raw = wp_unslash($_POST['aula_id'] ?? 0);
    $page_started_raw = wp_unslash($_POST['page_started_at'] ?? '');
    $page_signature_raw = wp_unslash($_POST['page_signature'] ?? '');
    $lesson_id = is_scalar($lesson_id_raw) ? absint($lesson_id_raw) : 0;
    $page_started_at = is_scalar($page_started_raw) ? sanitize_text_field((string) $page_started_raw) : '';
    $page_signature = is_string($page_signature_raw) ? sanitize_text_field($page_signature_raw) : '';

    if (!$user_id || !ldvt_is_valid_vimeo_video_id($video_id) || !is_string($intervals_raw)) {
        wp_send_json_error('Dados inválidos.');
    }

    $new_intervals = ldvt_parse_watched_intervals_json($intervals_raw);
    if (empty($new_intervals)) {
        wp_send_json_error('Intervalos assistidos inválidos.');
    }

    $mapping = ldvt_resolve_video_lesson($video_id, $lesson_id);
    $mapping['requested_lesson_id'] = $lesson_id;
    $result = ldvt_save_video_progress($user_id, $video_id, $new_intervals, $mapping, $page_started_at, $page_signature);
    if (!$result['success']) {
        wp_send_json_error($result['message']);
    }

    $duration = ldvt_get_vimeo_duration($video_id);
    $duration_update_failed = $duration > 0 && !ldvt_update_progress_duration($user_id, $video_id, $duration);

    $record = ldvt_get_tempo_video_record($user_id, $video_id);
    $saved_time = $record ? (int) $record->tempo : (int) $result['tempo'];
    $course_id = $mapping['valid'] ? (int) $mapping['course_id'] : 0;
    $lesson_id = $mapping['valid'] ? (int) $mapping['lesson_id'] : 0;
    $auto_completion = ldvt_is_auto_completion_enabled();
    $eligible_for_completion = $auto_completion && $duration > 0 && ldvt_has_completion_progress($saved_time, $duration);
    $has_course_access = $eligible_for_completion && $mapping['valid'] && ldvt_is_user_enrolled_in_course($user_id, $course_id);
    $step_completed = false;
    if (!$duration_update_failed && $has_course_access && $record) {
        $step_completed = ldvt_maybe_mark_step_complete(
            $user_id,
            $course_id,
            $lesson_id,
            (int) $record->tempo_verificado,
            $duration
        );
    }

    $completion_pending = $auto_completion && (
        $duration_update_failed
        || $duration <= 0
        || ($eligible_for_completion && !$step_completed)
    );
    $saved_at = $record ? $record->data_registro : current_time('mysql');
    $message = $step_completed
        ? 'Progresso salvo e etapa concluída no LearnDash.'
        : ($completion_pending
            ? ($duration_update_failed
                ? 'Progresso salvo; conclusão pendente porque não foi possível atualizar a duração.'
                : 'Progresso salvo; conclusão pendente.')
            : ($duration_update_failed ? 'Progresso salvo; duração não atualizada.' : 'Progresso salvo'));
    wp_send_json_success(array(
        'message' => $message,
        'tempo' => $saved_time,
        'tempo_formatado' => ldvt_format_seconds($saved_time),
        'data_registro' => $saved_at,
        'data_registro_formatada' => $saved_at ? date_i18n('d/m/Y H:i', strtotime($saved_at)) : '',
        'step_completed' => $step_completed,
        'completion_pending' => $completion_pending,
        'duracao_total' => $duration,
    ));
}
