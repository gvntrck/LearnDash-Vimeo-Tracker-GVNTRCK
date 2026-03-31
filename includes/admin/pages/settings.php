<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza a página de ajustes.
 *
 * @return void
 */
function ldvt_admin_page_settings()
{
    $settings = ldvt_get_settings();
    ?>
    <div class="wrap">
        <?php ldvt_render_admin_bootstrap_assets(); ?>
        <?php ldvt_render_admin_header('learndash-vimeo-tracker-ajustes', 'dashicons-admin-generic', 'Ajustes', 'Configure a conclusão automática de aulas no LearnDash por site.'); ?>
        <?php settings_errors(); ?>

        <div class="card">
            <div class="card-body">
                <form method="post" action="options.php">
                    <?php settings_fields('ldvt_settings_group'); ?>

                    <div class="mb-4">
                        <label class="form-label fw-bold d-block">Conclusão Automática no LearnDash</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ldvt_auto_complete_enabled"
                                name="<?php echo esc_attr(LDVT_SETTINGS_OPTION); ?>[auto_complete_enabled]" value="1"
                                <?php checked(!empty($settings['auto_complete_enabled'])); ?>>
                            <label class="form-check-label" for="ldvt_auto_complete_enabled">
                                Ativar marcação automática de aulas/tópicos concluídos
                            </label>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Quando ativado, o plugin tenta marcar automaticamente a etapa como concluída no LearnDash assim
                            que o percentual mínimo for atingido.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="ldvt_completion_threshold" class="form-label fw-bold">Porcentagem mínima para conclusão</label>
                        <div class="input-group" style="max-width: 220px;">
                            <input type="number" class="form-control" id="ldvt_completion_threshold"
                                name="<?php echo esc_attr(LDVT_SETTINGS_OPTION); ?>[completion_threshold]"
                                value="<?php echo esc_attr($settings['completion_threshold']); ?>" min="1" max="100"
                                step="0.1">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Exemplo: use <strong>70</strong> para concluir ao atingir 70% do vídeo assistido.
                        </small>
                    </div>

                    <?php submit_button('Salvar Ajustes'); ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('ldvt_auto_complete_enabled');
            const threshold = document.getElementById('ldvt_completion_threshold');
            const thresholdGroup = threshold ? threshold.closest('.input-group') : null;

            if (!toggle || !threshold) return;

            const syncState = () => {
                if (thresholdGroup) {
                    thresholdGroup.style.opacity = toggle.checked ? '1' : '0.65';
                }
            };

            toggle.addEventListener('change', syncState);
            syncState();
        });
    </script>

    <?php ldvt_render_admin_base_styles(array('card_max_width' => '900px')); ?>
    <?php
}
