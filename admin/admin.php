<?php

require_once CF7ANTISPAM_PLUGIN_DIR . '/admin/admin-customizations.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    CF7_AntiSpam
 * @subpackage CF7_AntiSpam/admin
 * @author     Codekraft Studio <info@codekraft.it>
 */
class CF7_AntiSpam_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 *
	 * @param      string $plugin_name The name of this plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// the menu item
		new CF7_AntiSpam_Admin_Customizations();

		$tools = new CF7_AntiSpam_Admin_Tools();

		add_filter( 'admin_body_class', array( $this, 'cf7a_body_class' ));

		add_action( 'admin_notices', array( $this, 'cf7a_display_notices' ) );

		add_action( 'admin_menu', array( $this, 'cf7a_admin_menu' ), 10, 0 );

		add_action( 'plugin_action_links_'.CF7ANTISPAM_PLUGIN_BASENAME, array($this, 'cf7a_plugin_settings_link'), 10, 2 );

		if ( defined( 'FLAMINGO_VERSION' ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'cf7a_dashboard_widget' ) );
		}

	}

	public function cf7a_admin_menu() {
		add_submenu_page( 'wpcf7',
			__( 'Antispam', $this->plugin_name ),
			__( 'Antispam', $this->plugin_name ),
			'wpcf7_edit_contact_forms',
			$this->plugin_name,
			array( $this, 'cf7a_admin_dashboard' )
		);
	}

	/**
	 * Add go to settings link on plugin page.
	 *
	 * @since 0.2.2
	 *
	 * @param  array $links Array of plugin action links.
	 * @return array Modified array of plugin action links.
	 */
	public function cf7a_plugin_settings_link( array $links ) {
		$settings_page_link = '<a href="' . admin_url( 'admin.php?page=cf7-antispam' ) . '">' . esc_attr__( 'Antispam Settings', 'cf7-antispam' ) . '</a>';
		array_unshift( $links, $settings_page_link );

		return $links;
	}

	public function cf7a_admin_dashboard() {
		$admin_display = new CF7_AntiSpam_Admin_Display();
		$admin_display->display_dashboard();
	}

	public function cf7a_display_notices() {

		$admin_page = get_current_screen();
		if (false === strpos($admin_page->base, $this->plugin_name )) return;

		$settings_updated = isset( $_REQUEST['settings-updated'] ) ? sanitize_text_field( $_REQUEST['settings-updated'] ) : false;
		if ( $settings_updated === 'true' ) {
			CF7_AntiSpam_Admin_Tools::cf7a_push_notice( __( 'Antispam setting updated with success', 'cf7-antispam' ), "success" );
		}

		if ( false !== ( $notice = get_transient( 'cf7a_notice' )) ) {
			echo $notice;
			delete_transient( 'cf7a_notice' );
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in load_admin as all of the hooks are defined
		 * in that particular class.
		 *
		 * The load_admin will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/style.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in load_admin as all of the hooks are defined
		 * in that particular class.
		 *
		 * The load_admin will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_register_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/admin-script.js', array(), $this->version );
		wp_enqueue_script( $this->plugin_name );

		wp_localize_script($this->plugin_name, "cf7a_admin_settings", array(
				"alertMessage" => esc_html__('Are you sure?', 'cf7-antispam')
		));
	}

	public function cf7a_body_class( $classes ) {
		$admin_page = get_current_screen();
		if (false === strpos($admin_page->base, $this->plugin_name )) return $classes;
        return "$classes cf7-antispam-admin";
	}

	public function cf7a_dashboard_widget() {
		global $wp_meta_boxes;
		wp_add_dashboard_widget('custom_help_widget', 'Contact Form 7 Antispam - Recap', array($this, 'cf7a_flamingo_recap') );
	}

	function cf7a_flamingo_recap() {

		$args = array(
			'post_type' => 'flamingo_inbound',
			'post_status' => array( 'flamingo-spam', 'publish' ),
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'date_query' => array(
				array(
					'after' => '1 week ago'
				)
			)
		);

		$mail_collection = array(
			'by_type' => array(
				'ham' => 0,
				'spam' => 0,
			),
			'by_date' => array()
		);


		$query = new WP_Query($args);
		if ( $query->have_posts() ) :
			// this is needed to parse and create a list of emails
			$html = '<div id="antispam-widget-list" class="activity-block"><h3>'.__('Last Week Emails', 'cf7-antispam').'</h3><ul>';

			while ( $query->have_posts() ) : $query->the_post();
				global $post;

				$is_ham = $post->post_status !== 'flamingo-spam';

				if ( get_the_date('Y-m-d') > date('Y-m-d', strtotime("-1 week") ) )
					$html .= sprintf('<li class="cf7-a_list-item"><span class="timestamp">%s </span><a href="%s" value="post-id-%s"><span>%s</span> %s</a> - %s</li>',
						get_the_date('Y-m-d') ,
						admin_url('admin.php?page=flamingo_inbound&post='.$post->ID.'&action=edit' ),
						$post->ID,
						$is_ham ? '??????' : '???',
						htmlentities(get_post_meta($post->ID, '_from')[0]),
						$post->post_title
					) ;

				// for each post collect the main informations like spam/ham or date
				if (!isset($mail_collection['by_date'][get_the_date('Y-m-d')])) $mail_collection['by_date'][get_the_date('Y-m-d')] = array();
				$mail_collection['by_type'][ $is_ham ? 'ham' : 'spam' ]++;
				array_push($mail_collection['by_date'][get_the_date('Y-m-d')], array( 'status' => $is_ham ? 'ham' : 'spam' ));

			endwhile;

			wp_reset_postdata();
			$html .= '</ul></div>';

			$count = array();

			$mail_collection['by_date'] = array_reverse($mail_collection['by_date']);

			// for each date
			foreach ( $mail_collection['by_date'] as $date => $items ) {

				// add the date to the list if not yet added
				if ( ! isset( $count[ $date ] ) ) { $count[ $date ] = array( 'ham' => 0, 'spam' => 0 ); }

				// for each item of that date feed the count by email type
				foreach ( $items as $item ) { 'spam' == $item['status'] ? $count[ $date ]['spam'] ++ : $count[ $date ]['ham'] ++; }

			}

			// Create two lists where the key is the date and the value is the number of mails of that type
			foreach ( $count as $date ) {
				$ham[]  = $date['ham'];
				$spam[] = $date['spam'];
			}

			?>
			<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
			<div>
				<canvas id="lineChart" width="400" height="200"></canvas>
				<hr>
				<canvas id="pieChart" width="50" height="50"></canvas>
				<?php
				// print the received mail list
				echo $html; ?>
				<p class="community-events-footer">
					<a href="<?php echo admin_url('admin.php?page=flamingo' ) ?>"><?php echo  __( 'Flamingo Inbound Messages', 'flamingo' ) ?><span aria-hidden="true" class="dashicons dashicons-external"></span></a>
					|
					<a href="<?php echo admin_url('admin.php?page=cf7-antispam' ) ?>">CF7-Antispam setup <span aria-hidden="true" class="dashicons dashicons-external"></span></a>
				</p>
				<script>

					const lineLabels = [ '<?php echo implode("','", array_keys($mail_collection['by_date'])); ?>' ];
					const pieLabels = [ '<?php echo implode("','", array_keys($mail_collection['by_type'])); ?>' ];

					const lineData = {
						labels: lineLabels,
						datasets: [{
							label: 'Ham',
							backgroundColor: 'rgb(0,255,122)',
							borderColor: 'rgb(3, 210, 106)',
							tension: 0.25,
							data: [<?php if ( isset( $ham ) ) {
									echo implode( ",", $ham );
								}?>],
						},
						{
							label: 'Spam',
							backgroundColor: 'rgb(255,4,0)',
							borderColor: 'rgb(248, 49, 47)',
							tension: 0.25,
							data: [<?php if ( isset( $spam ) ) {
								echo implode( ",", $spam );
							}?>],
						}]
					};

					const pieData = {
						labels: pieLabels,
						datasets: [{
							data: [<?php echo $mail_collection['by_type']['ham'] . ', ' . $mail_collection['by_type']['spam'] ; ?>],
							backgroundColor: [
								'rgb(15,199,107)',
								'rgb(248,49,47)'
							]
						}]
					};

					const lineConfig = {
						type: 'line',
						data: lineData,
						options: {
							responsive: true,
							plugins: {
								legend: {display: false}
							},
							scales: {
								y: {
									ticks: {
										min: 0,
										precision: 0
									}
								}
							}
						}
					};

					const PieConfig = {
						type: 'pie',
						data: pieData,
						options: {
							responsive: true,
							plugins: {
								legend: {display: false}
							},
						}
					};

					const lineChart = new Chart(
						document.getElementById('lineChart'),
						lineConfig
					);

					const pieChart = new Chart(
						document.getElementById('pieChart'),
						PieConfig
					);

				</script>
			</div>
		<?php
		else:
			echo '<div class="cf7-a_widget-empty"><span class="dashicons dashicons-welcome-comments"></span><p>You have not received any email in the last 7 days</p></div>';
		endif;
		?>

		<style>
			.cf7-a_list-item {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			#antispam-widget-list span.timestamp {
				margin-right: 5px;
				color: #a5a5a5;
				display: inline-block;
				font-size: 90%;
				font-family: monospace;
			}
			#pieChart {
				width: 36px!important;
				height: 36px !important;
				display: inline-block !important;
				padding: 4px;
				float: left;
				margin-right: 4px;
			}
			.cf7-a_widget-empty {
				text-align: center;
			}
			.cf7-a_widget-empty .dashicons,
			.cf7-a_widget-empty .dashicons-before {
				font-size: 96px;
				line-height: 1;
				height: 100px;
				margin-top: 6px;
				margin-left: 0;
				width: 96px;
				opacity: .7;
			}
		</style>
		<?php

	}
}
