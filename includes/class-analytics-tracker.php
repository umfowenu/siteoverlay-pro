<?php
/**
 * SiteOverlay Pro Analytics Tracker
 * 
 * ENHANCEMENT MODULE - Usage analytics and tracking without blocking core functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SiteOverlay_Analytics_Tracker {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers for analytics tracking
        add_action('wp_ajax_siteoverlay_track_admin_view', array($this, 'ajax_track_admin_view'));
        add_action('wp_ajax_siteoverlay_track_event', array($this, 'ajax_track_event'));

        // Admin interface
        add_action('admin_menu', array($this, 'add_analytics_page'));

        // Create database table on activation
        register_activation_hook(SITEOVERLAY_PRO_PLUGIN_FILE, array($this, 'create_analytics_table'));
    }

    /**
     * Track overlay save event
     */
    public function track_overlay_save($post_id, $overlay_url) {
        $this->track_event($post_id, $overlay_url, 'overlay_save');
    }

    /**
     * Track overlay removal event
     */
    public function track_overlay_removal($post_id) {
        $this->track_event($post_id, '', 'overlay_remove');
    }

    /**
     * Track overlay view event
     */
    public function track_overlay_view($post_id, $overlay_url) {
        $this->track_event($post_id, $overlay_url, 'overlay_view');
    }

    /**
     * Generic event tracking
     */
    private function track_event($post_id, $overlay_url, $action_type) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'siteoverlay_analytics';

        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'overlay_url' => $overlay_url,
                'action_type' => $action_type,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        // Send to Railway API for customer intelligence (non-blocking)
        $this->send_analytics_to_api($action_type, array(
            'post_id' => $post_id,
            'overlay_url' => $overlay_url
        ));
    }

    /**
     * AJAX track admin view
     */
    public function ajax_track_admin_view() {
        check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');

        $post_id = intval($_POST['post_id'] ?? 0);
        $this->track_event($post_id, '', 'admin_view');

        wp_send_json_success();
    }

    /**
     * AJAX track custom event
     */
    public function ajax_track_event() {
        check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');

        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $event_action = sanitize_text_field($_POST['event_action'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);

        $this->track_event($post_id, '', $event_type . '_' . $event_action);

        wp_send_json_success();
    }

    /**
     * Send analytics to Railway API (non-blocking)
     */
    private function send_analytics_to_api($event_type, $data) {
        $analytics_data = array(
            'event_type' => $event_type,
            'site_url' => get_site_url(),
            'license_key' => get_option('siteoverlay_license_key'),
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'site_intelligence' => $this->get_site_intelligence()
        );

        wp_remote_post('https://siteoverlay-api-production.up.railway.app/api/analytics', array(
            'timeout' => 5,
            'blocking' => false,
            'body' => json_encode($analytics_data),
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
    }

    /**
     * Get site intelligence data
     */
    private function get_site_intelligence() {
        global $wp_version;

        return array(
            'wp_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'plugin_version' => SITEOVERLAY_PRO_VERSION,
            'site_url' => get_site_url(),
            'site_title' => get_bloginfo('name'),
            'theme' => get_template(),
            'active_plugins' => get_option('active_plugins'),
            'users_count' => count_users()['total_users'],
            'posts_count' => wp_count_posts()->publish,
            'pages_count' => wp_count_posts('page')->publish
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Add analytics page to admin menu
     */
    public function add_analytics_page() {
        add_submenu_page(
            'options-general.php',
            'SiteOverlay Analytics',
            'Analytics',
            'manage_options',
            'siteoverlay-analytics',
            array($this, 'analytics_page')
        );
    }

    /**
     * Analytics page interface
     */
    public function analytics_page() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'siteoverlay_analytics';

        // Get analytics data
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $overlay_saves = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE action_type = 'overlay_save'");
        $overlay_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE action_type = 'overlay_view'");
        $overlay_removes = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE action_type = 'overlay_remove'");

        // Recent events
        $recent_events = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20"
        );

        // Top posts by overlay activity
        $top_posts = $wpdb->get_results(
            "SELECT post_id, COUNT(*) as event_count 
             FROM $table_name 
             WHERE post_id > 0 
             GROUP BY post_id 
             ORDER BY event_count DESC 
             LIMIT 10"
        );

        ?>
        <div class="wrap">
            <h1>SiteOverlay Pro Analytics</h1>

            <div class="analytics-stats">
                <h2>Overview</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Events</h3>
                        <p class="stat-number"><?php echo number_format($total_events); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Overlays Created</h3>
                        <p class="stat-number"><?php echo number_format($overlay_saves); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Overlay Views</h3>
                        <p class="stat-number"><?php echo number_format($overlay_views); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Overlays Removed</h3>
                        <p class="stat-number"><?php echo number_format($overlay_removes); ?></p>
                    </div>
                </div>
            </div>

            <div class="analytics-section">
                <h2>Top Posts by Activity</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Post Title</th>
                            <th>Post Type</th>
                            <th>Events</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_posts as $post_data): ?>
                        <?php $post = get_post($post_data->post_id); ?>
                        <tr>
                            <td>
                                <?php if ($post): ?>
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                <?php else: ?>
                                    <em>Post not found (ID: <?php echo $post_data->post_id; ?>)</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $post ? esc_html($post->post_type) : 'Unknown'; ?></td>
                            <td><?php echo number_format($post_data->event_count); ?></td>
                            <td>
                                <?php if ($post): ?>
                                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="analytics-section">
                <h2>Recent Activity</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Post</th>
                            <th>User</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_events as $event): ?>
                        <tr>
                            <td><?php echo esc_html($event->created_at); ?></td>
                            <td><?php echo esc_html(str_replace('_', ' ', ucfirst($event->action_type))); ?></td>
                            <td>
                                <?php if ($event->post_id > 0): ?>
                                    <?php $post = get_post($event->post_id); ?>
                                    <?php if ($post): ?>
                                        <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                    <?php else: ?>
                                        Post ID: <?php echo $event->post_id; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em>Global</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($event->user_id > 0): ?>
                                    <?php $user = get_user_by('id', $event->user_id); ?>
                                    <?php echo $user ? esc_html($user->display_name) : 'Unknown User'; ?>
                                <?php else: ?>
                                    <em>Guest</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($event->ip_address); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .analytics-stats {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }

        .analytics-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        </style>
        <?php
    }

    /**
     * Create analytics table
     */
    public function create_analytics_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'siteoverlay_analytics';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            overlay_url text NOT NULL,
            action_type varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
