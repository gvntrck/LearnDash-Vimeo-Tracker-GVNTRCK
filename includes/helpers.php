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
 * Normaliza e mescla intervalos assistidos.
 *
 * @param array $intervals      Lista de intervalos com start/end.
 * @param int   $total_duration Duracao total do video.
 *
 * @return array
 */
function ldvt_normalize_watched_intervals($intervals, $total_duration = 0)
{
    $total_duration = (int) $total_duration;
    $normalized = array();

    if (!is_array($intervals)) {
        return array();
    }

    foreach ($intervals as $interval) {
        if (is_object($interval)) {
            $interval = (array) $interval;
        }

        if (!is_array($interval) || !isset($interval['start'], $interval['end'])) {
            continue;
        }

        $start = max(0, (float) $interval['start']);
        $end = max(0, (float) $interval['end']);

        if ($total_duration > 0) {
            $start = min($start, $total_duration);
            $end = min($end, $total_duration);
        }

        if ($start >= $end) {
            continue;
        }

        $normalized[] = array(
            'start' => round($start, 3),
            'end' => round($end, 3),
        );
    }

    if (empty($normalized)) {
        return array();
    }

    usort($normalized, function ($first, $second) {
        return $first['start'] <=> $second['start'];
    });

    $merged = array();
    $current = array_shift($normalized);

    foreach ($normalized as $next) {
        if ($current['end'] >= $next['start']) {
            $current['end'] = max($current['end'], $next['end']);
            continue;
        }

        $merged[] = $current;
        $current = $next;
    }

    $merged[] = $current;

    return $merged;
}

/**
 * Decodifica intervalos assistidos enviados pelo navegador.
 *
 * @param string $json           JSON de intervalos.
 * @param int    $total_duration Duracao total do video.
 *
 * @return array
 */
function ldvt_parse_watched_intervals_json($json, $total_duration = 0)
{
    if (!is_string($json) || $json === '') {
        return array();
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        return array();
    }

    return ldvt_normalize_watched_intervals($decoded, $total_duration);
}

/**
 * Cria um intervalo legado a partir do total antigo salvo.
 *
 * @param int $watched_time    Tempo salvo.
 * @param int $total_duration  Duracao total do video.
 *
 * @return array
 */
function ldvt_get_legacy_watched_interval($watched_time, $total_duration = 0)
{
    $watched_time = (int) $watched_time;
    $total_duration = (int) $total_duration;

    if ($watched_time <= 0) {
        return array();
    }

    if ($total_duration > 0) {
        $watched_time = min($watched_time, $total_duration);
    }

    return array(
        array(
            'start' => 0,
            'end' => $watched_time,
        ),
    );
}

/**
 * Retorna intervalos salvos em um registro do plugin.
 *
 * @param object|null $record Registro do banco.
 *
 * @return array
 */
function ldvt_get_watched_intervals_from_record($record)
{
    if (!$record) {
        return array();
    }

    $total_duration = isset($record->duracao_total) ? (int) $record->duracao_total : 0;

    if (!empty($record->watched_intervals)) {
        $intervals = ldvt_parse_watched_intervals_json($record->watched_intervals, $total_duration);

        if (!empty($intervals)) {
            return $intervals;
        }
    }

    return ldvt_get_legacy_watched_interval((int) $record->tempo, $total_duration);
}

/**
 * Soma o tempo unico assistido nos intervalos.
 *
 * @param array $intervals Lista de intervalos.
 *
 * @return int
 */
function ldvt_calculate_watched_time_from_intervals($intervals)
{
    $total = 0;

    foreach (ldvt_normalize_watched_intervals($intervals) as $interval) {
        $total += $interval['end'] - $interval['start'];
    }

    return (int) round($total);
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
