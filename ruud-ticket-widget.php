<?php
/**
 * Plugin Name: Helpdesk (Digitaal Wendbaar)
 * Description: Zet een kort helpdesk-formulier in het WordPress-dashboard, dat rechtstreeks bij ons binnenkomt. Geen instellingen nodig — herkent automatisch om welke website het gaat.
 * Version: 1.5.0
 * Author: Digitaal Wendbaar
 * Text Domain: rtw
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automatisch updaten vanaf GitHub.
 */
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$rtwUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/ruudl67/wp-ticket-widget',
	__FILE__,
	'ruud-ticket-widget'
);
$rtwUpdateChecker->setBranch( 'main' );

/**
 * Vertalingen laden.
 */
function rtw_load_textdomain() {
	load_plugin_textdomain( 'rtw', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'rtw_load_textdomain' );

/**
 * Firebase-configuratie.
 */
define( 'RTW_FIREBASE_API_KEY', 'AIzaSyCbJ9YY6rhEWmYO_SPw7IG-OhUTqFW-rRE' );
define( 'RTW_FIREBASE_AUTH_DOMAIN', 'kaartenbak-6ae15.firebaseapp.com' );
define( 'RTW_FIREBASE_PROJECT_ID', 'kaartenbak-6ae15' );
define( 'RTW_FIREBASE_STORAGE_BUCKET', 'kaartenbak-6ae15.firebasestorage.app' );
define( 'RTW_FIREBASE_MESSAGING_SENDER_ID', '94857478666' );
define( 'RTW_FIREBASE_APP_ID', '1:94857478666:web:b299a0435653eb225cd5f3' );

function rtw_get_site_host() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! $host ) {
		return '';
	}
	$host = strtolower( $host );
	$host = preg_replace( '#^www\.#', '', $host );
	return $host;
}

/**
 * Widget registreren op het Dashboard.
 */
function rtw_register_dashboard_widget() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	wp_add_dashboard_widget(
		'rtw_ticket_widget',
		__( 'Helpdesk', 'rtw' ),
		'rtw_render_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'rtw_register_dashboard_widget' );

$GLOBALS['rtw_menu_hook'] = '';
function rtw_register_admin_menu() {
	$GLOBALS['rtw_menu_hook'] = add_menu_page(
		__( 'Helpdesk', 'rtw' ),
		__( 'Helpdesk', 'rtw' ),
		'read',
		'rtw-ticket-form',
		'rtw_render_dashboard_widget',
		'dashicons-megaphone',
		3
	);
}
add_action( 'admin_menu', 'rtw_register_admin_menu' );

function rtw_admin_menu_style() {
	?>
	<style>
		#toplevel_page_rtw-ticket-form > a.menu-top {
			background-color: #009284 !important;
		}
		#toplevel_page_rtw-ticket-form > a.menu-top .wp-menu-name,
		#toplevel_page_rtw-ticket-form div.wp-menu-image:before {
			color: #ffffff !important;
			font-weight: 700;
		}
		#toplevel_page_rtw-ticket-form:hover > a.menu-top,
		#toplevel_page_rtw-ticket-form.current > a.menu-top,
		#toplevel_page_rtw-ticket-form.wp-has-current-submenu > a.menu-top {
			background-color: #007266 !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'rtw_admin_menu_style' );

function rtw_enqueue_assets( $hook ) {
	if ( 'index.php' !== $hook && $hook !== $GLOBALS['rtw_menu_hook'] ) {
		return;
	}

	wp_enqueue_script(
		'rtw-firebase-app',
		'https://cdn.jsdelivr.net/npm/firebase@10.12.2/firebase-app-compat.js',
		array(),
		'10.12.2',
		true
	);
	wp_enqueue_script(
		'rtw-firebase-firestore',
		'https://cdn.jsdelivr.net/npm/firebase@10.12.2/firebase-firestore-compat.js',
		array( 'rtw-firebase-app' ),
		'10.12.2',
		true
	);

	wp_register_script( 'rtw-ticket-form', '', array( 'rtw-firebase-firestore' ), '1.0.0', true );
	wp_enqueue_script( 'rtw-ticket-form' );

	$current_user = wp_get_current_user();

	wp_localize_script(
		'rtw-ticket-form',
		'RTW_DATA',
		array(
			'firebaseConfig' => array(
				'apiKey'            => RTW_FIREBASE_API_KEY,
				'authDomain'        => RTW_FIREBASE_AUTH_DOMAIN,
				'projectId'         => RTW_FIREBASE_PROJECT_ID,
				'storageBucket'     => RTW_FIREBASE_STORAGE_BUCKET,
				'messagingSenderId' => RTW_FIREBASE_MESSAGING_SENDER_ID,
				'appId'             => RTW_FIREBASE_APP_ID,
			),
			'siteUrl'        => rtw_get_site_host(),
			'siteName'       => get_bloginfo( 'name' ),
			'userName'       => $current_user && $current_user->exists() ? $current_user->display_name : '',
			'i18n'           => array(
				'validationError' => __( 'Please describe what you would like changed.', 'rtw' ),
				'sending'         => __( 'Sending…', 'rtw' ),
				'success'         => __( 'Sent — thank you! Ruud will receive your request right away.', 'rtw' ),
				'error'           => __( 'Something went wrong — please try again.', 'rtw' ),
				'send'            => __( 'Send', 'rtw' ),
			),
		)
	);

	wp_add_inline_script( 'rtw-ticket-form', rtw_inline_script() );
}
add_action( 'admin_enqueue_scripts', 'rtw_enqueue_assets' );

function rtw_inline_script() {
	return <<<'JS'
(function () {
	function init() {
		var app = firebase.initializeApp(RTW_DATA.firebaseConfig, 'rtw-' + RTW_DATA.siteUrl);
		var db = app.firestore();
		var i18n = RTW_DATA.i18n;

		var form = document.getElementById('rtw-form');
		if (!form) return;
		var msgEl = document.getElementById('rtw-message');
		var nameEl = document.getElementById('rtw-name');
		var btn = document.getElementById('rtw-submit');
		var statusEl = document.getElementById('rtw-status');
		var missing = document.getElementById('rtw-missing-site');

		if (!RTW_DATA.siteUrl) {
			if (missing) missing.style.display = '';
			form.style.display = 'none';
			return;
		}

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var message = (msgEl.value || '').trim();
			statusEl.textContent = '';
			statusEl.className = 'rtw-status';
			if (!message) {
				statusEl.textContent = i18n.validationError;
				statusEl.className = 'rtw-status rtw-err';
				return;
			}
			btn.disabled = true;
			btn.textContent = i18n.sending;
			db.collection('tickets').add({
				url: RTW_DATA.siteUrl,
				siteName: RTW_DATA.siteName,
				message: message,
				contactName: (nameEl.value || '').trim() || null,
				status: 'open',
				createdAt: firebase.firestore.FieldValue.serverTimestamp(),
			}).then(function () {
				statusEl.textContent = i18n.success;
				statusEl.className = 'rtw-status rtw-ok';
				msgEl.value = '';
			}).catch(function () {
				statusEl.textContent = i18n.error;
				statusEl.className = 'rtw-status rtw-err';
			}).finally(function () {
				btn.disabled = false;
				btn.textContent = i18n.send;
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
JS;
}

function rtw_render_dashboard_widget() {
	$host = rtw_get_site_host();
	?>
	<div class="rtw-card">
		<div id="rtw-missing-site" class="notice notice-error" style="display:none;margin:0 0 10px;">
			<p><?php esc_html_e( 'Could not detect the domain of this website — please contact us.', 'rtw' ); ?></p>
		</div>
		<h2 class="rtw-title"><?php esc_html_e( 'Create a ticket', 'rtw' ); ?></h2>
		<p class="rtw-intro"><?php esc_html_e( 'Would you like something changed or added to your website? Describe it below — your request arrives with us directly, no account or email needed.', 'rtw' ); ?></p>
		<form id="rtw-form">
			<label for="rtw-message"><?php esc_html_e( 'What would you like changed or added?', 'rtw' ); ?></label>
			<textarea id="rtw-message" rows="4" placeholder="<?php esc_attr_e( 'E.g. please update the phone number in the footer to…', 'rtw' ); ?>"></textarea>
			<label for="rtw-name"><?php esc_html_e( 'Your name (optional)', 'rtw' ); ?></label>
			<input type="text" id="rtw-name" value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">
			<button type="submit" id="rtw-submit"><?php esc_html_e( 'Send', 'rtw' ); ?></button>
			<div id="rtw-status" class="rtw-status"></div>
		</form>
		<?php if ( $host ) : ?>
			<p class="rtw-footnote"><?php echo esc_html( sprintf( __( 'Website: %s', 'rtw' ), $host ) ); ?></p>
		<?php endif; ?>
	</div>
	<style>
		.rtw-card {
			background: #fff;
			border-radius: 20px;
			box-shadow: 0 2px 10px rgba(23,35,42,0.10);
			padding: 28px 24px;
			max-width: 420px;
			margin-top: 50px;
			box-sizing: border-box;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
		}
		.rtw-title { margin: 0 0 8px; font-size: 20px; font-weight: 700; letter-spacing: -0.01em; color: #009284; }
		.rtw-intro { margin: 0 0 4px; font-size: 13.5px; color: #5B6870; line-height: 1.5; }
		.rtw-card label { display: block; font-size: 12.5px; color: #5B6870; margin: 14px 0 6px; }
		.rtw-card textarea,
		.rtw-card input[type="text"] {
			width: 100%;
			border: 1px solid #E7E9EB;
			background: #F4F5F6;
			border-radius: 12px;
			padding: 11px 13px;
			font-size: 15px;
			color: #17232A;
			resize: vertical;
			font-family: inherit;
			box-sizing: border-box;
		}
		.rtw-card textarea:focus,
		.rtw-card input[type="text"]:focus { outline: 2px solid #009284; outline-offset: 1px; }
		.rtw-card button#rtw-submit {
			margin-top: 18px;
			width: 100%;
			background: #009284;
			color: #fff;
			border: none;
			border-radius: 999px;
			padding: 14px;
			font-size: 15px;
			font-weight: 700;
			cursor: pointer;
			box-shadow: none;
		}
		.rtw-card button#rtw-submit:hover { filter: brightness(1.05); }
		.rtw-card button#rtw-submit:disabled { opacity: .5; cursor: default; }
		.rtw-footnote { font-size: 11.5px; color: #939CA3; margin: 16px 0 0; line-height: 1.5; }
		.rtw-status { font-size: 13px; margin-top: 10px; display: block; }
		.rtw-status.rtw-ok { color: #1a7f37; font-weight: 600; }
		.rtw-status.rtw-err { color: #d63638; font-weight: 600; }
	</style>
	<?php
}
