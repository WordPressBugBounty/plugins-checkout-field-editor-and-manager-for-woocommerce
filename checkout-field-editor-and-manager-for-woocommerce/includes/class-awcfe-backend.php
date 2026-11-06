<?php

if (!defined('ABSPATH'))
    exit;

class AWCFE_Backend
{

    /**
     * @var    object
     * @access  private
     * @since    1.0.0
     */
    private static $_instance = null;

    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;
    public $hook_suffix = array();
    public $plugin_slug;

    /**
     * Constructor function.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function __construct($file = '', $version = '1.0.0')
    {
        $this->_version = $version;
        $this->_token = AWCFE_TOKEN;
        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

        $this->plugin_slug = 'abc';

        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        register_activation_hook($this->file, array($this, 'install'));

        add_action('admin_menu', array($this, 'register_root_page'),999);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'), 10, 1);

        // add_filter('woocommerce_admin_billing_fields', array($this, 'admin_billing_fields'), 15, 1);
        // add_filter('woocommerce_admin_shipping_fields', array($this, 'admin_shipping_fields'), 15, 1);

        add_action( '‌woocommerce_before_order_object_save', array($this, 'before_order_object_save'), 10, 2);

        // add_action('pll_init', array($this, 'pll_init')); // poly lang inits

        $plugin = plugin_basename($this->file);
        add_filter("plugin_action_links_$plugin", array($this, 'add_settings_link'));

        add_action('init', array($this, 'awcfe_init'));
        add_action('admin_notices', array($this, 'awcfe_admin_notices'));
        add_action('admin_head', array($this, 'awcfe_notification_scripting'));
        add_action('wp_ajax_awcfe_rating', array($this, 'awcfe_rating') );

        // deactivation form
        add_action( 'admin_footer', array($this, 'awcfe_deactivation_form') );
        add_action( 'wp_ajax_awcfe_deactivation_feedback', array($this, 'awcfe_deactivation_feedback') );

        add_action('plugins_loaded', [$this, 'aco_check_checkout_page_type']);
        add_action('admin_enqueue_scripts', [$this, 'aco_enqueue_admin_script']);

    }
    public function before_order_object_save($order, $ata_store){
        // $order
        $ata_store;
    }
    /**
     *
     *
     * Ensures only one instance of WCPA is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see WordPress_Plugin_Template()
     * @return Main WCPA instance
     */
    public static function instance($file = '', $version = '1.0.0')
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }
        return self::$_instance;
    }

    public function admin_billing_fields($billing_fields)
    {
        return $this->admin_order_fields($billing_fields, 'billing');
    }

    public function admin_order_fields($billing_fields, $section)
    {

        global $post;
        $order_id = $post->ID;
        $order   = $order_id ? wc_get_order( $order_id ) : null;
        // $meta_data = get_post_meta($order_id, AWCFE_ORDER_META_KEY, true);
        $meta_data = $order->get_meta(AWCFE_ORDER_META_KEY, true);

        if (isset($meta_data[$section]) && is_array($meta_data[$section])) {
            foreach ($meta_data[$section] as $v) {
                if (!in_array($v['type'], ['paragraph', 'header'])) {

                    $billing_fields[str_replace($section.'_','',$v['name'])] = array(
                        'label' => $v['label'],
//                        'id' => $v['name'],
                        'show' => true
//                        'value' => $v['value'],
                    );
                }
            }
        }
        return $billing_fields;
    }

    public function admin_shipping_fields($billing_fields)
    {

        return $this->admin_order_fields($billing_fields, 'shipping');
    }

    public function register_root_page()
    {

        $this->hook_suffix[] = add_submenu_page(
            'woocommerce',
            __('WooCommerce Checkout Fields Editor', 'checkout-field-editor-and-manager-for-woocommerce'),
            __('Checkout Fields', 'checkout-field-editor-and-manager-for-woocommerce'),
            'manage_woocommerce',
            'awcfe_admin_ui',
            array($this, 'admin_ui')
        );
//
        $this->hook_suffix[] = add_submenu_page(
            'options-general.php',
            __('Checkout Fields Editor Settings', 'checkout-field-editor-and-manager-for-woocommerce'),
            __('Checkout Fields', 'checkout-field-editor-and-manager-for-woocommerce'),
            'manage_woocommerce',
            'awcfe_admin_settings',
            array($this, 'admin_settings')
        );
//
    }

    public function admin_ui()
    {
        AWCFE_Backend::view('admin-root', []);
    }
//
    public function admin_settings()
    {
        AWCFE_Backend::view('admin-settings', []);
    }
//
    public function add_settings_link($links)
    {
        $settings = '<a href="' . admin_url('options-general.php?page=awcfe_admin_settings#/') . '">' . __('Settings','checkout-field-editor-and-manager-for-woocommerce') . '</a>';
        $settings1 = '<a href="' . admin_url('admin.php?page=awcfe_admin_ui#/') . '">' . __('Checkout Fields','checkout-field-editor-and-manager-for-woocommerce') . '</a>';
		$upgrade    = '<a href="https://acowebs.com/woocommerce-checkout-field-editor-and-manager/" target="_blank" style="font-weight:600;color:#6D71F9;">' . __('Upgrade to PRO','checkout-field-editor-and-manager-for-woocommerce') . '</a>';
        array_push($links, $settings);
        array_push($links, $settings1);

        array_push($links, $upgrade);
        return $links;
    }

    /**
     *    Create post type forms
     */

    static function view($view, $data = array())
    {
        extract($data);
        include(plugin_dir_path(__FILE__) . 'views/' . $view . '.php');
    }

    /**
     * Load admin CSS.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function admin_enqueue_styles($hook = '')
    {
        wp_register_style($this->_token . '-admin', esc_url($this->assets_url) . 'css/backend.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-admin');
    }

    /**
     * Load admin Javascript.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function admin_enqueue_scripts($hook = '')
    {
        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {
            return;
        }


        $screen = get_current_screen();

        wp_enqueue_script('jquery');
        // deactivation form js
        if ( $screen->id == 'plugins' ) {
            wp_enqueue_style('acowebs-feedback-ui', esc_url($this->assets_url) . 'acowebs-ui-kit/acowebs-feedback-ui.css', array(), $this->_version);
            wp_enqueue_script($this->_token . '-deactivation-message', esc_url($this->assets_url) . 'js/message.js', array(), $this->_version, true);
            wp_localize_script($this->_token . '-deactivation-message', 'awcfeDeactivation', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('awcfe_deactivation_feedback'),
                'slug'    => AWCFE_SLUG,
            ));
        }

        if (in_array($screen->id, $this->hook_suffix)) {
            $ml = new AWCFE_Ml();
            if (!wp_script_is('wp-i18n', 'registered')) {
                wp_register_script('wp-i18n', esc_url($this->assets_url) . 'js/i18n.min.js', array('jquery'), $this->_version, true);
            }


            wp_enqueue_script($this->_token . '-backend', esc_url($this->assets_url) . 'js/backend.js', array('wp-i18n'), $this->_version, true);
            wp_localize_script($this->_token . '-backend', 'awcfe_object', array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url('awcfe/v1/'),
                    'isMlActive' => $ml->is_active(),
                    'ml' => $ml->is_active() ? [
                        'currentLang' => $ml->current_language(),
                        'isDefault' => $ml->is_default_lan() ? $ml->is_default_lan() : (($ml->current_language() === 'all') ? true : false)
                    ] : false,
                    
                    // 'newpage_url' => admin_url('admin.php?page=awcfe_admin_NewPage#/'),
                    'settings_url' =>  admin_url('options-general.php?page=awcfe_admin_settings#/')

                )
            );

      		wp_set_script_translations($this->_token . '-backend', 'checkout-field-editor-and-manager-for-woocommerce' );
        }
    }


    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Installation. Runs on activation.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function install()
    {
        $this->_log_version_number();

    }

    /**
     * Log the plugin version number.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    private function _log_version_number()
    {
        update_option($this->_token . '_version', $this->_version);
    }

    // public function pll_init()
    // {
    //     $ml = new AWCFE_Ml();
    //     if ($ml->is_active()) {
    //         $ml->settings_to_ml_poly();
    //     }
    //
    // }

	/* Admin notice */

	    public function awcfe_init(){

        if ( false === get_option('awcfe_install_date') ){
           add_option('awcfe_install_date', date("d-m-Y"), '', 'yes');
        }

      }

	  public function awcfe_admin_notices(){

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$screen = get_current_screen();
			if ($screen->id === 'dashboard' || $screen->id === 'woocommerce_page_awcfe_admin_ui'){
        if(!isset($_COOKIE['awcfeShowRating'])){
          if ( get_option('awcfe_rate_us') === false || get_option('awcfe_rate_us') == '' ){
             if ( get_option('awcfe_install_date') != '' ){
               $setDate = strtotime(get_option('awcfe_install_date'));
               $curDate = strtotime(date("d-m-Y"));
               if($curDate > $setDate){

        			    echo $this->awcfe_display_notices();
                  //update_option('awcfe_rate_us', 'yes');
                  //setcookie('awcfeShowRating','yes', time() + 86400);
              }
            }
          }
        }
			}
		}

    public function awcfe_display_notices(){
      $msg = '<div id="awcfe_notice" class="notice notice-info is-dismissible">
        <div class="awcfe-notice-info">
          <h3> Enjoy using Checkout Field Editor and Manager for WooCommerce plugin? </h3>
          <p> We\'ve worked tirelessly to make this the best plugin - not only in terms of its functionality but also with its design and UX - and we hope you\'re enjoying it. If you think that we deserve a kudos for this, <strong>please do rate us on WordPress</strong>, which would really motivate us to make this even better with more exciting features. Thanks! </p>
          <div>
            <a class="awcfe-notice-link desrve" data-item="deserve" href="https://wordpress.org/support/plugin/checkout-field-editor-and-manager-for-woocommerce/reviews/?filter=5#new-post" target="_blank" ><span class="dashicons dashicons-smiley"></span> Ok, you deserve it</a>
            <a href="#" class="awcfe-notice-link do-dismiss-notice" data-item="later" ><span class="thumbs-down"></span> Nope, maybe later</a>
            <a href="#" class="awcfe-notice-link do-accept-notice" data-item="already" ><span class="thumbs-up"></span> I already did</a>

            </div>
        </div>
        <div  class="awcfe-notice-img">
        </div>
      </div>';
      return $msg;
    }

	function awcfe_notification_scripting(){
		$screen = get_current_screen();
		if ($screen->id === 'dashboard' || $screen->id === 'woocommerce_page_awcfe_admin_ui'){
		?>
		<script>
    jQuery(document).on('click', '.awcfe-notice-link', function (e) {
        e.preventDefault();
        var item = jQuery(this).data('item');
        jQuery.ajax({
          type: "POST",
          url: "<?php echo admin_url('admin-ajax.php'); ?>",
          data: {
            item: item,
            action: 'awcfe_rating',
            _ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'awcfe_rating' ) ); ?>'
          },
          success: function(result){}
        });
        //if( item == 'later' || item == 'already' ){
          jQuery(this).parent().parent('.awcfe-notice-info').siblings('.notice-dismiss').trigger('click');
        //}
        if( item == 'deserve' ){
          var url = jQuery(this).attr('href');
          window.open(url, '_blank');
        }
    });
		</script>
		<?php
		}
	}

  public function awcfe_rating(){

    check_ajax_referer( 'awcfe_rating' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
      wp_send_json_error( null, 403 );
    }

    if ( isset( $_POST['item'] ) ) {
      $item = sanitize_key( wp_unslash( $_POST['item'] ) );

      if ( $item === 'deserve' || $item === 'already' ) {
        update_option( 'awcfe_rate_us', 'yes' );
      } elseif ( $item === 'later' ) {
        setcookie( 'awcfeShowRating', 'yes', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
      }
    }

    wp_send_json_success();

  }

	/* Admin notice */



      /**
       * Deactivation feedback popup (Acowebs feedback UI kit), printed on plugins.php only.
       */
      public function awcfe_deactivation_form()
      {
        $screen = get_current_screen();
        if ( ! $screen || 'plugins' !== $screen->id ) {
            return;
        }

        $user        = wp_get_current_user();
        $plugin_name = __( 'Checkout Field Editor', 'checkout-field-editor-and-manager-for-woocommerce' );
        $reasons     = array(
            array( __( "I couldn't get it to work", 'checkout-field-editor-and-manager-for-woocommerce' ), __( 'What went wrong? Any error message helps.', 'checkout-field-editor-and-manager-for-woocommerce' ) ),
            array( __( 'I found a better plugin', 'checkout-field-editor-and-manager-for-woocommerce' ), __( 'Which plugin did you switch to? What does it do better?', 'checkout-field-editor-and-manager-for-woocommerce' ) ),
            array( __( "It's missing a feature I need", 'checkout-field-editor-and-manager-for-woocommerce' ), __( 'Which feature were you looking for?', 'checkout-field-editor-and-manager-for-woocommerce' ) ),
            array( __( 'It broke my site or conflicts with another plugin', 'checkout-field-editor-and-manager-for-woocommerce' ), __( 'What broke, or which plugin does it conflict with?', 'checkout-field-editor-and-manager-for-woocommerce' ) ),
            array( __( 'I only needed it for a short time', 'checkout-field-editor-and-manager-for-woocommerce' ), __( 'Thanks! Anything we could improve?', 'checkout-field-editor-and-manager-for-woocommerce' ) ),
            array( __( 'Other', 'checkout-field-editor-and-manager-for-woocommerce' ), __( 'Please tell us a little more.', 'checkout-field-editor-and-manager-for-woocommerce' ) ),
        );
        ?>
        <div class="acofb acofb-overlay" id="awcfe-deactivation" data-acofb="deactivation" hidden>
          <div class="acofb-modal" role="dialog" aria-modal="true" aria-labelledby="awcfe-dx-title" aria-describedby="awcfe-dx-desc">
            <form class="acofb-modal-form" novalidate>
              <div class="acofb-modal-head">
                <span class="acofb-badge" aria-hidden="true">
                  <svg viewBox="-8 -1.5 86 86" focusable="false"><path fill="#00DFB1" fill-rule="evenodd" d="M2.85642 22.0022C6.72119 18.0569 13.0525 17.9916 16.9978 21.8564L41.4978 45.8564C43.4052 47.7249 44.4861 50.2785 44.4999 52.9485C44.5136 55.6185 43.4591 58.1831 41.5711 60.0711L21.5711 80.0711C17.6658 83.9763 11.3342 83.9763 7.42894 80.0711C3.52369 76.1658 3.52369 69.8342 7.42894 65.9289L20.2846 53.0733L3.0022 36.1436C-0.943088 32.2788 -1.00836 25.9475 2.85642 22.0022Z"></path><path fill="#00B2FF" fill-rule="evenodd" d="M60.1791 3.124C64.5289 7.36505 64.6171 14.3293 60.376 18.6791L48.4585 30.9022L65.7782 48.2218C70.0739 52.5176 70.0739 59.4824 65.7782 63.7782C61.4824 68.0739 54.5176 68.0739 50.2218 63.7782L25.2218 38.7782C20.9645 34.5208 20.9209 27.6318 25.124 23.3209L44.624 3.3209C48.8651 -1.02889 55.8293 -1.11705 60.1791 3.124Z"></path><path fill="#0A84FF" fill-rule="evenodd" d="M43.5968 57.1543L25.2218 38.7786C22.1371 35.6937 21.2645 31.2271 22.6168 27.3607L41.4978 45.8572C43.4052 47.7257 44.4861 50.2794 44.4999 52.9495C44.5074 54.4143 44.1935 55.8473 43.5968 57.1543Z"></path></svg>
                </span>
                <div>
                  <h2 class="acofb-title" id="awcfe-dx-title"><?php
                    /* translators: %s: plugin name */
                    echo esc_html( sprintf( __( 'Quick question before you deactivate %s', 'checkout-field-editor-and-manager-for-woocommerce' ), $plugin_name ) );
                  ?></h2>
                  <p class="acofb-sub" id="awcfe-dx-desc"><?php esc_html_e( 'If you have a moment, tell us why. The Acowebs team reads every answer and uses it to improve the plugin.', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></p>
                </div>
              </div>

              <div class="acofb-modal-body">
                <fieldset class="acofb-reasons">
                  <legend class="acofb-sr"><?php
                    /* translators: %s: plugin name */
                    echo esc_html( sprintf( __( 'Why are you deactivating %s?', 'checkout-field-editor-and-manager-for-woocommerce' ), $plugin_name ) );
                  ?></legend>
                  <?php foreach ( $reasons as $i => $reason ) : $n = $i + 1; ?>
                  <div class="acofb-reason">
                    <input class="acofb-radio" type="radio" name="reason" id="awcfe-dx-r<?php echo (int) $n; ?>" value="<?php echo esc_attr( $reason[0] ); ?>" required>
                    <label class="acofb-reason-card" for="awcfe-dx-r<?php echo (int) $n; ?>"><?php echo esc_html( $reason[0] ); ?></label>
                    <div class="acofb-details">
                      <label class="acofb-sr" for="awcfe-dx-d<?php echo (int) $n; ?>"><?php esc_html_e( 'Tell us more (optional)', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></label>
                      <textarea class="acofb-textarea" id="awcfe-dx-d<?php echo (int) $n; ?>" name="reason_details" rows="3" maxlength="5000" placeholder="<?php echo esc_attr( $reason[1] ); ?>"></textarea>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </fieldset>

                <div class="acofb-contact">
                  <input class="acofb-check" type="checkbox" name="consent" id="awcfe-dx-contact" value="1" checked>
                  <label class="acofb-check-label" for="awcfe-dx-contact"><?php esc_html_e( 'Acowebs can contact me about this feedback', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></label>
                  <div class="acofb-contact-fields">
                    <div class="acofb-field">
                      <label class="acofb-label" for="awcfe-dx-name"><?php esc_html_e( 'Name', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></label>
                      <input class="acofb-input" type="text" id="awcfe-dx-name" name="name" value="<?php echo esc_attr( $user->display_name ); ?>" maxlength="255" autocomplete="name">
                    </div>
                    <div class="acofb-field">
                      <label class="acofb-label" for="awcfe-dx-email"><?php esc_html_e( 'Email', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></label>
                      <input class="acofb-input" type="email" id="awcfe-dx-email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" maxlength="320" autocomplete="email">
                    </div>
                  </div>
                </div>

                <p class="acofb-privacy">
                  <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V6H4a1.5 1.5 0 0 0-1.5 1.5v6A1.5 1.5 0 0 0 4 15h8a1.5 1.5 0 0 0 1.5-1.5v-6A1.5 1.5 0 0 0 12 6h-.5V4.5A3.5 3.5 0 0 0 8 1Zm2 5V4.5a2 2 0 1 0-4 0V6h4Z"></path></svg>
                  <span><?php esc_html_e( 'We only send this if you click Send: your answer, your site address, name and language, the plugin version and your WordPress, WooCommerce and PHP versions. Your name and email are included only while the box above is ticked.', 'checkout-field-editor-and-manager-for-woocommerce' ); ?> <a class="acofb-link" href="<?php echo esc_url( 'https://acowebs.com/privacy-policy/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'checkout-field-editor-and-manager-for-woocommerce' ); ?><span class="acofb-sr"> <?php esc_html_e( '(opens in a new tab)', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></span></a></span>
                </p>
              </div>

              <div class="acofb-modal-foot">
                <button type="submit" class="acofb-btn acofb-btn--primary acofb-btn--send" data-acofb-action="send"><?php esc_html_e( 'Send & deactivate', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></button>
                <button type="button" class="acofb-btn acofb-btn--text" data-acofb-action="skip"><?php esc_html_e( 'Skip & deactivate', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></button>
                <button type="button" class="acofb-btn acofb-btn--text acofb-btn--cancel" data-acofb-action="cancel"><?php esc_html_e( 'Cancel', 'checkout-field-editor-and-manager-for-woocommerce' ); ?></button>
              </div>
            </form>
          </div>
        </div>
        <?php
      }

      /**
       * AJAX: forward deactivation feedback to the Acowebs Feedback API.
       */
      public function awcfe_deactivation_feedback()
      {
        check_ajax_referer( 'awcfe_deactivation_feedback', 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
        }

        // Shared client is only loaded on PHP 7.1+ (see start.php).
        if ( ! class_exists( 'Acowebs_Feedback_Client' ) ) {
            wp_send_json_success();
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
        $consent = isset( $_POST['consent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['consent'] ) );
        $args    = array(
            'plugin'         => AWCFE_FEEDBACK_PLUGIN,
            'edition'        => AWCFE_EDITION,
            'plugin_version' => AWCFE_VERSION,
            'plugin_slug'    => AWCFE_SLUG,
            'reason'         => isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '',
            'reason_details' => isset( $_POST['reason_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason_details'] ) ) : '',
            'consent'        => $consent,
            'name'           => null,
            'email'          => null,
        );

        // Name and email only while the contact box is ticked.
        if ( $consent ) {
            $args['name']  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $args['email'] = is_email( $email ) ? $email : '';
        }
        // phpcs:enable

        // The client fills missing keys with '' but only strips null, so turn empty strings into null:
        // the API never receives "" (an empty email would fail validation and lose the feedback).
        foreach ( $args as $key => $value ) {
            if ( '' === $value ) {
                $args[ $key ] = null;
            }
        }

        Acowebs_Feedback_Client::send_deactivation( $args );

        wp_send_json_success();
      }



    
public function aco_check_checkout_page_type() {
    if (!function_exists('wc_get_page_id')) {
        return;
    }

    $checkout_page_id = wc_get_page_id('checkout');
    $blocks = parse_blocks(get_post_field('post_content', $checkout_page_id));

    $is_block_checkout = false;
    foreach ($blocks as $block) {
        if ($block['blockName'] === 'woocommerce/checkout') {
            $is_block_checkout = true;
            break;
        }
    }

    // Save flag to global (or static variable)
    global $aco_is_block_checkout;
    $aco_is_block_checkout = $is_block_checkout;
}


public function aco_enqueue_admin_script($hook) {
    if ($hook !== 'toplevel_page_aco-wc-checkout-block') {
        return;
    }

    global $aco_is_block_checkout;

    // Default to classic if not set
    $is_block_checkout = isset($aco_is_block_checkout) && $aco_is_block_checkout;

    $handle = 'aco-wc-checkout-admin';

    wp_enqueue_script(
        $handle,
        plugin_dir_url(__FILE__) . '../block-assets/admin/admin-index.js',
        array('wp-element', 'wp-i18n', 'wp-components', 'wp-blocks'),
        filemtime(plugin_dir_path(__FILE__) . '../block-assets/admin/admin-index.js'),
        true
    );

    wp_localize_script($handle, 'acoAdminData', array(
        'checkout_classic' => $is_block_checkout ? 'false' : 'true',
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aco_nonce'),
        'pluginUrl' => plugin_dir_url(__FILE__),
    ));

   
}


}
