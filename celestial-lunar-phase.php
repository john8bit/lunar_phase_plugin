<?php
/**
 * Plugin Name: Celestial Lunar Phase Widget
 * Plugin URI: https://celestialwebdevelopment.com/lunar-phase-plugin/
 * Description: Display the current lunar phase with bundled moon imagery, moonrise, moonset, astronomical dawn/dusk, and illumination using local astronomy calculations.
 * Version: 2.2.0
 * Author: Celestial Web Development
 * Author URI: https://celestialwebdevelopment.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: celestial-lunar-phase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Celestial_Lunar_Phase_Widget' ) ) {

	class Celestial_Lunar_Phase_Widget {
		const OPTION_KEY       = 'lpsw_settings';
		const TRANSIENT_PREFIX = 'lpsw_v220_';
		const VERSION          = '2.2.0';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
			add_action( 'admin_notices', array( $this, 'admin_configuration_notice' ) );
			add_action( 'wp_ajax_lpsw_lookup_location', array( $this, 'ajax_lookup_location' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
			add_action( 'init', array( $this, 'register_block' ) );
			add_shortcode( 'celestial_lunar_phase_widget', array( $this, 'render_shortcode' ) );
			add_shortcode( 'lunar_phase_widget', array( $this, 'render_shortcode' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		public function plugin_action_links( $links ) {
			$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=lpsw-settings' ) ) . '">' . esc_html__( 'Settings', 'celestial-lunar-phase' ) . '</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		public function register_frontend_assets() {
			wp_register_style( 'lpsw-frontend', plugin_dir_url( __FILE__ ) . 'assets/css/lpsw.css', array(), self::VERSION );
		}

		public function admin_assets( $hook ) {
			if ( 'settings_page_lpsw-settings' !== $hook ) {
				return;
			}

			wp_register_script( 'lpsw-admin', '', array(), self::VERSION, true );
			wp_enqueue_script( 'lpsw-admin' );

			$nonce = wp_create_nonce( 'lpsw_location_lookup' );
			$js = "
			document.addEventListener('DOMContentLoaded', function() {
				var useCurrent = document.getElementById('lpsw-use-current-location');
				var lookup = document.getElementById('lpsw-lookup-location');
				var status = document.getElementById('lpsw-location-status');
				var nonce = '" . esc_js( $nonce ) . "';
				function setStatus(msg, isError) { if (status) { status.textContent = msg; status.style.color = isError ? '#b32d2e' : '#2271b1'; } }
				function setField(name, value) { var el = document.querySelector('[name=\"lpsw_settings[' + name + ']\"]'); if (el && value !== undefined && value !== null && value !== '') { el.value = value; } }
				function getField(name) { var el = document.querySelector('[name=\"lpsw_settings[' + name + ']\"]'); return el ? el.value : ''; }

				if (lookup) {
					lookup.addEventListener('click', function(e) {
						e.preventDefault();
						var query = getField('default_location');
						if (!query || query.trim().length < 2) { setStatus('Enter a city, state, address, or place name first.', true); return; }
						setStatus('Looking up location...', false);
						var data = new URLSearchParams();
						data.append('action', 'lpsw_lookup_location');
						data.append('nonce', nonce);
						data.append('query', query);
						fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: data.toString() })
							.then(function(r) { return r.json(); })
							.then(function(resp) {
								if (!resp || !resp.success) { throw new Error(resp && resp.data && resp.data.message ? resp.data.message : 'Location lookup failed.'); }
								setField('latitude', resp.data.latitude);
								setField('longitude', resp.data.longitude);
								setField('default_location', resp.data.label);
								if (resp.data.timezone) { setField('timezone', resp.data.timezone); }
								setStatus(resp.data.timezone ? 'Location filled. Save changes to apply.' : 'Location filled. Please verify the timezone dropdown, then save changes.', false);
							})
							.catch(function(err) { setStatus(err.message || 'Location lookup failed.', true); });
					});
				}

				if (useCurrent) {
					useCurrent.addEventListener('click', function(e) {
						e.preventDefault();
						if (!navigator.geolocation) { setStatus('Browser geolocation is not available.', true); return; }
						setStatus('Requesting browser location...', false);
						navigator.geolocation.getCurrentPosition(function(pos) {
							var lat = pos.coords.latitude.toFixed(6);
							var lon = pos.coords.longitude.toFixed(6);
							setField('latitude', lat);
							setField('longitude', lon);
							setField('default_location', lat + ',' + lon);
							try { setField('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone || ''); } catch(e) {}
							setStatus('Current location filled. Save changes to apply.', false);
						}, function(err) { setStatus(err.message || 'Unable to read browser location.', true); }, {enableHighAccuracy:false, timeout:10000, maximumAge:300000});
					});
				}
			});";
			wp_add_inline_script( 'lpsw-admin', $js );
		}

		public function ajax_lookup_location() {
			check_ajax_referer( 'lpsw_location_lookup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to look up locations.', 'celestial-lunar-phase' ) ), 403 );
			}

			$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
			if ( strlen( $query ) < 2 ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a location to search.', 'celestial-lunar-phase' ) ), 400 );
			}

			$url = add_query_arg(
				array(
					'format'         => 'jsonv2',
					'addressdetails' => '1',
					'limit'          => '1',
					'q'              => $query,
				),
				'https://nominatim.openstreetmap.org/search'
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 10,
					'headers' => array(
						'User-Agent' => 'Celestial Lunar Phase Widget/' . self::VERSION . ' (https://celestialwebdevelopment.com/lunar-phase-plugin/)',
						'Referer'    => admin_url( 'options-general.php?page=lpsw-settings' ),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( 200 !== (int) $code || empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
				wp_send_json_error( array( 'message' => __( 'No matching location was found. Try a more specific city, state, or address.', 'celestial-lunar-phase' ) ), 404 );
			}

			$result = $data[0];
			$lat = $this->sanitize_float_string( $result['lat'], -90, 90 );
			$lon = $this->sanitize_float_string( $result['lon'], -180, 180 );
			if ( '' === $lat || '' === $lon ) {
				wp_send_json_error( array( 'message' => __( 'The location service returned invalid coordinates.', 'celestial-lunar-phase' ) ), 500 );
			}

			$label = $this->build_location_label_from_nominatim( $result );
			$tz    = $this->maybe_infer_timezone_from_coordinates( 'UTC', (float) $lat, (float) $lon );
			if ( 'UTC' === $tz ) {
				$tz = '';
			}

			wp_send_json_success(
				array(
					'latitude'  => $lat,
					'longitude' => $lon,
					'label'     => $label,
					'timezone'  => $tz,
				)
			);
		}

		private function build_location_label_from_nominatim( $result ) {
			$address = isset( $result['address'] ) && is_array( $result['address'] ) ? $result['address'] : array();
			$city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? $address['municipality'] ?? '';
			$state = $address['state'] ?? $address['region'] ?? '';
			$country = $address['country'] ?? '';
			$parts = array_filter( array( $city, $state, $country ) );
			if ( ! empty( $parts ) ) {
				return sanitize_text_field( implode( ', ', $parts ) );
			}
			return sanitize_text_field( $result['display_name'] ?? '' );
		}

		public function admin_configuration_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$settings = $this->get_settings();
			if ( ! empty( $settings['latitude'] ) && ! empty( $settings['longitude'] ) && ! empty( $settings['timezone'] ) ) {
				return;
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$settings_url = admin_url( 'options-general.php?page=lpsw-settings' );
			$class = ( $screen && 'settings_page_lpsw-settings' === $screen->id ) ? 'notice notice-warning inline' : 'notice notice-warning is-dismissible';
			echo '<div class="' . esc_attr( $class ) . '"><p><strong>' . esc_html__( 'Celestial Lunar Phase Widget needs location settings.', 'celestial-lunar-phase' ) . '</strong> ' . esc_html__( 'Version 2 now calculates lunar data locally and no longer uses WeatherAPI. Please complete the location setup using Location Lookup or Use My Current Location, then save your settings.', 'celestial-lunar-phase' ) . ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Open settings', 'celestial-lunar-phase' ) . '</a></p></div>';
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

			wp_register_style( 'lpsw-block-editor', plugin_dir_url( __FILE__ ) . 'assets/css/lpsw-editor.css', array( 'wp-edit-blocks' ), self::VERSION );

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
			add_options_page( __( 'Celestial Lunar Phase Widget', 'celestial-lunar-phase' ), __( 'Celestial Lunar Phase Widget', 'celestial-lunar-phase' ), 'manage_options', 'lpsw-settings', array( $this, 'render_settings_page' ) );
		}

		public function register_settings() {
			register_setting( 'lpsw_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );

			add_settings_section(
				'lpsw_main_section',
				__( 'Widget Settings', 'celestial-lunar-phase' ),
				function () {
					echo '<p>' . esc_html__( 'Celestial Lunar Phase Widget calculates lunar data locally. Use Location Lookup or Use My Current Location, then verify the timezone and save your settings.', 'celestial-lunar-phase' ) . '</p>';
				},
				'lpsw-settings'
			);

			$fields = array(
				'default_location' => __( 'Location Search / Label', 'celestial-lunar-phase' ),
				'location_helper'  => __( 'Use Current Location', 'celestial-lunar-phase' ),
				'latitude'         => __( 'Latitude', 'celestial-lunar-phase' ),
				'longitude'        => __( 'Longitude', 'celestial-lunar-phase' ),
				'timezone'         => __( 'Timezone', 'celestial-lunar-phase' ),
				'elevation'        => __( 'Elevation', 'celestial-lunar-phase' ),
				'default_title'    => __( 'Default Title', 'celestial-lunar-phase' ),
				'time_format'      => __( 'Time Format', 'celestial-lunar-phase' ),
				'show_location'    => __( 'Show Location Label', 'celestial-lunar-phase' ),
				'show_credit'      => __( 'Show Data Credit / Source Note', 'celestial-lunar-phase' ),
				'show_sun_times'   => __( 'Show Dawn / Dusk', 'celestial-lunar-phase' ),
			);

			foreach ( $fields as $key => $label ) {
				add_settings_field( $key, $label, array( $this, 'render_settings_field' ), 'lpsw-settings', 'lpsw_main_section', array( 'key' => $key ) );
			}
		}

		public function sanitize_settings( $input ) {
			$sanitized = array();
			$sanitized['default_location'] = isset( $input['default_location'] ) ? sanitize_text_field( $input['default_location'] ) : '';
			$sanitized['latitude']         = isset( $input['latitude'] ) ? $this->sanitize_float_string( $input['latitude'], -90, 90 ) : '';
			$sanitized['longitude']        = isset( $input['longitude'] ) ? $this->sanitize_float_string( $input['longitude'], -180, 180 ) : '';
			$sanitized['timezone']         = isset( $input['timezone'] ) ? sanitize_text_field( $input['timezone'] ) : wp_timezone_string();
			if ( ! in_array( $sanitized['timezone'], timezone_identifiers_list(), true ) ) {
				$sanitized['timezone'] = wp_timezone_string();
			}
			$sanitized['elevation']        = isset( $input['elevation'] ) ? $this->sanitize_float_string( $input['elevation'], -500, 9000 ) : '0';
			$sanitized['default_title']    = isset( $input['default_title'] ) ? sanitize_text_field( $input['default_title'] ) : __( 'Current Lunar Phase', 'celestial-lunar-phase' );
			$sanitized['time_format']      = isset( $input['time_format'] ) && in_array( $input['time_format'], array( '12', '24' ), true ) ? $input['time_format'] : '12';
			$sanitized['show_location']    = ! empty( $input['show_location'] ) ? '1' : '0';
			$sanitized['show_credit']      = ! empty( $input['show_credit'] ) ? '1' : '0';
			$sanitized['show_sun_times']   = ! empty( $input['show_sun_times'] ) ? '1' : '0';
			return $sanitized;
		}

		private function sanitize_float_string( $value, $min, $max ) {
			$value = trim( (string) $value );
			if ( '' === $value || ! is_numeric( $value ) ) {
				return '';
			}
			$float = (float) $value;
			if ( $float < $min || $float > $max ) {
				return '';
			}
			return (string) $float;
		}

		public function get_settings() {
			$defaults = array(
				'default_location' => '',
				'latitude'         => '',
				'longitude'        => '',
				'timezone'         => wp_timezone_string(),
				'elevation'        => '0',
				'default_title'    => __( 'Current Lunar Phase', 'celestial-lunar-phase' ),
				'time_format'      => '12',
				'show_location'    => '1',
				'show_credit'      => '1',
				'show_sun_times'   => '1',
			);
			$saved = get_option( self::OPTION_KEY, array() );
			return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		}

		public function render_settings_field( $args ) {
			$key      = $args['key'];
			$settings = $this->get_settings();
			$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

			switch ( $key ) {

				case 'default_location':
					printf( '<input type="text" id="lpsw-location-query" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="Elgin, MN" /> <button type="button" class="button" id="lpsw-lookup-location">%4$s</button> <p class="description">%5$s</p>', esc_attr( self::OPTION_KEY ), esc_attr( $key ), esc_attr( $value ), esc_html__( 'Lookup Location', 'celestial-lunar-phase' ), esc_html__( 'Search by city, state, address, or place name. The lookup fills latitude and longitude and may suggest a timezone. Verify the timezone before saving.', 'celestial-lunar-phase' ) );
					break;

				case 'location_helper':
					echo '<button type="button" class="button" id="lpsw-use-current-location">' . esc_html__( 'Use My Current Location', 'celestial-lunar-phase' ) . '</button>';
					echo '<p id="lpsw-location-status" class="description">' . esc_html__( 'Uses your browser location to fill latitude, longitude, and timezone. The values are saved only after you click Save Changes.', 'celestial-lunar-phase' ) . '</p>';
					break;

				case 'latitude':
				case 'longitude':
				case 'elevation':
					$placeholder = 'elevation' === $key ? '0' : ( 'latitude' === $key ? '44.0521' : '-91.6393' );
					$desc        = 'elevation' === $key ? __( 'Optional elevation in meters. The v2.0 lunar calculation currently uses standard horizon assumptions; elevation is saved for future precision improvements.', 'celestial-lunar-phase' ) : __( 'Required for local calculation mode. Use decimal degrees.', 'celestial-lunar-phase' );
					printf( '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" /> <p class="description">%5$s</p>', esc_attr( self::OPTION_KEY ), esc_attr( $key ), esc_attr( $value ), esc_attr( $placeholder ), esc_html( $desc ) );
					break;

				case 'timezone':
					echo '<select class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
					echo $this->render_timezone_options( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '</select>';
					echo '<p class="description">' . esc_html__( 'Choose the observing location timezone. This prevents date shifts and keeps moonrise, moonset, dawn, and dusk in local time.', 'celestial-lunar-phase' ) . '</p>';
					break;

				case 'default_title':
					printf( '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />', esc_attr( self::OPTION_KEY ), esc_attr( $key ), esc_attr( $value ) );
					break;

				case 'time_format':
					?>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]">
						<option value="12" <?php selected( $value, '12' ); ?>><?php esc_html_e( '12-hour', 'celestial-lunar-phase' ); ?></option>
						<option value="24" <?php selected( $value, '24' ); ?>><?php esc_html_e( '24-hour', 'celestial-lunar-phase' ); ?></option>
					</select>
					<?php
					break;

				case 'show_location':
				case 'show_credit':
				case 'show_sun_times':
					printf( '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>', esc_attr( self::OPTION_KEY ), esc_attr( $key ), checked( $value, '1', false ), esc_html__( 'Enabled', 'celestial-lunar-phase' ) );
					break;
			}
		}

		private function render_timezone_options( $selected ) {
			$selected = $this->normalize_timezone( $selected );
			$common = array(
				'America/New_York'    => __( 'Eastern Time (America/New_York)', 'celestial-lunar-phase' ),
				'America/Chicago'     => __( 'Central Time (America/Chicago)', 'celestial-lunar-phase' ),
				'America/Denver'      => __( 'Mountain Time (America/Denver)', 'celestial-lunar-phase' ),
				'America/Phoenix'     => __( 'Arizona Time (America/Phoenix)', 'celestial-lunar-phase' ),
				'America/Los_Angeles' => __( 'Pacific Time (America/Los_Angeles)', 'celestial-lunar-phase' ),
				'America/Anchorage'   => __( 'Alaska Time (America/Anchorage)', 'celestial-lunar-phase' ),
				'Pacific/Honolulu'    => __( 'Hawaii Time (Pacific/Honolulu)', 'celestial-lunar-phase' ),
				'Europe/London'       => __( 'London (Europe/London)', 'celestial-lunar-phase' ),
				'Europe/Paris'        => __( 'Central Europe (Europe/Paris)', 'celestial-lunar-phase' ),
				'Australia/Sydney'    => __( 'Sydney (Australia/Sydney)', 'celestial-lunar-phase' ),
				'UTC'                 => __( 'UTC', 'celestial-lunar-phase' ),
			);

			$out = '<optgroup label="' . esc_attr__( 'Common Timezones', 'celestial-lunar-phase' ) . '">';
			foreach ( $common as $tz => $label ) {
				$out .= '<option value="' . esc_attr( $tz ) . '" ' . selected( $selected, $tz, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$out .= '</optgroup>';

			$out .= '<optgroup label="' . esc_attr__( 'All IANA Timezones', 'celestial-lunar-phase' ) . '">';
			foreach ( timezone_identifiers_list() as $tz ) {
				$out .= '<option value="' . esc_attr( $tz ) . '" ' . selected( $selected, $tz, false ) . '>' . esc_html( str_replace( '_', ' ', $tz ) ) . '</option>';
			}
			$out .= '</optgroup>';
			return $out;
		}


		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Celestial Lunar Phase Widget', 'celestial-lunar-phase' ); ?></h1>
				<form method="post" action="options.php">
					<?php settings_fields( 'lpsw_settings_group' ); do_settings_sections( 'lpsw-settings' ); submit_button(); ?>
				</form>
				<hr />
				<h2><?php esc_html_e( 'Shortcode', 'celestial-lunar-phase' ); ?></h2>
				<p><code>[celestial_lunar_phase_widget]</code></p>
				<p><code>[celestial_lunar_phase_widget title="Tonight's Moon" show_credit="no"]</code></p>
				<h2><?php esc_html_e( 'Accuracy Notes', 'celestial-lunar-phase' ); ?></h2>
				<ul style="list-style:disc;padding-left:20px;">
					<li><?php esc_html_e( 'Local moonrise and moonset are calculated using SunCalc-style lunar coordinates sampled through the local day with quadratic interpolation at the standard lunar horizon.', 'celestial-lunar-phase' ); ?></li>
					<li><?php esc_html_e( 'Different services can still vary because of location precision, elevation, terrain/horizon, refraction assumptions, and algorithm choices.', 'celestial-lunar-phase' ); ?></li>
					<li><?php esc_html_e( 'Results are cached per date, coordinates, and timezone.', 'celestial-lunar-phase' ); ?></li>
				</ul>
				<?php $this->render_local_diagnostics_panel(); ?>
			</div>
			<?php
		}

		private function render_local_diagnostics_panel() {
			$settings = $this->get_settings();
			echo '<hr />';
			echo '<h2>' . esc_html__( 'Local Calculation Diagnostics', 'celestial-lunar-phase' ) . '</h2>';
			echo '<p>' . esc_html__( 'This admin-only test runs the local calculation directly from the settings screen and shows the exact result or error.', 'celestial-lunar-phase' ) . '</p>';

			$tz  = $this->normalize_timezone( $settings['timezone'] ?? '' );
			$lat = isset( $settings['latitude'] ) ? (float) $settings['latitude'] : 0;
			$lon = isset( $settings['longitude'] ) ? (float) $settings['longitude'] : 0;
			$date = $this->get_default_local_date( $settings );

			echo '<table class="widefat striped" style="max-width:800px;margin-top:12px;"><tbody>';
			echo '<tr><th scope="row">Version</th><td>' . esc_html( self::VERSION ) . '</td></tr>';
			echo '<tr><th scope="row">Latitude</th><td>' . esc_html( (string) ( $settings['latitude'] ?? '' ) ) . '</td></tr>';
			echo '<tr><th scope="row">Longitude</th><td>' . esc_html( (string) ( $settings['longitude'] ?? '' ) ) . '</td></tr>';
			echo '<tr><th scope="row">Timezone</th><td>' . esc_html( $tz ) . '</td></tr>';
			echo '<tr><th scope="row">Test Date</th><td>' . esc_html( $date ) . '</td></tr>';
			echo '</tbody></table>';

			try {
				if ( '' === (string) ( $settings['latitude'] ?? '' ) || '' === (string) ( $settings['longitude'] ?? '' ) ) {
					throw new Exception( 'Latitude and longitude are required for local calculation mode.' );
				}
				$tzobj  = new DateTimeZone( $tz );
				$start  = new DateTimeImmutable( $date . ' 00:00:00', $tzobj );
				// Use the requested local calendar date for rise/set events, and local noon for phase/illumination.
				// This prevents late-day UTC offsets from shifting phase calculations into the next date.
				$phase_time = new DateTimeImmutable( $date . ' 12:00:00', $tzobj );
				$events = $this->calculate_moonrise_moonset( $start, $lat, $lon );
				$sun    = $this->calculate_sunrise_sunset( $start, $lat, $lon, $tz );
				$phase  = $this->calculate_phase( $phase_time );

				echo '<h3>' . esc_html__( 'Local Test Result', 'celestial-lunar-phase' ) . '</h3>';
				echo '<table class="widefat striped" style="max-width:800px;"><tbody>';
				echo '<tr><th scope="row">Phase</th><td>' . esc_html( $phase['name'] ?? '' ) . '</td></tr>';
				echo '<tr><th scope="row">Illumination</th><td>' . esc_html( (string) ( $phase['illumination'] ?? '' ) ) . '%</td></tr>';
				echo '<tr><th scope="row">Moonrise</th><td>' . esc_html( $this->format_output_time( $events['rise'] ?? '', $settings['time_format'] ?? '12' ) ) . '</td></tr>';
				echo '<tr><th scope="row">Moonset</th><td>' . esc_html( $this->format_output_time( $events['set'] ?? '', $settings['time_format'] ?? '12' ) ) . '</td></tr>';
				echo '<tr><th scope="row">Dawn</th><td>' . esc_html( $this->format_output_time( $sun['sunrise'] ?? '', $settings['time_format'] ?? '12' ) ) . '</td></tr>';
				echo '<tr><th scope="row">Dusk</th><td>' . esc_html( $this->format_output_time( $sun['sunset'] ?? '', $settings['time_format'] ?? '12' ) ) . '</td></tr>';
				echo '</tbody></table>';
			} catch ( Throwable $e ) {
				error_log( 'Celestial Lunar Phase Widget diagnostics error: ' . $e->getMessage() );
				echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Local calculation diagnostic error:', 'celestial-lunar-phase' ) . '</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
			}
		}

		public function render_shortcode( $atts = array() ) {
			$settings = $this->get_settings();
			$atts = shortcode_atts(
				array(
					'location'      => $settings['default_location'],
					'date'          => $this->get_default_local_date( $settings ),
					'title'         => $settings['default_title'],
					'show_location' => '1' === $settings['show_location'] ? 'yes' : 'no',
					'show_credit'   => '1' === $settings['show_credit'] ? 'yes' : 'no',
				),
				$atts,
				'celestial_lunar_phase_widget'
			);
			try {
				return $this->render_widget( $atts );
			} catch ( Throwable $e ) {
				return $this->handle_render_exception( $e, 'shortcode' );
			}
		}

		public function render_block( $attributes = array() ) {
			$settings = $this->get_settings();
			try {
				return $this->render_widget(
					array(
						'location'      => ! empty( $attributes['location'] ) ? $attributes['location'] : $settings['default_location'],
						'date'          => ! empty( $attributes['date'] ) ? $attributes['date'] : $this->get_default_local_date( $settings ),
						'title'         => ! empty( $attributes['title'] ) ? $attributes['title'] : $settings['default_title'],
						'show_location' => ! empty( $attributes['showLocation'] ) ? 'yes' : 'no',
						'show_credit'   => ! empty( $attributes['showCredit'] ) ? 'yes' : 'no',
					)
				);
			} catch ( Throwable $e ) {
				return $this->handle_render_exception( $e, 'block' );
			}
		}

		private function render_widget( $args ) {
			$settings      = $this->get_settings();
			$location      = sanitize_text_field( $args['location'] ?? $settings['default_location'] );
			$date          = sanitize_text_field( $args['date'] ?? $this->get_default_local_date( $settings ) );
			$title         = sanitize_text_field( $args['title'] ?? $settings['default_title'] );
			$show_location = 'yes' === strtolower( (string) ( $args['show_location'] ?? 'yes' ) );
			$show_credit   = 'yes' === strtolower( (string) ( $args['show_credit'] ?? 'yes' ) );

			if ( ! $this->validate_date( $date ) ) {
				return $this->render_notice( __( 'Invalid date format. Use YYYY-MM-DD.', 'celestial-lunar-phase' ) );
			}

			$data = $this->get_local_data( $settings, $date, $location );
			if ( is_wp_error( $data ) ) {
				return $this->render_notice( $data->get_error_message() );
			}

			$phase_name     = $data['phase_name'];
			$moonrise       = $this->format_output_time( $data['moonrise'], $settings['time_format'] );
			$moonset        = $this->format_output_time( $data['moonset'], $settings['time_format'] );
			$sunrise        = $this->format_output_time( $data['sunrise'], $settings['time_format'] );
			$sunset         = $this->format_output_time( $data['sunset'], $settings['time_format'] );
			$illumination   = $data['illumination'];
			$location_label = ! empty( $data['location_label'] ) ? $data['location_label'] : $location;
			$image_url      = $this->get_phase_image_url( $phase_name );
			wp_enqueue_style( 'lpsw-frontend' );

			ob_start();
			?>
			<div class="lpsw-card">
				<?php if ( ! empty( $title ) ) : ?><div class="lpsw-title"><?php echo esc_html( $title ); ?></div><?php endif; ?>
				<div class="lpsw-media"><img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $phase_name ); ?>" loading="lazy" /></div>
				<div class="lpsw-content">
					<div class="lpsw-phase"><?php echo esc_html( $phase_name ); ?></div>
					<?php if ( $show_location && ! empty( $location_label ) ) : ?><div class="lpsw-location"><?php echo esc_html( $location_label ); ?></div><?php endif; ?>
					<div class="lpsw-meta">
						<?php $this->render_meta_row( __( 'Moonrise', 'celestial-lunar-phase' ), $moonrise ); ?>
						<?php $this->render_meta_row( __( 'Moonset', 'celestial-lunar-phase' ), $moonset ); ?>
						<?php if ( '1' === $settings['show_sun_times'] ) : ?>
							<?php $this->render_meta_row( __( 'Dawn', 'celestial-lunar-phase' ), $sunrise ); ?>
							<?php $this->render_meta_row( __( 'Dusk', 'celestial-lunar-phase' ), $sunset ); ?>
						<?php endif; ?>
						<?php if ( '' !== (string) $illumination ) : ?><?php $this->render_meta_row( __( 'Illumination', 'celestial-lunar-phase' ), $illumination . '%' ); ?><?php endif; ?>
					</div>
					<?php if ( $show_credit ) : ?>
						<div class="lpsw-credit"><?php echo wp_kses_post( $data['credit'] ); ?></div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		private function render_meta_row( $label, $value ) {
			echo '<div class="lpsw-meta-row"><span class="lpsw-label">' . esc_html( $label ) . '</span><span class="lpsw-value">' . esc_html( $value ) . '</span></div>';
		}

		private function render_notice( $message ) {
			return '<div class="lpsw-notice">' . esc_html( $message ) . '</div>';
		}

		private function handle_render_exception( Throwable $e, $context ) {
			$message = 'Celestial Lunar Phase Widget ' . $context . ' error: ' . $e->getMessage();
			error_log( $message );
			if ( current_user_can( 'manage_options' ) ) {
				return $this->render_notice( __( 'Celestial Lunar Phase Widget local calculation error: ', 'celestial-lunar-phase' ) . $e->getMessage() );
			}
			return $this->render_notice( __( 'Celestial Lunar Phase Widget encountered an error. Please check the plugin settings.', 'celestial-lunar-phase' ) );
		}


		private function get_default_local_date( $settings = null ) {
			if ( null === $settings ) {
				$settings = $this->get_settings();
			}

			// In local astronomy mode, the calendar date must come from the plugin's
			// configured observing timezone, not necessarily the WordPress/site timezone.
			// Otherwise sites left on UTC can show tomorrow's lunar events during the
			// evening in America/Chicago and other western timezones.
			$tz = $this->normalize_timezone( $settings['timezone'] ?? '' );
			if ( ! empty( $settings['latitude'] ) && ! empty( $settings['longitude'] ) ) {
				$tz = $this->maybe_infer_timezone_from_coordinates( $tz, (float) $settings['latitude'], (float) $settings['longitude'] );
			}

			try {
				return ( new DateTimeImmutable( 'now', new DateTimeZone( $tz ) ) )->format( 'Y-m-d' );
			} catch ( Throwable $e ) {
				return current_time( 'Y-m-d' );
			}
		}

		private function validate_date( $date ) {
			$dt = DateTime::createFromFormat( 'Y-m-d', $date );
			return $dt && $dt->format( 'Y-m-d' ) === $date;
		}

		private function get_local_data( $settings, $date, $location_label ) {
			try {
				if ( '' === (string) $settings['latitude'] || '' === (string) $settings['longitude'] ) {
					return new WP_Error( 'lpsw_missing_coordinates', __( 'Local calculation mode requires latitude and longitude in the plugin settings.', 'celestial-lunar-phase' ) );
				}

				$lat = (float) $settings['latitude'];
				$lon = (float) $settings['longitude'];
				if ( $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 ) {
					return new WP_Error( 'lpsw_invalid_coordinates', __( 'Local calculation mode requires valid latitude and longitude values.', 'celestial-lunar-phase' ) );
				}

				$tz = $this->normalize_timezone( $settings['timezone'] ?? '' );
				$tz = $this->maybe_infer_timezone_from_coordinates( $tz, $lat, $lon );
				$key = self::TRANSIENT_PREFIX . 'local_' . md5( $date . '|' . round( $lat, 4 ) . '|' . round( $lon, 4 ) . '|' . $tz );
				$cached = get_transient( $key );
				if ( false !== $cached ) {
					return $cached;
				}

				$tzobj  = new DateTimeZone( $tz );
				$start  = new DateTimeImmutable( $date . ' 00:00:00', $tzobj );
				// Use the requested local calendar date for rise/set events, and local noon for phase/illumination.
				// This prevents late-day UTC offsets from shifting phase calculations into the next date.
				$phase_time = new DateTimeImmutable( $date . ' 12:00:00', $tzobj );
				$events = $this->calculate_moonrise_moonset( $start, $lat, $lon );
				$sun    = $this->calculate_sunrise_sunset( $start, $lat, $lon, $tz );
				$phase  = $this->calculate_phase( $phase_time );

				$data = array(
					'phase_name'     => $phase['name'],
					'illumination'   => (string) $phase['illumination'],
					'moonrise'       => $events['rise'],
					'moonset'        => $events['set'],
					'sunrise'        => $sun['sunrise'],
					'sunset'         => $sun['sunset'],
					'location_label' => $location_label,
					'credit'         => sprintf( __( 'Calculated locally by Celestial Web Development for %1$s. Times use %2$s. Dawn/Dusk are astronomical twilight. Results may vary from other services due to calculation and horizon assumptions.', 'celestial-lunar-phase' ), esc_html( $date ), esc_html( $tz ) ),
				);
				set_transient( $key, $data, 12 * HOUR_IN_SECONDS );
				return $data;
			} catch ( Throwable $e ) {
				error_log( 'Celestial Lunar Phase Widget local data error: ' . $e->getMessage() );
				if ( current_user_can( 'manage_options' ) ) {
					return new WP_Error( 'lpsw_local_calculation_error', __( 'Local calculation error: ', 'celestial-lunar-phase' ) . $e->getMessage() );
				}
				return new WP_Error( 'lpsw_local_calculation_error', __( 'Local calculation failed. Please check the plugin settings.', 'celestial-lunar-phase' ) );
			}
		}

		private function normalize_timezone( $tz ) {
			$tz = trim( (string) $tz );
			$candidates = array( $tz, wp_timezone_string(), get_option( 'timezone_string' ), 'UTC' );
			foreach ( $candidates as $candidate ) {
				$candidate = trim( (string) $candidate );
				if ( '' === $candidate ) {
					continue;
				}
				try {
					new DateTimeZone( $candidate );
					return $candidate;
				} catch ( Exception $e ) {
					continue;
				}
			}
			return 'UTC';
		}

		private function maybe_infer_timezone_from_coordinates( $tz, $lat, $lon ) {
			/*
			 * If the site/plugin timezone was left as UTC but coordinates are clearly
			 * in the continental United States, use a practical location timezone.
			 * This prevents local astronomical events from displaying as UTC times.
			 * A real lookup remains preferred and will overwrite this setting.
			 */
			if ( 'UTC' !== $tz ) {
				return $tz;
			}
			if ( $lat >= 24 && $lat <= 50 && $lon <= -66 && $lon >= -125 ) {
				if ( $lon <= -115 ) {
					return 'America/Los_Angeles';
				}
				if ( $lon <= -102 ) {
					return 'America/Denver';
				}
				if ( $lon <= -85 ) {
					return 'America/Chicago';
				}
				return 'America/New_York';
			}
			return $tz;
		}

		private function format_output_time( $value, $format ) {
			if ( empty( $value ) ) {
				return __( 'N/A', 'celestial-lunar-phase' );
			}

			// Important: DateTimeInterface objects cannot be cast to strings in PHP.
			// Check object values before any string operations.
			if ( $value instanceof DateTimeInterface ) {
				return '24' === $format ? $value->format( 'H:i' ) : $value->format( 'g:i A' );
			}

			$value_string = (string) $value;
			$lower        = strtolower( $value_string );
			if ( false !== strpos( $lower, 'no ' ) || 'n/a' === $lower ) {
				return $value_string;
			}

			$dt = DateTime::createFromFormat( 'g:i A', $value_string );
			if ( ! $dt ) {
				$dt = DateTime::createFromFormat( 'H:i', $value_string );
			}
			if ( ! $dt ) {
				return $value_string;
			}
			return '24' === $format ? $dt->format( 'H:i' ) : $dt->format( 'g:i A' );
		}

		private function calculate_sunrise_sunset( DateTimeImmutable $start, $lat, $lon, $tz ) {
			$timestamp = $start->getTimestamp();
			$info = date_sun_info( $timestamp, $lat, $lon );
			$tzobj = new DateTimeZone( $tz );

			// For an astronomy-focused widget, show astronomical twilight boundaries:
			// - astronomical_twilight_begin = astronomical dawn, when the Sun is 18 degrees below the horizon before sunrise
			// - astronomical_twilight_end   = astronomical dusk, when the Sun is 18 degrees below the horizon after sunset
			// The returned array keeps the existing sunrise/sunset keys so the widget template remains compatible.
			$map = array(
				'sunrise' => 'astronomical_twilight_begin',
				'sunset'  => 'astronomical_twilight_end',
			);

			$out = array( 'sunrise' => '', 'sunset' => '' );
			foreach ( $map as $out_key => $sun_info_key ) {
				if ( isset( $info[ $sun_info_key ] ) && is_numeric( $info[ $sun_info_key ] ) ) {
					$out[ $out_key ] = ( new DateTimeImmutable( '@' . (int) $info[ $sun_info_key ] ) )->setTimezone( $tzobj );
				}
			}
			return $out;
		}

		private function calculate_moonrise_moonset( DateTimeImmutable $start, $lat, $lon ) {
			/*
			 * v2.0.8: Replaced the earlier experimental Meeus/Schlyter hybrid
			 * with the proven SunCalc-style moonTimes algorithm. This samples the
			 * apparent lunar altitude through the local calendar day and solves
			 * horizon crossings using quadratic interpolation. This matches common
			 * public almanac values much more closely for normal latitudes.
			 */
			$hc   = deg2rad( 0.133 ); // Standard Moon rise/set altitude, including apparent radius/refraction.
			$h0   = $this->suncalc_moon_altitude_rad( $start, $lat, $lon ) - $hc;
			$rise = null;
			$set  = null;

			for ( $i = 1; $i <= 24; $i += 2 ) {
				$h1 = $this->suncalc_moon_altitude_rad( $start->modify( '+' . $i . ' hours' ), $lat, $lon ) - $hc;
				$h2 = $this->suncalc_moon_altitude_rad( $start->modify( '+' . ( $i + 1 ) . ' hours' ), $lat, $lon ) - $hc;

				$a  = ( $h0 + $h2 ) / 2 - $h1;
				$b  = ( $h2 - $h0 ) / 2;
				$xe = ( 0.0 !== $a ) ? -$b / ( 2 * $a ) : 0;
				$ye = ( $a * $xe + $b ) * $xe + $h1;
				$d  = $b * $b - 4 * $a * $h1;
				$roots = 0;
				$x1 = 0;
				$x2 = 0;

				if ( $d >= 0 && 0.0 !== $a ) {
					$dx = sqrt( $d ) / ( abs( $a ) * 2 );
					$x1 = $xe - $dx;
					$x2 = $xe + $dx;
					if ( abs( $x1 ) <= 1 ) {
						$roots++;
					}
					if ( abs( $x2 ) <= 1 ) {
						$roots++;
					}
					if ( $x1 < -1 ) {
						$x1 = $x2;
					}
				}

				if ( 1 === $roots ) {
					if ( $h0 < 0 ) {
						$rise = $i + $x1;
					} else {
						$set = $i + $x1;
					}
				} elseif ( 2 === $roots ) {
					$rise = $i + ( $ye < 0 ? $x2 : $x1 );
					$set  = $i + ( $ye < 0 ? $x1 : $x2 );
				}

				if ( null !== $rise && null !== $set ) {
					break;
				}

				$h0 = $h2;
			}

			return array(
				'rise' => null !== $rise ? $start->modify( '+' . (int) round( $rise * 3600 ) . ' seconds' ) : __( 'No moonrise', 'celestial-lunar-phase' ),
				'set'  => null !== $set ? $start->modify( '+' . (int) round( $set * 3600 ) . ' seconds' ) : __( 'No moonset', 'celestial-lunar-phase' ),
			);
		}

		private function suncalc_moon_altitude_rad( DateTimeInterface $dt, $lat, $lon ) {
			$lw  = deg2rad( -$lon );
			$phi = deg2rad( $lat );
			$d   = $this->suncalc_to_days( $dt );
			$c   = $this->suncalc_moon_coords( $d );
			$H   = $this->suncalc_sidereal_time( $d, $lw ) - $c['ra'];
			$h   = $this->suncalc_altitude( $H, $phi, $c['dec'] );
			return $h + $this->suncalc_astro_refraction( $h );
		}

		private function suncalc_moon_coords( $d ) {
			$L = deg2rad( 218.316 + 13.176396 * $d );
			$M = deg2rad( 134.963 + 13.064993 * $d );
			$F = deg2rad( 93.272 + 13.229350 * $d );
			$l = $L + deg2rad( 6.289 ) * sin( $M );
			$b = deg2rad( 5.128 ) * sin( $F );
			$dt = 385001 - 20905 * cos( $M );
			return array(
				'ra'   => $this->suncalc_right_ascension( $l, $b ),
				'dec'  => $this->suncalc_declination( $l, $b ),
				'dist' => $dt,
			);
		}

		private function suncalc_sun_coords( $d ) {
			$M = deg2rad( 357.5291 + 0.98560028 * $d );
			$C = deg2rad( 1.9148 * sin( $M ) + 0.0200 * sin( 2 * $M ) + 0.0003 * sin( 3 * $M ) );
			$P = deg2rad( 102.9372 );
			$L = $M + $C + $P + pi();
			return array(
				'dec' => $this->suncalc_declination( $L, 0 ),
				'ra'  => $this->suncalc_right_ascension( $L, 0 ),
			);
		}

		private function calculate_phase( DateTimeImmutable $start ) {
			/*
			 * v2.0.8: Use SunCalc's Moon illumination method. The returned phase
			 * value is 0=new, 0.25=first quarter, 0.5=full, 0.75=last quarter.
			 */
			$d = $this->suncalc_to_days( $start->setTime( 12, 0, 0 ) );
			$s = $this->suncalc_sun_coords( $d );
			$m = $this->suncalc_moon_coords( $d );
			$sdist = 149598000;
			$phi = acos( sin( $s['dec'] ) * sin( $m['dec'] ) + cos( $s['dec'] ) * cos( $m['dec'] ) * cos( $s['ra'] - $m['ra'] ) );
			$inc = atan2( $sdist * sin( $phi ), $m['dist'] - $sdist * cos( $phi ) );
			$angle = atan2(
				cos( $s['dec'] ) * sin( $s['ra'] - $m['ra'] ),
				sin( $s['dec'] ) * cos( $m['dec'] ) - cos( $s['dec'] ) * sin( $m['dec'] ) * cos( $s['ra'] - $m['ra'] )
			);
			$fraction = ( 1 + cos( $inc ) ) / 2;
			$phase = 0.5 + 0.5 * $inc * ( $angle < 0 ? -1 : 1 ) / pi();
			if ( $phase < 0 ) {
				$phase += 1;
			} elseif ( $phase >= 1 ) {
				$phase -= 1;
			}

			$illum = (int) round( $fraction * 100 );

			if ( $illum <= 2 ) {
				$name = 'New Moon';
			} elseif ( $illum >= 98 ) {
				$name = 'Full Moon';
			} elseif ( $phase < 0.25 ) {
				$name = 'Waxing Crescent';
			} elseif ( abs( $phase - 0.25 ) <= 0.03 ) {
				$name = 'First Quarter';
			} elseif ( $phase < 0.5 ) {
				$name = 'Waxing Gibbous';
			} elseif ( $phase < 0.75 ) {
				$name = 'Waning Gibbous';
			} elseif ( abs( $phase - 0.75 ) <= 0.03 ) {
				$name = 'Last Quarter';
			} else {
				$name = 'Waning Crescent';
			}

			return array( 'name' => $name, 'illumination' => $illum, 'phase' => round( $phase, 4 ) );
		}

		private function suncalc_to_days( DateTimeInterface $dt ) {
			return $this->julian_day( $dt ) - 2451545.0;
		}

		private function suncalc_right_ascension( $l, $b ) {
			$e = deg2rad( 23.4397 );
			return atan2( sin( $l ) * cos( $e ) - tan( $b ) * sin( $e ), cos( $l ) );
		}

		private function suncalc_declination( $l, $b ) {
			$e = deg2rad( 23.4397 );
			return asin( sin( $b ) * cos( $e ) + cos( $b ) * sin( $e ) * sin( $l ) );
		}

		private function suncalc_sidereal_time( $d, $lw ) {
			return deg2rad( 280.16 + 360.9856235 * $d ) - $lw;
		}

		private function suncalc_altitude( $H, $phi, $dec ) {
			return asin( sin( $phi ) * sin( $dec ) + cos( $phi ) * cos( $dec ) * cos( $H ) );
		}

		private function suncalc_astro_refraction( $h ) {
			if ( $h < 0 ) {
				$h = 0;
			}
			return 0.0002967 / tan( $h + 0.00312536 / ( $h + 0.08901179 ) );
		}

		private function julian_day( DateTimeInterface $dt ) {
			$utc = ( new DateTimeImmutable( '@' . $dt->getTimestamp() ) )->setTimezone( new DateTimeZone( 'UTC' ) );
			$y = (int) $utc->format( 'Y' );
			$m = (int) $utc->format( 'n' );
			$day = (int) $utc->format( 'j' ) + ( (int) $utc->format( 'G' ) + (int) $utc->format( 'i' ) / 60 + (int) $utc->format( 's' ) / 3600 ) / 24;
			if ( $m <= 2 ) { $y -= 1; $m += 12; }
			$A = floor( $y / 100 );
			$B = 2 - $A + floor( $A / 4 );
			return floor( 365.25 * ( $y + 4716 ) ) + floor( 30.6001 * ( $m + 1 ) ) + $day + $B - 1524.5;
		}

		private function fix_angle( $deg ) {
			$deg = fmod( $deg, 360.0 );
			return $deg < 0 ? $deg + 360.0 : $deg;
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
			$key = strtolower( trim( $phase_name ) );
			$file = isset( $map[ $key ] ) ? $map[ $key ] : 'full-moon.jpg';
			return plugin_dir_url( __FILE__ ) . 'assets/images/phases/' . $file;
		}
	}

	new Celestial_Lunar_Phase_Widget();
}
