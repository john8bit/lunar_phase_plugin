<?php
/**
 * Plugin Name: Lunar Phase Stock Widget
 * Plugin URI: https://celestialwebdevelopment.com/lunar-phase-plugin/
 * Description: Display the current lunar phase with a bundled moon image, phase name, moonrise, moonset, and illumination using WeatherAPI astronomy data.
 * Version: 1.1.0
 * Author: Celestial Web Development
 * Author URI: https://celestialwebdevelopment.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lunar-phase-stock-widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Lunar_Phase_Stock_Widget' ) ) {

	class Lunar_Phase_Stock_Widget {
		const OPTION_KEY       = 'lpsw_settings';
		const TRANSIENT_PREFIX = 'lpsw_';
		const VERSION          = '1.1.0';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
			add_action( 'init', array( $this, 'register_block' ) );
			add_shortcode( 'lunar_phase_widget', array( $this, 'render_shortcode' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		public function plugin_action_links( $links ) {
			$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=lpsw-settings' ) ) . '">' . esc_html__( 'Settings', 'lunar-phase-stock-widget' ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		public function register_frontend_assets() {
			wp_register_style(
				'lpsw-frontend',
				plugin_dir_url( __FILE__ ) . 'assets/css/lpsw.css',
				array(),
				self::VERSION
			);
		}

		public function register_block() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}

			wp_register_script(
				'lpsw-block-editor',
				plugin_dir_url( __FILE__ ) . 'assets/js/lpsw-block.js',
				array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
				self::VERSION,
				true
			);

			wp_register_style(
				'lpsw-block-editor',
				plugin_dir_url( __FILE__ ) . 'assets/css/lpsw-editor.css',
				array( 'wp-edit-blocks' ),
				self::VERSION
			);

			register_block_type(
				'celestial-web-development/lunar-phase-widget',
				array(
					'api_version'     => 2,
					'editor_script'   => 'lpsw-block-editor',
					'editor_style'    => 'lpsw-block-editor',
					'style'           => 'lpsw-frontend',
					'render_callback' => array( $this, 'render_block' ),
					'attributes'      => array(
						'location'     => array( 'type' => 'string', 'default' => '' ),
						'date'         => array( 'type' => 'string', 'default' => '' ),
						'title'        => array( 'type' => 'string', 'default' => '' ),
						'showLocation' => array( 'type' => 'boolean', 'default' => true ),
						'showCredit'   => array( 'type' => 'boolean', 'default' => true ),
					),
				)
			);
		}

		public function register_admin_menu() {
			add_options_page(
				__( 'Lunar Phase Widget', 'lunar-phase-stock-widget' ),
				__( 'Lunar Phase Widget', 'lunar-phase-stock-widget' ),
				'manage_options',
				'lpsw-settings',
				array( $this, 'render_settings_page' )
			);
		}

		public function register_settings() {
			register_setting(
				'lpsw_settings_group',
				self::OPTION_KEY,
				array( $this, 'sanitize_settings' )
			);

			add_settings_section(
				'lpsw_main_section',
				__( 'Widget Settings', 'lunar-phase-stock-widget' ),
				function () {
					echo '<p>' . esc_html__( 'Enter your API key and a default location. You can also override the location in the shortcode or Gutenberg block.', 'lunar-phase-stock-widget' ) . '</p>';
				},
				'lpsw-settings'
			);

			$fields = array(
				'api_key'          => __( 'WeatherAPI Key', 'lunar-phase-stock-widget' ),
				'default_location' => __( 'Default Location', 'lunar-phase-stock-widget' ),
				'default_title'    => __( 'Default Title', 'lunar-phase-stock-widget' ),
				'time_format'      => __( 'Time Format', 'lunar-phase-stock-widget' ),
				'show_location'    => __( 'Show Location Label', 'lunar-phase-stock-widget' ),
				'show_credit'      => __( 'Show Data Credit', 'lunar-phase-stock-widget' ),
			);

			foreach ( $fields as $key => $label ) {
				add_settings_field(
					$key,
					$label,
					array( $this, 'render_settings_field' ),
					'lpsw-settings',
					'lpsw_main_section',
					array( 'key' => $key )
				);
			}
		}

		public function sanitize_settings( $input ) {
			$sanitized                     = array();
			$sanitized['api_key']          = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
			$sanitized['default_location'] = isset( $input['default_location'] ) ? sanitize_text_field( $input['default_location'] ) : '';
			$sanitized['default_title']    = isset( $input['default_title'] ) ? sanitize_text_field( $input['default_title'] ) : __( 'Current Lunar Phase', 'lunar-phase-stock-widget' );
			$sanitized['time_format']      = ( isset( $input['time_format'] ) && in_array( $input['time_format'], array( '12', '24' ), true ) ) ? $input['time_format'] : '12';
			$sanitized['show_location']    = ! empty( $input['show_location'] ) ? '1' : '0';
			$sanitized['show_credit']      = ! empty( $input['show_credit'] ) ? '1' : '0';
			return $sanitized;
		}

		public function get_settings() {
			$defaults = array(
				'api_key'          => '',
				'default_location' => '',
				'default_title'    => __( 'Current Lunar Phase', 'lunar-phase-stock-widget' ),
				'time_format'      => '12',
				'show_location'    => '1',
				'show_credit'      => '1',
			);

			$saved = get_option( self::OPTION_KEY, array() );
			return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		}

		public function render_settings_field( $args ) {
			$key      = $args['key'];
			$settings = $this->get_settings();
			$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

			switch ( $key ) {
				case 'api_key':
					printf(
						'<input type="password" class="regular-text" name="%1$s[%2$s]" value="%3$s" autocomplete="off" /> <p class="description">%4$s</p>',
						esc_attr( self::OPTION_KEY ),
						esc_attr( $key ),
						esc_attr( $value ),
						esc_html__( 'Required. Get a key from WeatherAPI.com.', 'lunar-phase-stock-widget' )
					);
					break;

				case 'default_location':
					printf(
						'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="New York, NY" /> <p class="description">%4$s</p>',
						esc_attr( self::OPTION_KEY ),
						esc_attr( $key ),
						esc_attr( $value ),
						esc_html__( 'Examples: "Winona, MN", "10001", or "44.05,-91.64".', 'lunar-phase-stock-widget' )
					);
					break;

				case 'default_title':
					printf(
						'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
						esc_attr( self::OPTION_KEY ),
						esc_attr( $key ),
						esc_attr( $value )
					);
					break;

				case 'time_format':
					?>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]">
						<option value="12" <?php selected( $value, '12' ); ?>><?php esc_html_e( '12-hour', 'lunar-phase-stock-widget' ); ?></option>
						<option value="24" <?php selected( $value, '24' ); ?>><?php esc_html_e( '24-hour', 'lunar-phase-stock-widget' ); ?></option>
					</select>
					<?php
					break;

				case 'show_location':
				case 'show_credit':
					printf(
						'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
						esc_attr( self::OPTION_KEY ),
						esc_attr( $key ),
						checked( $value, '1', false ),
						esc_html__( 'Enabled', 'lunar-phase-stock-widget' )
					);
					break;
			}
		}

		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Lunar Phase Widget', 'lunar-phase-stock-widget' ); ?></h1>

				<form method="post" action="options.php">
					<?php
					settings_fields( 'lpsw_settings_group' );
					do_settings_sections( 'lpsw-settings' );
					submit_button();
					?>
				</form>

				<hr />

				<h2><?php esc_html_e( 'Shortcode', 'lunar-phase-stock-widget' ); ?></h2>
				<p><code>[lunar_phase_widget]</code></p>
				<p><code>[lunar_phase_widget location="Winona, MN" title="Tonight\'s Moon" show_credit="no"]</code></p>
				<p><code>[lunar_phase_widget date="2026-03-29" show_location="yes"]</code></p>

				<h2><?php esc_html_e( 'Block', 'lunar-phase-stock-widget' ); ?></h2>
				<p><?php esc_html_e( 'Search for “Lunar Phase Widget” in the block inserter to place the widget visually in the editor.', 'lunar-phase-stock-widget' ); ?></p>

				<h2><?php esc_html_e( 'Notes', 'lunar-phase-stock-widget' ); ?></h2>
				<ul style="list-style:disc;padding-left:20px;">
					<li><?php esc_html_e( 'This plugin uses WeatherAPI astronomy data and bundled local moon phase images.', 'lunar-phase-stock-widget' ); ?></li>
					<li><?php esc_html_e( 'Visitor auto-location is not enabled in this version, which avoids exposing your API key in the browser.', 'lunar-phase-stock-widget' ); ?></li>
					<li><?php esc_html_e( 'API responses are cached for 12 hours per location and date.', 'lunar-phase-stock-widget' ); ?></li>
				</ul>
			</div>
			<?php
		}

		public function render_shortcode( $atts = array() ) {
			$settings = $this->get_settings();

			$atts = shortcode_atts(
				array(
					'location'      => $settings['default_location'],
					'date'          => current_time( 'Y-m-d' ),
					'title'         => $settings['default_title'],
					'show_location' => ( '1' === $settings['show_location'] ) ? 'yes' : 'no',
					'show_credit'   => ( '1' === $settings['show_credit'] ) ? 'yes' : 'no',
				),
				$atts,
				'lunar_phase_widget'
			);

			return $this->render_widget(
				array(
					'location'      => $atts['location'],
					'date'          => $atts['date'],
					'title'         => $atts['title'],
					'show_location' => $atts['show_location'],
					'show_credit'   => $atts['show_credit'],
				)
			);
		}

		public function render_block( $attributes = array() ) {
			$settings = $this->get_settings();

			return $this->render_widget(
				array(
					'location'      => isset( $attributes['location'] ) && '' !== $attributes['location'] ? $attributes['location'] : $settings['default_location'],
					'date'          => isset( $attributes['date'] ) && '' !== $attributes['date'] ? $attributes['date'] : current_time( 'Y-m-d' ),
					'title'         => isset( $attributes['title'] ) && '' !== $attributes['title'] ? $attributes['title'] : $settings['default_title'],
					'show_location' => ! empty( $attributes['showLocation'] ) ? 'yes' : 'no',
					'show_credit'   => ! empty( $attributes['showCredit'] ) ? 'yes' : 'no',
				)
			);
		}

		private function render_widget( $args ) {
			$settings = $this->get_settings();

			$location      = sanitize_text_field( $args['location'] );
			$date          = sanitize_text_field( $args['date'] );
			$title         = sanitize_text_field( $args['title'] );
			$show_location = 'yes' === strtolower( (string) $args['show_location'] );
			$show_credit   = 'yes' === strtolower( (string) $args['show_credit'] );

			if ( empty( $settings['api_key'] ) ) {
				return $this->render_notice( __( 'Lunar Phase Widget is not configured yet. Please add your WeatherAPI key in Settings.', 'lunar-phase-stock-widget' ) );
			}

			if ( empty( $location ) ) {
				return $this->render_notice( __( 'Please set a default location in the plugin settings or pass a location in the shortcode or block.', 'lunar-phase-stock-widget' ) );
			}

			if ( ! $this->validate_date( $date ) ) {
				return $this->render_notice( __( 'Invalid date format. Use YYYY-MM-DD.', 'lunar-phase-stock-widget' ) );
			}

			$data = $this->get_astronomy_data( $settings['api_key'], $location, $date );

			if ( is_wp_error( $data ) ) {
				return $this->render_notice( $data->get_error_message() );
			}

			$astro         = isset( $data['astronomy']['astro'] ) ? $data['astronomy']['astro'] : array();
			$phase_name    = isset( $astro['moon_phase'] ) ? sanitize_text_field( $astro['moon_phase'] ) : __( 'Unknown', 'lunar-phase-stock-widget' );
			$moonrise      = isset( $astro['moonrise'] ) ? $this->format_time( $astro['moonrise'], $settings['time_format'] ) : __( 'N/A', 'lunar-phase-stock-widget' );
			$moonset       = isset( $astro['moonset'] ) ? $this->format_time( $astro['moonset'], $settings['time_format'] ) : __( 'N/A', 'lunar-phase-stock-widget' );
			$illumination  = isset( $astro['moon_illumination'] ) ? sanitize_text_field( $astro['moon_illumination'] ) : '';
			$location_name = ! empty( $data['location']['name'] ) ? sanitize_text_field( $data['location']['name'] ) : $location;
			$region        = ! empty( $data['location']['region'] ) ? sanitize_text_field( $data['location']['region'] ) : '';
			$country       = ! empty( $data['location']['country'] ) ? sanitize_text_field( $data['location']['country'] ) : '';

			$location_label = $location_name;
			if ( ! empty( $region ) ) {
				$location_label .= ', ' . $region;
			}
			if ( ! empty( $country ) ) {
				$location_label .= ', ' . $country;
			}

			$image_url = $this->get_phase_image_url( $phase_name );

			wp_enqueue_style( 'lpsw-frontend' );

			ob_start();
			?>
			<div class="lpsw-card">
				<?php if ( ! empty( $title ) ) : ?>
					<div class="lpsw-title"><?php echo esc_html( $title ); ?></div>
				<?php endif; ?>

				<div class="lpsw-media">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $phase_name ); ?>" loading="lazy" />
				</div>

				<div class="lpsw-content">
					<div class="lpsw-phase"><?php echo esc_html( $phase_name ); ?></div>

					<?php if ( $show_location ) : ?>
						<div class="lpsw-location"><?php echo esc_html( $location_label ); ?></div>
					<?php endif; ?>

					<div class="lpsw-meta">
						<div class="lpsw-meta-row">
							<span class="lpsw-label"><?php esc_html_e( 'Moonrise', 'lunar-phase-stock-widget' ); ?></span>
							<span class="lpsw-value"><?php echo esc_html( $moonrise ); ?></span>
						</div>
						<div class="lpsw-meta-row">
							<span class="lpsw-label"><?php esc_html_e( 'Moonset', 'lunar-phase-stock-widget' ); ?></span>
							<span class="lpsw-value"><?php echo esc_html( $moonset ); ?></span>
						</div>
						<?php if ( '' !== $illumination ) : ?>
							<div class="lpsw-meta-row">
								<span class="lpsw-label"><?php esc_html_e( 'Illumination', 'lunar-phase-stock-widget' ); ?></span>
								<span class="lpsw-value"><?php echo esc_html( $illumination ); ?>%</span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $show_credit ) : ?>
						<div class="lpsw-credit">
							<?php echo wp_kses_post( __( 'Moon data by <a href="https://www.weatherapi.com/" target="_blank" rel="noopener noreferrer">WeatherAPI</a>. Phase images bundled locally.', 'lunar-phase-stock-widget' ) ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		private function render_notice( $message ) {
			return '<div class="lpsw-notice">' . esc_html( $message ) . '</div>';
		}

		private function validate_date( $date ) {
			$dt = \DateTime::createFromFormat( 'Y-m-d', $date );
			return $dt && $dt->format( 'Y-m-d' ) === $date;
		}

		private function get_astronomy_data( $api_key, $location, $date ) {
			$transient_key = self::TRANSIENT_PREFIX . md5( strtolower( $location . '|' . $date ) );
			$cached        = get_transient( $transient_key );

			if ( false !== $cached ) {
				return $cached;
			}

			$url = add_query_arg(
				array(
					'key' => $api_key,
					'q'   => $location,
					'dt'  => $date,
				),
				'https://api.weatherapi.com/v1/astronomy.json'
			);

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'lpsw_request_failed', __( 'Unable to connect to the lunar data service right now.', 'lunar-phase-stock-widget' ) );
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body   = wp_remote_retrieve_body( $response );
			$data   = json_decode( $body, true );

			if ( 200 !== $status ) {
				$message = __( 'The lunar data service returned an error.', 'lunar-phase-stock-widget' );
				if ( isset( $data['error']['message'] ) ) {
					$message = sanitize_text_field( $data['error']['message'] );
				}
				return new WP_Error( 'lpsw_api_error', $message );
			}

			if ( ! is_array( $data ) || empty( $data['astronomy']['astro'] ) ) {
				return new WP_Error( 'lpsw_invalid_response', __( 'The lunar data response was incomplete.', 'lunar-phase-stock-widget' ) );
			}

			set_transient( $transient_key, $data, 12 * HOUR_IN_SECONDS );

			return $data;
		}

		private function format_time( $time_string, $format ) {
			if ( empty( $time_string ) ) {
				return __( 'N/A', 'lunar-phase-stock-widget' );
			}

			$lower = strtolower( $time_string );
			if ( 'no moonrise' === $lower || 'no moonset' === $lower ) {
				return $time_string;
			}

			$dt = \DateTime::createFromFormat( 'g:i A', $time_string );
			if ( ! $dt ) {
				return $time_string;
			}

			return ( '24' === $format ) ? $dt->format( 'H:i' ) : $dt->format( 'g:i A' );
		}

		private function get_phase_image_url( $phase_name ) {
			$map = array(
				'new moon'        => 'new-moon.jpg',
				'waxing crescent' => 'waxing-crescent.jpg',
				'first quarter'   => 'first-quarter.jpg',
				'waxing gibbous'  => 'waxing-gibbous.jpg',
				'full moon'       => 'full-moon.jpg',
				'waning gibbous'  => 'waning-gibbous.jpg',
				'last quarter'    => 'last-quarter.jpg',
				'third quarter'   => 'last-quarter.jpg',
				'waning crescent' => 'waning-crescent.jpg',
			);

			$key  = strtolower( trim( $phase_name ) );
			$file = isset( $map[ $key ] ) ? $map[ $key ] : 'full-moon.jpg';

			return plugin_dir_url( __FILE__ ) . 'assets/images/phases/' . $file;
		}
	}

	new Lunar_Phase_Stock_Widget();
}
