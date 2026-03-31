<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ldvt_add_admin_menu');
add_action('admin_init', 'ldvt_register_settings');

/**
 * Adiciona menu de administração do plugin.
 *
 * @return void
 */
function ldvt_add_admin_menu()
{
    add_menu_page(
        'Vimeo Tracker',
        'Vimeo Tracker',
        'manage_options',
        'learndash-vimeo-tracker',
        'ldvt_admin_page',
        'dashicons-video-alt3',
        30
    );

    add_submenu_page(
        'learndash-vimeo-tracker',
        'Relatório Geral',
        'Relatório Geral',
        'manage_options',
        'learndash-vimeo-tracker',
        'ldvt_admin_page'
    );

    add_submenu_page(
        'learndash-vimeo-tracker',
        'Progresso por Curso',
        'Progresso por Curso',
        'manage_options',
        'learndash-vimeo-tracker-curso',
        'ldvt_admin_page_progresso_curso'
    );

    add_submenu_page(
        'learndash-vimeo-tracker',
        'Ajustes',
        'Ajustes',
        'manage_options',
        'learndash-vimeo-tracker-ajustes',
        'ldvt_admin_page_settings'
    );
}
