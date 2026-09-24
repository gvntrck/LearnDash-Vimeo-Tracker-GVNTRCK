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
    blogId = 9,
    userId = 7,
    storageDenied = false,
    sessionStorageDenied = false,
    showIndicator = true,
    iframeSources = null,
    queued = null,
} = {}) {
    const stored = createStorage(sharedStorage, storageDenied);
    const playerEvents = {};
    const documentEvents = {};
    const windowEvents = {};
    const getRequests = [];
    const intervals = [];
    const classes = new Set();
    const meta = { textContent: '' };
    const time = { textContent: '' };
    const indicator = {
        dataset: {},
        classList: {
            add: name => classes.add(name),
            remove: (...names) => names.forEach(name => classes.delete(name)),
            contains: name => classes.has(name),
        },
        querySelector: selector => selector === '.ldvt-watch-progress__meta' ? meta : time,
    };
    const iframe = videoId ? { src: `https://player.vimeo.com/video/${videoId}?h=test` } : null;
    const iframeCandidates = iframeSources || (iframe ? [iframe] : []);
    const playerFrames = [];
    const config = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'nonce-current-page',
        blogId,
        userId,
        lessonId,
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
            Vimeo: { Player: class { constructor(frame) { playerFrames.push(frame.src); } on(name, callback) { playerEvents[name] = callback; } } },
            crypto: { randomUUID: () => tabId },
            performance: { now: () => clock.value },
            addEventListener: (name, callback) => { windowEvents[name] = callback; },
        },
        Vimeo: null,
        document: {
            visibilityState: 'visible',
            addEventListener: (name, callback) => { documentEvents[name] = callback; },
            querySelector: selector => iframeCandidates.find(frame => (
                selector.includes('vimeo.com/video/')
                    ? frame.src.includes('vimeo.com/video/')
                    : frame.src.includes('vimeo.com')
            )) || null,
            querySelectorAll: () => showIndicator ? [indicator] : [],
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
    vm.runInNewContext(source, context);
    documentEvents.DOMContentLoaded();
    return { stored, classes, meta, config, intervals, playerEvents, windowEvents, documentEvents, getRequests, playerFrames, clock, currentKey };
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

function testSelectsLessonVideoIframeAfterNonVideoIframe() {
    const videoFrame = { src: 'https://player.vimeo.com/video/1189714750?h=test' };
    const harness = createHarness(() => Promise.resolve({ ok: true, json: async () => ({ success: true, data: {} }) }), {
        iframeSources: [
            { src: 'https://vimeo.com/channels/staffpicks/42' },
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
    await testResumeAfterAcknowledgedPause();
    await testChangesDuringRequestAndReloadReplay();
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
