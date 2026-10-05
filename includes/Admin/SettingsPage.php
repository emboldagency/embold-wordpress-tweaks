<?php

namespace App\Admin;

use App\Services\DisableMailService;

class SettingsPage {



	private const OPTION_NAME = 'embold_tweaks_options';
	private DisableMailService $mailService;

	public function __construct() {
		$this->mailService = new DisableMailService();
	}

	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'addSettingsPage' ] );
		add_action( 'admin_init', [ $this, 'registerSettings' ] );
		add_action( 'admin_init', [ $this, 'handleReset' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		add_action( 'admin_notices', [ $this, 'showConflictDetection' ] );

		// Add settings link to plugin row
		add_filter( 'plugin_action_links_embold-wordpress-tweaks/embold-wordpress-tweaks.php', [ $this, 'addSettingsLink' ] );

		// Handle test email
		add_action( 'admin_post_embold_send_test_email', [ $this, 'handleSendTestEmail' ] );
	}

	public function enqueueAssets( $hook ): void {
		if ( $hook !== 'settings_page_embold-wordpress-tweaks' ) {
			return;
		}

		$plugin_data = get_file_data(
			dirname( dirname( __DIR__ ) ) . '/embold-wordpress-tweaks.php',
			[ 'Version' => 'Version' ]
		);

		$version = $plugin_data['Version'] ?? '1.6.0';

		wp_enqueue_style(
			'embold-tweaks-settings',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/settings-page.css',
			[],
			$version
		);

		wp_enqueue_script(
			'embold-tweaks-settings',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/settings-page.js',
			[],
			$version,
			true
		);
	}

	public function handleSendTestEmail() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'embold-wordpress-tweaks' ) );
		}

		check_admin_referer( 'embold_send_test_email', 'embold_test_nonce' );

		if ( ! isset( $_POST['embold_test_email'] ) ) {
			wp_die( esc_html__( 'Missing email address.', 'embold-wordpress-tweaks' ) );
		}

		$to      = sanitize_email( wp_unslash( $_POST['embold_test_email'] ) );
		$subject = 'Embold Tweaks: Test Email';
		$body    = 'This is a test email sent from Embold WordPress Tweaks to verify your mail configuration.';

		// Capture errors
		$mail_failed_msg = '';
		$failed_cb       = function ( $wp_error ) use ( &$mail_failed_msg ) {
			if ( is_wp_error( $wp_error ) ) {
				$mail_failed_msg = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $failed_cb );

		$sent = wp_mail( $to, $subject, $body );

		remove_action( 'wp_mail_failed', $failed_cb );

		if ( $sent ) {
			$msg      = rawurlencode( sprintf( __( 'Test email sent to %s.', 'embold-wordpress-tweaks' ), $to ) );
			$redirect = add_query_arg(
				[
					'page'             => 'embold-wordpress-tweaks',
					'settings-updated' => 'true',
					'embold_msg'       => $msg,
				],
				admin_url( 'options-general.php' )
			);
		} else {
			$error    = $mail_failed_msg ?: __( 'wp_mail returned false.', 'embold-wordpress-tweaks' );
			$msg      = rawurlencode( sprintf( __( 'Test email failed: %s', 'embold-wordpress-tweaks' ), $error ) );
			$redirect = add_query_arg(
				[
					'page'       => 'embold-wordpress-tweaks',
					'error'      => 'true',
					'embold_err' => $msg,
				],
				admin_url( 'options-general.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public function handleReset(): void {
		if ( isset( $_POST['embold_reset_settings'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! check_admin_referer( 'embold_reset_settings_action', 'embold_reset_nonce' ) ) {
				return;
			}

			delete_option( self::OPTION_NAME );

			add_settings_error(
				self::OPTION_NAME,
				'embold_reset',
				__( 'Settings reset to defaults.', 'embold-wordpress-tweaks' ),
				'updated'
			);

			// Schedule cleanup of the notice after this request
			add_action(
				'shutdown',
				function () {
					global $wp_settings_errors;
					if ( isset( $wp_settings_errors ) ) {
						// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						$wp_settings_errors = array_filter(
							$wp_settings_errors,
							function ( $error ) {
								return $error['setting'] !== self::OPTION_NAME || $error['code'] !== 'embold_reset';
							}
						);
					}
				}
			);
		}
	}

	/**
	 * Add settings link to plugin row on plugins page.
	 *
	 * @param array $links The plugin action links.
	 * @return array The modified plugin action links.
	 */
	public function addSettingsLink( $links ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=embold-wordpress-tweaks' ) . '">' . __( 'Settings', 'embold-wordpress-tweaks' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Show conflict detection warning if remove-xmlrpc-pingback-ping plugin is active.
	 *
	 * @return void
	 */
	public function showConflictDetection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_plugin_active( 'remove-xmlrpc-pingback-ping/remove-xmlrpc-pingback-ping.php' ) ) {
			return;
		}

		$options         = get_option( self::OPTION_NAME, [] );
		$xmlrpc_disabled = ! empty( $options['disable_xmlrpc'] ) || ( defined( 'EMBOLD_DISABLE_XMLRPC' ) && EMBOLD_DISABLE_XMLRPC );

		if ( ! $xmlrpc_disabled ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'emBold Tweaks:', 'embold-wordpress-tweaks' ); ?></strong>
				<?php esc_html_e( 'Warning: "Remove XMLRPC Pingback Ping" is active and may conflict with emBold Tweaks\'s XML-RPC disable feature. Please deactivate one of them.', 'embold-wordpress-tweaks' ); ?>
			</p>
		</div>
		<?php
	}

	public function addSettingsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$should_render = false;

		// Check if restrictions are globally disabled (Loose Mode)
		$restrictions = $this->resolveUserRestrictions();
		if ( $restrictions['restrictions_disabled'] ) {
			$should_render = true;
		} else {
			// Restrictions are ACTIVE. User MUST be Elevated to see this page.

			// Use unified elevation logic (Defaults + Resolved Priority Source)
			$current_user = wp_get_current_user();
			$user_email   = strtolower( $current_user->user_email );

			// Default hardcoded emails
			$elevated_emails = [
				'info@embold.com',
				'info@wphaven.app',
			];

			// Add configured emails (Constants > Options)
			$resolution      = $this->resolveElevatedEmails();
			$elevated_emails = array_merge( $elevated_emails, $resolution['emails'] );

			// Normalize and check
			$elevated_emails = array_unique( array_map( 'strtolower', $elevated_emails ) );

			if ( in_array( $user_email, $elevated_emails, true ) ) {
				$should_render = true;
			}
		}

		if ( ! $should_render ) {
			return;
		}

		add_options_page(
			__( 'Embold Tweaks', 'embold-wordpress-tweaks' ),
			__( 'Embold Tweaks', 'embold-wordpress-tweaks' ),
			'manage_options',
			'embold-wordpress-tweaks',
			[ $this, 'renderSettingsPage' ]
		);
	}

	/**
	 * Settings page layout: tabs => sections => fields.
	 *
	 * Fields default to a checkbox. `type` picks another renderer (text, number,
	 * email, password, textarea) and `render` names a bespoke render method.
	 * `class` is applied to the field's table row by the Settings API.
	 *
	 * Within a section, order fields alphabetically by what they toggle (the
	 * subject, not the verb), keeping a toggle and the fields it reveals together.
	 */
	private function getTabs(): array {
		return [
			'security'    => [
				'label'    => __( 'Security', 'embold-wordpress-tweaks' ),
				'sections' => [
					'embold_tweaks_security'          => [
						'title'  => __( 'Hardening', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Reduce attack surface and information leakage.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'disable_generator_tag' => [
								'label' => __( 'Generator Tag', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_GENERATOR_TAG',
								'desc'  => __( 'Removes the WordPress version generator meta tag from the page head and RSS/Atom feeds.', 'embold-wordpress-tweaks' ),
							],
							'disable_rest_metadata' => [
								'label' => __( 'REST Metadata', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_REST_METADATA',
								'desc'  => __( 'Removes WP REST metadata from your &lt;head&gt; (the discovery link tag and HTTP header). It does not disable the REST API.', 'embold-wordpress-tweaks' ),
							],
							'enable_svg'            => [
								'label' => __( 'SVG Uploads', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_ALLOW_SVG',
								'desc'  => __( 'Allows SVG files to be uploaded to the Media Library.', 'embold-wordpress-tweaks' ) .
									'<div class="embold-warning">' .
									'<strong>' . __( 'Security Warning', 'embold-wordpress-tweaks' ) . '</strong>' .
									'<span>' . __( 'SVGs can contain malicious code; ensure only trusted users have upload permissions.', 'embold-wordpress-tweaks' ) . '</span>' .
									'</div>',
							],
							'disable_xmlrpc'        => [
								'label' => __( 'XML-RPC', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_XMLRPC',
								'desc'  => __( 'Disables the XML-RPC API to protect against brute-force attacks and DDoS.', 'embold-wordpress-tweaks' ),
							],
						],
					],
					'embold_tweaks_user_restrictions' => [
						'title'  => __( 'User Restrictions', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Control which users can manage plugins, themes, and files.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'elevated_emails'         => [
								'label'  => __( 'Elevated Admin Emails', 'embold-wordpress-tweaks' ),
								'render' => 'renderElevatedEmailsField',
							],
							'loose_user_restrictions' => [
								'label'  => __( 'Enforce User Restrictions', 'embold-wordpress-tweaks' ),
								'render' => 'renderLooseUserRestrictionsField',
							],
						],
					],
				],
			],
			'mail'        => [
				'label'    => __( 'Mail', 'embold-wordpress-tweaks' ),
				'sections' => [
					'embold_tweaks_mail' => [
						'title'  => __( 'Mail Behavior', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Control how mail behaves per environment.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'mail_mode'       => [
								'label'  => __( 'Mail Mode', 'embold-wordpress-tweaks' ),
								'render' => 'renderMailModeField',
							],
							'smtp_host'       => [
								'label'   => __( 'SMTP Host', 'embold-wordpress-tweaks' ),
								'type'    => 'text',
								'default' => 'mailpit',
								'const'   => 'EMBOLD_SMTP_HOST',
								'class'   => 'embold-smtp-field',
							],
							'smtp_port'       => [
								'label'   => __( 'SMTP Port', 'embold-wordpress-tweaks' ),
								'type'    => 'number',
								'default' => '1025',
								'const'   => 'EMBOLD_SMTP_PORT',
								'class'   => 'embold-smtp-field',
							],
							'smtp_from_email' => [
								'label'   => __( 'From Email', 'embold-wordpress-tweaks' ),
								'type'    => 'email',
								'default' => 'admin@wordpress.local',
								'const'   => 'EMBOLD_SMTP_FROM_EMAIL',
								'class'   => 'embold-smtp-field',
							],
							'smtp_from_name'  => [
								'label'   => __( 'From Name', 'embold-wordpress-tweaks' ),
								'type'    => 'text',
								'default' => 'WordPress',
								'const'   => 'EMBOLD_SMTP_FROM_NAME',
								'class'   => 'embold-smtp-field',
							],
							'smtp_username'   => [
								'label' => __( 'SMTP Username', 'embold-wordpress-tweaks' ),
								'type'  => 'text',
								'const' => 'EMBOLD_SMTP_USERNAME',
								'class' => 'embold-smtp-field',
							],
							'smtp_password'   => [
								'label' => __( 'SMTP Password', 'embold-wordpress-tweaks' ),
								'type'  => 'password',
								'const' => 'EMBOLD_SMTP_PASSWORD',
								'class' => 'embold-smtp-field',
							],
							'smtp_secure'     => [
								'label' => __( 'Encryption', 'embold-wordpress-tweaks' ),
								'type'  => 'text',
								'desc'  => __( 'Leave blank for no encryption, or use <code>ssl</code> or <code>tls</code>.', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_SMTP_SECURE',
								'class' => 'embold-smtp-field',
							],
						],
					],
				],
			],
			'admin'       => [
				'label'    => __( 'Admin & Editor', 'embold-wordpress-tweaks' ),
				'sections' => [
					'embold_tweaks_admin' => [
						'title'  => __( 'Admin & Editor', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Quality-of-life improvements for people editing the site.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'enable_duplicate_post' => [
								'label' => __( 'Duplicate Posts', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_ENABLE_DUPLICATE_POST',
								'desc'  => __( 'Adds a "Duplicate" link to the row actions on posts, pages, and custom post types to clone them (content, meta, and taxonomies) as a new draft.', 'embold-wordpress-tweaks' ),
							],
							'remove_howdy'          => [
								'label' => __( '"Howdy" Greeting', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_REMOVE_HOWDY',
								'desc'  => __( 'Removes the "Howdy" text from the admin bar greeting.', 'embold-wordpress-tweaks' ),
							],
							'highlight_html_blocks' => [
								'label' => __( 'HTML Blocks', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_HIGHLIGHT_HTML_BLOCKS',
								'desc'  => __( 'Outlines and labels Custom HTML blocks in the block editor, so embed code made only of script tags no longer looks like an empty block.', 'embold-wordpress-tweaks' ),
							],
							'enable_slug_column'    => [
								'label' => __( 'Slug Column', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_ENABLE_SLUG_COLUMN',
								'desc'  => __( 'Adds a "Slug" column to post lists.', 'embold-wordpress-tweaks' ),
							],
							'enable_slug_search'    => [
								'label' => __( 'Slug Search', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_ENABLE_SLUG_SEARCH',
								'desc'  => __( 'Allows searching by slug using the prefix <code>slug:your-slug</code>.', 'embold-wordpress-tweaks' ),
							],
						],
					],
				],
			],
			'performance' => [
				'label'    => __( 'Performance', 'embold-wordpress-tweaks' ),
				'sections' => [
					'embold_tweaks_bloat'       => [
						'title'  => __( 'Remove Bloat', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Strip scripts, styles and head tags most sites never use.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'disable_dashicons' => [
								'label' => __( 'Dashicons', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_DASHICONS',
								'desc'  => __( 'Disables admin icons (Dashicons) on the front end for logged out users.', 'embold-wordpress-tweaks' ),
							],
							'disable_wp_emoji'  => [
								'label' => __( 'Emoji', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_WP_EMOJI',
								'desc'  => __( 'Disables the built-in WordPress JavaScript for rendering emojis. Modern browsers support emojis natively, so this script is usually unnecessary overhead.', 'embold-wordpress-tweaks' ),
							],
							'disable_oembed'    => [
								'label' => __( 'oEmbed', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_OEMBED',
								'desc'  => __( 'Disables the automatic embedding of some content (such as YouTube videos and Tweets) when pasting the URL into your blog posts.', 'embold-wordpress-tweaks' ),
							],
							'disable_rsd_link'  => [
								'label' => __( 'RSD Link', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_RSD_LINK',
								'desc'  => __( 'Removes the Really Simple Discovery (RSD) link tag from the page head.', 'embold-wordpress-tweaks' ),
							],
							'disable_rss_links' => [
								'label' => __( 'RSS Links', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_RSS_LINKS',
								'desc'  => __( 'Removes the RSS/Atom feed link tags from the page head.', 'embold-wordpress-tweaks' ),
							],
							'disable_shortlink' => [
								'label' => __( 'Shortlink', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_SHORTLINK',
								'desc'  => __( 'Removes the shortlink tag from the page head.', 'embold-wordpress-tweaks' ),
							],
						],
					],
					'embold_tweaks_performance' => [
						'title'  => __( 'Script Loading', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Script loading tweaks that also prevent 502 errors on local environments.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'async_scripts' => [
								'label' => __( 'Admin Scripts', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_ASYNC_SCRIPTS',
								'desc'  => __( 'Loads admin bar, heartbeat, and other admin scripts asynchronously.', 'embold-wordpress-tweaks' ),
							],
							'defer_scripts' => [
								'label' => __( 'Non-Critical Scripts', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DEFER_SCRIPTS',
								'desc'  => __( 'Defers common scripts (like admin bar JS) to improve load times and prevent 502 errors on local environments.', 'embold-wordpress-tweaks' ),
							],
						],
					],
				],
			],
			'developer'   => [
				'label'    => __( 'Developer', 'embold-wordpress-tweaks' ),
				'sections' => [
					'embold_tweaks_compatibility' => [
						'title'  => __( 'Third-Party Plugins', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Workarounds for specific third-party plugins.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'disable_acf_escaping' => [
								'label' => __( 'ACF Shortcodes', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_DISABLE_ACF_ESCAPING',
								'desc'  => __( 'Reverts the ACF 6.2.5 security change to allow HTML in shortcode content.', 'embold-wordpress-tweaks' ),
							],
							'clean_img_tags'       => [
								'label' => __( 'LiteSpeed Cache', 'embold-wordpress-tweaks' ),
								'const' => 'EMBOLD_CLEAN_IMG_TAGS',
								'desc'  => __( 'Removes line breaks from img tags to ensure compatibility with LiteSpeed Cache.', 'embold-wordpress-tweaks' ),
							],
						],
					],
					'embold_tweaks_debugging'     => [
						'title'  => __( 'Debug Log', 'embold-wordpress-tweaks' ),
						'desc'   => __( 'Keep debug.log readable.', 'embold-wordpress-tweaks' ),
						'fields' => [
							'suppress_notices'              => [
								'label'        => __( 'Debug Notices', 'embold-wordpress-tweaks' ),
								'const'        => 'EMBOLD_SUPPRESS_LOGS',
								'legacy_const' => 'WPH_SUPPRESS_TEXTDOMAIN_NOTICES',
								'desc'         => __( 'Suppresses noisy <code>_doing_it_wrong</code> notices (e.g. textdomain loading) and matching PHP errors/warnings to keep debug.log clean.', 'embold-wordpress-tweaks' ),
								'class'        => 'embold-suppress-toggle',
							],
							'suppress_notice_extra_strings' => [
								'label' => __( 'Extra Suppression Strings', 'embold-wordpress-tweaks' ),
								'type'  => 'textarea',
								'const' => 'EMBOLD_SUPPRESS_LOGS_EXTRA',
								'desc'  => __( 'Add additional partial strings to suppress from <code>_doing_it_wrong</code> notices, one per line.', 'embold-wordpress-tweaks' ),
								'class' => 'embold-suppress-strings-row',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Flatten the tab layout into key => field args.
	 */
	private function getFields(): array {
		$fields = [];
		foreach ( $this->getTabs() as $tab ) {
			foreach ( $tab['sections'] as $section ) {
				$fields += $section['fields'];
			}
		}
		return $fields;
	}

	private function isFieldLocked( array $field ): bool {
		return ( ! empty( $field['const'] ) && defined( $field['const'] ) )
			|| ( ! empty( $field['legacy_const'] ) && defined( $field['legacy_const'] ) );
	}

	public function registerSettings(): void {
		register_setting( self::OPTION_NAME, self::OPTION_NAME, [ $this, 'sanitize' ] );

		$renderers = [
			'checkbox' => 'renderCheckboxField',
			'textarea' => 'renderTextareaField',
		];

		foreach ( $this->getTabs() as $tab ) {
			foreach ( $tab['sections'] as $section_id => $section ) {
				$desc = $section['desc'];
				add_settings_section(
					$section_id,
					$section['title'],
					function () use ( $desc ) {
						echo '<p>' . esc_html( $desc ) . '</p>';
					},
					'embold-wordpress-tweaks'
				);

				foreach ( $section['fields'] as $key => $args ) {
					$type     = $args['type'] ?? 'checkbox';
					$renderer = $args['render'] ?? ( $renderers[ $type ] ?? 'renderTextField' );

					add_settings_field(
						$key,
						$args['label'],
						[ $this, $renderer ],
						'embold-wordpress-tweaks',
						$section_id,
						array_merge( [ 'key' => $key ], $args )
					);
				}
			}
		}
	}

	/**
	 * Generic renderer for boolean checkbox fields
	 */
	public function renderCheckboxField( $args ): void {
		$key          = $args['key'];
		$const        = $args['const'] ?? null;
		$legacy_const = $args['legacy_const'] ?? null;
		$desc         = $args['desc'] ?? '';

		$opts = $this->getOptions();

		// Determine effective value and lock state
		$is_locked         = false;
		$locked_val        = null;
		$locked_const_name = null;
		$is_checked        = false;

		// Check primary constant first, then legacy constant
		if ( $const && defined( $const ) ) {
			$is_locked         = true;
			$locked_val        = constant( $const );
			$locked_const_name = $const;
			$is_checked        = (bool) $locked_val;
		} elseif ( $legacy_const && defined( $legacy_const ) ) {
			$is_locked         = true;
			$locked_val        = constant( $legacy_const );
			$locked_const_name = $legacy_const;
			$is_checked        = (bool) $locked_val;
		} else {
			// Force boolean logic: if not empty/false/0, it is true.
			$is_checked = ! empty( $opts[ $key ] );
		}

		$name          = self::OPTION_NAME . "[$key]";
		$disabled_attr = $is_locked ? 'disabled' : '';

		echo '<label>';
		printf(
			'<input type="checkbox" name="%s" value="1" %s %s> ',
			esc_attr( $name ),
			checked( 1, $is_checked ? 1 : 0, false ),
			$disabled_attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		if ( $desc ) {
			echo wp_kses_post( $desc );
		}
		echo '</label>';

		if ( $is_locked && $locked_const_name ) {
			echo wp_kses_post( $this->getConstantOverrideHtml( $locked_const_name ) );
		}
	}

	public function renderTextField( $args ): void {
		$key           = $args['key'];
		$type          = $args['type'] ?? 'text';
		$desc          = $args['desc'] ?? '';
		$default       = $args['default'] ?? '';
		$const         = $args['const'] ?? null;

		$opts = $this->getOptions();

		// Determine if locked by constant
		$is_locked         = false;
		$locked_const_name = null;
		if ( $const && defined( $const ) ) {
			$is_locked         = true;
			$locked_const_name = $const;
			$value             = (string) constant( $const );
		} else {
			$value = $opts[ $key ] ?? '';
		}

		// Show default placeholder if empty
		$placeholder   = $default ? 'placeholder="' . esc_attr( $default ) . '"' : '';
		$readonly_attr = $is_locked ? 'readonly' : '';

		// Provide sensible autocomplete attributes to satisfy browser/accessibility checks
		$autocomplete_attr = '';
		if ( ! empty( $args['autocomplete'] ) ) {
			$autocomplete_attr = $args['autocomplete'];
		} elseif ( $type === 'password' ) {
			$autocomplete_attr = 'autocomplete="current-password"';
		} elseif ( $type === 'email' ) {
			$autocomplete_attr = 'autocomplete="email"';
		} elseif ( $key === 'smtp_username' ) {
			$autocomplete_attr = 'autocomplete="username"';
		}

		$name = self::OPTION_NAME . "[$key]";

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		printf(
			'<input type="%s" name="%s" value="%s" class="regular-text" %s %s %s>',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			$placeholder,
			$readonly_attr,
			$autocomplete_attr
		);

		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $desc ) {
			echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
		}

		if ( $is_locked && $locked_const_name ) {
			echo wp_kses_post( $this->getConstantOverrideHtml( $locked_const_name ) );
		}
	}

	public function renderTextareaField( $args ): void {
		$key       = $args['key'];
		$const     = $args['const'] ?? null;
		$desc      = $args['desc'] ?? '';

		$opts = $this->getOptions();

		// Determine if locked by constant
		$is_locked         = false;
		$locked_const_name = null;
		if ( $const && defined( $const ) ) {
			$is_locked         = true;
			$locked_const_name = $const;
			$const_val         = constant( $const );
			// Support both array and string formats
			if ( is_array( $const_val ) ) {
				$value = implode( "\n", $const_val );
			} else {
				$value = (string) $const_val;
			}
		} else {
			$value = $opts[ $key ] ?? '';
		}

		$name          = self::OPTION_NAME . "[$key]";
		$readonly_attr = $is_locked ? 'readonly' : '';

		printf(
			'<textarea name="%s" rows="3" class="large-text" %s>%s</textarea>',
			esc_attr( $name ),
			$readonly_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_textarea( $value )
		);

		if ( $desc ) {
			echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
		}

		if ( $is_locked && $locked_const_name ) {
			echo wp_kses_post( $this->getConstantOverrideHtml( $locked_const_name ) );
		}
	}

	public function renderMailModeField(): void {
		$opts       = $this->getOptions();
		$mode       = $opts['mail_mode'];
		$resolution = $this->mailService->resolveMailMode();
		$effective  = $resolution['mode'];
		$locked_by  = $resolution['locked_by'];
		$source     = $resolution['source'];

		// Lock if CONSTANT is set.
		$is_readonly = ! empty( $locked_by );

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[mail_mode]" data-effective-mode="' . esc_attr( $effective ) . '" ' . ( $is_readonly ? 'disabled' : '' ) . '>';
		$this->renderOption( 'auto', __( 'Auto (environment-based)', 'embold-wordpress-tweaks' ), $mode );
		$this->renderOption( 'block_all', __( 'Block', 'embold-wordpress-tweaks' ), $mode );
		$this->renderOption( 'smtp_override', __( 'SMTP Override (Mailpit/Custom)', 'embold-wordpress-tweaks' ), $mode );
		$this->renderOption( 'allow_all', __( 'Allow (No Override)', 'embold-wordpress-tweaks' ), $mode );
		echo '</select>';

		if ( ! $is_readonly ) {
			echo '<p class="description">' . esc_html__( 'Auto: blocks mail on development/staging; allows in production. SMTP Override: uses settings below.', 'embold-wordpress-tweaks' ) . '</p>';
		}

		$status_label = $this->formatModeLabel( $effective );

		echo '<div class="embold-status-box">';
		echo '<strong>' . esc_html__( 'Effective Status:', 'embold-wordpress-tweaks' ) . '</strong> ';
		echo '<span>' . esc_html( $status_label ) . '</span>';
		echo '</div>';

		if ( ! empty( $locked_by ) ) {
			echo wp_kses_post( $this->getConstantOverrideHtml( $locked_by ) );
		} else {
			echo '<p class="description">' . esc_html( sprintf( __( 'Source: %s', 'embold-wordpress-tweaks' ), $source ) ) . '</p>';
		}
	}

	public function renderSettingsPage(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Embold Tweaks Settings', 'embold-wordpress-tweaks' ); ?></h1>

			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>
			<?php if ( isset( $_GET['embold_msg'] ) ) : ?>
				<?php $msg = urldecode( wp_unslash( $_GET['embold_msg'] ) ); ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $msg ); ?></p>
				</div>
				<?php
				// Clear settings-updated to avoid duplicate message
				unset( $_GET['settings-updated'] );
			endif;
			?>
			<?php if ( isset( $_GET['embold_err'] ) ) : ?>
				<?php $err = urldecode( wp_unslash( $_GET['embold_err'] ) ); ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $err ); ?></p>
				</div>
			<?php endif; ?>
			<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized ?>

			<?php
			settings_errors( self::OPTION_NAME );
			// Clear the notice queue after rendering so it doesn't persist on reload
			add_action(
				'shutdown',
				function () {
					global $wp_settings_errors;
					if ( isset( $wp_settings_errors ) ) {
						// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						$wp_settings_errors = [];
					}
				}
			);
			?>

			<?php $tabs = $this->getTabs(); ?>

			<nav class="nav-tab-wrapper embold-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'embold-wordpress-tweaks' ); ?>">
				<?php foreach ( $tabs as $tab_id => $tab ) : ?>
					<a href="#<?php echo esc_attr( $tab_id ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_NAME );

				foreach ( $tabs as $tab_id => $tab ) {
					echo '<div class="embold-tab-panel" data-tab="' . esc_attr( $tab_id ) . '">';
					foreach ( array_keys( $tab['sections'] ) as $section_id ) {
						$this->renderSection( $section_id, count( $tab['sections'] ) > 1 );
					}
					echo '</div>';
				}

				// Ensure the save button has a unique id and name to avoid duplicate #submit warnings and method shadowing
				submit_button( null, 'primary', 'embold_save_changes', true, [ 'id' => 'embold-save-changes' ] );
				?>
			</form>

			<div class="embold-tab-panel" data-tab="mail">
			<hr class="embold-divider">

			<h2><?php echo esc_html__( 'Test Configuration', 'embold-wordpress-tweaks' ); ?></h2>
			<p class="description">
				<?php echo esc_html__( 'This sends a test email using the currently active mail configuration.', 'embold-wordpress-tweaks' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'embold_send_test_email', 'embold_test_nonce' ); ?>
				<input type="hidden" name="action" value="embold_send_test_email">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label
								for="embold_test_email"><?php echo esc_html__( 'Recipient Email', 'embold-wordpress-tweaks' ); ?></label>
						</th>
						<td>
							<input id="embold_test_email" name="embold_test_email" type="email" class="regular-text"
								value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
							<?php submit_button( __( 'Send test email', 'embold-wordpress-tweaks' ), 'secondary', 'embold_send_test_email', false ); ?>
						</td>
					</tr>
				</table>
			</form>
			</div>

			<hr class="embold-divider">

			<h2><?php echo esc_html__( 'Reset Settings', 'embold-wordpress-tweaks' ); ?></h2>
			<p><?php echo esc_html__( 'This will delete plugin options, reverting settings to defaults.', 'embold-wordpress-tweaks' ); ?>
			</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'embold_reset_settings_action', 'embold_reset_nonce' ); ?>
				<input type="hidden" name="embold_reset_settings" value="1">
				<?php
				submit_button(
					__( 'Reset to Defaults', 'embold-wordpress-tweaks' ),
					'delete',
					'embold_reset_settings',
					true,
					[
						'id'      => 'embold-reset-settings',
						'onclick' => "return confirm('" . esc_js( __( 'Are you sure you want to reset all Embold WordPress Tweaks settings?', 'embold-wordpress-tweaks' ) ) . "');",
					]
				);
				?>
			</form>
		</div>
		<?php
	}
	/**
	 * Render one registered section, like do_settings_sections() does for all of them.
	 */
	private function renderSection( string $section_id, bool $show_title ): void {
		global $wp_settings_sections;

		$section = $wp_settings_sections['embold-wordpress-tweaks'][ $section_id ] ?? null;
		if ( ! $section ) {
			return;
		}

		if ( $show_title ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
		}

		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		echo '<table class="form-table" role="presentation">';
		do_settings_fields( 'embold-wordpress-tweaks', $section_id );
		echo '</table>';
	}

	private function renderOption( string $value, string $label, string $current ): void {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}

	private function formatModeLabel( string $mode ): string {
		switch ( $mode ) {
			case 'block_all':
				return __( 'Block all mail', 'embold-wordpress-tweaks' );
			case 'smtp_override':
				return __( 'SMTP Override (Mailpit/Custom)', 'embold-wordpress-tweaks' );
			case 'allow_all':
			case 'no_override':
				return __( 'Allow all mail (no override)', 'embold-wordpress-tweaks' );
			default:
				return __( 'Auto (environment-based)', 'embold-wordpress-tweaks' );
		}
	}

	public function renderElevatedEmailsField(): void {
		$name = self::OPTION_NAME . '[elevated_emails]';

		$is_const_controlled = defined( 'ELEVATED_EMAILS' ) && constant( 'ELEVATED_EMAILS' );

		if ( $is_const_controlled ) {
			$const_val = constant( 'ELEVATED_EMAILS' );
			$emails    = is_array( $const_val ) ? $const_val : [];
		} else {
			$emails = $this->readSharedElevatedEmails();
		}

		$value = implode( "\n", $emails );

		printf(
			'<textarea name="%s" rows="4" class="large-text" %s>%s</textarea>',
			esc_attr( $name ),
			$is_const_controlled ? 'disabled' : '',
			esc_textarea( $value )
		);
		echo '<p class="description">' . esc_html__( 'Additional email addresses that can manage plugins and themes. Enter one email per line. Users with info@embold.com or info@wphaven.app are automatically elevated.', 'embold-wordpress-tweaks' ) . '</p>';

		if ( $is_const_controlled ) {
			echo wp_kses_post( $this->getConstantOverrideHtml( 'ELEVATED_EMAILS' ) );
		}
	}

	/**
	 * Read the elevated emails list from the shared source of truth.
	 *
	 * When wphaven-connect is installed, both plugins use its option key
	 * (`wphaven_connect_options`) so wphaven-connect's ElevatedUsers reader
	 * sees the same list edited here. Otherwise, use embold's own option.
	 */
	private function readSharedElevatedEmails(): array {
		if ( class_exists( 'WPHavenConnect\\Providers\\SettingsServiceProvider' ) ) {
			$wph_opts = get_option( 'wphaven_connect_options', [] );
			$stored   = $wph_opts['elevated_emails'] ?? [];
		} else {
			$opts   = $this->getOptions();
			$stored = $opts['elevated_emails'] ?? [];
		}

		return is_array( $stored ) ? $stored : [];
	}

	public function renderLooseUserRestrictionsField(): void {
		$name       = self::OPTION_NAME . '[loose_user_restrictions]';
		$resolution = $this->resolveUserRestrictions();

		$is_const         = $resolution['locked_by'] === 'LOOSE_USER_RESTRICTIONS';
		$current_db_value = $resolution['restrictions_disabled'];
		$source           = $resolution['source'];

		$checked  = checked( 0, (int) $current_db_value, false );
		$readonly = $is_const ? 'disabled' : '';
		$extra    = $is_const ? ' ' . $this->getConstantOverrideHtml( 'LOOSE_USER_RESTRICTIONS' ) : '';

		echo '<label>';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		printf(
			'<input type="checkbox" name="%s" value="1" %s %s> ',
			esc_attr( $name ),
			$checked,
			$readonly
		);
		//phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		echo esc_html__( 'When checked, only elevated users can manage plugins, themes, and files. Uncheck this box to temporarily allow full access to all administrators.', 'embold-wordpress-tweaks' );
		echo '</label>';

		if ( $extra ) {
			echo wp_kses_post( $extra );
		}

		if ( ! $is_const && $source === 'default' ) {
			echo '<p class="description"><em>' .
				esc_html__( 'Default Status: Restrictions are active (Safe).', 'embold-wordpress-tweaks' ) .
				'</em></p>';
		}
	}

	/**
	 * Resolve effective user restrictions setting
	 */
	public function resolveUserRestrictions(): array {
		// Constant takes priority
		if ( defined( 'LOOSE_USER_RESTRICTIONS' ) ) {
			return [
				'restrictions_disabled' => (bool) constant( 'LOOSE_USER_RESTRICTIONS' ),
				'locked_by'             => 'LOOSE_USER_RESTRICTIONS',
				'source'                => 'constant',
			];
		}

		// Plugin option
		$opts = $this->getOptions();
		if ( isset( $opts['loose_user_restrictions'] ) ) {
			return [
				'restrictions_disabled' => (bool) $opts['loose_user_restrictions'],
				'locked_by'             => null,
				'source'                => 'option',
			];
		}

		// Default: restrictions are enabled (loose_user_restrictions = false)
		return [
			'restrictions_disabled' => false,
			'locked_by'             => null,
			'source'                => 'default',
		];
	}

	/**
	 * Resolve effective elevated emails setting
	 */
	public function resolveElevatedEmails(): array {
		// Check if wphaven-connect is managing this
		if ( class_exists( 'WPHavenConnect\\Providers\\SettingsServiceProvider' ) ) {
			$wph_opts = get_option( 'wphaven_connect_options', [] );
			if ( ! empty( $wph_opts['elevated_emails'] ) ) {
				return [
					'emails'     => (array) $wph_opts['elevated_emails'],
					'managed_by' => 'wphaven_connect',
					'source'     => 'wphaven_connect_option',
				];
			}
		}

		// Check for constants
		if ( defined( 'ELEVATED_EMAILS' ) && constant( 'ELEVATED_EMAILS' ) ) {
			$const_val = constant( 'ELEVATED_EMAILS' );
			return [
				'emails'     => is_array( $const_val ) ? $const_val : [],
				'managed_by' => 'ELEVATED_EMAILS',
				'source'     => 'constant',
			];
		}

		// Fall back to embold option
		$opts = $this->getOptions();
		if ( ! empty( $opts['elevated_emails'] ) ) {
			return [
				'emails'     => (array) $opts['elevated_emails'],
				'managed_by' => null,
				'source'     => 'embold_option',
			];
		}

		return [
			'emails'     => [],
			'managed_by' => null,
			'source'     => 'default',
		];
	}

	/**
	 * Sanitize settings input
	 */
	public function sanitize( $input ): array {
		$output = get_option( self::OPTION_NAME, [] );

		// --- Registry fields ---
		// Fields locked by a constant keep their stored value: a disabled checkbox
		// isn't submitted, and a readonly input would copy the constant into the DB.
		foreach ( $this->getFields() as $key => $field ) {
			if ( isset( $field['render'] ) || $this->isFieldLocked( $field ) ) {
				continue;
			}

			$type = $field['type'] ?? 'checkbox';

			if ( $type === 'checkbox' ) {
				$output[ $key ] = isset( $input[ $key ] );
				continue;
			}

			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			switch ( $type ) {
				case 'number':
					$output[ $key ] = absint( $input[ $key ] );
					break;
				case 'email':
					$output[ $key ] = sanitize_email( $input[ $key ] );
					break;
				case 'textarea':
					$output[ $key ] = sanitize_textarea_field( $input[ $key ] );
					break;
				default:
					$output[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		// --- Mail Mode ---
		if ( isset( $input['mail_mode'] ) ) {
			$mode    = sanitize_text_field( $input['mail_mode'] );
			$allowed = [ 'auto', 'block_all', 'allow_all', 'smtp_override' ];
			if ( in_array( $mode, $allowed, true ) ) {
				$output['mail_mode'] = $mode;
			}
		}

		// --- User Restrictions (Inverted Logic) ---
		// The checkbox is disabled when the constant is set, so an unsubmitted box
		// must not be read as "unchecked" — that would silently loosen restrictions.
		if ( ! defined( 'LOOSE_USER_RESTRICTIONS' ) ) {
			$output['loose_user_restrictions'] = ! isset( $input['loose_user_restrictions'] );
		}

		// --- Elevated Emails ---
		// Skip if locked by constant.
		if ( ! ( defined( 'ELEVATED_EMAILS' ) && constant( 'ELEVATED_EMAILS' ) ) ) {
			$emails = [];
			if ( isset( $input['elevated_emails'] ) ) {
				if ( is_array( $input['elevated_emails'] ) ) {
					$emails = array_map( 'sanitize_email', $input['elevated_emails'] );
				} else {
					$raw   = is_string( $input['elevated_emails'] ) ? $input['elevated_emails'] : '';
					$parts = preg_split( '/[\r\n,;]+/', $raw );
					foreach ( $parts as $p ) {
						$p = trim( $p );
						if ( ! empty( $p ) ) {
							$emails[] = sanitize_email( $p );
						}
					}
				}
			}
			$emails = array_values( array_filter( $emails, 'is_email' ) );

			// Write to the shared source of truth. When wphaven-connect is installed,
			// its option is the canonical store (its ElevatedUsers reader only looks there);
			// otherwise we keep the list in embold's own option.
			if ( class_exists( 'WPHavenConnect\\Providers\\SettingsServiceProvider' ) ) {
				$wph_opts = get_option( 'wphaven_connect_options', [] );
				if ( ! is_array( $wph_opts ) ) {
					$wph_opts = [];
				}
				$wph_opts['elevated_emails'] = $emails;
				update_option( 'wphaven_connect_options', $wph_opts );
				// Don't duplicate in embold's option — keep one source of truth.
				unset( $output['elevated_emails'] );
			} else {
				$output['elevated_emails'] = $emails;
			}
		}

		return $output;
	}

	/**
	 * Retrieves the plugin options with defaults applied.
	 */
	private function getOptions(): array {
		$defaults = [
			'elevated_emails'               => [],
			'loose_user_restrictions'       => false,
			'mail_mode'                     => 'auto',
			'enable_svg'                    => true,
			'disable_xmlrpc'                => true,
			'disable_wp_emoji'              => true,
			'disable_dashicons'             => true,
			'disable_rsd_link'              => true,
			'disable_shortlink'             => true,
			'disable_generator_tag'         => true,
			'disable_rss_links'             => false,
			'disable_rest_metadata'         => true,
			'disable_oembed'                => false,
			'defer_scripts'                 => true,
			'async_scripts'                 => true,
			'clean_img_tags'                => true,
			'enable_slug_search'            => true,
			'enable_slug_column'            => true,
			'disable_acf_escaping'          => true,
			'remove_howdy'                  => true,
			'highlight_html_blocks'         => true,
			'enable_duplicate_post'         => true,
			'suppress_notices'              => true,
			'suppress_notice_extra_strings' => '',
			'smtp_host'                     => 'mailpit',
			'smtp_port'                     => '1025',
			'smtp_from_email'               => 'admin@wordpress.local',
			'smtp_from_name'                => 'WordPress',
			'smtp_username'                 => '',
			'smtp_password'                 => '',
			'smtp_secure'                   => false,
		];

		return wp_parse_args( get_option( self::OPTION_NAME, [] ), $defaults );
	}

	/**
	 * Generate HTML snippet indicating a constant override
	 */
	private function getConstantOverrideHtml( $constant_name ) {
		$message = wp_kses_post(
			sprintf(
				__( 'Locked by constant: <code>%s</code>', 'embold-wordpress-tweaks' ),
				esc_html( $constant_name )
			)
		);
		return '<p class="description wph-const-override">' . $message . '</p>';
	}
}