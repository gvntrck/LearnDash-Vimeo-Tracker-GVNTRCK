<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verifica se o progresso atingiu o percentual mínimo de conclusão.
 *
 * @param int $tempo         Tempo assistido.
 * @param int $duracao_total Duração total do vídeo.
 *
 * @return bool
 */
function ldvt_has_completion_progress($tempo, $duracao_total)
{
    return ldvt_calculate_progress($tempo, $duracao_total) >= ldvt_get_completion_threshold();
}

/**
 * Marca a etapa atual como concluída no LearnDash quando o limiar é atingido.
 *
 * @param int $user_id       ID do usuário.
 * @param int $course_id     ID do curso.
 * @param int $step_id       ID da etapa.
 * @param int $tempo         Tempo assistido.
 * @param int $duracao_total Duração total do vídeo.
 *
 * @return bool
 */
function ldvt_maybe_mark_step_complete($user_id, $course_id, $step_id, $tempo, $duracao_total)
{
    if (!ldvt_is_auto_completion_enabled() || !$user_id || !$step_id || !ldvt_has_completion_progress($tempo, $duracao_total)) {
        return false;
    }

    if (!function_exists('learndash_process_mark_complete') || !function_exists('learndash_user_progress_is_step_complete')) {
        return false;
    }

    if (!$course_id && function_exists('learndash_get_course_id')) {
        $course_id = (int) learndash_get_course_id($step_id);
    }

    if (!$course_id) {
        return false;
    }

    $step_type = get_post_type($step_id);
    if (!in_array($step_type, array('sfwd-lessons', 'sfwd-topic'), true)) {
        return false;
    }

    if (learndash_user_progress_is_step_complete($user_id, $course_id, $step_id)) {
        return true;
    }

    if (function_exists('learndash_can_complete_step') && !learndash_can_complete_step($user_id, $step_id, $course_id)) {
        return false;
    }

    return (bool) learndash_process_mark_complete($user_id, $step_id, false, $course_id, false);
}

/**
 * Verifica no LearnDash se uma etapa já está concluída para um usuário.
 *
 * @param int $user_id   ID do usuário.
 * @param int $course_id ID do curso.
 * @param int $step_id   ID da aula/tópico.
 *
 * @return bool
 */
function ldvt_is_step_completed_in_learndash($user_id, $course_id, $step_id)
{
    static $completion_cache = array();

    $user_id = (int) $user_id;
    $course_id = (int) $course_id;
    $step_id = (int) $step_id;

    if (!$user_id || !$step_id || !function_exists('learndash_user_progress_is_step_complete')) {
        return false;
    }

    if (!$course_id && function_exists('learndash_get_course_id')) {
        $course_id = (int) learndash_get_course_id($step_id);
    }

    if (!$course_id) {
        return false;
    }

    $step_type = get_post_type($step_id);
    if (!in_array($step_type, array('sfwd-lessons', 'sfwd-topic'), true)) {
        return false;
    }

    $cache_key = $user_id . ':' . $course_id . ':' . $step_id;

    if (!array_key_exists($cache_key, $completion_cache)) {
        $completion_cache[$cache_key] = (bool) learndash_user_progress_is_step_complete($user_id, $course_id, $step_id);
    }

    return $completion_cache[$cache_key];
}
