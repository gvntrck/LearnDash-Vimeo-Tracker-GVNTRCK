<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna as configurações padrão do plugin.
 *
 * @return array
 */
function ldvt_get_default_settings()
{
    return array(
        'auto_complete_enabled' => 1,
        'completion_threshold' => 70,
    );
}

/**
 * Sanitiza o percentual de conclusão.
 *
 * @param mixed $value Valor informado.
 *
 * @return float
 */
function ldvt_sanitize_completion_threshold($value)
{
    $defaults = ldvt_get_default_settings();
    $value = is_numeric($value) ? (float) $value : (float) $defaults['completion_threshold'];

    return max(1, min(100, $value));
}

/**
 * Sanitiza as configurações do plugin.
 *
 * @param array $input Dados enviados pelo formulário.
 *
 * @return array
 */
function ldvt_sanitize_settings($input)
{
    $defaults = ldvt_get_default_settings();
    $input = is_array($input) ? $input : array();

    return array(
        'auto_complete_enabled' => !empty($input['auto_complete_enabled']) ? 1 : 0,
        'completion_threshold' => ldvt_sanitize_completion_threshold($input['completion_threshold'] ?? $defaults['completion_threshold']),
    );
}

/**
 * Retorna as configurações atuais do plugin.
 *
 * @return array
 */
function ldvt_get_settings()
{
    $defaults = ldvt_get_default_settings();
    $settings = get_option(LDVT_SETTINGS_OPTION, array());
    $settings = is_array($settings) ? $settings : array();

    return ldvt_sanitize_settings(wp_parse_args($settings, $defaults));
}

/**
 * Verifica se a conclusão automática está habilitada.
 *
 * @return bool
 */
function ldvt_is_auto_completion_enabled()
{
    $settings = ldvt_get_settings();

    return !empty($settings['auto_complete_enabled']);
}

/**
 * Retorna o percentual mínimo para considerar a aula concluída.
 *
 * @return float
 */
function ldvt_get_completion_threshold()
{
    $settings = ldvt_get_settings();
    $threshold = apply_filters('ldvt_completion_threshold', $settings['completion_threshold'], $settings);

    return ldvt_sanitize_completion_threshold($threshold);
}

/**
 * Retorna o percentual mínimo formatado para exibição.
 *
 * @return string
 */
function ldvt_get_completion_threshold_label()
{
    $threshold = ldvt_get_completion_threshold();
    $decimals = floor($threshold) === $threshold ? 0 : 1;

    return number_format_i18n($threshold, $decimals);
}
