<?php
/**
 * Plugin Name: LearnDash Vimeo Tracker GVNTRCK
 * Plugin URI: https://github.com/gvntrck/LearnDash-Vimeo-Tracker-GVNTRCK
 * Description: Rastreia o tempo de visualização de vídeos Vimeo em cursos LearnDash, salvando o progresso do aluno no banco de dados.
 * Version: 1.7.4
 * Author: GVNTRCK
 * Author URI: https://github.com/gvntrck
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learndash-vimeo-tracker-gvntrck
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Previne acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do plugin
define( 'LDVT_VERSION', '1.7.4' );
define( 'LDVT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDVT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LDVT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// === HOOKS DE ATIVAÇÃO E DESATIVAÇÃO ===

register_activation_hook( __FILE__, 'ldvt_plugin_activate' );
register_deactivation_hook( __FILE__, 'ldvt_plugin_deactivate' );

/**
 * Executa na ativação do plugin
 */
function ldvt_plugin_activate() {
    ldvt_criar_tabela_tempo_video();
    flush_rewrite_rules();
}

/**
 * Executa na desativação do plugin
 */
function ldvt_plugin_deactivate() {
    flush_rewrite_rules();
}

// === REGISTRO DO TEMPO DE VÍDEO ASSISTIDO VIMEO + LEARNDASH ===

add_action( 'wp_footer', 'ldvt_vimeo_tracking_script' );

/**
 * Injeta o script de rastreamento do Vimeo no footer
 */
function ldvt_vimeo_tracking_script() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $curso_id = function_exists( 'learndash_get_course_id' ) ? learndash_get_course_id( get_the_ID() ) : 0;
    $aula_id  = get_the_ID();
    ?>
    <script src="https://player.vimeo.com/api/player.js"></script>
    <script>
    ( () => {
        const AJAX_URL = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
        const CURSO_ID = <?php echo intval( $curso_id ); ?>;
        const AULA_ID  = <?php echo intval( $aula_id ); ?>;

        document.addEventListener( 'DOMContentLoaded', () => {
            const iframe = document.querySelector( 'iframe[src*="vimeo.com/video"]' );
            if ( ! iframe ) return;

            const player = new Vimeo.Player( iframe );
            
            // Rastreamento de intervalos assistidos
            let watchedIntervals = []; // Array de {start, end} representando intervalos assistidos
            let lastTime = 0;
            let currentPlaybackRate = 1.0;
            let lastSent = 0;
            let sending = false;
            let videoDuration = 0;

            // Captura a duração total do vídeo
            player.getDuration().then( duration => {
                videoDuration = Math.round( duration );
            } ).catch( error => {
                console.error( 'Erro ao obter duração do vídeo:', error );
            } );

            // Captura a velocidade de reprodução inicial
            player.getPlaybackRate().then( rate => {
                currentPlaybackRate = rate;
            } ).catch( error => {
                console.error( 'Erro ao obter velocidade de reprodução:', error );
            } );

            // Atualiza quando a velocidade muda
            player.on( 'playbackratechange', ( data ) => {
                currentPlaybackRate = data.playbackRate;
            } );

            // Função para adicionar intervalo assistido
            const addWatchedInterval = ( start, end ) => {
                if ( start >= end ) return;
                
                // Adiciona o novo intervalo
                watchedIntervals.push( { start, end } );
                
                // Mescla intervalos sobrepostos
                watchedIntervals.sort( ( a, b ) => a.start - b.start );
                
                const merged = [];
                let current = watchedIntervals[ 0 ];
                
                for ( let i = 1; i < watchedIntervals.length; i++ ) {
                    const next = watchedIntervals[ i ];
                    
                    if ( current.end >= next.start ) {
                        // Intervalos se sobrepõem, mescla
                        current.end = Math.max( current.end, next.end );
                    } else {
                        // Não se sobrepõem, adiciona o atual e move para o próximo
                        merged.push( current );
                        current = next;
                    }
                }
                merged.push( current );
                watchedIntervals = merged;
            };

            // Calcula o tempo total assistido (soma de todos os intervalos)
            const getTotalWatchedTime = () => {
                return watchedIntervals.reduce( ( total, interval ) => {
                    return total + ( interval.end - interval.start );
                }, 0 );
            };

            // Rastreia o progresso do vídeo
            player.on( 'timeupdate', ( { seconds } ) => {
                // Só conta se o vídeo está avançando (não retrocedendo)
                if ( seconds > lastTime && seconds - lastTime < 2 ) {
                    // Adiciona o intervalo assistido
                    addWatchedInterval( lastTime, seconds );
                }
                lastTime = seconds;
            } );

            const sendTime = () => {
                const tempoReal = Math.round( getTotalWatchedTime() );
                
                if ( tempoReal > lastSent && ! sending ) {
                    sending = true;

                    fetch( AJAX_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams( {
                            action:        'ldvt_salvar_tempo_video',
                            video_id:      iframe.src.split( '/video/' )[ 1 ].split( '?' )[ 0 ],
                            tempo:         tempoReal,
                            curso_id:      CURSO_ID,
                            aula_id:       AULA_ID,
                            duracao_total: videoDuration,
                        } ),
                    } ).finally( () => {
                        lastSent = tempoReal;
                        sending  = false;
                    } );
                }
            };

            setInterval( sendTime, 180000 ); // 3 min
            player.on( 'ended', sendTime );
            window.addEventListener( 'beforeunload', sendTime );
        } );
    } )();
    </script>
    <?php
}

// === CALLBACK AJAX PARA BUSCAR CURSOS DO ALUNO ===

add_action( 'wp_ajax_ldvt_get_user_courses', 'ldvt_get_user_courses_callback' );

/**
 * Callback AJAX para buscar cursos em que o aluno está inscrito
 */
function ldvt_get_user_courses_callback() {
    // Verifica permissões (apenas admins devem acessar isso)
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permissão negada.' );
    }

    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    
    if ( empty( $email ) ) {
        wp_send_json_error( 'Email não fornecido.' );
    }

    $user = get_user_by( 'email', $email );
    
    if ( ! $user ) {
        wp_send_json_error( 'Usuário não encontrado.' );
    }

    if ( ! function_exists( 'learndash_user_get_enrolled_courses' ) ) {
        wp_send_json_error( 'LearnDash não está ativo.' );
    }

    // Busca IDs dos cursos do usuário
    $course_ids = learndash_user_get_enrolled_courses( $user->ID );
    
    // Se não tiver cursos, retorna array vazio
    if ( empty( $course_ids ) ) {
        wp_send_json_success( array() );
    }

    // Busca os detalhes dos cursos
    $cursos = get_posts( array(
        'post_type'      => 'sfwd-courses',
        'post__in'       => $course_ids,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $cursos_data = array();
    foreach ( $cursos as $curso ) {
        $cursos_data[] = array(
            'id'    => $curso->ID,
            'title' => $curso->post_title,
        );
    }

    wp_send_json_success( $cursos_data );
}

// === CALLBACK AJAX PARA SALVAR TEMPO NO BANCO ===

add_action( 'wp_ajax_ldvt_salvar_tempo_video', 'ldvt_salvar_tempo_video_callback' );

/**
 * Callback AJAX para salvar o tempo assistido no banco de dados
 */
function ldvt_salvar_tempo_video_callback() {
    global $wpdb;

    $user_id       = get_current_user_id();
    $video_id      = sanitize_text_field( $_POST['video_id'] ?? '' );
    $tempo         = intval( $_POST['tempo']         ?? 0 );
    $curso_id      = intval( $_POST['curso_id']      ?? 0 );
    $aula_id       = intval( $_POST['aula_id']       ?? 0 );
    $duracao_total = intval( $_POST['duracao_total'] ?? 0 );

    if ( ! $user_id || ! $video_id || ! $tempo ) {
        wp_send_json_error( 'Dados inválidos.' );
    }

    ldvt_criar_tabela_tempo_video();

    $table = $wpdb->prefix . 'tempo_video';
    $agora = current_time( 'mysql' ); // horário local configurado no WP — https://developer.wordpress.org/reference/functions/current_time/

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO $table (user_id, video_id, tempo, curso_id, aula_id, duracao_total, data_registro)
             VALUES (%d, %s, %d, %d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE
                 tempo         = GREATEST( tempo, VALUES( tempo ) ),
                 curso_id      = VALUES( curso_id ),
                 aula_id       = VALUES( aula_id ),
                 duracao_total = VALUES( duracao_total ),
                 data_registro = VALUES( data_registro )",
            $user_id,
            $video_id,
            $tempo,
            $curso_id,
            $aula_id,
            $duracao_total,
            $agora
        )
    );

    wp_send_json_success( 'Tempo salvo.' );
}

// === CRIA TABELA SE NÃO EXISTIR ===

/**
 * Cria a tabela de rastreamento de tempo de vídeo se não existir
 */
function ldvt_criar_tabela_tempo_video() {
    global $wpdb;

    $table           = $wpdb->prefix . 'tempo_video';
    $charset_collate = $wpdb->get_charset_collate();

    // Verifica se a tabela existe e se tem a coluna duracao_total
    $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $table LIKE 'duracao_total'" );
    if ( empty( $column_exists ) ) {
        $wpdb->query( "ALTER TABLE $table ADD COLUMN duracao_total INT DEFAULT 0" );
    }

    $sql = "CREATE TABLE IF NOT EXISTS $table (
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
    dbDelta( $sql );
}

// === PÁGINA DE ADMINISTRAÇÃO ===

add_action( 'admin_menu', 'ldvt_add_admin_menu' );

/**
 * Adiciona menu de administração do plugin
 */
function ldvt_add_admin_menu() {
    add_menu_page(
        'Vimeo Tracker',
        'Vimeo Tracker',
        'manage_options',
        'learndash-vimeo-tracker',
        'ldvt_admin_page',
        'dashicons-video-alt3',
        30
    );
    
    // Submenu: Relatório Geral (página principal)
    add_submenu_page(
        'learndash-vimeo-tracker',
        'Relatório Geral',
        'Relatório Geral',
        'manage_options',
        'learndash-vimeo-tracker',
        'ldvt_admin_page'
    );
    
    // Submenu: Progresso por Curso
    add_submenu_page(
        'learndash-vimeo-tracker',
        'Progresso por Curso',
        'Progresso por Curso',
        'manage_options',
        'learndash-vimeo-tracker-curso',
        'ldvt_admin_page_progresso_curso'
    );
}

/**
 * Renderiza a página de administração
 */
function ldvt_admin_page() {
    global $wpdb;

    $table = $wpdb->prefix . 'tempo_video';
    
    // Paginação
    $por_pagina = 50;
    $pagina_atual = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $offset = ( $pagina_atual - 1 ) * $por_pagina;
    
    // Filtro por email
    $filtro_email = isset( $_GET['filtro_email'] ) ? sanitize_email( $_GET['filtro_email'] ) : '';
    
    // Monta a query com filtro
    $where = '';
    $params = array();
    
    if ( ! empty( $filtro_email ) ) {
        $user = get_user_by( 'email', $filtro_email );
        if ( $user ) {
            $where = ' WHERE user_id = %d';
            $params[] = $user->ID;
        }
    }
    
    // Conta total de registros
    $total_registros = $wpdb->get_var( 
        empty( $params ) 
            ? "SELECT COUNT(*) FROM $table" 
            : $wpdb->prepare( "SELECT COUNT(*) FROM $table" . $where, $params )
    );
    
    $total_paginas = ceil( $total_registros / $por_pagina );
    
    // Busca registros com paginação
    $query = "SELECT * FROM $table" . $where . " ORDER BY data_registro DESC LIMIT %d OFFSET %d";
    $params[] = $por_pagina;
    $params[] = $offset;
    
    $results = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-video-alt3" style="font-size: 30px; margin-right: 10px;"></span>
            LearnDash Vimeo Tracker
        </h1>
        <p>Relatório de tempo assistido de vídeos Vimeo pelos alunos.</p>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Filtro por Email -->
        <div class="mb-3">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="learndash-vimeo-tracker">
                    <div class="col-md-4">
                        <label for="filtro_email" class="form-label fw-bold">Filtrar por Email:</label>
                        <input type="email" 
                               class="form-control" 
                               id="filtro_email" 
                               name="filtro_email" 
                               value="<?php echo esc_attr( $filtro_email ); ?>" 
                               placeholder="exemplo@email.com">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Filtrar
                        </button>
                    </div>
                    <?php if ( ! empty( $filtro_email ) ) : ?>
                        <div class="col-md-2">
                            <a href="?page=learndash-vimeo-tracker" class="btn btn-secondary w-100">
                                <span class="dashicons dashicons-dismiss" style="margin-top: 3px;"></span> Limpar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ( empty( $results ) ) : ?>
            <div class="alert alert-info">
                <strong>Nenhum registro encontrado.</strong> 
                <?php echo ! empty( $filtro_email ) ? 'Tente outro email ou limpe o filtro.' : 'Os dados aparecerão aqui quando os alunos começarem a assistir os vídeos.'; ?>
            </div>
        <?php else : ?>
            <div class="mt-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Total de Registros: <?php echo number_format( $total_registros, 0, ',', '.' ); ?></h5>
                    <span class="badge bg-light text-dark">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
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
                                    <th>Data Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $results as $row ) : 
                                    $user       = get_userdata( $row->user_id );
                                    $curso_nome = $row->curso_id ? get_the_title( $row->curso_id ) : 'N/A';
                                    $aula_nome  = $row->aula_id ? get_the_title( $row->aula_id ) : 'N/A';
                                    $progresso  = $row->duracao_total > 0 ? round( ( $row->tempo / $row->duracao_total ) * 100, 1 ) : 0;
                                    
                                    // Formata tempo em horas:minutos:segundos
                                    $tempo_formatado = gmdate( 'H:i:s', $row->tempo );
                                    $duracao_formatada = gmdate( 'H:i:s', $row->duracao_total );
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $user ? $user->display_name : 'Usuário #' . $row->user_id ); ?></strong>
                                        <?php if ( $user && ! empty( $user->user_email ) ) : ?>
                                            <a href="?page=learndash-vimeo-tracker&filtro_email=<?php echo urlencode( $user->user_email ); ?>" 
                                               title="Filtrar por <?php echo esc_attr( $user->user_email ); ?>"
                                               class="text-decoration-none ms-2">
                                                <span class="dashicons dashicons-search" style="font-size: 18px; width: 18px; height: 18px; vertical-align: middle; color: #555;"></span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $user ? $user->user_email : 'N/A' ); ?></td>
                                    <td>
                                        <?php 
                                        $edit_link = $row->curso_id ? get_edit_post_link( $row->curso_id ) : '';
                                        if ( $edit_link ) {
                                            // Adiciona a tab do dashboard do LearnDash se possível
                                            $edit_link = add_query_arg( 'currentTab', 'learndash_sfwd-courses_dashboard', $edit_link );
                                            ?>
                                            <a href="<?php echo esc_url( $edit_link ); ?>" target="_blank" title="Editar Curso no LearnDash" class="text-decoration-none">
                                                <?php echo esc_html( $curso_nome ); ?>
                                                <span class="dashicons dashicons-external" style="font-size: 12px; width: 12px; height: 12px; vertical-align: text-top; color: #777;"></span>
                                            </a>
                                            <?php
                                        } else {
                                            echo esc_html( $curso_nome );
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html( $aula_nome ); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo esc_html( $tempo_formatado ); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo esc_html( $duracao_formatada ); ?></span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 25px; min-width: 100px;">
                                            <div class="progress-bar <?php echo $progresso >= 80 ? 'bg-success' : ( $progresso >= 50 ? 'bg-warning' : 'bg-danger' ); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $progresso; ?>%;" 
                                                 aria-valuenow="<?php echo $progresso; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $progresso; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->data_registro ) ) ); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Paginação -->
            <?php if ( $total_paginas > 1 ) : ?>
                <div class="mt-4 d-flex justify-content-center">
                    <nav aria-label="Navegação de página">
                        <ul class="pagination pagination-lg">
                            <?php if ( $pagina_atual > 1 ) : ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=learndash-vimeo-tracker&paged=1<?php echo ! empty( $filtro_email ) ? '&filtro_email=' . urlencode( $filtro_email ) : ''; ?>">
                                        &laquo; Primeira
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=learndash-vimeo-tracker&paged=<?php echo $pagina_atual - 1; ?><?php echo ! empty( $filtro_email ) ? '&filtro_email=' . urlencode( $filtro_email ) : ''; ?>">
                                        &lsaquo; Anterior
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $inicio = max( 1, $pagina_atual - 2 );
                            $fim = min( $total_paginas, $pagina_atual + 2 );
                            
                            for ( $i = $inicio; $i <= $fim; $i++ ) :
                            ?>
                                <li class="page-item <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=learndash-vimeo-tracker&paged=<?php echo $i; ?><?php echo ! empty( $filtro_email ) ? '&filtro_email=' . urlencode( $filtro_email ) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ( $pagina_atual < $total_paginas ) : ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=learndash-vimeo-tracker&paged=<?php echo $pagina_atual + 1; ?><?php echo ! empty( $filtro_email ) ? '&filtro_email=' . urlencode( $filtro_email ) : ''; ?>">
                                        Próxima &rsaquo;
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=learndash-vimeo-tracker&paged=<?php echo $total_paginas; ?><?php echo ! empty( $filtro_email ) ? '&filtro_email=' . urlencode( $filtro_email ) : ''; ?>">
                                        Última &raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
        <?php endif; ?>
    </div>

    <style>
        #wpbody-content .wrap {
            max-width: 100% !important;
            width: 100% !important;
            background: #fff;
            padding: 20px;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        #wpbody-content {
            padding-right: 20px;
        }
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
    </style>
    <?php
}

/**
 * Renderiza a página de Progresso por Curso
 */
function ldvt_admin_page_progresso_curso() {
    global $wpdb;
    
    if ( ! function_exists( 'learndash_get_course_id' ) ) {
        ?>
        <div class="wrap">
            <h1>Progresso por Curso</h1>
            <div class="alert alert-warning">
                <strong>LearnDash não detectado!</strong> Este recurso requer o plugin LearnDash ativo.
            </div>
        </div>
        <?php
        return;
    }
    
    $table = $wpdb->prefix . 'tempo_video';
    
    // Filtros
    $filtro_email = isset( $_GET['filtro_email'] ) ? sanitize_email( $_GET['filtro_email'] ) : '';
    $filtro_curso = isset( $_GET['filtro_curso'] ) ? intval( $_GET['filtro_curso'] ) : 0;
    
    // Busca usuário pelo email
    $user = null;
    if ( ! empty( $filtro_email ) ) {
        $user = get_user_by( 'email', $filtro_email );
    }
    
    // Busca cursos (filtrando pelo usuário se fornecido)
    $args_cursos = array(
        'post_type'      => 'sfwd-courses',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    if ( $user ) {
        $user_courses = learndash_user_get_enrolled_courses( $user->ID );
        if ( ! empty( $user_courses ) ) {
            $args_cursos['post__in'] = $user_courses;
            $cursos = get_posts( $args_cursos );
        } else {
            $cursos = array(); // Usuário não tem cursos
        }
    } else {
        $cursos = get_posts( $args_cursos );
    }
    
    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-chart-bar" style="font-size: 30px; margin-right: 10px;"></span>
            Progresso por Curso
        </h1>
        <p>Visualize o progresso detalhado de vídeos por aluno e curso.</p>
        
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="learndash-vimeo-tracker-curso">
                    
                    <div class="col-md-4">
                        <label for="filtro_email" class="form-label fw-bold">Email do Aluno:</label>
                        <input type="email" 
                               class="form-control" 
                               id="filtro_email" 
                               name="filtro_email" 
                               value="<?php echo esc_attr( $filtro_email ); ?>" 
                               placeholder="exemplo@email.com"
                               required>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="filtro_curso" class="form-label fw-bold">Curso:</label>
                        <select class="form-select" id="filtro_curso" name="filtro_curso" required>
                            <option value="">Selecione um curso</option>
                            <?php foreach ( $cursos as $curso ) : ?>
                                <option value="<?php echo $curso->ID; ?>" <?php selected( $filtro_curso, $curso->ID ); ?>>
                                    <?php echo esc_html( $curso->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Buscar
                        </button>
                    </div>
                    
                    <?php if ( ! empty( $filtro_email ) || ! empty( $filtro_curso ) ) : ?>
                        <div class="col-md-2">
                            <a href="?page=learndash-vimeo-tracker-curso" class="btn btn-secondary w-100">
                                <span class="dashicons dashicons-dismiss" style="margin-top: 3px;"></span> Limpar
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <?php
        // Se tiver filtros aplicados, mostra o relatório
        if ( ! empty( $filtro_email ) && ! empty( $filtro_curso ) ) {
            if ( ! $user ) {
                ?>
                <div class="alert alert-danger">
                    <strong><span class="dashicons dashicons-warning"></span> Email não encontrado!</strong><br>
                    O email <strong><?php echo esc_html( $filtro_email ); ?></strong> não está cadastrado no sistema.
                    <br><br>
                    <small>Verifique se o email está correto ou se o usuário está cadastrado no WordPress.</small>
                </div>
                <?php
            } else {
                ldvt_exibir_relatorio_progresso( $user, $filtro_curso, $table );
            }
        } elseif ( ! empty( $filtro_email ) || ! empty( $filtro_curso ) ) {
            ?>
            <div class="alert alert-info">
                <strong><span class="dashicons dashicons-info"></span> Atenção!</strong> 
                Por favor, preencha ambos os filtros (Email e Curso) para visualizar o relatório.
            </div>
            <?php
        }
        ?>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('filtro_email');
            const cursoSelect = document.getElementById('filtro_curso');
            let typingTimer;
            const doneTypingInterval = 800; // Tempo em ms para esperar antes de buscar

            if (!emailInput || !cursoSelect) return;

            // Função para buscar cursos
            const fetchCourses = () => {
                const email = emailInput.value;
                
                // Se email estiver vazio, não faz nada (ou poderia buscar todos, mas melhor manter o estado atual)
                if (!email) return;

                // Mostra loading no select
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
                        
                        // Limpa e reconstrói as opções
                        cursoSelect.innerHTML = '<option value="">Selecione um curso</option>';
                        
                        if (cursos.length === 0) {
                             const option = document.createElement('option');
                             option.text = 'Nenhum curso encontrado para este aluno';
                             option.disabled = true;
                             cursoSelect.add(option);
                        } else {
                            cursos.forEach(curso => {
                                const option = document.createElement('option');
                                option.value = curso.id;
                                option.text = curso.title;
                                cursoSelect.add(option);
                            });
                        }
                    } else {
                        // Se der erro (ex: user não encontrado), volta ao estado inicial ou mostra erro
                        // Mas para UX, se o email for inválido, talvez manter os cursos anteriores ou limpar?
                        // Vamos limpar para indicar que esse email não tem cursos
                         cursoSelect.innerHTML = '<option value="">Usuário não encontrado ou sem cursos</option>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    cursoSelect.disabled = false;
                    cursoSelect.innerHTML = originalOptions; // Restaura em caso de erro de rede
                });
            };

            // Evento ao sair do campo
            emailInput.addEventListener('blur', fetchCourses);
            
            // Evento ao pressionar Enter (para evitar submit imediato se quiser só filtrar cursos, mas o form submit vai acontecer anyway)
            // Vamos adicionar debounce na digitação também
            emailInput.addEventListener('keyup', () => {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(fetchCourses, doneTypingInterval);
            });

            emailInput.addEventListener('keydown', () => {
                clearTimeout(typingTimer);
            });
        });
        </script>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>
    </div>
    
    <style>
        #wpbody-content .wrap {
            max-width: 100% !important;
            width: 100% !important;
            background: #fff;
            padding: 20px;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        #wpbody-content {
            padding-right: 20px;
        }
        .card {
            width: 100% !important;
            max-width: 100% !important;
        }
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
    </style>
    <?php
}

/**
 * Exibe o relatório de progresso detalhado
 */
function ldvt_exibir_relatorio_progresso( $user, $curso_id, $table ) {
    global $wpdb;
    
    $curso = get_post( $curso_id );
    if ( ! $curso ) {
        echo '<div class="alert alert-danger">Curso não encontrado.</div>';
        return;
    }
    
    // Busca todas as aulas do curso
    $lessons = learndash_get_lesson_list( $curso_id );
    
    if ( empty( $lessons ) ) {
        echo '<div class="alert alert-warning">Este curso não possui aulas cadastradas.</div>';
        return;
    }
    
    // Busca todos os registros de vídeo do usuário neste curso
    $registros = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND curso_id = %d",
        $user->ID,
        $curso_id
    ), OBJECT_K );
    
    // Debug: Busca TODOS os registros do usuário (para verificar se há registros em outros cursos)
    $todos_registros = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d",
        $user->ID
    ) );
    
    // Calcula estatísticas
    $total_aulas = count( $lessons );
    $aulas_com_video = 0;
    $aulas_completas = 0;
    $aulas_em_andamento = 0;
    $aulas_nao_iniciadas = 0;
    $progresso_total = 0;
    
    ?>
    <!-- Cabeçalho do Relatório -->
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <span class="dashicons dashicons-admin-users" style="font-size: 24px; vertical-align: middle;"></span>
                <?php echo esc_html( $user->display_name ); ?> - <?php echo esc_html( $user->user_email ); ?>
            </h4>
        </div>
        <div class="card-body">
            <h5 class="card-title">
                <span class="dashicons dashicons-book" style="vertical-align: middle;"></span>
                <?php echo esc_html( $curso->post_title ); ?>
            </h5>
            <p class="text-muted mb-0">Total de Aulas: <?php echo $total_aulas; ?></p>
        </div>
    </div>
    
    <!-- Tabela de Progresso das Aulas -->
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
                        <?php foreach ( $lessons as $lesson ) :
                            $lesson_id = $lesson->ID;
                            $lesson_title = $lesson->post_title;
                            
                            // Verifica se tem registro de vídeo para esta aula
                            $registro = null;
                            foreach ( $registros as $reg ) {
                                if ( $reg->aula_id == $lesson_id ) {
                                    $registro = $reg;
                                    break;
                                }
                            }
                            
                            if ( $registro ) {
                                $aulas_com_video++;
                                $progresso = $registro->duracao_total > 0 ? round( ( $registro->tempo / $registro->duracao_total ) * 100, 1 ) : 0;
                                $progresso_total += $progresso;
                                
                                if ( $progresso >= 80 ) {
                                    $aulas_completas++;
                                    $status = 'Completo';
                                    $badge_class = 'bg-success';
                                    $icon = 'yes-alt';
                                } else {
                                    $aulas_em_andamento++;
                                    $status = 'Em Andamento';
                                    $badge_class = 'bg-warning';
                                    $icon = 'update';
                                }
                                
                                $tempo_formatado = gmdate( 'H:i:s', $registro->tempo );
                                $duracao_formatada = gmdate( 'H:i:s', $registro->duracao_total );
                                $data_formatada = date_i18n( 'd/m/Y H:i', strtotime( $registro->data_registro ) );
                            } else {
                                $aulas_nao_iniciadas++;
                                $progresso = 0;
                                $status = 'Não Iniciado';
                                $badge_class = 'bg-secondary';
                                $icon = 'minus';
                                $tempo_formatado = '00:00:00';
                                $duracao_formatada = 'N/A';
                                $data_formatada = '-';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $lesson_title ); ?></strong>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <span class="dashicons dashicons-<?php echo $icon; ?>" style="font-size: 12px; vertical-align: middle;"></span>
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $tempo_formatado; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $duracao_formatada; ?></span>
                            </td>
                            <td>
                                <div class="progress" style="height: 25px; min-width: 100px;">
                                    <div class="progress-bar <?php echo $progresso >= 80 ? 'bg-success' : ( $progresso >= 50 ? 'bg-warning' : 'bg-danger' ); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $progresso; ?>%;" 
                                         aria-valuenow="<?php echo $progresso; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo $progresso; ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?php echo esc_html( $data_formatada ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Resumo Geral -->
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
                        <h2 class="text-primary mb-0"><?php echo $total_aulas; ?></h2>
                        <small class="text-muted">Total de Aulas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 rounded">
                        <h2 class="text-success mb-0"><?php echo $aulas_completas; ?></h2>
                        <small class="text-muted">Completas (≥80%)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-warning bg-opacity-10 rounded">
                        <h2 class="text-warning mb-0"><?php echo $aulas_em_andamento; ?></h2>
                        <small class="text-muted">Em Andamento</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-secondary bg-opacity-10 rounded">
                        <h2 class="text-secondary mb-0"><?php echo $aulas_nao_iniciadas; ?></h2>
                        <small class="text-muted">Não Iniciadas</small>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row">
                <div class="col-md-6">
                    <h6>Progresso Médio de Todas as Aulas:</h6>
                    <?php 
                    // Calcula progresso médio considerando TODAS as aulas (inclusive não iniciadas = 0%)
                    $progresso_medio_geral = $total_aulas > 0 ? round( $progresso_total / $total_aulas, 1 ) : 0;
                    ?>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar <?php echo $progresso_medio_geral >= 80 ? 'bg-success' : ( $progresso_medio_geral >= 50 ? 'bg-warning' : 'bg-danger' ); ?>" 
                             role="progressbar" 
                             style="width: <?php echo $progresso_medio_geral; ?>%;" 
                             aria-valuenow="<?php echo $progresso_medio_geral; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <strong><?php echo $progresso_medio_geral; ?>%</strong>
                        </div>
                    </div>
                    <small class="text-muted">
                        Média considerando todas as <?php echo $total_aulas; ?> aulas (inclusive não iniciadas)
                    </small>
                </div>
                
                <div class="col-md-6">
                    <h6>Taxa de Conclusão (Aulas ≥80%):</h6>
                    <?php 
                    $taxa_conclusao = $total_aulas > 0 ? round( ( $aulas_completas / $total_aulas ) * 100, 1 ) : 0;
                    ?>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar <?php echo $taxa_conclusao >= 80 ? 'bg-success' : ( $taxa_conclusao >= 50 ? 'bg-info' : 'bg-danger' ); ?>" 
                             role="progressbar" 
                             style="width: <?php echo $taxa_conclusao; ?>%;" 
                             aria-valuenow="<?php echo $taxa_conclusao; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <strong><?php echo $taxa_conclusao; ?>%</strong>
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php echo $aulas_completas; ?> de <?php echo $total_aulas; ?> aulas completas
                    </small>
                </div>
            </div>
            
            <?php if ( $taxa_conclusao >= 80 ) : ?>
                <div class="alert alert-success mt-3 mb-0">
                    <strong><span class="dashicons dashicons-yes-alt"></span> Parabéns!</strong> 
                    O aluno está com excelente progresso no curso!
                </div>
            <?php elseif ( $taxa_conclusao >= 50 ) : ?>
                <div class="alert alert-info mt-3 mb-0">
                    <strong><span class="dashicons dashicons-info"></span> Bom progresso!</strong> 
                    O aluno está avançando no curso.
                </div>
            <?php else : ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong><span class="dashicons dashicons-warning"></span> Atenção!</strong> 
                    O aluno precisa de mais dedicação para concluir o curso.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // Mensagem informativa se não houver registros neste curso mas houver em outros
    if ( empty( $registros ) && ! empty( $todos_registros ) ) :
        $cursos_com_registro = array();
        foreach ( $todos_registros as $reg ) {
            if ( $reg->curso_id > 0 && ! in_array( $reg->curso_id, $cursos_com_registro ) ) {
                $cursos_com_registro[] = $reg->curso_id;
            }
        }
        
        if ( ! empty( $cursos_com_registro ) ) :
        ?>
        <div class="alert alert-info mt-4">
            <h6><span class="dashicons dashicons-info"></span> Informação Importante</h6>
            <p class="mb-2">
                <strong>Este aluno não possui registros de vídeos neste curso específico.</strong>
            </p>
            <p class="mb-2">
                Porém, encontramos <strong><?php echo count( $todos_registros ); ?> registro(s)</strong> 
                de vídeos assistidos em <strong><?php echo count( $cursos_com_registro ); ?> outro(s) curso(s)</strong>.
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
        <?php
        endif;
    endif;
    ?>
    <?php
}

// === FUNÇÕES AUXILIARES ===

/**
 * Retorna a versão do plugin
 *
 * @return string
 */
function ldvt_get_version() {
    return LDVT_VERSION;
}

/**
 * Verifica se o LearnDash está ativo
 *
 * @return bool
 */
function ldvt_is_learndash_active() {
    return function_exists( 'learndash_get_course_id' );
}
