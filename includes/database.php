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
        data_registro DATETIME         NOT NULL,
        UNIQUE KEY unique_video_user ( user_id, video_id )
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
