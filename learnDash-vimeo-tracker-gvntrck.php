<?php
/**
 * Plugin Name: LearnDash Vimeo Tracker GVNTRCK
 * Plugin URI: https://github.com/gvntrck/LearnDash-Vimeo-Tracker-GVNTRCK
 * Description: Rastreia o tempo de visualização de vídeos Vimeo em cursos LearnDash, salvando o progresso do aluno no banco de dados.
 * Version: 1.1.0
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
define( 'LDVT_VERSION', '1.1.0' );
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

            const player       = new Vimeo.Player( iframe );
            let   totalWatched = 0;
            let   lastTime     = 0;
            let   lastSent     = 0;
            let   sending      = false;
            let   videoDuration = 0;

            // Captura a duração total do vídeo
            player.getDuration().then( duration => {
                videoDuration = Math.round( duration );
            } ).catch( error => {
                console.error( 'Erro ao obter duração do vídeo:', error );
            } );

            player.on( 'timeupdate', ( { seconds } ) => {
                if ( seconds > lastTime ) {
                    totalWatched += seconds - lastTime;
                }
                lastTime = seconds;
            } );

            const sendTime = () => {
                const tempo = Math.round( totalWatched );
                if ( tempo > lastSent && ! sending ) {
                    sending = true;

                    fetch( AJAX_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams( {
                            action:        'ldvt_salvar_tempo_video',
                            video_id:      iframe.src.split( '/video/' )[ 1 ].split( '?' )[ 0 ],
                            tempo,
                            curso_id:      CURSO_ID,
                            aula_id:       AULA_ID,
                            duracao_total: videoDuration,
                        } ),
                    } ).finally( () => {
                        lastSent = tempo;
                        sending  = false;
                    } );
                }
            };

            setInterval( sendTime, 180000 ); // 3 min
            player.on( 'ended', sendTime );
            window.addEventListener( 'beforeunload', sendTime );
        } );
    } )();
    </script>
    <?php
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

    $table = $wpdb->prefix . 'ldvt_tempo_video';
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

    $table           = $wpdb->prefix . 'ldvt_tempo_video';
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
}

/**
 * Renderiza a página de administração
 */
function ldvt_admin_page() {
    global $wpdb;

    // Busca todos os registros do banco
    $table   = $wpdb->prefix . 'ldvt_tempo_video';
    $results = $wpdb->get_results( "SELECT * FROM $table ORDER BY data_registro DESC" );

    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-video-alt3" style="font-size: 30px; margin-right: 10px;"></span>
            LearnDash Vimeo Tracker
        </h1>
        <p>Relatório de tempo assistido de vídeos Vimeo pelos alunos.</p>

        <?php if ( empty( $results ) ) : ?>
            <div class="notice notice-info">
                <p>Nenhum registro encontrado. Os dados aparecerão aqui quando os alunos começarem a assistir os vídeos.</p>
            </div>
        <?php else : ?>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
            
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Total de Registros: <?php echo count( $results ); ?></h5>
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
                                    </td>
                                    <td><?php echo esc_html( $user ? $user->user_email : 'N/A' ); ?></td>
                                    <td><?php echo esc_html( $curso_nome ); ?></td>
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

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
        <?php endif; ?>
    </div>

    <style>
        .wrap {
            background: #fff;
            padding: 20px;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
