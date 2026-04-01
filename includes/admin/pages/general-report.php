<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza a página de administração com o relatório geral.
 *
 * @return void
 */
function ldvt_admin_page()
{
    global $wpdb;

    $table = ldvt_get_tempo_video_table_name();
    $completion_threshold = ldvt_get_completion_threshold();
    $items_per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    $filter_email = isset($_GET['filtro_email']) ? sanitize_email(wp_unslash($_GET['filtro_email'])) : '';
    $filter_aula = isset($_GET['filtro_aula']) ? absint($_GET['filtro_aula']) : 0;
    $filter_lesson_name = $filter_aula ? get_the_title($filter_aula) : '';
    $filter_lesson_name = !empty($filter_lesson_name) ? $filter_lesson_name : ($filter_aula ? 'Aula #' . $filter_aula : '');
    $where_conditions = array();
    $params = array();
    $base_page_args = array(
        'page' => 'learndash-vimeo-tracker',
    );

    if (!empty($filter_email)) {
        $user = get_user_by('email', $filter_email);
        if ($user) {
            $where_conditions[] = 'user_id = %d';
            $params[] = $user->ID;
        } else {
            $where_conditions[] = '1 = 0';
        }
    }

    if (!empty($filter_aula)) {
        $where_conditions[] = 'aula_id = %d';
        $params[] = $filter_aula;
    }

    $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

    if (!empty($filter_email)) {
        $base_page_args['filtro_email'] = $filter_email;
    }

    if (!empty($filter_aula)) {
        $base_page_args['filtro_aula'] = $filter_aula;
    }

    $count_query = "SELECT COUNT(*) FROM $table" . $where_clause;
    $total_records = $wpdb->get_var(
        !empty($params)
            ? $wpdb->prepare($count_query, $params)
            : $count_query
    );

    $total_pages = (int) ceil($total_records / $items_per_page);
    $query = "SELECT * FROM $table" . $where_clause . " ORDER BY data_registro DESC LIMIT %d OFFSET %d";
    $params[] = $items_per_page;
    $params[] = $offset;
    $results = $wpdb->get_results($wpdb->prepare($query, $params));
    ?>
    <div class="wrap">
        <?php ldvt_render_admin_bootstrap_assets(); ?>
        <?php ldvt_render_admin_header('learndash-vimeo-tracker', 'dashicons-video-alt3', 'LearnDash Vimeo Tracker', 'Relatório de tempo assistido de vídeos Vimeo pelos alunos.'); ?>

        <div class="mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="learndash-vimeo-tracker">
                    <div class="col-md-4">
                        <label for="filtro_email" class="form-label fw-bold">Filtrar por Email:</label>
                        <input type="email" class="form-control" id="filtro_email" name="filtro_email"
                            value="<?php echo esc_attr($filter_email); ?>" placeholder="exemplo@email.com">
                    </div>
                    <div class="col-md-4">
                        <label for="filtro_aula_nome" class="form-label fw-bold">Filtrar por Aula:</label>
                        <input type="hidden" name="filtro_aula" value="<?php echo esc_attr($filter_aula); ?>">
                        <input type="text" class="form-control" id="filtro_aula_nome"
                            value="<?php echo esc_attr($filter_lesson_name); ?>"
                            placeholder="Clique na lupa ao lado da aula" readonly>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Filtrar
                        </button>
                    </div>
                    <?php if (!empty($filter_email) || !empty($filter_aula)): ?>
                        <div class="col-md-2">
                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'learndash-vimeo-tracker'), admin_url('admin.php'))); ?>"
                                class="btn btn-secondary w-100">
                                <span class="dashicons dashicons-dismiss" style="margin-top: 3px;"></span> Limpar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="alert alert-info">
                <strong>Nenhum registro encontrado.</strong>
                <?php echo (!empty($filter_email) || !empty($filter_aula)) ? 'Tente outros filtros ou limpe a busca.' : 'Os dados aparecerão aqui quando os alunos começarem a assistir os vídeos.'; ?>
            </div>
        <?php else: ?>
            <div class="mt-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Total de Registros: <?php echo number_format((int) $total_records, 0, ',', '.'); ?></h5>
                    <span class="badge bg-light text-dark">Página <?php echo $current_page; ?> de <?php echo $total_pages; ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Aluno</th>
                                    <th>Email</th>
                                    <th>Curso</th>
                                    <th>Aula</th>
                                    <th>Tempo Assistido</th>
                                    <th>Duração Total</th>
                                    <th>Progresso</th>
                                    <th>Conclusão</th>
                                    <th>Data Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $row): ?>
                                    <?php
                                    $user = get_userdata($row->user_id);
                                    $course_name = $row->curso_id ? get_the_title($row->curso_id) : 'N/A';
                                    $lesson_name = $row->aula_id ? get_the_title($row->aula_id) : 'N/A';
                                    $progress = ldvt_calculate_progress($row->tempo, $row->duracao_total);
                                    $progress_bar_class = ldvt_get_progress_bar_class($progress, $completion_threshold);
                                    $step_completed = ldvt_is_step_completed_in_learndash($row->user_id, $row->curso_id, $row->aula_id);
                                    $time_formatted = ldvt_format_seconds($row->tempo);
                                    $duration_formatted = ldvt_format_seconds($row->duracao_total);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($user ? $user->display_name : 'Usuário #' . $row->user_id); ?></strong>
                                            <?php if ($user && !empty($user->user_email)): ?>
                                                <a href="?page=learndash-vimeo-tracker&filtro_email=<?php echo urlencode($user->user_email); ?>"
                                                    title="Filtrar por <?php echo esc_attr($user->user_email); ?>"
                                                    class="text-decoration-none ms-2">
                                                    <span class="dashicons dashicons-search"
                                                        style="font-size: 18px; width: 18px; height: 18px; vertical-align: middle; color: #555;"></span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user): ?>
                                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" target="_blank"
                                                    title="Editar Usuário" class="text-decoration-none">
                                                    <?php echo esc_html($user->user_email); ?>
                                                    <span class="dashicons dashicons-external"
                                                        style="font-size: 12px; width: 12px; height: 12px; vertical-align: text-top; color: #777;"></span>
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $edit_link = $row->curso_id ? get_edit_post_link($row->curso_id) : '';
                                            if ($edit_link) {
                                                $edit_link = add_query_arg('currentTab', 'learndash_sfwd-courses_dashboard', $edit_link);
                                                ?>
                                                <a href="<?php echo esc_url($edit_link); ?>" target="_blank"
                                                    title="Editar Curso no LearnDash" class="text-decoration-none">
                                                    <?php echo esc_html($course_name); ?>
                                                    <span class="dashicons dashicons-external"
                                                        style="font-size: 12px; width: 12px; height: 12px; vertical-align: text-top; color: #777;"></span>
                                                </a>
                                                <?php
                                            } else {
                                                echo esc_html($course_name);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($lesson_name); ?></strong>
                                            <?php if (!empty($row->aula_id)): ?>
                                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'learndash-vimeo-tracker', 'filtro_aula' => (int) $row->aula_id), admin_url('admin.php'))); ?>"
                                                    title="Filtrar por <?php echo esc_attr($lesson_name); ?>"
                                                    class="text-decoration-none ms-2">
                                                    <span class="dashicons dashicons-search"
                                                        style="font-size: 18px; width: 18px; height: 18px; vertical-align: middle; color: #555;"></span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo esc_html($time_formatted); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo esc_html($duration_formatted); ?></span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 25px; min-width: 100px;">
                                                <div class="progress-bar <?php echo esc_attr($progress_bar_class); ?>"
                                                    role="progressbar" style="width: <?php echo esc_attr($progress); ?>%;"
                                                    aria-valuenow="<?php echo esc_attr($progress); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo esc_html($progress); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if ($step_completed): ?>
                                                <span class="dashicons dashicons-yes-alt text-success"
                                                    title="Concluída no LearnDash"
                                                    aria-label="Concluída no LearnDash"
                                                    style="font-size: 22px; width: 22px; height: 22px;"></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($row->data_registro))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="mt-4 d-flex justify-content-center">
                    <nav aria-label="Navegação de página">
                        <ul class="pagination pagination-lg">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="<?php echo esc_url(add_query_arg(array_merge($base_page_args, array('paged' => 1)), admin_url('admin.php'))); ?>">
                                        &laquo; Primeira
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="<?php echo esc_url(add_query_arg(array_merge($base_page_args, array('paged' => $current_page - 1)), admin_url('admin.php'))); ?>">
                                        &lsaquo; Anterior
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $current_page - 2);
                            $end = min($total_pages, $current_page + 2);

                            for ($page = $start; $page <= $end; $page++):
                                ?>
                                <li class="page-item <?php echo $page === $current_page ? 'active' : ''; ?>">
                                    <a class="page-link"
                                        href="<?php echo esc_url(add_query_arg(array_merge($base_page_args, array('paged' => $page)), admin_url('admin.php'))); ?>">
                                        <?php echo $page; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="<?php echo esc_url(add_query_arg(array_merge($base_page_args, array('paged' => $current_page + 1)), admin_url('admin.php'))); ?>">
                                        Próxima &rsaquo;
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="<?php echo esc_url(add_query_arg(array_merge($base_page_args, array('paged' => $total_pages)), admin_url('admin.php'))); ?>">
                                        Última &raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

            <script src="<?php echo esc_url('https://cdn.jsdelivr.net/npm/bootstrap@' . LDVT_ADMIN_BOOTSTRAP_VERSION . '/dist/js/bootstrap.min.js'); ?>"></script>
        <?php endif; ?>
    </div>
    <?php ldvt_render_admin_base_styles(array('include_table_styles' => true)); ?>
    <?php
}
