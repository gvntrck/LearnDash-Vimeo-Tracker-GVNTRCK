<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', 'ldvt_vimeo_tracking_script');

/**
 * Injeta o script de rastreamento do Vimeo no footer.
 *
 * @return void
 */
function ldvt_vimeo_tracking_script()
{
    if (!is_user_logged_in()) {
        return;
    }

    $course_id = function_exists('learndash_get_course_id') ? learndash_get_course_id(get_the_ID()) : 0;
    $lesson_id = get_the_ID();
    ?>
    <script src="https://player.vimeo.com/api/player.js"></script>
    <script>
        (() => {
            const AJAX_URL = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const CURSO_ID = <?php echo (int) $course_id; ?>;
            const AULA_ID = <?php echo (int) $lesson_id; ?>;

            document.addEventListener('DOMContentLoaded', () => {
                const iframe = document.querySelector('iframe[src*="vimeo.com/video"]');
                if (!iframe) return;

                const player = new Vimeo.Player(iframe);
                let watchedIntervals = [];
                let lastTime = 0;
                let lastSent = 0;
                let sending = false;
                let videoDuration = 0;

                player.getDuration().then(duration => {
                    videoDuration = Math.round(duration);
                }).catch(error => {
                    console.error('Erro ao obter duração do vídeo:', error);
                });

                const addWatchedInterval = (start, end) => {
                    if (start >= end) return;

                    watchedIntervals.push({ start, end });
                    watchedIntervals.sort((a, b) => a.start - b.start);

                    const mergedIntervals = [];
                    let currentInterval = watchedIntervals[0];

                    for (let index = 1; index < watchedIntervals.length; index++) {
                        const nextInterval = watchedIntervals[index];

                        if (currentInterval.end >= nextInterval.start) {
                            currentInterval.end = Math.max(currentInterval.end, nextInterval.end);
                            continue;
                        }

                        mergedIntervals.push(currentInterval);
                        currentInterval = nextInterval;
                    }

                    mergedIntervals.push(currentInterval);
                    watchedIntervals = mergedIntervals;
                };

                const getTotalWatchedTime = () => watchedIntervals.reduce(
                    (total, interval) => total + (interval.end - interval.start),
                    0
                );

                player.on('timeupdate', ({ seconds }) => {
                    if (seconds > lastTime && seconds - lastTime < 2) {
                        addWatchedInterval(lastTime, seconds);
                    }

                    lastTime = seconds;
                });

                const sendTime = () => {
                    const watchedTime = Math.round(getTotalWatchedTime());

                    if (watchedTime <= lastSent || sending) {
                        return;
                    }

                    sending = true;

                    fetch(AJAX_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'ldvt_salvar_tempo_video',
                            video_id: iframe.src.split('/video/')[1].split('?')[0],
                            tempo: watchedTime,
                            curso_id: CURSO_ID,
                            aula_id: AULA_ID,
                            duracao_total: videoDuration,
                        }),
                    }).finally(() => {
                        lastSent = watchedTime;
                        sending = false;
                    });
                };

                setInterval(sendTime, 180000);
                player.on('ended', sendTime);
                window.addEventListener('beforeunload', sendTime);
            });
        })();
    </script>
    <?php
}
