<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;

class EmboldWordpressTweaks {
	/**
	 * Option key matching the Settings Page
	 */
	const OPTION_NAME = 'embold_tweaks_options';

	/**
	 * Helper to check if a feature is enabled.
	 * Priority: Constant > Option > Default
	 */
	private function isFeatureEnabled( string $key, ?string $constant = null, bool $default = true ): bool {
		// 1. Check Constant
		if ( $constant && defined( $constant ) ) {
			return (bool) constant( $constant );
		}

		// 2. Retrieve Options
		$opts = get_option( self::OPTION_NAME, [] );

		// Safety: Ensure we have an array
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}

		// 3. Check DB Option
		// If the specific key exists, return its value (cast to bool)
		if ( array_key_exists( $key, $opts ) ) {
			return (bool) $opts[ $key ];
		}

		// 4. Key missing from DB: fall back to this feature's default state.
		return $default;
	}

	private function getOption( string $key, $default = '' ) {
		// Priority: EMBOLD_SUPPRESS_LOGS_EXTRA (constant) -> option -> default
		if ( $key === 'suppress_notice_extra_strings' ) {
			if ( defined( 'EMBOLD_SUPPRESS_LOGS_EXTRA' ) ) {
				$const_val = constant( 'EMBOLD_SUPPRESS_LOGS_EXTRA' );
				// Support both array and string formats
				if ( is_array( $const_val ) ) {
					return implode( "\n", $const_val );
				}
				return (string) $const_val;
			}
		}

		$opts = get_option( self::OPTION_NAME, [] );
		return $opts[ $key ] ?? $default;
	}

	public function allowSpecificUsersToEditFiles() {
		// Check if restrictions are globally disabled (Loose Mode)

		// Check Constant
		if ( defined( 'LOOSE_USER_RESTRICTIONS' ) && constant( 'LOOSE_USER_RESTRICTIONS' ) ) {
			return; // Exit early: Restrictions disabled by constant
		}

		// Check Option (Saved as boolean TRUE if loose/unsafe)
		$opts = get_option( self::OPTION_NAME, [] );
		if ( ! empty( $opts['loose_user_restrictions'] ) ) {
			return; // Exit early: Restrictions disabled by settings checkbox
		}

		// If we are here, restrictions are ACTIVE. Proceed to check emails.
		$default_emails = [
			'info@embold.com',
			'info@wphaven.app',
		];

		$allowed_emails = $default_emails;

		// Priority: wphaven-connect option > constants > embold option
		$wph_opts_elevated = null;
		if ( class_exists( 'WPHavenConnect\\Providers\\SettingsServiceProvider' ) ) {
			$wph_opts = get_option( 'wphaven_connect_options', [] );
			if ( ! empty( $wph_opts['elevated_emails'] ) ) {
				$allowed_emails    = array_merge( $allowed_emails, (array) $wph_opts['elevated_emails'] );
				$wph_opts_elevated = true;
			}
		}

		// Check for constants (only if wphaven didn't provide emails)
		if ( ! $wph_opts_elevated ) {
			if ( defined( 'ELEVATED_EMAILS' ) && is_array( ELEVATED_EMAILS ) ) {
				$allowed_emails = array_merge( $allowed_emails, ELEVATED_EMAILS );
			} elseif ( ! empty( $opts['elevated_emails'] ) && is_array( $opts['elevated_emails'] ) ) {
				$allowed_emails = array_merge( $allowed_emails, $opts['elevated_emails'] );
			}
		}

		// Ensure emails are unique and lowercase
		$allowed_emails = array_unique( array_map( 'strtolower', $allowed_emails ) );
		$current_user   = wp_get_current_user();
		$user_email     = strtolower( $current_user->user_email );

		// If user is allowed, do nothing
		if ( in_array( $user_email, $allowed_emails, true ) ) {
			return;
		}

		// --- ENFORCE RESTRICTIONS ---

		// Hide this plugin from the plugins list
		add_filter(
			'all_plugins',
			function ( $plugins ) {
				if ( isset( $plugins['embold-wordpress-tweaks/embold-wordpress-tweaks.php'] ) ) {
					unset( $plugins['embold-wordpress-tweaks/embold-wordpress-tweaks.php'] );
				}
				return $plugins;
			}
		);

		// Filter to disallow file/plugin/theme edits
		add_filter(
			'user_has_cap',
			function ( $all_capabilities, $caps, $args ) {
				// PLUGINS
				$all_capabilities['update_plugins']  = false;
				$all_capabilities['install_plugins'] = false;
				$all_capabilities['delete_plugins']  = false;

				// THEMES
				$all_capabilities['update_themes']  = false;
				$all_capabilities['switch_themes']  = false;
				$all_capabilities['install_themes'] = false;
				$all_capabilities['edit_themes']    = false;
				$all_capabilities['delete_themes']  = false;

				// TOOLS
				$all_capabilities['import'] = false;

				// CORE / FILES
				$all_capabilities['update_core']  = false;
				$all_capabilities['edit_files']   = false;
				$all_capabilities['edit_plugins'] = false;

				return $all_capabilities;
			},
			10,
			3
		);
	}

	/**
	 * Add SVG support.
	 *
	 * @return void
	 */
	public function addSvgSupport() {
		if ( ! $this->isFeatureEnabled( 'enable_svg', 'EMBOLD_ALLOW_SVG' ) ) {
			return;
		}

		add_filter(
			'upload_mimes',
			function ( $mimes ) {
				$mimes['svg'] = 'image/svg+xml';
				return $mimes;
			}
		);
	}

	/**
	 * Disable XML-RPC.
	 */
	public function disableXmlRpc() {
		if ( ! $this->isFeatureEnabled( 'disable_xmlrpc', 'EMBOLD_DISABLE_XMLRPC' ) ) {
			return;
		}

		add_filter( 'xmlrpc_enabled', '__return_false' );

		// Remove X-Pingback header
		add_filter(
			'wp_headers',
			function ( $headers ) {
				if ( isset( $headers['X-Pingback'] ) ) {
					unset( $headers['X-Pingback'] );
				}
				return $headers;
			}
		);

		// Explicitly remove pingback.ping method
		add_filter(
			'xmlrpc_methods',
			function ( $methods ) {
				unset( $methods['pingback.ping'] );
				return $methods;
			}
		);
	}

	/**
	 * Disable the built-in WordPress emoji detection script and styles.
	 */
	public function disableWpEmoji() {
		if ( ! $this->isFeatureEnabled( 'disable_wp_emoji', 'EMBOLD_DISABLE_WP_EMOJI' ) ) {
			return;
		}

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', [ $this, 'removeEmojiTinymcePlugin' ] );
		add_filter( 'wp_resource_hints', [ $this, 'removeEmojiDnsPrefetch' ], 10, 2 );
	}

	/**
	 * Remove the emoji plugin from TinyMCE.
	 *
	 * @param array $plugins Active TinyMCE plugins.
	 * @return array
	 */
	public function removeEmojiTinymcePlugin( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, [ 'wpemoji' ] );
		}
		return [];
	}

	/**
	 * Remove the emoji CDN dns-prefetch resource hint.
	 *
	 * @param array  $urls          Resource hint URLs.
	 * @param string $relation_type The relation type (e.g. 'dns-prefetch').
	 * @return array
	 */
	public function removeEmojiDnsPrefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );
			$urls          = array_diff( $urls, [ $emoji_svg_url ] );
		}
		return $urls;
	}

	/**
	 * Disable the RSD (Really Simple Discovery) link tag in wp_head.
	 */
	public function disableRsdLink() {
		if ( ! $this->isFeatureEnabled( 'disable_rsd_link', 'EMBOLD_DISABLE_RSD_LINK' ) ) {
			return;
		}

		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Disable the shortlink tag/header in wp_head.
	 */
	public function disableShortlink() {
		if ( ! $this->isFeatureEnabled( 'disable_shortlink', 'EMBOLD_DISABLE_SHORTLINK' ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	}

	/**
	 * Disable the WordPress generator meta tag (and the RSS/Atom feed generator tag).
	 */
	public function disableGeneratorTag() {
		if ( ! $this->isFeatureEnabled( 'disable_generator_tag', 'EMBOLD_DISABLE_GENERATOR_TAG' ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
	}

	/**
	 * Disable the RSS feed link tags in wp_head.
	 */
	public function disableRssLinks() {
		if ( ! $this->isFeatureEnabled( 'disable_rss_links', 'EMBOLD_DISABLE_RSS_LINKS' ) ) {
			return;
		}

		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	/**
	 * Disable WP REST API metadata (discovery link tag and HTTP header).
	 * This does not disable the REST API itself.
	 */
	public function disableRestMetadata() {
		if ( ! $this->isFeatureEnabled( 'disable_rest_metadata', 'EMBOLD_DISABLE_REST_METADATA' ) ) {
			return;
		}

		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * Disable automatic oEmbed conversion of pasted URLs (YouTube, Twitter/X, etc.)
	 * and the associated discovery/REST endpoints.
	 */
	public function disableOembed() {
		if ( ! $this->isFeatureEnabled( 'disable_oembed', 'EMBOLD_DISABLE_OEMBED', false ) ) {
			return;
		}

		// Remove the oEmbed discovery link and host JS from the front end.
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		add_filter( 'embed_oembed_discover', '__return_false' );

		// Remove the oEmbed REST routes, including the proxy the block editor
		// uses to fetch a preview when a URL is pasted into post content.
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );

		// Stop auto-converting bare URLs pasted into post content into embeds.
		if ( isset( $GLOBALS['wp_embed'] ) ) {
			remove_filter( 'the_content', [ $GLOBALS['wp_embed'], 'autoembed' ], 8 );
		}

		// Remove the embed plugin from TinyMCE (Classic Editor).
		add_filter(
			'tiny_mce_plugins',
			function ( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, [ 'wpembed' ] ) : [];
			}
		);

		// Remove embed-specific rewrite rules and the "embed" query var.
		add_filter(
			'rewrite_rules_array',
			function ( $rules ) {
				foreach ( $rules as $rule => $rewrite ) {
					if ( false !== strpos( $rewrite, 'embed=true' ) ) {
						unset( $rules[ $rule ] );
					}
				}
				return $rules;
			}
		);

		add_action(
			'init',
			function () {
				global $wp;
				if ( isset( $wp->public_query_vars ) ) {
					$wp->public_query_vars = array_diff( $wp->public_query_vars, [ 'embed' ] );
				}
			},
			9999
		);
	}

	/**
	 * Disable Dashicons for logged out users on the front end.
	 */
	public function disableDashiconsForLoggedOutUsers() {
		if ( ! $this->isFeatureEnabled( 'disable_dashicons', 'EMBOLD_DISABLE_DASHICONS' ) ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( ! is_user_logged_in() ) {
					wp_deregister_style( 'dashicons' );
				}
			},
			100
		);
	}

	/**
	 * Defer scripts to try to avoid Coders 502 errors.
	 *
	 * @return void
	 */
	public function deferScripts() {
		if ( ! $this->isFeatureEnabled( 'defer_scripts', 'EMBOLD_DEFER_SCRIPTS' ) ) {
			return;
		}

		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) {
				$scripts_to_defer = [
					'common',
					'wp-menu',
					'post-edit',
				];

				foreach ( $scripts_to_defer as $defer_script ) {
					if ( $defer_script === $handle ) {
						return str_replace( ' src', " defer='defer' src", $tag );
					}
				}

				return $tag;
			},
			10,
			2
		);
	}

	/**
	 * Async scripts to try to avoid Coders 502 errors.
	 *
	 * @return void
	 */
	public function asyncScripts() {
		if ( ! $this->isFeatureEnabled( 'async_scripts', 'EMBOLD_ASYNC_SCRIPTS' ) ) {
			return;
		}

		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) {
				$scripts_to_async = [
					'admin-bar',
					'heartbeat',
					'mce-view',
					'image-edit',
					'quicktags',
					'wplink',
					'jquery-ui-autocomplete',
					'media-upload',
					'editor/0',
					'editor/1',
					'svg-painter',
					'wp-auth-check',
					'wordcount',
					'block-editor',
					'references',
					'style-engine',
				];

				foreach ( $scripts_to_async as $async_script ) {
					if ( $async_script === $handle ) {
						return str_replace( ' src', ' async src', $tag );
					}
				}

				return $tag;
			},
			10,
			2
		);
	}

	/**
	 * Disable all known mail plugins.
	 */
	public function disableAllKnownMailPlugins() {
		$plugins_to_disable = [
			'mailgun/mailgun.php',
			'sparkpost/sparkpost.php',
		];

		foreach ( $plugins_to_disable as $plugin_to_disable ) {
			add_action(
				'admin_init',
				function () use ( $plugin_to_disable ) {
					deactivate_plugins( $plugin_to_disable );
				}
			);
		}
	}

	/**
	 * Remove line breaks from img tags if litespeed-cache is enabled
	 */
	public function removeLineBreaksFromImgTags() {
		if ( ! $this->isFeatureEnabled( 'clean_img_tags', 'EMBOLD_CLEAN_IMG_TAGS' ) ) {
			return;
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
			// Define the content filter function inline
			add_filter(
				'litespeed_buffer_before',
				function ( $content ) {
					// Remove extra spaces and newlines from img tags in the content
					preg_match_all( '/<img[^>]*>/i', $content, $matches );
					foreach ( $matches[0] as $match ) {
						$cleaned_tag = preg_replace( '/\s+/', ' ', $match );
						$cleaned_tag = str_replace( [ "\r", "\n" ], '', $cleaned_tag );
						$content     = str_replace( $match, $cleaned_tag, $content );
					}
					return $content;
				},
				0
			);
		}
	}

	/**
	 * Show post/page slugs in the admin panel and enable slug search
	 */
	public function addSlugSearchAndColumns() {
		// Enable Slug Search
		if ( $this->isFeatureEnabled( 'enable_slug_search', 'EMBOLD_ENABLE_SLUG_SEARCH' ) ) {
			add_filter(
				'posts_search',
				function ( $search, \WP_Query $q ) use ( &$wpdb ) {
					global $wpdb;

					// Nothing to do
					if (
						! did_action( 'load-edit.php' )
						|| ! is_admin()
						|| ! $q->is_search()
						|| ! $q->is_main_query()
					) {
						return $search;
					}

					$s = $q->get( 's' );

					// Check for "slug:" part in the search input
					if ( 'slug:' === mb_substr( trim( $s ), 0, 5 ) ) {
						// Override the search query
						$search = $wpdb->prepare(
							" AND {$wpdb->posts}.post_name LIKE %s ",
							str_replace(
								[ '**', '*' ],
								[ '*', '%' ],
								mb_strtolower(
									$wpdb->esc_like(
										trim( mb_substr( $s, 5 ) )
									)
								)
							)
						);

						// Adjust the ordering
						$q->set( 'orderby', 'post_name' );
						$q->set( 'order', 'ASC' );
					}
					return $search;
				},
				PHP_INT_MAX,
				2
			);
		}

		// Enable Slug Column
		if ( $this->isFeatureEnabled( 'enable_slug_column', 'EMBOLD_ENABLE_SLUG_COLUMN' ) ) {
			$post_types = [ 'page', 'post' ];
			foreach ( $post_types as $post_type ) {
				add_filter(
					"manage_{$post_type}_posts_columns",
					function ( $columns ) use ( $post_type ) {
						$new                            = [];
						$slug                           = __( 'Slug', 'embold-wordpress-tweaks' );
						$columns[ "{$post_type}_slug" ] = $slug;
						unset( $columns[ "{$post_type}_slug" ] );

						// Insert slug column after title
						foreach ( $columns as $key => $value ) {
							$new[ $key ] = $value;
							if ( $key === 'title' ) {
								$new[ "{$post_type}_slug" ] = $slug;
							}
						}
						return $new;
					}
				);

				add_action(
					"manage_{$post_type}_posts_custom_column",
					function ( $column, $post_id ) use ( $post_type ) {
						if ( $column === "{$post_type}_slug" ) {
							echo esc_html( get_post_field( 'post_name', $post_id, 'raw' ) );
						}
					},
					10,
					2
				);
			}
		}
	}

	/**
	 * Disable escaping ACF shortcode content introduced in ACF 6.2.5
	 */
	public function disableEscapingAcfShortcodes() {
		if ( ! $this->isFeatureEnabled( 'disable_acf_escaping', 'EMBOLD_DISABLE_ACF_ESCAPING' ) ) {
			return;
		}

		if ( function_exists( 'is_plugin_active' ) && ( is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) ) {
			// always return true, no matter which ACF shortcode is being used
			add_filter( 'acf/shortcode/allow_unsafe_html', '__return_true', 10, 2 );

			// Disable the notice about this in the admin
			add_filter( 'acf/admin/prevent_escaped_html_notice', '__return_true' );
		}
	}

	/**
	 * Remove the "Howdy" greeting from the admin bar
	 */
	public function removeHowdy() {
		if ( ! $this->isFeatureEnabled( 'remove_howdy', 'EMBOLD_REMOVE_HOWDY' ) ) {
			return;
		}

		add_action(
			'wp_before_admin_bar_render',
			function () {
				global $wp_admin_bar;
				$my_account = $wp_admin_bar->get_node( 'my-account' );
				if ( $my_account ) {
					$greeting = str_replace( 'Howdy, ', '', $my_account->title );
					$wp_admin_bar->add_node(
						[
							'id'    => 'my-account',
							'title' => $greeting,
						]
					);
				}
			},
			25
		);
	}

	/**
	 * Add a "Duplicate" link to the row actions on posts, pages, and custom
	 * post types, allowing them to be cloned as a new draft.
	 */
	public function enablePostDuplication() {
		if ( ! $this->isFeatureEnabled( 'enable_duplicate_post', 'EMBOLD_ENABLE_DUPLICATE_POST' ) ) {
			return;
		}

		// Add the "Duplicate" link to the row actions.
		// post_row_actions covers posts and custom post types; page_row_actions covers pages.
		add_filter( 'post_row_actions', [ $this, 'addDuplicateRowAction' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'addDuplicateRowAction' ], 10, 2 );

		// Handle the duplicate request (admin.php?action=embold_duplicate_post).
		add_action( 'admin_action_embold_duplicate_post', [ $this, 'handleDuplicatePost' ] );
	}

	/**
	 * Append the "Duplicate" action link to a post/page row.
	 *
	 * @param array    $actions The existing row action links.
	 * @param \WP_Post $post    The post object for the current row.
	 * @return array The modified row action links.
	 */
	public function addDuplicateRowAction( $actions, $post ) {
		$post_type_obj = get_post_type_object( $post->post_type );

		// Only show the link to users who can create posts of this type.
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=embold_duplicate_post&post=' . $post->ID ),
			'embold_duplicate_post_' . $post->ID
		);

		$actions['embold_duplicate'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'Duplicate this item as a new draft', 'embold-wordpress-tweaks' ),
			esc_html__( 'Duplicate', 'embold-wordpress-tweaks' )
		);

		return $actions;
	}

	/**
	 * Create a draft copy of the requested post and redirect to its edit screen.
	 */
	public function handleDuplicatePost() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( ! $post_id ) {
			wp_die( esc_html__( 'No item to duplicate has been provided.', 'embold-wordpress-tweaks' ) );
		}

		// Verify the request originated from our row-action link.
		check_admin_referer( 'embold_duplicate_post_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'The original item could not be found.', 'embold-wordpress-tweaks' ) );
		}

		// Capability check: user must be able to create posts of this type.
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'embold-wordpress-tweaks' ) );
		}

		$current_user = wp_get_current_user();

		// Build the new draft from the original.
		$new_post_id = wp_insert_post(
			[
				'post_author'           => $current_user->ID,
				'post_content'          => $post->post_content,
				'post_content_filtered' => $post->post_content_filtered,
				'post_excerpt'          => $post->post_excerpt,
				'post_parent'           => $post->post_parent,
				'post_password'         => $post->post_password,
				'post_status'           => 'draft',
				/* translators: %s: original post title. */
				'post_title'            => sprintf( __( '%s (Copy)', 'embold-wordpress-tweaks' ), $post->post_title ),
				'post_type'             => $post->post_type,
				'comment_status'        => $post->comment_status,
				'ping_status'           => $post->ping_status,
				'menu_order'            => $post->menu_order,
			],
			true
		);

		if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
			wp_die( esc_html__( 'Failed to create the duplicate.', 'embold-wordpress-tweaks' ) );
		}

		// Copy taxonomy terms (categories, tags, custom taxonomies).
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy, false );
			}
		}

		// Copy post meta (ACF fields, block bindings, etc.), skipping internal keys.
		$skip_meta = [ '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date' ];
		$meta      = get_post_meta( $post_id );
		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skip_meta, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				// get_post_meta returns raw (still-serialized, unslashed) values;
				// unserialize then re-slash so add_post_meta stores them correctly.
				add_post_meta( $new_post_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}

		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Manage the MU-plugin for notice suppression.
	 * Ensures early loading to catch _doing_it_wrong notices.
	 */
	public function enableNoticeSuppression() {
		$mu_path             = WPMU_PLUGIN_DIR . '/00-suppress-logs.php';
		$legacy_wphaven_path = WPMU_PLUGIN_DIR . '/00-suppress-textdomain-notices.php';
		$should_be_active    = $this->resolveSuppressLogsConstant();

		// Only manage the file in the admin to avoid disk I/O on every frontend request
		// Unless it's a dev environment where we might be toggling things
		if ( ! is_admin() && ! defined( 'WP_CLI' ) ) {
			return;
		}

		// Cleanup legacy MU-plugin files
		if ( file_exists( $legacy_wphaven_path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $legacy_wphaven_path );
		}

		if ( $should_be_active ) {
			// Create or update the MU-plugin if needed
			if ( ! file_exists( $mu_path ) || $this->shouldUpdateMuPlugin( $mu_path ) ) {
				$this->createMuPlugin( $mu_path );
			}
		} else {
			// If feature is disabled, cleanup the MU plugin
			$this->removeMuPlugin();
		}

		// Fallback: If writing to MU failed (permissions), apply late filters anyway
		if ( $should_be_active && ! file_exists( $mu_path ) ) {
			$this->applyLateNoticeSuppression();
		}
	}

	/**
	 * Resolve suppress logs constant with backwards compatibility.
	 * Priority: EMBOLD_SUPPRESS_LOGS (new) -> WPH_SUPPRESS_TEXTDOMAIN_NOTICES (legacy) -> option -> default (true)
	 */
	private function resolveSuppressLogsConstant(): bool {
		// Check new constant first
		if ( defined( 'EMBOLD_SUPPRESS_LOGS' ) ) {
			return (bool) constant( 'EMBOLD_SUPPRESS_LOGS' );
		}

		// Fall back to legacy constant for backwards compatibility
		if ( defined( 'WPH_SUPPRESS_TEXTDOMAIN_NOTICES' ) ) {
			return (bool) constant( 'WPH_SUPPRESS_TEXTDOMAIN_NOTICES' );
		}

		// Check database option
		return $this->isFeatureEnabled( 'suppress_notices' );
	}

	/**
	 * Check if the MU-plugin needs updating by comparing versions.
	 */
	private function shouldUpdateMuPlugin( $mu_path ): bool {
		if ( ! file_exists( $mu_path ) ) {
			return true;
		}

		$source = plugin_dir_path( __DIR__ ) . 'templates/00-suppress-logs.php';
		if ( ! file_exists( $source ) ) {
			return false;
		}

		// Extract version from template header
		$template_version = $this->extractPluginHeaderVersion( $source );
		$current_version  = $this->extractPluginHeaderVersion( $mu_path );

		// Update if template is newer (version_compare returns > 0 if first is greater)
		return version_compare( $template_version, $current_version, '>' );
	}

	/**
	 * Extract the Version line from a plugin header (first 30 lines).
	 */
	private function extractPluginHeaderVersion( $file_path ): string {
		if ( ! file_exists( $file_path ) ) {
			return '0.0.0';
		}

		$file_data = file( $file_path, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $file_data ) ) {
			return '0.0.0';
		}

		foreach ( array_slice( $file_data, 0, 30 ) as $line ) {
			if ( preg_match( '/^\s*\*?\s*Version:\s*(.+?)\s*$/', $line, $matches ) ) {
				return trim( $matches[1] );
			}
		}

		return '0.0.0';
	}

	/**
	 * Handle plugin deactivation
	 * Cleans up the MU-plugin file when plugin is deactivated
	 */
	public static function onDeactivation() {
		$mu_path = WPMU_PLUGIN_DIR . '/00-suppress-logs.php';

		if ( file_exists( $mu_path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			if ( @unlink( $mu_path ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Embold] MU-plugin cleaned up' );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Embold] Failed to delete MU-plugin: ' . $mu_path );
			}
		}
	}

	/**
	 * Removes the MU-plugin file if it exists.
	 */
	private function removeMuPlugin() {
		$mu_path = WPMU_PLUGIN_DIR . '/00-suppress-logs.php';
		if ( file_exists( $mu_path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $mu_path );
		}
	}

	private function createMuPlugin( $path ) {
		// Ensure directory exists
		if ( ! is_dir( dirname( $path ) ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			if ( ! mkdir( dirname( $path ), 0755, true ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Embold] Failed to create mu-plugins directory: ' . dirname( $path ) );
				return;
			}
		}

		// Resolve source path using plugin_dir_path for proper plugin-relative path
		$source = plugin_dir_path( __DIR__ ) . 'templates/00-suppress-logs.php';

		if ( ! file_exists( $source ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Embold] MU-Plugin Template missing at: ' . $source );
			return;
		}

		if ( ! copy( $source, $path ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Embold] Failed to copy MU-Plugin to: ' . $path );
		}
	}

	/**
	 * Fallback method if MU plugin cannot be written.
	 */
	private function applyLateNoticeSuppression() {
		$strings_to_check = [
			'_load_textdomain_just_in_time',
			'Translation loading',
			'automatic_feed_links',
			'wp_deregister_script',
			'wp_register_script',
			'wp_enqueue_script',
			'Scripts and styles should not be registered or enqueued until the',
		];

		// Add custom strings
		$extra = $this->getOption( 'suppress_notice_extra_strings' );
		if ( ! empty( $extra ) ) {
			$custom_strings = preg_split( '/[\r\n]+/', $extra );
			if ( is_array( $custom_strings ) ) {
				$strings_to_check = array_merge( $strings_to_check, array_filter( array_map( 'trim', $custom_strings ) ) );
			}
		}

		add_filter(
			'doing_it_wrong_trigger_error',
			function ( $trigger, $function_name, $message, $version ) use ( $strings_to_check ) {
				foreach ( $strings_to_check as $s ) {
					if ( empty( $s ) ) {
						continue;
					}
					if ( $function_name === $s || strpos( $message, $s ) !== false ) {
						return false;
					}
				}
				return $trigger;
			},
			10,
			4
		);
	}

	/**
	 * Configure Mail Behavior (Block / SMTP Override)
	 */
	public function configureMailBehavior() {
		// Resolve Mode
		// We replicate the resolver logic here to keep it self-contained in the main class
		// or check if we can reuse the SettingsPage static helper if accessible.
		// For simplicity, let's implement the resolver logic directly here.
		$mode      = 'auto';
		$locked_by = null;

		// Constants
		if ( defined( 'DISABLE_MAIL' ) && constant( 'DISABLE_MAIL' ) ) {
			$mode = 'block_all';
		} else {
			$opts = get_option( self::OPTION_NAME, [] );
			$mode = $opts['mail_mode'] ?? 'auto';
		}

		// Auto Logic
		if ( $mode === 'auto' ) {
			$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
			if ( $env === 'local' ) {
				$mode = 'smtp_override';
			} elseif ( in_array( $env, [ 'development', 'staging' ], true ) ) {
				$mode = 'block_all';
			} else {
				$mode = 'no_override';
			}
		}

		// Handle Modes
		switch ( $mode ) {
			case 'block_all':
				add_filter(
					'pre_wp_mail',
					function ( $result, $args = [] ) {
						$error = new \WP_Error( 'embold_mail_blocked', 'Mail sending is blocked by Embold Tweaks.' );
						do_action( 'wp_mail_failed', $error );
						return false;
					},
					9999,
					2
				);
				break;

			case 'smtp_override':
				// Override From Addresses
				add_filter(
					'wp_mail_from',
					function () {
						return $this->getOption( 'smtp_from_email', 'admin@wordpress.local' );
					},
					9999
				);
				add_filter(
					'wp_mail_from_name',
					function () {
						return $this->getOption( 'smtp_from_name', 'WordPress' );
					},
					9999
				);

				// Configure PHPMailer
				add_action(
					'phpmailer_init',
					function ( PHPMailer $phpmailer ) {
						$phpmailer->isSMTP();
						$phpmailer->Host       = $this->getOption( 'smtp_host', 'mailpit' );
						$phpmailer->Port       = (int) $this->getOption( 'smtp_port', 1025 );
						$phpmailer->SMTPAuth   = false;
						$phpmailer->SMTPSecure = '';
					},
					9999
				);

				// Disable conflicting plugins
				$this->disableConflictingMailPlugins();
				break;
		}
	}

	private function disableConflictingMailPlugins() {
		if ( ! is_admin() ) {
			return;
		}

		$plugins = [ 'mailgun/mailgun.php', 'sparkpost/sparkpost.php', 'wp-mail-smtp/wp_mail_smtp.php', 'easy-wp-smtp/easy-wp-smtp.php' ];
		$active  = array_filter( $plugins, 'is_plugin_active' );
		if ( ! empty( $active ) ) {
			deactivate_plugins( $active );
		}
	}
}
