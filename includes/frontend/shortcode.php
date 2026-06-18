<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('ldvt_tempo_assistido', 'ldvt_tempo_assistido_shortcode');
add_shortcode('ldvt_tempo_registrado', 'ldvt_tempo_assistido_shortcode');

/**
 * Extrai o primeiro ID de video Vimeo encontrado em um conteudo.
 *
 * @param string $content Conteudo HTML/texto.
 *
 * @return string
 */
function ldvt_extract_vimeo_video_id($content)
{
    if (!is_string($content) || $content === '') {
        return '';
    }

    if (!preg_match('#https?://(?:player\.)?vimeo\.com/(?:video/)?([0-9A-Za-z_-]+)#i', $content, $matches)) {
        return '';
    }

    return sanitize_text_field($matches[1]);
}

/**
 * Retorna o primeiro ID de video Vimeo do post atual.
 *
 * @param int $post_id ID do post/aula.
 *
 * @return string
 */
function ldvt_get_post_vimeo_video_id($post_id)
{
    $content = get_post_field('post_content', (int) $post_id);

    return ldvt_extract_vimeo_video_id($content);
}

/**
 * Renderiza os estilos do indicador de tempo registrado.
 *
 * @return string
 */
function ldvt_get_tempo_assistido_shortcode_styles()
{
    static $styles_printed = false;

    if ($styles_printed) {
        return '';
    }

    $styles_printed = true;

    return '<style>
        .ldvt-watch-progress {
            display: flex;
            align-items: center;
            gap: 8px 12px;
            flex-wrap: wrap;
            margin: 12px 0;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
            color: #1f2937;
            font-size: 14px;
            line-height: 1.35;
        }
        .ldvt-watch-progress__label {
            font-weight: 600;
            color: #334155;
        }
        .ldvt-watch-progress__time {
            font-size: 16px;
            font-variant-numeric: tabular-nums;
            color: #0f172a;
        }
        .ldvt-watch-progress__meta {
            color: #64748b;
            font-size: 13px;
        }
        .ldvt-watch-progress.is-saving {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .ldvt-watch-progress.is-saved {
            border-color: #86efac;
            background: #f0fdf4;
        }
        .ldvt-watch-progress.is-error {
            border-color: #fca5a5;
            background: #fff1f2;
        }
        @media (max-width: 480px) {
            .ldvt-watch-progress {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>';
}

/**
 * Shortcode que exibe o tempo salvo no registro do plugin.
 *
 * Uso:
 * [ldvt_tempo_assistido]
 * [ldvt_tempo_assistido video_id="123456789"]
 *
 * @param array $atts Atributos do shortcode.
 *
 * @return string
 */
function ldvt_tempo_assistido_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'video_id' => '',
        ),
        $atts,
        'ldvt_tempo_assistido'
    );

    $video_id = sanitize_text_field($atts['video_id']);

    if (!$video_id && is_singular()) {
        $video_id = ldvt_get_post_vimeo_video_id(get_the_ID());
    }

    $saved_time = 0;
    $saved_time_formatted = ldvt_format_seconds(0);
    $meta = is_user_logged_in() ? 'Ainda não salvo' : 'Faça login para registrar seu progresso';

    if (is_user_logged_in() && $video_id) {
        $record = ldvt_get_tempo_video_record(get_current_user_id(), $video_id);

        if ($record) {
            $saved_time = (int) $record->tempo;
            $saved_time_formatted = ldvt_format_seconds($saved_time);
            $meta = 'Salvo em ' . date_i18n('d/m/Y H:i', strtotime($record->data_registro));
        }
    } elseif (is_user_logged_in()) {
        $meta = 'Aguardando identificação do vídeo';
    }

    return ldvt_get_tempo_assistido_shortcode_styles()
        . '<div class="ldvt-watch-progress" data-video-id="' . esc_attr($video_id) . '" data-saved-time="' . esc_attr($saved_time) . '" aria-live="polite">'
        . '<span class="ldvt-watch-progress__label">Tempo registrado</span>'
        . '<strong class="ldvt-watch-progress__time">' . esc_html($saved_time_formatted) . '</strong>'
        . '<span class="ldvt-watch-progress__meta">' . esc_html($meta) . '</span>'
        . '</div>';
}
