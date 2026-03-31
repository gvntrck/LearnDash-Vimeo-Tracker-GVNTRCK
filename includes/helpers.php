<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna a versão do plugin.
 *
 * @return string
 */
function ldvt_get_version()
{
    return LDVT_VERSION;
}

/**
 * Verifica se o LearnDash está ativo.
 *
 * @return bool
 */
function ldvt_is_learndash_active()
{
    return function_exists('learndash_get_course_id');
}

/**
 * Formata segundos como H:i:s.
 *
 * @param int $seconds Quantidade de segundos.
 *
 * @return string
 */
function ldvt_format_seconds($seconds)
{
    return gmdate('H:i:s', max(0, (int) $seconds));
}

/**
 * Calcula o percentual de progresso de um vídeo.
 *
 * @param int $watched_time    Tempo assistido.
 * @param int $total_duration  Duração total do vídeo.
 *
 * @return float
 */
function ldvt_calculate_progress($watched_time, $total_duration)
{
    $watched_time = (int) $watched_time;
    $total_duration = (int) $total_duration;

    if ($watched_time <= 0 || $total_duration <= 0) {
        return 0;
    }

    return round(($watched_time / $total_duration) * 100, 1);
}

/**
 * Retorna a classe CSS da barra de progresso.
 *
 * @param float  $progress             Progresso atual.
 * @param float  $completion_threshold Limite de conclusão.
 * @param float  $midpoint             Faixa intermediária.
 * @param string $success_class        Classe de sucesso.
 * @param string $mid_class            Classe intermediária.
 * @param string $low_class            Classe de alerta.
 *
 * @return string
 */
function ldvt_get_progress_bar_class($progress, $completion_threshold, $midpoint = 50, $success_class = 'bg-success', $mid_class = 'bg-warning', $low_class = 'bg-danger')
{
    if ($progress >= $completion_threshold) {
        return $success_class;
    }

    if ($progress >= $midpoint) {
        return $mid_class;
    }

    return $low_class;
}
