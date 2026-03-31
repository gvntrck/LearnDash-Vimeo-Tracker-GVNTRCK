<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra as configurações do plugin.
 *
 * @return void
 */
function ldvt_register_settings()
{
    register_setting(
        'ldvt_settings_group',
        LDVT_SETTINGS_OPTION,
        'ldvt_sanitize_settings'
    );
}

/**
 * Renderiza o cabeçalho com abas do admin.
 *
 * @param string $current_page Página atual.
 * @param string $icon         Ícone dashicon.
 * @param string $title        Título da página.
 * @param string $description  Descrição da página.
 *
 * @return void
 */
function ldvt_render_admin_header($current_page, $icon, $title, $description)
{
    $tabs = array(
        'learndash-vimeo-tracker' => 'Relatório Geral',
        'learndash-vimeo-tracker-curso' => 'Progresso por Curso',
        'learndash-vimeo-tracker-ajustes' => 'Ajustes',
    );
    ?>
    <h1>
        <span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size: 30px; margin-right: 10px;"></span>
        <?php echo esc_html($title); ?>
    </h1>
    <p><?php echo esc_html($description); ?></p>
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
        <?php foreach ($tabs as $slug => $label): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $slug)); ?>"
                class="nav-tab <?php echo $slug === $current_page ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * Carrega o CSS do Bootstrap usado nas telas do admin.
 *
 * @return void
 */
function ldvt_render_admin_bootstrap_assets()
{
    ?>
    <link href="<?php echo esc_url('https://cdn.jsdelivr.net/npm/bootstrap@' . LDVT_ADMIN_BOOTSTRAP_VERSION . '/dist/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <?php
}

/**
 * Renderiza estilos base compartilhados pelas telas administrativas.
 *
 * @param array $args Ajustes de layout.
 *
 * @return void
 */
function ldvt_render_admin_base_styles($args = array())
{
    $args = wp_parse_args($args, array(
        'card_max_width' => '',
        'card_full_width' => false,
        'include_table_styles' => false,
    ));
    ?>
    <style>
        #wpbody-content .wrap {
            max-width: 100% !important;
            width: 100% !important;
            background: #fff;
            padding: 20px;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        #wpbody-content {
            padding-right: 20px;
        }

        <?php if ($args['include_table_styles']): ?>
        .table {
            font-size: 14px;
        }

        .table th {
            font-weight: 600;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }
        <?php endif; ?>

        <?php if (!empty($args['card_max_width'])): ?>
        .card {
            max-width: <?php echo esc_html($args['card_max_width']); ?>;
        }
        <?php endif; ?>

        <?php if (!empty($args['card_full_width'])): ?>
        .card {
            width: 100% !important;
            max-width: 100% !important;
        }
        <?php endif; ?>
    </style>
    <?php
}
