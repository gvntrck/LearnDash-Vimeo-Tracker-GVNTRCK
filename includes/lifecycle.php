<?php

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(LDVT_PLUGIN_FILE, 'ldvt_plugin_activate');
register_deactivation_hook(LDVT_PLUGIN_FILE, 'ldvt_plugin_deactivate');

/**
 * Executa na ativação do plugin.
 *
 * @return void
 */
function ldvt_plugin_activate()
{
    ldvt_criar_tabela_tempo_video();

    if (false === get_option(LDVT_SETTINGS_OPTION, false)) {
        add_option(LDVT_SETTINGS_OPTION, ldvt_get_default_settings());
    }

    flush_rewrite_rules();
}

/**
 * Executa na desativação do plugin.
 *
 * @return void
 */
function ldvt_plugin_deactivate()
{
    flush_rewrite_rules();
}
