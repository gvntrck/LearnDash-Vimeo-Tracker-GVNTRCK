const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync(require('path').join(__dirname, '../includes/frontend/tracking.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function deferred() {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
}

function createStorage(values = new Map(), denied = false) {
    return {
        values,
        get length() {
            if (denied) throw new Error('localStorage denied');
            return values.size;
        },
        key: index => {
            if (denied) throw new Error('localStorage denied');
            return Array.from(values.keys())[index] || null;
        },
        getItem: key => {
            if (denied) throw new Error('localStorage denied');
            return values.has(key) ? values.get(key) : null;
        },
        setItem: (key, value) => {
            if (denied) throw new Error('localStorage denied');
            values.set(key, value);
        },
        removeItem: key => {
            if (denied) throw new Error('localStorage denied');
            values.delete(key);
        },
    };
}

function createHarness(saveHandler, {
    sharedStorage = new Map(),
    tabId = 'tab-1',
    lessonId = 123,
    videoId = '1189714750',
    configuredVideoId = videoId,
    blogId = 9,
    userId = 7,
    storageDenied = false,
    sessionStorageDenied = false,
    showIndicator = true,
    visibilityState = 'visible',
    iframeSources = null,
    readyState = 'loading',
    autoDOMContentLoaded = true,
    playerReady = () => Promise.resolve(),
    vimeoAvailable = true,
    queued = null,
} = {}) {
    const stored = createStorage(sharedStorage, storageDenied);
    const playerEvents = {};
    const documentEvents = {};
    const windowEvents = {};
    const getRequests = [];
    const intervals = [];
    const classes = new Set();
    const classChanges = { savingAdds: 0 };
    const meta = { textContent: '' };
    const time = { textContent: '' };
    const indicator = {
        dataset: {},
        classList: {
            add: name => {
                if (name === 'is-saving') classChanges.savingAdds++;
                classes.add(name);
            },
            remove: (...names) => names.forEach(name => classes.delete(name)),
            contains: name => classes.has(name),
        },
        querySelector: selector => selector === '.ldvt-watch-progress__meta' ? meta : time,
    };
    const iframe = videoId ? { src: `https://player.vimeo.com/video/${videoId}?h=test` } : null;
    const iframeCandidates = iframeSources || (iframe ? [iframe] : []);
    const playerFrames = [];
    const observers = [];
    const config = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'nonce-current-page',
        blogId,
        userId,
        lessonId,
        videoId: configuredVideoId,
        pageStartedAt: 100,
        pageSignature: 'signature-current-page',
    };
    const clock = { value: 0 };
    const currentKey = videoId ? `ldvt:${blogId}:${userId}:${lessonId}:${videoId}:${tabId}` : '';
    if (queued) {
        stored.setItem(currentKey, JSON.stringify({ lessonId, videoId, intervals: queued }));
    }
    const context = {
        window: {
            LDVTTracking: config,
            Vimeo: { Player: class {
                constructor(frame) { playerFrames.push(frame.src); this.frame = frame; }
                on(name, callback) { playerEvents[name] = callback; }
                off(name, callback) { if (playerEvents[name] === callback) delete playerEvents[name]; }
                ready() { return playerReady(this.frame); }
            } },
            crypto: { randomUUID: () => tabId },
            performance: { now: () => clock.value },
            addEventListener: (name, callback) => { windowEvents[name] = callback; },
        },
        Vimeo: null,
        MutationObserver: class {
            constructor(callback) { this.callback = callback; observers.push(this); }
            observe(target, options) { this.target = target; this.options = options; }
            disconnect() { this.disconnected = true; }
        },
        document: {
            readyState,
            documentElement: {},
            visibilityState,
            addEventListener: (name, callback) => { documentEvents[name] = callback; },
            querySelector: selector => iframeCandidates.find(frame => (
                selector.includes('vimeo.com/video/')
                    ? (frame.src || '').includes('vimeo.com/video/')
                    : (frame.src || '').includes('vimeo.com')
            )) || null,
            querySelectorAll: selector => selector.includes('iframe')
                ? iframeCandidates.filter(frame => (frame.src || '').includes('vimeo.com/video/'))
                : showIndicator ? [indicator] : [],
        },
        localStorage: stored,
        sessionStorage: {
            getItem: () => {
                if (sessionStorageDenied) throw new Error('sessionStorage denied');
                return null;
            },
            setItem: () => {
                if (sessionStorageDenied) throw new Error('sessionStorage denied');
            },
        },
        URLSearchParams,
        fetch: (url, options) => {
            const body = new URLSearchParams(options.body);
            if (body.get('action') === 'ldvt_get_tempo_video') {
                getRequests.push({ url, options, body });
                return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { has_record: false } }) });
            }
            return saveHandler({ url, options, body });
        },
        setInterval: (callback, milliseconds) => intervals.push({ callback, milliseconds }),
        console: { error: () => {} },
        Math,
        Date,
    };
    context.Vimeo = context.window.Vimeo;
    if (!vimeoAvailable) context.window.Vimeo = null;
    vm.runInNewContext(source, context);
    if (autoDOMContentLoaded && documentEvents.DOMContentLoaded) documentEvents.DOMContentLoaded();
    return { stored, classes, classChanges, meta, config, intervals, playerEvents, windowEvents, documentEvents, getRequests, playerFrames, iframeCandidates, observers, clock, currentKey, window: context.window, vimeoSdk: context.Vimeo };
}

async function testFailedRequestAndNonceRetry() {
    const requests = [];
    let shouldFail = true;
    const harness = createHarness(request => {
        requests.push(request);
        if (shouldFail) return Promise.resolve({ ok: true, json: async () => ({ success: false, data: 'nonce invalid' }) });
        return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 2, data_registro_formatada: 'saved' } }) });
    }, { sessionStorageDenied: true });
    assert.strictEqual(harness.getRequests[0].body.get('aula_id'), '123', 'GET revalidation sends current lesson ID');
    assert.strictEqual(harness.getRequests[0].body.get('nonce'), 'nonce-current-page', 'GET revalidation uses current nonce');
    harness.playerEvents.timeupdate({ seconds: 0.8 });
    harness.playerEvents.timeupdate({ seconds: 1.8 });
    assert.strictEqual(harness.stored.values.size, 1, 'intervals persist synchronously during timeupdate');
    harness.playerEvents.pause();
    await tick();
    await tick();
    assert.strictEqual(harness.stored.values.size, 1, 'failed/false-nonce response leaves durable queue intact');
    assert(harness.classes.has('is-error'), 'failed save indicator reports pending error');
    assert.strictEqual(JSON.parse(harness.stored.values.get(harness.currentKey)).lessonId, 123);
    assert(harness.currentKey.endsWith(':tab-1'), 'random per-tab key works when sessionStorage is denied');
    assert.strictEqual(new URLSearchParams(requests[0].options.body).get('nonce'), 'nonce-current-page');
    harness.config.nonce = 'nonce-fresh-page';
    shouldFail = false;
    harness.windowEvents.online();
    await tick();
    await tick();
    assert.strictEqual(harness.stored.values.size, 0, 'queue clears only after successful database ACK');
    assert.strictEqual(new URLSearchParams(requests[1].options.body).get('nonce'), 'nonce-fresh-page');
    assert(harness.classes.has('is-saved'), 'ACK updates indicator to saved');
    assert.strictEqual(harness.meta.textContent, 'Salvo em saved', 'non-pending progress preserves saved timestamp indicator');
}

async function testMemoryQueueWhenStorageDenied() {
    let saves = 0;
    const harness = createHarness(() => {
        saves++;
        return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 1 } }) });
    }, { storageDenied: true, sessionStorageDenied: true });
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.playerEvents.pause();
    await tick();
    await tick();
    assert.strictEqual(saves, 1, 'storage denial still permits memory-backed save attempt');
    assert(harness.classes.has('is-saved'), 'memory queue clears only after ACK');
}

async function testClosedTabQueuesAcrossLessonsAndVideos() {
    const storage = new Map([
        ['ldvt:9:7:123:1189714750:closed-tab-a', JSON.stringify({ lessonId: 123, videoId: '1189714750', intervals: [{ start: 0, end: 4 }] })],
        ['ldvt:9:7:124:1189714750:closed-tab-b', JSON.stringify({ lessonId: 124, videoId: '1189714750', intervals: [{ start: 5, end: 8 }] })],
        ['ldvt:9:7:123:1189714751:closed-tab-c', JSON.stringify({ lessonId: 123, videoId: '1189714751', intervals: [{ start: 10, end: 12 }] })],
        ['ldvt:8:7:123:1189714750:other-site', JSON.stringify({ lessonId: 123, videoId: '1189714750', intervals: [{ start: 0, end: 99 }] })],
        ['ldvt:9:88:123:1189714750:other-user', JSON.stringify({ lessonId: 123, videoId: '1189714750', intervals: [{ start: 0, end: 99 }] })],
    ]);
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 4, data_registro_formatada: 'saved' } }) });
    }, { sharedStorage: storage, tabId: 'new-random-tab', sessionStorageDenied: true });
    await tick();
    await tick();
    assert.strictEqual(requests.length, 3, 'new tab replays queues from closed tabs across lessons and videos');
    assert(requests.every(request => new URLSearchParams(request.options.body).get('nonce') === 'nonce-current-page'), 'all replays use current page nonce');
    const fields = requests.map(request => new URLSearchParams(request.options.body));
    assert.deepStrictEqual(fields.map(body => body.get('video_id')).sort(), ['1189714750', '1189714750', '1189714751']);
    assert(fields.some(body => body.get('aula_id') === '124' && !body.has('page_signature')), 'other-lesson replay omits current lesson clock token');
    assert(fields.filter(body => body.get('aula_id') === '123').every(body => body.get('page_signature') === 'signature-current-page'), 'same-lesson replay uses current signed token');
    assert(storage.has('ldvt:8:7:123:1189714750:other-site') && storage.has('ldvt:9:88:123:1189714750:other-user'), 'other sites and accounts remain untouched');
    assert.strictEqual(storage.size, 2, 'ACK removes only acknowledged snapshots from matching site/user queues');
}

async function testPeriodicSaveWithoutPerSecondResendOrBlink() {
    const first = deferred();
    const second = deferred();
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return requests.length === 1 ? first.promise : second.promise;
    });
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 1000;
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.clock.value = 2000;
    harness.playerEvents.timeupdate({ seconds: 2 });
    assert.strictEqual(requests.length, 0, 'no premature save during playback');

    harness.intervals[0].callback();
    assert.strictEqual(requests.length, 1, '15-second timer flushes accumulated playback');
    harness.clock.value = 3000;
    harness.playerEvents.timeupdate({ seconds: 3 });
    first.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 2, tempo_formatado: '00:00:02' } }) });
    await tick();
    await tick();
    assert.strictEqual(requests.length, 1, 'ACK during playback does not trigger immediate repeated saves');
    assert(harness.classes.has('is-saving'), 'new playback remains pending until next periodic save');
    assert.strictEqual(harness.classChanges.savingAdds, 1, 'pending indicator does not restart on every timeupdate');

    harness.intervals[0].callback();
    assert.strictEqual(requests.length, 2, 'next interval sends remaining playback');
    second.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 3, tempo_formatado: '00:00:03' } }) });
    await tick();
    await tick();
    assert(harness.classes.has('is-saved'), 'periodic ACK updates shortcode indicator');
    assert.strictEqual(harness.stored.values.size, 0, 'all acknowledged progress is removed from pending queue');
}

async function testHiddenLessonDoesNotSaveEverySecond() {
    const first = deferred();
    const second = deferred();
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return requests.length === 1 ? first.promise : requests.length === 2 ? second.promise
            : Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 3 } }) });
    }, { visibilityState: 'hidden' });
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 1000;
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.documentEvents.visibilitychange();
    assert.strictEqual(requests.length, 1, 'hiding the lesson saves pending playback immediately');
    harness.clock.value = 2000;
    harness.playerEvents.timeupdate({ seconds: 2 });
    first.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 1 } }) });
    await tick();
    await tick();
    assert.strictEqual(requests.length, 2, 'one new batch may be sent after the hide-triggered request');
    harness.clock.value = 3000;
    harness.playerEvents.timeupdate({ seconds: 3 });
    second.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 2 } }) });
    await tick();
    await tick();
    assert.strictEqual(requests.length, 2, 'hidden playback waits for timer rather than writing to database every second');
    harness.intervals[0].callback();
    await tick();
    await tick();
    assert.strictEqual(requests.length, 3, 'periodic save still works while lesson is hidden');
    assert.strictEqual(harness.stored.values.size, 0);
}

async function testPauseDuringPeriodicRequestFlushesAfterAck() {
    const first = deferred();
    const second = deferred();
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return requests.length === 1 ? first.promise : requests.length === 2 ? second.promise
            : Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 3 } }) });
    });
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 1000;
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.intervals[0].callback();
    harness.clock.value = 2000;
    harness.playerEvents.timeupdate({ seconds: 2 });
    harness.playerEvents.pause();
    harness.playerEvents.play({ seconds: 2 });
    first.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 1 } }) });
    await tick();
    await tick();
    assert.strictEqual(requests.length, 2, 'pause during in-flight periodic save flushes new time after ACK even if playback resumed');

    harness.clock.value = 3000;
    harness.playerEvents.timeupdate({ seconds: 3 });
    second.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 2 } }) });
    await tick();
    await tick();
    assert.strictEqual(requests.length, 2, 'forced flush does not perpetuate keepalive and resend each second');
    harness.intervals[0].callback();
    await tick();
    await tick();
    assert.strictEqual(requests.length, 3, 'next 15-second timer sends remaining progress');
    assert.strictEqual(harness.stored.values.size, 0);
}

async function testResumeAfterAcknowledgedPause() {
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: requests.length } }) });
    });
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.playerEvents.pause();
    await tick();
    await tick();
    assert.strictEqual(harness.stored.values.size, 0, 'first ACK clears the queue');

    harness.playerEvents.play({ seconds: 1 });
    harness.clock.value = 500;
    harness.playerEvents.timeupdate({ seconds: 2 });
    harness.playerEvents.pause();
    await tick();
    await tick();
    assert.strictEqual(requests.length, 2, 'resume after ACK saves new watched time');
    assert.strictEqual(requests[1].body.get('watched_intervals'), '[{"start":1,"end":2}]');
}

async function testChangesDuringRequestAndReloadReplay() {
    const first = deferred();
    const second = deferred();
    const sent = [];
    const harness = createHarness(request => {
        sent.push(request);
        return sent.length === 1 ? first.promise : second.promise;
    });
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.playerEvents.pause();
    await tick();
    harness.playerEvents.play({ seconds: 1 });
    harness.clock.value = 500;
    harness.playerEvents.timeupdate({ seconds: 2 });
    first.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 1 } }) });
    await tick();
    await tick();
    const pending = JSON.parse(harness.stored.values.get(harness.currentKey));
    assert.strictEqual(pending.intervals[0].end, 2, 'ACK for older snapshot preserves new in-flight intervals');
    assert.strictEqual(sent.length, 2, 'new in-flight intervals are re-sent');
    second.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 2 } }) });
    await tick();
    await tick();
    assert.strictEqual(harness.stored.values.size, 0, 'final ACK clears only latest snapshot');
}

async function testLateIframeAndSourceBecomeTracked() {
    const frames = [];
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: 1 } }) });
    }, { iframeSources: frames });
    assert(harness.classes.has('is-error'), 'missing player is reported rather than silently ignored');
    assert.strictEqual(harness.observers.length, 1, 'DOM changes are observed while waiting for the player');
    const frame = { src: '' };
    frames.push(frame);
    harness.observers[0].callback();
    assert.strictEqual(harness.playerFrames.length, 0, 'iframe with missing src is not bound');
    frame.src = 'https://player.vimeo.com/video/1189714750?h=test';
    harness.observers[0].callback();
    await tick();
    assert.deepStrictEqual(harness.playerFrames, [frame.src], 'player is attached after src appears');
    assert(!harness.classes.has('is-error'), 'successful ready clears the tracking warning');
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 1000;
    harness.playerEvents.timeupdate({ seconds: 1 });
    harness.playerEvents.pause();
    await tick();
    await tick();
    assert.strictEqual(requests.length, 1, 'late player still persists watched time');
}

async function testLateIframeWithoutServerVideoId() {
    const frames = [];
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), {
        iframeSources: frames, configuredVideoId: '',
    });
    assert.strictEqual(harness.getRequests.length, 0, 'unknown video cannot be fetched before discovery');
    frames.push({ src: 'https://player.vimeo.com/video/1189714750?h=test' });
    harness.observers[0].callback();
    await tick();
    assert.strictEqual(harness.getRequests.length, 1, 'newly discovered video revalidates its saved progress');
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 1000;
    harness.playerEvents.timeupdate({ seconds: 1 });
    assert.strictEqual(JSON.parse(harness.stored.values.get(harness.currentKey)).videoId, '1189714750');
}

async function testReplacedIframeKeepsProgress() {
    const frames = [{ src: 'https://player.vimeo.com/video/1189714750?h=test' }];
    const requests = [];
    const harness = createHarness(request => {
        requests.push(request);
        return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { tempo: requests.length } }) });
    }, { iframeSources: frames });
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 1000;
    harness.playerEvents.timeupdate({ seconds: 1 });
    const replacement = { src: frames[0].src };
    frames.splice(0, 1, replacement);
    harness.observers[0].callback();
    await tick();
    await tick();
    assert.strictEqual(requests.length, 1, 'replacement flushes the old player pending progress');
    harness.playerEvents.play({ seconds: 1 });
    harness.clock.value = 2000;
    harness.playerEvents.timeupdate({ seconds: 2 });
    harness.playerEvents.pause();
    await tick();
    await tick();
    assert.deepStrictEqual(harness.playerFrames, [replacement.src, replacement.src]);
    assert.strictEqual(requests.length, 2, 'replacement player persists new progress without duplicate handlers');
}

async function testPlayerReadyFailureIsVisible() {
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), {
        playerReady: () => Promise.reject(new Error('player unavailable')),
    });
    await tick();
    assert(harness.classes.has('is-error'), 'failed ready does not imply tracking is active');
    assert(harness.meta.textContent.includes('registrado'), 'student sees that progress is not being recorded');
}

async function testVimeoSdkDelayedAfterPageLoad() {
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), {
        vimeoAvailable: false,
    });
    assert(harness.classes.has('is-error'), 'missing Vimeo SDK never silently disables tracking');
    harness.window.Vimeo = harness.vimeoSdk;
    harness.intervals[0].callback();
    await tick();
    assert.strictEqual(harness.playerFrames.length, 1, 'existing periodic timer reconnects after Vimeo SDK loads');
    assert(!harness.classes.has('is-error'));
}

function testStartsWhenDocumentAlreadyLoaded() {
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), {
        readyState: 'complete', autoDOMContentLoaded: false,
    });
    assert.strictEqual(harness.playerFrames.length, 1, 'script loaded after DOMContentLoaded still binds the player');
    assert.strictEqual(harness.getRequests.length, 1, 'saved progress is still revalidated');
}

function testSelectsLessonVideoIframeAfterNonVideoIframe() {
    const videoFrame = { src: 'https://player.vimeo.com/video/1189714750?h=test' };
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), {
        iframeSources: [
            { src: 'https://vimeo.com/channels/staffpicks/42' },
            { src: 'https://player.vimeo.com/video/1189714751?h=other' },
            videoFrame,
        ],
    });
    assert.deepStrictEqual(harness.playerFrames, [videoFrame.src], 'non-video Vimeo iframe is skipped in favor of lesson video');
    harness.playerEvents.play({ seconds: 0 });
    harness.clock.value = 500;
    harness.playerEvents.timeupdate({ seconds: 1 });
    assert.strictEqual(JSON.parse(harness.stored.values.get(harness.currentKey)).videoId, '1189714750', 'selected lesson video ID is tracked');
}

function testGetRevalidationWithoutIndicator() {
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), { showIndicator: false });
    assert.strictEqual(harness.getRequests.length, 1, 'lesson visit runs GET revalidation even without progress widget');
    assert.strictEqual(harness.getRequests[0].body.get('aula_id'), '123');
}

function testIdleResumeAndFirstFraction() {
    const idle = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }));
    idle.clock.value = 60000;
    idle.playerEvents.timeupdate({ seconds: 100 });
    assert.strictEqual(idle.stored.values.size, 0, 'first late timeupdate sets baseline without counting old prefix');
    idle.playerEvents.play({ seconds: 100 });
    idle.clock.value = 60500;
    idle.playerEvents.timeupdate({ seconds: 101 });
    const resumed = JSON.parse(idle.stored.values.get(idle.currentKey));
    assert.deepStrictEqual(resumed.intervals, [{ start: 100, end: 101 }], 'resume at 100s never records [0, 100]');
    idle.playerEvents.pause();
    idle.clock.value = 61500;
    idle.playerEvents.timeupdate({ seconds: 102 });
    assert.deepStrictEqual(JSON.parse(idle.stored.values.get(idle.currentKey)).intervals, resumed.intervals, 'pause and late timeupdate do not add watched time');

    const started = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }));
    started.clock.value = 250;
    started.playerEvents.timeupdate({ seconds: 0.5 });
    const initial = JSON.parse(started.stored.values.get(started.currentKey));
    assert.deepStrictEqual(initial.intervals, [{ start: 0, end: 0.5 }], 'compatible fractional opening interval is retained');
}

function testSeekAfterIdleResetsBaseline() {
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }));
    harness.playerEvents.play({ seconds: 10 });
    harness.clock.value = 60000;
    harness.playerEvents.seeking({ seconds: 10 });
    harness.playerEvents.timeupdate({ seconds: 200 });
    assert.strictEqual(harness.stored.values.size, 0, 'timeupdates while seeking are ignored');
    harness.playerEvents.seeked({ seconds: 200 });
    harness.clock.value = 60500;
    harness.playerEvents.timeupdate({ seconds: 201 });
    const entry = JSON.parse(harness.stored.values.get(harness.currentKey));
    assert.deepStrictEqual(entry.intervals, [{ start: 200, end: 201 }], 'seek after idle discards jump and resumes from seeked baseline');
}

function testTwoTimesPlaybackAndSeekGap() {
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }));
    harness.playerEvents.play({ seconds: 0 });
    harness.playerEvents.timeupdate({ seconds: 0 });
    harness.clock.value = 1200;
    harness.playerEvents.timeupdate({ seconds: 2.4 });
    harness.clock.value = 1450;
    harness.playerEvents.timeupdate({ seconds: 200 });
    const entry = JSON.parse(harness.stored.values.get(harness.currentKey));
    assert.strictEqual(entry.intervals.length, 1, 'instantaneous seek jump is rejected');
    assert.strictEqual(entry.intervals[0].end, 2.4, '2x playback survives timeupdate events spaced over one second');
}

(async () => {
    await testFailedRequestAndNonceRetry();
    await testMemoryQueueWhenStorageDenied();
    await testClosedTabQueuesAcrossLessonsAndVideos();
    await testPeriodicSaveWithoutPerSecondResendOrBlink();
    await testHiddenLessonDoesNotSaveEverySecond();
    await testPauseDuringPeriodicRequestFlushesAfterAck();
    await testResumeAfterAcknowledgedPause();
    await testChangesDuringRequestAndReloadReplay();
    await testLateIframeAndSourceBecomeTracked();
    await testLateIframeWithoutServerVideoId();
    await testReplacedIframeKeepsProgress();
    await testPlayerReadyFailureIsVisible();
    await testVimeoSdkDelayedAfterPageLoad();
    testStartsWhenDocumentAlreadyLoaded();
    testSelectsLessonVideoIframeAfterNonVideoIframe();
    testGetRevalidationWithoutIndicator();
    testIdleResumeAndFirstFraction();
    testSeekAfterIdleResetsBaseline();
    testTwoTimesPlaybackAndSeekGap();
    process.stdout.write('OK: tracking queue, cross-tab replay, and timing tests passed\n');
})().catch(error => {
    process.stderr.write(`${error.stack}\n`);
    process.exitCode = 1;
});
