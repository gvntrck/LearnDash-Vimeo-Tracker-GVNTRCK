<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna o nome da tabela de vídeos.
 *
 * @return string
 */
function ldvt_get_tempo_video_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'tempo_video';
}

/**
 * Verifica se a tabela de rastreamento existe.
 *
 * @return bool
 */
function ldvt_tempo_video_table_exists()
{
    global $wpdb;

    $table = ldvt_get_tempo_video_table_name();
    $table_like = $wpdb->esc_like($table);

    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_like)) === $table;
}

/**
 * Verifica se a tabela ja possui a coluna de intervalos assistidos.
 *
 * @return bool
 */
function ldvt_tempo_video_has_watched_intervals_column()
{
    global $wpdb;

    if (!ldvt_tempo_video_table_exists()) {
        return false;
    }

    $table = ldvt_get_tempo_video_table_name();

    return (bool) $wpdb->get_var(
        $wpdb->prepare(
            "SHOW COLUMNS FROM $table LIKE %s",
            'watched_intervals'
        )
    );
}

/**
 * Retorna o registro de progresso de um usuario para um video.
 *
 * @param int    $user_id  ID do usuario.
 * @param string $video_id ID do video no Vimeo.
 *
 * @return object|null
 */
function ldvt_get_tempo_video_record($user_id, $video_id)
{
    global $wpdb;

    $user_id = (int) $user_id;
    $video_id = sanitize_text_field($video_id);

    if (!$user_id || !$video_id || !ldvt_tempo_video_table_exists()) {
        return null;
    }

    $table = ldvt_get_tempo_video_table_name();
    $watched_intervals_select = ldvt_tempo_video_has_watched_intervals_column()
        ? 'watched_intervals'
        : "'' AS watched_intervals";

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT tempo, curso_id, aula_id, duracao_total, $watched_intervals_select, data_registro
             FROM $table
             WHERE user_id = %d AND video_id = %s
             LIMIT 1",
            $user_id,
            $video_id
        )
    );
}

/**
 * Cria a tabela de rastreamento de tempo de vídeo se não existir.
 *
 * @return void
 */
function ldvt_criar_tabela_tempo_video()
{
    global $wpdb;

    $table = ldvt_get_tempo_video_table_name();
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        user_id       BIGINT UNSIGNED NOT NULL,
        video_id      VARCHAR(50)      NOT NULL,
        tempo         INT              NOT NULL,
        curso_id      BIGINT DEFAULT 0,
        aula_id       BIGINT DEFAULT 0,
        duracao_total INT DEFAULT 0,
        watched_intervals LONGTEXT NULL,
        data_registro DATETIME         NOT NULL,
        UNIQUE KEY unique_video_user ( user_id, video_id )
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
