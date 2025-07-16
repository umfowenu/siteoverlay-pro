<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteOverlay_Email_Capture {
    
    public function __construct() {
        add_action('wp_ajax_siteoverlay_capture_email', array($this, 'capture_email'));
        add_action('wp_ajax_nopriv_siteoverlay_capture_email', array($this, 'capture_email'));
        add_action('admin_menu', array($this, 'add_email_page'));
        
        // Create email table on activation
        register_activation_hook(__FILE__, array($this, 'create_email_table'));
    }
    
    public function capture_email() {
        check_ajax_referer('siteoverlay_email_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $source = sanitize_text_field($_POST['source']);
        
        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'siteoverlay_emails';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'source' => $source,
                'date_added' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            )
        );
        
        if ($result) {
            // Send to external email service (MailChimp, ConvertKit, etc.)
            $this->send_to_email_service($email, $source);
            wp_send_json_success('Email captured successfully');
        } else {
            wp_send_json_error('Failed to save email');
        }
    }
    
    private function send_to_email_service($email, $source) {
        // Integration with email service providers
        $api_key = get_option('siteoverlay_email_api_key');
        $list_id = get_option('siteoverlay_email_list_id');
        
        if (!$api_key || !$list_id) return;
        
        // Example MailChimp integration
        $data = array(
            'email_address' => $email,
            'status' => 'subscribed',
            'merge_fields' => array(
                'SOURCE' => $source
            )
        );
        
        wp_remote_post('https://us1.api.mailchimp.com/3.0/lists/' . $list_id . '/members', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $api_key)
            ),
            'body' => json_encode($data)
        ));
    }
    
    public function add_email_page() {
        add_submenu_page(
            'options-general.php',
            'Email Capture',
            'Email Capture',
            'manage_options',
            'siteoverlay-emails',
            array($this, 'email_page')
        );
    }
    
    public function email_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siteoverlay_emails';
        $emails = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date_added DESC LIMIT 100");
        ?>
        
        <div class="wrap">
            <h1>Email Capture List</h1>
            
            <div class="email-stats">
                <h2>Statistics</h2>
                <p><strong>Total Emails:</strong> <?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name"); ?></p>
                <p><strong>This Month:</strong> <?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"); ?></p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Source</th>
                        <th>Date Added</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $email): ?>
                    <tr>
                        <td><?php echo esc_html($email->email); ?></td>
                        <td><?php echo esc_html($email->source); ?></td>
                        <td><?php echo esc_html($email->date_added); ?></td>
                        <td><?php echo esc_html($email->ip_address); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function create_email_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siteoverlay_emails';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            source varchar(50) DEFAULT '',
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT '',
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

new SiteOverlay_Email_Capture();
?>