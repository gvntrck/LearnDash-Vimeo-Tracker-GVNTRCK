<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza a página de Progresso por Curso.
 *
 * @return void
 */
function ldvt_admin_page_progresso_curso()
{
    if (!function_exists('learndash_get_course_id')) {
        ?>
        <div class="wrap">
            <?php ldvt_render_admin_bootstrap_assets(); ?>
            <?php ldvt_render_admin_header('learndash-vimeo-tracker-curso', 'dashicons-chart-bar', 'Progresso por Curso', 'Visualize o progresso detalhado de vídeos por aluno e curso.'); ?>
            <div class="alert alert-warning">
                <strong>LearnDash não detectado!</strong> Este recurso requer o plugin LearnDash ativo.
            </div>
        </div>
        <?php ldvt_render_admin_base_styles(array('card_full_width' => true, 'include_table_styles' => true)); ?>
        <?php
        return;
    }

    $filter_email = isset($_GET['filtro_email']) ? sanitize_email($_GET['filtro_email']) : '';
    $filter_course = isset($_GET['filtro_curso']) ? (int) $_GET['filtro_curso'] : 0;
    $user = !empty($filter_email) ? get_user_by('email', $filter_email) : null;
    $courses_args = array(
        'post_type' => 'sfwd-courses',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    );

    if ($user) {
        $user_courses = learndash_user_get_enrolled_courses($user->ID);
        $courses = !empty($user_courses)
            ? get_posts(array_merge($courses_args, array('post__in' => $user_courses)))
            : array();
    } else {
        $courses = get_posts($courses_args);
    }
    ?>
    <div class="wrap">
        <?php ldvt_render_admin_bootstrap_assets(); ?>
        <?php ldvt_render_admin_header('learndash-vimeo-tracker-curso', 'dashicons-chart-bar', 'Progresso por Curso', 'Visualize o progresso detalhado de vídeos por aluno e curso.'); ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="learndash-vimeo-tracker-curso">

                    <div class="col-md-4">
                        <label for="filtro_email" class="form-label fw-bold">Email do Aluno:</label>
                        <input type="email" class="form-control" id="filtro_email" name="filtro_email"
                            value="<?php echo esc_attr($filter_email); ?>" placeholder="exemplo@email.com" required>
                    </div>

                    <div class="col-md-4">
                        <label for="filtro_curso" class="form-label fw-bold">Curso:</label>
                        <select class="form-select" id="filtro_curso" name="filtro_curso" required>
                            <option value="">Selecione um curso</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course->ID; ?>" <?php selected($filter_course, $course->ID); ?>>
                                    <?php echo esc_html($course->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Buscar
                        </button>
                    </div>

                    <?php if (!empty($filter_email) || !empty($filter_course)): ?>
                        <div class="col-md-2">
                            <a href="?page=learndash-vimeo-tracker-curso" class="btn btn-secondary w-100">
                                <span class="dashicons dashicons-dismiss" style="margin-top: 3px;"></span> Limpar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!empty($filter_email) && !empty($filter_course)): ?>
            <?php if (!$user): ?>
                <div class="alert alert-danger">
                    <strong><span class="dashicons dashicons-warning"></span> Email não encontrado!</strong><br>
                    O email <strong><?php echo esc_html($filter_email); ?></strong> não está cadastrado no sistema.
                    <br><br>
                    <small>Verifique se o email está correto ou se o usuário está cadastrado no WordPress.</small>
                </div>
            <?php else: ?>
                <?php ldvt_exibir_relatorio_progresso($user, $filter_course, ldvt_get_tempo_video_table_name()); ?>
            <?php endif; ?>
        <?php elseif (!empty($filter_email) || !empty($filter_course)): ?>
            <div class="alert alert-info">
                <strong><span class="dashicons dashicons-info"></span> Atenção!</strong>
                Por favor, preencha ambos os filtros (Email e Curso) para visualizar o relatório.
            </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const emailInput = document.getElementById('filtro_email');
                const cursoSelect = document.getElementById('filtro_curso');
                let typingTimer;
                const doneTypingInterval = 800;

                if (!emailInput || !cursoSelect) return;

                const fetchCourses = () => {
                    const email = emailInput.value;

                    if (!email) return;

                    const originalOptions = cursoSelect.innerHTML;
                    cursoSelect.innerHTML = '<option value="">Carregando cursos...</option>';
                    cursoSelect.disabled = true;

                    const formData = new FormData();
                    formData.append('action', 'ldvt_get_user_courses');
                    formData.append('email', email);

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(response => {
                            cursoSelect.disabled = false;

                            if (response.success) {
                                const cursos = response.data;
                                cursoSelect.innerHTML = '<option value="">Selecione um curso</option>';

                                if (cursos.length === 0) {
                                    const option = document.createElement('option');
                                    option.text = 'Nenhum curso encontrado para este aluno';
                                    option.disabled = true;
                                    cursoSelect.add(option);
                                    return;
                                }

                                cursos.forEach(curso => {
                                    const option = document.createElement('option');
                                    option.value = curso.id;
                                    option.text = curso.title;
                                    cursoSelect.add(option);
                                });
                            } else {
                                cursoSelect.innerHTML = '<option value="">Usuário não encontrado ou sem cursos</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            cursoSelect.disabled = false;
                            cursoSelect.innerHTML = originalOptions;
                        });
                };

                emailInput.addEventListener('blur', fetchCourses);

                emailInput.addEventListener('keyup', () => {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(fetchCourses, doneTypingInterval);
                });

                emailInput.addEventListener('keydown', () => {
                    clearTimeout(typingTimer);
                });
            });
        </script>

        <script src="<?php echo esc_url('https://cdn.jsdelivr.net/npm/bootstrap@' . LDVT_ADMIN_BOOTSTRAP_VERSION . '/dist/js/bootstrap.min.js'); ?>"></script>
    </div>
    <?php ldvt_render_admin_base_styles(array('card_full_width' => true, 'include_table_styles' => true)); ?>
    <?php
}

/**
 * Exibe o relatório de progresso detalhado.
 *
 * @param WP_User $user     Usuário selecionado.
 * @param int     $curso_id ID do curso.
 * @param string  $table    Nome da tabela.
 *
 * @return void
 */
function ldvt_exibir_relatorio_progresso($user, $curso_id, $table)
{
    global $wpdb;

    $completion_threshold = ldvt_get_completion_threshold();
    $completion_label = ldvt_get_completion_threshold_label();
    $course = get_post($curso_id);

    if (!$course) {
        echo '<div class="alert alert-danger">Curso não encontrado.</div>';
        return;
    }

    $lessons = learndash_get_lesson_list($curso_id);

    if (empty($lessons)) {
        echo '<div class="alert alert-warning">Este curso não possui aulas cadastradas.</div>';
        return;
    }

    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND curso_id = %d",
        $user->ID,
        $curso_id
    ), OBJECT_K);

    $all_user_records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user->ID
    ));

    $total_lessons = count($lessons);
    $lessons_with_video = 0;
    $completed_lessons = 0;
    $in_progress_lessons = 0;
    $not_started_lessons = 0;
    $total_progress = 0;
    ?>
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <span class="dashicons dashicons-admin-users" style="font-size: 24px; vertical-align: middle;"></span>
                <?php echo esc_html($user->display_name); ?> - <?php echo esc_html($user->user_email); ?>
            </h4>
        </div>
        <div class="card-body">
            <h5 class="card-title">
                <span class="dashicons dashicons-book" style="vertical-align: middle;"></span>
                <?php echo esc_html($course->post_title); ?>
            </h5>
            <p class="text-muted mb-0">Total de Aulas: <?php echo $total_lessons; ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Aula</th>
                            <th>Status</th>
                            <th>Tempo Assistido</th>
                            <th>Duração Total</th>
                            <th>Progresso</th>
                            <th>Última Visualização</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <?php
                            $lesson_id = $lesson->ID;
                            $lesson_title = $lesson->post_title;
                            $record = null;

                            foreach ($records as $current_record) {
                                if ((int) $current_record->aula_id === (int) $lesson_id) {
                                    $record = $current_record;
                                    break;
                                }
                            }

                            if ($record) {
                                $lessons_with_video++;
                                $progress = ldvt_calculate_progress($record->tempo, $record->duracao_total);
                                $total_progress += $progress;

                                if ($progress >= $completion_threshold) {
                                    $completed_lessons++;
                                    $status = 'Completo';
                                    $badge_class = 'bg-success';
                                    $icon = 'yes-alt';
                                } else {
                                    $in_progress_lessons++;
                                    $status = 'Em Andamento';
                                    $badge_class = 'bg-warning';
                                    $icon = 'update';
                                }

                                $time_formatted = ldvt_format_seconds($record->tempo);
                                $duration_formatted = ldvt_format_seconds($record->duracao_total);
                                $date_formatted = date_i18n('d/m/Y H:i', strtotime($record->data_registro));
                            } else {
                                $not_started_lessons++;
                                $progress = 0;
                                $status = 'Não Iniciado';
                                $badge_class = 'bg-secondary';
                                $icon = 'minus';
                                $time_formatted = '00:00:00';
                                $duration_formatted = 'N/A';
                                $date_formatted = '-';
                            }

                            $progress_bar_class = ldvt_get_progress_bar_class($progress, $completion_threshold);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($lesson_title); ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?php echo esc_attr($badge_class); ?>">
                                        <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"
                                            style="font-size: 12px; vertical-align: middle;"></span>
                                        <?php echo esc_html($status); ?>
                                    </span>
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
                                <td><?php echo esc_html($date_formatted); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <span class="dashicons dashicons-chart-pie" style="font-size: 20px; vertical-align: middle;"></span>
                Resumo Geral do Progresso
            </h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded">
                        <h2 class="text-primary mb-0"><?php echo $total_lessons; ?></h2>
                        <small class="text-muted">Total de Aulas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 rounded">
                        <h2 class="text-success mb-0"><?php echo $completed_lessons; ?></h2>
                        <small class="text-muted">Completas (≥<?php echo esc_html($completion_label); ?>%)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-warning bg-opacity-10 rounded">
                        <h2 class="text-warning mb-0"><?php echo $in_progress_lessons; ?></h2>
                        <small class="text-muted">Em Andamento</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-secondary bg-opacity-10 rounded">
                        <h2 class="text-secondary mb-0"><?php echo $not_started_lessons; ?></h2>
                        <small class="text-muted">Não Iniciadas</small>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h6>Progresso Médio de Todas as Aulas:</h6>
                    <?php $average_progress = $total_lessons > 0 ? round($total_progress / $total_lessons, 1) : 0; ?>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar <?php echo esc_attr(ldvt_get_progress_bar_class($average_progress, $completion_threshold)); ?>"
                            role="progressbar" style="width: <?php echo esc_attr($average_progress); ?>%;"
                            aria-valuenow="<?php echo esc_attr($average_progress); ?>" aria-valuemin="0" aria-valuemax="100">
                            <strong><?php echo esc_html($average_progress); ?>%</strong>
                        </div>
                    </div>
                    <small class="text-muted">
                        Média considerando todas as <?php echo $total_lessons; ?> aulas (inclusive não iniciadas)
                    </small>
                </div>

                <div class="col-md-6">
                    <h6>Taxa de Conclusão (Aulas ≥<?php echo esc_html($completion_label); ?>%):</h6>
                    <?php $completion_rate = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100, 1) : 0; ?>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar <?php echo esc_attr(ldvt_get_progress_bar_class($completion_rate, $completion_threshold, 50, 'bg-success', 'bg-info', 'bg-danger')); ?>"
                            role="progressbar" style="width: <?php echo esc_attr($completion_rate); ?>%;"
                            aria-valuenow="<?php echo esc_attr($completion_rate); ?>" aria-valuemin="0" aria-valuemax="100">
                            <strong><?php echo esc_html($completion_rate); ?>%</strong>
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php echo $completed_lessons; ?> de <?php echo $total_lessons; ?> aulas completas
                    </small>
                </div>
            </div>
        </div>

        <?php if (empty($records) && !empty($all_user_records)): ?>
            <?php
            $courses_with_records = array();
            foreach ($all_user_records as $current_record) {
                if ($current_record->curso_id > 0 && !in_array($current_record->curso_id, $courses_with_records, true)) {
                    $courses_with_records[] = $current_record->curso_id;
                }
            }
            ?>

            <?php if (!empty($courses_with_records)): ?>
                <div class="alert alert-info mt-4">
                    <h6><span class="dashicons dashicons-info"></span> Informação Importante</h6>
                    <p class="mb-2">
                        <strong>Este aluno não possui registros de vídeos neste curso específico.</strong>
                    </p>
                    <p class="mb-2">
                        Porém, encontramos <strong><?php echo count($all_user_records); ?> registro(s)</strong>
                        de vídeos assistidos em <strong><?php echo count($courses_with_records); ?> outro(s) curso(s)</strong>.
                    </p>
                    <hr>
                    <p class="mb-0">
                        <strong>Possíveis causas:</strong>
                    </p>
                    <ul class="mb-0">
                        <li>O aluno assistiu vídeos em outro(s) curso(s)</li>
                        <li>O <code>curso_id</code> não foi salvo corretamente no banco de dados</li>
                        <li>O vídeo foi assistido antes de associar a aula ao curso</li>
                    </ul>
                    <hr>
                    <p class="mb-0">
                        <strong>Dica:</strong> Verifique o "Relatório Geral" filtrando por este email para ver todos os registros.
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
