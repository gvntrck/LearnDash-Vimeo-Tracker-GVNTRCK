// === REGISTRO DO TEMPO DE VÍDEO ASSISTIDO VIMEO + LEARNDASH ===

add_action( 'wp_footer', 'vimeo_tracking_script' );

function vimeo_tracking_script() {
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
                            action:        'salvar_tempo_video',
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

add_action( 'wp_ajax_salvar_tempo_video', 'salvar_tempo_video_callback' );

function salvar_tempo_video_callback() {
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

    criar_tabela_tempo_video();

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

function criar_tabela_tempo_video() {
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
