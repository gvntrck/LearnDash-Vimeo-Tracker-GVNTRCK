(() => {
    const config = window.LDVTTracking || {};

    document.addEventListener('DOMContentLoaded', () => {
        if (!config.ajaxUrl) return;

        const iframe = document.querySelector('iframe[src*="vimeo.com/video/"]');
        const videoMatch = iframe && iframe.src.match(/https?:\/\/(?:player\.)?vimeo\.com\/(?:video\/)?([0-9]+)(?:[/?#"\s]|$)/i);
        const videoId = videoMatch ? videoMatch[1] : '';
        const lessonId = Number(config.lessonId) || 0;
        const prefix = `ldvt:${config.blogId}:${config.userId}:`;
        const tabId = window.crypto && window.crypto.randomUUID
            ? window.crypto.randomUUID()
            : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
        const currentKey = videoId ? `${prefix}${lessonId}:${videoId}:${tabId}` : '';
        const progressIndicators = Array.from(document.querySelectorAll('.ldvt-watch-progress'));
        const queues = new Map();
        const sending = new Set();
        const mergeIntervals = intervals => {
            const normalized = intervals
                .filter(interval => interval && Number.isFinite(Number(interval.start)) && Number.isFinite(Number(interval.end)))
                .map(interval => ({ start: Math.max(0, Number(interval.start)), end: Math.max(0, Number(interval.end)) }))
                .filter(interval => interval.end > interval.start && interval.end <= 604800 && interval.start <= 604800)
                .sort((first, second) => first.start - second.start);
            const merged = [];
            normalized.forEach(interval => {
                const current = merged[merged.length - 1];
                if (current && current.end >= interval.start) {
                    current.end = Math.max(current.end, interval.end);
                } else if (merged.length < 10000) {
                    merged.push(interval);
                }
            });
            return merged;
        };
        let hasBaseline = false;
        let seeking = false;
        let playbackActive = false;
        let lastTime = 0;
        let lastClock = clockNow();

        progressIndicators.forEach(indicator => {
            if (videoId && !indicator.dataset.videoId) indicator.dataset.videoId = videoId;
        });

        function clockNow() {
            return window.performance && typeof window.performance.now === 'function'
                ? window.performance.now()
                : Date.now();
        }

        function setPlaybackBaseline(seconds) {
            seconds = Number(seconds);
            if (!Number.isFinite(seconds) || seconds < 0) return;
            lastTime = seconds;
            lastClock = clockNow();
            hasBaseline = true;
        }

        function recordPlaybackInterval(start, end) {
            if (!(end > start)) return;
            const entry = queues.get(currentKey) || { key: currentKey, lessonId, videoId, intervals: [] };
            entry.intervals = mergeIntervals(entry.intervals.concat({ start, end }));
            queues.set(currentKey, entry);
            persistQueue(entry);
            setIndicatorState('saving', 'Progresso pendente');
        }

        function readStoredQueue(key) {
            try {
                const raw = localStorage.getItem(key);
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                const keyParts = key.slice(prefix.length).split(':');
                const legacyKey = keyParts.length === 2;
                const storedLessonId = Number(parsed.lessonId || (legacyKey && videoId === keyParts[0] ? lessonId : 0));
                const storedVideoId = String(parsed.videoId || (legacyKey ? keyParts[0] : ''));
                if (!/^[1-9][0-9]{0,19}$/.test(storedVideoId) || !Number.isInteger(storedLessonId) || storedLessonId < 0) return null;
                const intervals = Array.isArray(parsed.intervals) ? mergeIntervals(parsed.intervals) : [];
                return intervals.length ? { key, lessonId: storedLessonId, videoId: storedVideoId, intervals } : null;
            } catch (error) {
                return null;
            }
        }

        function persistQueue(entry) {
            try {
                localStorage.setItem(entry.key, JSON.stringify({
                    lessonId: entry.lessonId,
                    videoId: entry.videoId,
                    intervals: entry.intervals,
                }));
                return true;
            } catch (error) {
                return false;
            }
        }

        function enumerateQueues() {
            try {
                for (let index = 0; index < localStorage.length; index++) {
                    const key = localStorage.key(index);
                    if (!key || !key.startsWith(prefix)) continue;
                    const entry = readStoredQueue(key);
                    if (entry) queues.set(key, entry);
                }
            } catch (error) {
                return;
            }
        }

        const intervalsEqual = (first, second) => JSON.stringify(first) === JSON.stringify(second);
        const totalWatchedTime = intervals => Math.round(intervals.reduce(
            (total, interval) => total + interval.end - interval.start,
            0
        ));
        const isCurrentQueue = entry => entry.lessonId === lessonId && entry.videoId === videoId;
        const hasPendingCurrentQueue = () => Array.from(queues.values()).some(entry => isCurrentQueue(entry) && entry.intervals.length);
        const getMatchingIndicators = () => progressIndicators.filter(indicator => (
            !indicator.dataset.videoId || indicator.dataset.videoId === videoId
        ));
        const setIndicatorState = (state, metaText = '') => {
            getMatchingIndicators().forEach(indicator => {
                indicator.classList.remove('is-saving', 'is-saved', 'is-error');
                if (state) indicator.classList.add(`is-${state}`);
                if (metaText) {
                    const meta = indicator.querySelector('.ldvt-watch-progress__meta');
                    if (meta) meta.textContent = metaText;
                }
            });
        };
        const updateSavedTimeIndicators = (data, entry, { keepStatus = false } = {}) => {
            if (!isCurrentQueue(entry)) return;
            getMatchingIndicators().forEach(indicator => {
                const time = indicator.querySelector('.ldvt-watch-progress__time');
                const meta = indicator.querySelector('.ldvt-watch-progress__meta');
                if (time && data.tempo_formatado) time.textContent = data.tempo_formatado;
                if (meta && !keepStatus) {
                    if (data.completion_pending) meta.textContent = 'Progresso salvo; conclusão pendente';
                    else if (data.data_registro_formatada) meta.textContent = `Salvo em ${data.data_registro_formatada}`;
                    else if (data.has_record === false) meta.textContent = 'Ainda não salvo';
                }
                if (typeof data.tempo !== 'undefined') indicator.dataset.savedTime = data.tempo;
            });
        };

        function buildRequestBody(entry, intervals) {
            const fields = {
                action: 'ldvt_salvar_tempo_video',
                nonce: config.nonce || '',
                video_id: entry.videoId,
                tempo: totalWatchedTime(intervals),
                aula_id: entry.lessonId,
                watched_intervals: JSON.stringify(intervals),
            };
            if (entry.lessonId === lessonId) {
                fields.page_started_at = config.pageStartedAt || 0;
                fields.page_signature = config.pageSignature || '';
            }
            return new URLSearchParams(fields).toString();
        }

        function flushQueue(key, { keepalive = false } = {}) {
            const entry = queues.get(key);
            if (!entry || !entry.intervals.length || sending.has(key)) return;
            const snapshot = mergeIntervals(entry.intervals);
            sending.add(key);
            if (isCurrentQueue(entry)) setIndicatorState('saving', 'Progresso pendente; salvando...');

            fetch(config.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: buildRequestBody(entry, snapshot),
                keepalive,
                credentials: 'same-origin',
            }).then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            }).then(data => {
                if (!data.success) throw new Error(data.data || 'Erro ao salvar tempo do vídeo.');
                const current = queues.get(key);
                const stored = readStoredQueue(key);
                const latest = mergeIntervals((current ? current.intervals : []).concat(stored ? stored.intervals : []));
                const acknowledged = intervalsEqual(latest, snapshot);
                if (acknowledged) {
                    try {
                        localStorage.removeItem(key);
                    } catch (error) {
                        persistQueue({ ...entry, intervals: [] });
                    }
                    queues.delete(key);
                    const otherPending = hasPendingCurrentQueue();
                    updateSavedTimeIndicators(data.data || {}, entry, { keepStatus: otherPending });
                    if (isCurrentQueue(entry)) {
                        setIndicatorState(otherPending ? 'saving' : 'saved', otherPending ? 'Há progresso pendente' : '');
                    }
                    return;
                }
                const updated = { ...entry, intervals: latest };
                queues.set(key, updated);
                persistQueue(updated);
                updateSavedTimeIndicators(data.data || {}, entry, { keepStatus: true });
                if (isCurrentQueue(entry)) setIndicatorState('saving', 'Há progresso novo pendente');
            }).catch(error => {
                if (isCurrentQueue(entry)) setIndicatorState('error', 'Progresso pendente; nova tentativa necessária');
                console.error('Erro ao salvar tempo do vídeo:', error);
            }).finally(() => {
                sending.delete(key);
                const current = queues.get(key);
                if (current && current.intervals.length && !intervalsEqual(current.intervals, snapshot)) flushQueue(key);
            });
        }

        const flushPendingQueues = ({ keepalive = false } = {}) => {
            queues.forEach((entry, key) => flushQueue(key, { keepalive }));
        };
        const buildSavedTimeRequestBody = () => new URLSearchParams({
            action: 'ldvt_get_tempo_video',
            nonce: config.nonce || '',
            video_id: videoId,
            aula_id: lessonId,
        }).toString();

        enumerateQueues();
        if (currentKey && !queues.has(currentKey)) {
            queues.set(currentKey, { key: currentKey, lessonId, videoId, intervals: [] });
        }

        if (videoId) {
            fetch(config.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: buildSavedTimeRequestBody(),
                credentials: 'same-origin',
            }).then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            }).then(data => {
                if (!data.success) throw new Error(data.data || 'Erro ao buscar tempo salvo do vídeo.');
                const activeQueue = queues.get(currentKey) || { lessonId, videoId };
                updateSavedTimeIndicators(data.data || {}, activeQueue, { keepStatus: hasPendingCurrentQueue() });
            }).catch(error => console.error('Erro ao buscar tempo salvo do vídeo:', error));
        }

        const player = iframe && videoId && window.Vimeo ? new Vimeo.Player(iframe) : null;
        if (player) {
            player.on('play', ({ seconds }) => {
                seeking = false;
                playbackActive = true;
                setPlaybackBaseline(seconds);
            });
            player.on('seeking', () => {
                seeking = true;
            });
            player.on('seeked', ({ seconds }) => {
                seeking = false;
                setPlaybackBaseline(seconds);
            });
            player.on('timeupdate', ({ seconds }) => {
                const now = clockNow();
                seconds = Number(seconds);
                if (!Number.isFinite(seconds) || seconds < 0 || seeking) return;

                if (!hasBaseline) {
                    const elapsed = Math.max(0, (now - lastClock) / 1000);
                    if (seconds > 0 && seconds <= 1 && seconds <= (2 * elapsed) + 1) {
                        recordPlaybackInterval(0, seconds);
                        playbackActive = true;
                    }
                    lastTime = seconds;
                    lastClock = now;
                    hasBaseline = true;
                    return;
                }
                if (!playbackActive) return;

                const elapsed = Math.max(0, (now - lastClock) / 1000);
                const delta = seconds - lastTime;
                if (delta > 0 && delta <= (2 * elapsed) + 1) {
                    recordPlaybackInterval(lastTime, seconds);
                }
                lastTime = seconds;
                lastClock = now;
            });
            player.on('ended', () => {
                playbackActive = false;
                if (currentKey) flushQueue(currentKey);
            });
            player.on('pause', () => {
                playbackActive = false;
                if (currentKey) flushQueue(currentKey);
            });
        }

        setInterval(() => flushPendingQueues(), 15000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') flushPendingQueues({ keepalive: true });
        });
        window.addEventListener('online', () => flushPendingQueues());
        window.addEventListener('pagehide', () => flushPendingQueues({ keepalive: true }));
        window.addEventListener('beforeunload', () => flushPendingQueues({ keepalive: true }));
        flushPendingQueues();
    });
})();
