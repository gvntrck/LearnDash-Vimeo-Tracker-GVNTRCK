<?php

$test_wp_root = sys_get_temp_dir() . '/ldvt-test-wordpress-' . getmypid() . '/';
$test_upgrade_dir = $test_wp_root . 'wp-admin/includes';
if (!is_dir($test_upgrade_dir)) {
    mkdir($test_upgrade_dir, 0777, true);
}
file_put_contents($test_upgrade_dir . '/upgrade.php', "<?php\n");
define('ABSPATH', $test_wp_root);
define('DAY_IN_SECONDS', 86400);
define('LDVT_VERSION', '1.9.12');
define('LDVT_PLUGIN_URL', 'https://example.test/plugins/ldvt/');
define('LDVT_SETTINGS_OPTION', 'ldvt_settings');

class TestResponse extends Exception
{
    public $success;
    public $data;
    public $status;

    public function __construct($success, $data, $status = 200)
    {
        $this->success = $success;
        $this->data = $data;
        $this->status = $status;
    }
}

class TestWpError
{
}

class TestWpdb
{
    public $prefix = 'wp_';
    public $rows = array();
    public $last_query = '';
    public $last_args = array();
    public $fail_query = false;
    public $fail_lock = false;
    public $lock_held = false;
    public $query_count = 0;
    public $last_affected = null;
    public $next_zero = false;
    public $fail_query_at = 0;
    public $columns = array('watched_intervals' => true, 'tempo_verificado' => true, 'ultima_confirmacao' => true);
    public $apply_schema = true;
    public $db_delta_count = 0;
    public $schema_sql = '';

    public function prepare($query, ...$args)
    {
        $this->last_query = $query;
        $this->last_args = $args;
        return $query;
    }

    public function esc_like($value)
    {
        return $value;
    }

    public function get_var($query)
    {
        if (strpos($query, 'SHOW TABLES') === 0) {
            return 'wp_tempo_video';
        }
        if (strpos($query, 'SHOW COLUMNS') === 0) {
            $column = $this->last_args[0] ?? '';
            return isset($this->columns[$column]) ? $column : null;
        }
        if (strpos($query, 'GET_LOCK') !== false) {
            if ($this->fail_lock || $this->lock_held) {
                return 0;
            }
            $this->lock_held = true;
            return 1;
        }
        if (strpos($query, 'RELEASE_LOCK') !== false) {
            $this->lock_held = false;
            return 1;
        }
        return null;
    }

    public function get_row($query)
    {
        $key = $this->last_args[0] . ':' . $this->last_args[1];
        return isset($this->rows[$key]) ? clone $this->rows[$key] : null;
    }

    public function query($query)
    {
        $this->query_count++;
        if ($this->fail_query || $this->fail_query_at === $this->query_count) {
            return false;
        }
        $args = $this->last_args;
        $key = $args[0] . ':' . $args[1];
        $row = (object) array(
            'tempo' => (int) $args[2],
            'tempo_verificado' => (int) $args[3],
            'ultima_confirmacao' => $args[4],
            'curso_id' => (int) $args[5],
            'aula_id' => (int) $args[6],
            'duracao_total' => (int) $args[7],
            'watched_intervals' => $args[8],
            'data_registro' => $args[9],
        );
        $same = isset($this->rows[$key]) && serialize($this->rows[$key]) === serialize($row);
        $this->rows[$key] = $row;
        $this->last_affected = $this->next_zero || $same ? 0 : 1;
        $this->next_zero = false;
        return $this->last_affected;
    }

    public function get_charset_collate()
    {
        return '';
    }
}

$wpdb = new TestWpdb();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['test_options'] = array('ldvt_db_version' => LDVT_VERSION);
$GLOBALS['test_transients'] = array();
$GLOBALS['test_posts'] = array(
    123 => array('type' => 'sfwd-lessons', 'content' => '<iframe src="https://player.vimeo.com/video/1189714750"></iframe>', 'meta' => ''),
    124 => array('type' => 'sfwd-topic', 'content' => '', 'meta' => ''),
    125 => array('type' => 'post', 'content' => '<iframe src="https://player.vimeo.com/video/1189714750"></iframe>', 'meta' => ''),
);
$GLOBALS['test_oembed'] = new TestWpError();
$GLOBALS['test_completions'] = 0;
$GLOBALS['test_nonce'] = 'valid';
$GLOBALS['test_auto_completion'] = 1;
$GLOBALS['test_enrolled_courses'] = array(88);
$GLOBALS['test_has_access'] = true;
$GLOBALS['test_enqueued_scripts'] = array();
$GLOBALS['test_localized_scripts'] = array();
$GLOBALS['test_queried_object_id'] = 123;
$GLOBALS['test_is_singular'] = true;
$GLOBALS['test_remote_calls'] = 0;

function add_action(...$args) {}
function add_shortcode(...$args) {}
function get_option($name, $default = false)
{
    if ($name === 'ldvt_settings') {
        return array('auto_complete_enabled' => $GLOBALS['test_auto_completion'], 'completion_threshold' => 70);
    }
    return $GLOBALS['test_options'][$name] ?? $default;
}
function update_option($name, $value)
{
    $GLOBALS['test_options'][$name] = $value;
    return true;
}
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return is_string($value) ? trim(strip_tags($value)) : ''; }
function absint($value) { return abs((int) $value); }
function wp_verify_nonce($nonce, $action) { return $nonce === $GLOBALS['test_nonce'] && $action === 'ldvt_progress'; }
function wp_salt($scheme = 'auth') { return 'test-salt-' . $scheme; }
function get_current_blog_id() { return 5; }
function get_current_user_id() { return 7; }
function is_user_logged_in() { return true; }
function get_queried_object_id() { return $GLOBALS['test_queried_object_id']; }
function get_the_ID() { return 125; }
function is_singular() { return $GLOBALS['test_is_singular']; }
function admin_url($path) { return 'https://example.test/' . $path; }
function wp_create_nonce($action) { return 'valid'; }
function wp_enqueue_script(...$args) { $GLOBALS['test_enqueued_scripts'][] = $args; }
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['test_localized_scripts'][$object_name] = $data; }
function current_time($type) { return gmdate('Y-m-d H:i:s'); }
function wp_json_encode($value) { return json_encode($value); }
function date_i18n($format, $timestamp) { return gmdate($format === 'd/m/Y H:i' ? 'd/m/Y H:i' : $format, $timestamp); }
function is_wp_error($value) { return $value instanceof TestWpError; }
function wp_remote_get($url, $args = array())
{
    $GLOBALS['last_remote_request'] = array($url, $args);
    $GLOBALS['test_remote_calls']++;
    $GLOBALS['http_while_locked'] = $GLOBALS['wpdb']->lock_held;
    return $GLOBALS['test_oembed'];
}
function wp_remote_retrieve_response_code($response) { return $response['code'] ?? 0; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
function get_transient($key) { return $GLOBALS['test_transients'][$key] ?? false; }
function set_transient($key, $value, $expiration) { $GLOBALS['test_transients'][$key] = $value; return true; }
function get_post_type($post_id) { return $GLOBALS['test_posts'][$post_id]['type'] ?? ''; }
function get_post_field($field, $post_id) { return $GLOBALS['test_posts'][$post_id]['content'] ?? ''; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['test_posts'][$post_id]['meta'] ?? ''; }
function learndash_get_course_id($step_id) { return in_array((int) $step_id, array(123, 124), true) ? 88 : 0; }
function learndash_user_get_enrolled_courses($user_id) { return $GLOBALS['test_enrolled_courses']; }
function sfwd_lms_has_access($course_id, $user_id) { return $GLOBALS['test_has_access']; }
function learndash_user_progress_is_step_complete($user_id, $course_id, $step_id) { return false; }
function learndash_can_complete_step($user_id, $step_id, $course_id) { return true; }
function learndash_process_mark_complete($user_id, $step_id, $mark_complete, $course_id, $force) { $GLOBALS['test_completions']++; return true; }
function wp_send_json_error($data, $status = 400) { throw new TestResponse(false, $data, $status); }
function wp_send_json_success($data) { throw new TestResponse(true, $data); }
function apply_filters($name, $value, $settings = null) { return $value; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, (array) $args); }
function dbDelta($sql)
{
    $GLOBALS['wpdb']->db_delta_count++;
    $GLOBALS['wpdb']->schema_sql = $sql;
    if ($GLOBALS['wpdb']->apply_schema) {
        $GLOBALS['wpdb']->columns['tempo_verificado'] = true;
        $GLOBALS['wpdb']->columns['ultima_confirmacao'] = true;
    }
    return array();
}

require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/settings.php';
require dirname(__DIR__) . '/includes/completion.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/frontend/shortcode.php';
require dirname(__DIR__) . '/includes/ajax.php';
require dirname(__DIR__) . '/includes/frontend/tracking.php';

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

function reset_test_state()
{
    $GLOBALS['wpdb']->rows = array();
    $GLOBALS['wpdb']->fail_query = false;
    $GLOBALS['wpdb']->fail_lock = false;
    $GLOBALS['wpdb']->lock_held = false;
    $GLOBALS['wpdb']->query_count = 0;
    $GLOBALS['wpdb']->last_affected = null;
    $GLOBALS['wpdb']->next_zero = false;
    $GLOBALS['wpdb']->fail_query_at = 0;
    $GLOBALS['wpdb']->columns = array('watched_intervals' => true, 'tempo_verificado' => true, 'ultima_confirmacao' => true);
    $GLOBALS['wpdb']->apply_schema = true;
    $GLOBALS['test_transients'] = array();
    $GLOBALS['test_oembed'] = new TestWpError();
    $GLOBALS['test_completions'] = 0;
    $GLOBALS['test_auto_completion'] = 1;
    $GLOBALS['test_enrolled_courses'] = array(88);
    $GLOBALS['test_has_access'] = true;
    $GLOBALS['http_while_locked'] = false;
    $GLOBALS['test_nonce'] = 'valid';
    $GLOBALS['test_remote_calls'] = 0;
    $GLOBALS['test_enqueued_scripts'] = array();
    $GLOBALS['test_localized_scripts'] = array();
    $GLOBALS['test_queried_object_id'] = 123;
    $GLOBALS['test_is_singular'] = true;
}

function submit_progress($video_id, $intervals, $lesson_id = 123, $nonce = 'valid', $signature_valid = true)
{
    $started_at = time() - 30;
    $_POST = array(
        'nonce' => $nonce,
        'video_id' => (string) $video_id,
        'watched_intervals' => json_encode($intervals),
        'tempo' => 999999,
        'duracao_total' => 1,
        'curso_id' => 999,
        'aula_id' => $lesson_id,
        'page_started_at' => (string) $started_at,
        'page_signature' => $signature_valid ? ldvt_create_page_start_signature(7, $lesson_id, $started_at) : 'invalid',
    );

    try {
        ldvt_salvar_tempo_video_callback();
    } catch (TestResponse $response) {
        return $response;
    }
    throw new RuntimeException('AJAX callback did not return a response');
}

function fetch_saved_progress($video_id, $lesson_id = 123)
{
    $_POST = array('nonce' => 'valid', 'video_id' => (string) $video_id, 'aula_id' => $lesson_id);
    try {
        ldvt_get_tempo_video_callback();
    } catch (TestResponse $response) {
        return $response;
    }
    throw new RuntimeException('GET callback did not return a response');
}

reset_test_state();
$GLOBALS['test_posts'][124]['meta'] = json_encode(array(array(
    'elType' => 'widget',
    'widgetType' => 'html',
    'settings' => array('html' => '<iframe src="https://player.vimeo.com/video/1189714751"></iframe>'),
)));
expect_true(ldvt_get_post_vimeo_video_id(123) === '1189714750', 'post_content mapping detected');
expect_true(ldvt_calculate_watched_time_from_intervals(ldvt_parse_watched_intervals_json('[{"start":0,"end":9173}]')) === 9173, 'valid 2.5-hour Vimeo interval is preserved');
expect_true(in_array('1189714751', ldvt_get_post_vimeo_video_ids(124), true), 'Elementor HTML widget mapping detected');
expect_true(ldvt_resolve_video_lesson('1189714751', 124)['valid'], 'Elementor video maps to a LearnDash topic');
expect_true(!ldvt_resolve_video_lesson('1189714751', 125)['valid'], 'non-LearnDash post cannot authorize completion');
ldvt_vimeo_tracking_script();
$localized_tracking = $GLOBALS['test_localized_scripts']['LDVTTracking'];
expect_true($localized_tracking['lessonId'] === 123 && get_the_ID() === 125, 'footer signs queried lesson instead of mutable global post');
expect_true($localized_tracking['videoId'] === '1189714750', 'footer identifies the queried lesson video before lazy iframe appears');
expect_true($localized_tracking['pageSignature'] === ldvt_create_page_start_signature(7, 123, $localized_tracking['pageStartedAt']), 'page-start signature binds queried lesson');
$script_count = count($GLOBALS['test_enqueued_scripts']);
$GLOBALS['test_queried_object_id'] = 125;
ldvt_vimeo_tracking_script();
expect_true(count($GLOBALS['test_enqueued_scripts']) === $script_count, 'non-LearnDash singular object does not enqueue lesson tracking');

$GLOBALS['test_oembed'] = array('code' => 200, 'body' => '{malformed');
expect_true(ldvt_get_vimeo_duration('1189714752') === 0, 'malformed oEmbed rejected');
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 100, 'video_id' => 999)));
expect_true(ldvt_get_vimeo_duration('1189714753') === 0, 'oEmbed ID mismatch rejected');
expect_true(strpos($GLOBALS['last_remote_request'][0], 'https://vimeo.com/api/oembed.json?') === 0, 'oEmbed endpoint host is fixed');
expect_true($GLOBALS['last_remote_request'][1]['timeout'] === 3, 'oEmbed timeout is bounded');

reset_test_state();
$GLOBALS['test_oembed'] = new TestWpError();
$save = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
expect_true($save->success && $save->data['completion_pending'], 'initial oEmbed failure leaves eligible progress pending');
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$get = fetch_saved_progress('1189714750');
expect_true($get->success && $get->data['step_completed'] && !$get->data['completion_pending'], 'returning GET revalidates and completes eligible saved progress');
expect_true((int) $GLOBALS['wpdb']->rows['7:1189714750']->tempo === 45 && (int) $GLOBALS['wpdb']->rows['7:1189714750']->duracao_total === 50, 'GET revalidation keeps raw progress and stores authoritative duration');

reset_test_state();
$GLOBALS['test_oembed'] = new TestWpError();
submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
$get = fetch_saved_progress('1189714750');
expect_true($get->success && $get->data['tempo'] === 45 && $get->data['completion_pending'], 'GET oEmbed failure returns existing progress as pending');
expect_true((int) $GLOBALS['wpdb']->rows['7:1189714750']->tempo === 45 && $GLOBALS['test_completions'] === 0, 'GET failure cannot erase or complete saved progress');

reset_test_state();
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$get = fetch_saved_progress('1189714750');
expect_true($get->success && !$get->data['has_record'] && $GLOBALS['test_remote_calls'] === 0, 'GET without a record does not contact Vimeo');

reset_test_state();
$GLOBALS['wpdb']->rows['7:1189714750'] = (object) array(
    'tempo' => 20,
    'tempo_verificado' => 20,
    'ultima_confirmacao' => gmdate('Y-m-d H:i:s'),
    'curso_id' => 88,
    'aula_id' => 123,
    'duracao_total' => 50,
    'watched_intervals' => '[{"start":0,"end":20}]',
    'data_registro' => gmdate('Y-m-d H:i:s'),
);
$GLOBALS['test_transients']['ldvt_vimeo_duration_1189714750'] = 50;
$get = fetch_saved_progress('1189714750');
expect_true($get->success && !$get->data['completion_pending'] && $GLOBALS['test_remote_calls'] === 0, 'known below-threshold GET does not query Vimeo or mark pending');

reset_test_state();
$GLOBALS['wpdb']->rows['7:1189714750'] = (object) array(
    'tempo' => 45,
    'tempo_verificado' => 45,
    'ultima_confirmacao' => gmdate('Y-m-d H:i:s'),
    'curso_id' => 88,
    'aula_id' => 123,
    'duracao_total' => 0,
    'watched_intervals' => '[{"start":0,"end":45}]',
    'data_registro' => gmdate('Y-m-d H:i:s'),
);
$GLOBALS['wpdb']->fail_query_at = 1;
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$get = fetch_saved_progress('1189714750');
expect_true($get->success && $get->data['tempo'] === 45 && $get->data['completion_pending'], 'GET duration-write failure still returns progress successfully and pending');
expect_true((int) $GLOBALS['wpdb']->rows['7:1189714750']->tempo === 45 && $GLOBALS['test_completions'] === 0, 'GET metadata write failure preserves progress and skips completion');

reset_test_state();
$GLOBALS['test_oembed'] = new TestWpError();
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
$row = $GLOBALS['wpdb']->rows['7:1189714750'];
expect_true($response->success && $response->data['completion_pending'], 'missing oEmbed saves progress and reports pending completion');
expect_true((int) $row->tempo === 45 && (int) $row->duracao_total === 0, 'browser duration is ignored while raw progress persists');
expect_true($GLOBALS['test_completions'] === 0, 'missing duration never marks a step complete');
expect_true(empty($GLOBALS['http_while_locked']), 'oEmbed request runs only after advisory lock release');

reset_test_state();
$GLOBALS['test_enrolled_courses'] = array();
$GLOBALS['test_has_access'] = true;
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
$row = $GLOBALS['wpdb']->rows['7:1189714750'];
expect_true($response->success && $response->data['step_completed'], 'open course access permits completion without enrollment list membership');
expect_true((int) $row->tempo_verificado === 45 && (int) $row->duracao_total === 50, 'server clock credit and authoritative duration persisted');
expect_true($GLOBALS['test_completions'] === 1, 'verified threshold invokes LearnDash completion');

reset_test_state();
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 20)));
expect_true($response->success && !$response->data['completion_pending'] && $response->data['message'] === 'Progresso salvo', 'known below-threshold progress is not reported pending');

reset_test_state();
$GLOBALS['test_auto_completion'] = 0;
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
expect_true($response->success && !$response->data['completion_pending'] && !$response->data['step_completed'], 'disabled auto-completion is not reported pending');

reset_test_state();
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)), 123, 'valid', false);
expect_true($response->success && $response->data['completion_pending'] && $GLOBALS['test_completions'] === 0, 'clock-insufficient eligible progress remains pending, not complete');

reset_test_state();
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714751')));
$response = submit_progress('1189714751', array(array('start' => 0, 'end' => 45)), 123);
expect_true($response->success && $response->data['completion_pending'] && $GLOBALS['test_completions'] === 0, 'unverifiable video-to-lesson link preserves eligible progress as pending');

reset_test_state();
$GLOBALS['wpdb']->rows['7:1189714750'] = (object) array(
    'tempo' => 0,
    'tempo_verificado' => 0,
    'ultima_confirmacao' => null,
    'curso_id' => 88,
    'aula_id' => 123,
    'duracao_total' => 77,
    'watched_intervals' => '[]',
    'data_registro' => gmdate('Y-m-d H:i:s'),
);
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 2)));
expect_true($response->success && (int) $GLOBALS['wpdb']->rows['7:1189714750']->duracao_total === 77, 'legacy report duration is preserved when oEmbed is unavailable');

reset_test_state();
$GLOBALS['wpdb']->rows['7:1189714750'] = (object) array(
    'tempo' => 0,
    'tempo_verificado' => 0,
    'ultima_confirmacao' => null,
    'curso_id' => 88,
    'aula_id' => 123,
    'duracao_total' => 77,
    'watched_intervals' => '[]',
    'data_registro' => gmdate('Y-m-d H:i:s'),
);
$GLOBALS['wpdb']->fail_query_at = 2;
$GLOBALS['test_oembed'] = array('code' => 200, 'body' => json_encode(array('duration' => 50, 'video_id' => '1189714750')));
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
$row = $GLOBALS['wpdb']->rows['7:1189714750'];
expect_true($response->success && $response->data['completion_pending'] && strpos($response->data['message'], 'não foi possível atualizar') !== false, 'duration update failure still ACKs already-saved progress with warning');
expect_true((int) $row->tempo === 45 && (int) $row->duracao_total === 77 && $GLOBALS['test_completions'] === 0, 'failed duration update preserves progress and prior report duration without completing');

reset_test_state();
$GLOBALS['test_oembed'] = new TestWpError();
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 30)));
$verified_once = (int) $GLOBALS['wpdb']->rows['7:1189714750']->tempo_verificado;
$GLOBALS['wpdb']->next_zero = true;
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 30)));
expect_true($response->success && $GLOBALS['wpdb']->last_affected === 0, 'zero-row idempotent write is acknowledged');
expect_true((int) $GLOBALS['wpdb']->rows['7:1189714750']->tempo_verificado === $verified_once, 'interval replay does not increase verified time');
submit_progress('1189714750', array(array('start' => 30, 'end' => 45)));
$row = $GLOBALS['wpdb']->rows['7:1189714750'];
expect_true((int) $row->tempo === 45 && count(json_decode($row->watched_intervals, true)) === 1, 'separate tab intervals merge without loss');

reset_test_state();
$GLOBALS['wpdb']->rows['7:1189714750'] = (object) array(
    'tempo' => 100,
    'tempo_verificado' => 0,
    'ultima_confirmacao' => null,
    'curso_id' => 88,
    'aula_id' => 123,
    'duracao_total' => 60,
    'watched_intervals' => '{broken',
    'data_registro' => gmdate('Y-m-d H:i:s'),
);
submit_progress('1189714750', array(array('start' => 100, 'end' => 110)));
$row = $GLOBALS['wpdb']->rows['7:1189714750'];
expect_true((int) $row->tempo === 110, 'legacy time retained when stored interval JSON is invalid');
expect_true(json_decode($row->watched_intervals, true)[0]['end'] === 110, 'legacy progress and new intervals merge without duration clipping');

reset_test_state();
$GLOBALS['wpdb']->fail_query = true;
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 45)));
expect_true(!$response->success && $GLOBALS['test_completions'] === 0, 'SQL failure returns no ACK and no completion');
expect_true(!$GLOBALS['wpdb']->lock_held, 'SQL failure still releases the advisory lock');
expect_true(empty($GLOBALS['wpdb']->rows), 'SQL failure creates no fake progress row');

reset_test_state();
$queries_before = $GLOBALS['wpdb']->query_count;
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 10)), 123, 'invalid');
expect_true(!$response->success && $GLOBALS['wpdb']->query_count === $queries_before, 'false nonce rejects request before database write');

reset_test_state();
$_POST = array('nonce' => 'valid', 'video_id' => '1189714750', 'watched_intervals' => '{invalid');
try {
    ldvt_salvar_tempo_video_callback();
    $invalid_intervals = null;
} catch (TestResponse $response) {
    $invalid_intervals = $response;
}
expect_true($invalid_intervals instanceof TestResponse && !$invalid_intervals->success && $GLOBALS['wpdb']->query_count === 0, 'invalid new intervals never fall back to client tempo');

reset_test_state();
$GLOBALS['wpdb']->fail_lock = true;
$response = submit_progress('1189714750', array(array('start' => 0, 'end' => 10)));
expect_true(!$response->success && empty($GLOBALS['wpdb']->rows), 'lock failure asks client to retry without writing');

reset_test_state();
$GLOBALS['wpdb']->rows['7:1189714750'] = (object) array('tempo' => 40, 'watched_intervals' => '[{"start":0,"end":40}]');
$history_before_migration = serialize($GLOBALS['wpdb']->rows);
$GLOBALS['test_options']['ldvt_db_version'] = '1.9.6';
$GLOBALS['wpdb']->columns = array('watched_intervals' => true, 'tempo_verificado' => true);
$GLOBALS['wpdb']->apply_schema = false;
ldvt_criar_tabela_tempo_video();
expect_true($GLOBALS['test_options']['ldvt_db_version'] === '1.9.6', 'database version stays old until both clock columns exist');
expect_true(serialize($GLOBALS['wpdb']->rows) === $history_before_migration, 'failed schema expansion preserves existing rows');
$GLOBALS['wpdb']->apply_schema = true;
ldvt_criar_tabela_tempo_video();
expect_true($GLOBALS['test_options']['ldvt_db_version'] === LDVT_VERSION, 'database version advances after both clock columns are present');
expect_true(isset($GLOBALS['wpdb']->columns['tempo_verificado'], $GLOBALS['wpdb']->columns['ultima_confirmacao']), 'migration adds both required clock columns');
expect_true(strpos($GLOBALS['wpdb']->schema_sql, 'tempo_verificado INT NOT NULL DEFAULT 0') !== false && strpos($GLOBALS['wpdb']->schema_sql, 'ultima_confirmacao DATETIME NULL') !== false, 'migration schema keeps required column definitions');
expect_true(serialize($GLOBALS['wpdb']->rows) === $history_before_migration, 'successful schema expansion preserves existing rows');
$delta_count = $GLOBALS['wpdb']->db_delta_count;
ldvt_criar_tabela_tempo_video();
expect_true($GLOBALS['wpdb']->db_delta_count === $delta_count, 'current schema version skips repeated dbDelta');

fwrite(STDOUT, "OK: reliability and security regression tests passed\n");
