<?php
/**
 * Plugin Name: WPC External Variations for WooCommerce
 * Plugin URI: https://wpclever.net/
 * Description: WPC External Variations allows you to define an external URL for any variation.
 * Version: 1.0.3
 * Author: WPClever
 * Author URI: https://wpclever.net
 * Text Domain: wpc-external-variations
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * Requires at least: 4.0
 * Tested up to: 6.6
 * WC requires at least: 3.0
 * WC tested up to: 9.3
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

! defined( 'WPCEV_VERSION' ) && define( 'WPCEV_VERSION', '1.0.3' );
! defined( 'WPCEV_LITE' ) && define( 'WPCEV_LITE', __FILE__ );
! defined( 'WPCEV_FILE' ) && define( 'WPCEV_FILE', __FILE__ );
! defined( 'WPCEV_URI' ) && define( 'WPCEV_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCEV_DIR' ) && define( 'WPCEV_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCEV_REVIEWS' ) && define( 'WPCEV_REVIEWS', 'https://wordpress.org/support/plugin/wpc-external-variations/reviews/?filter=5' );
! defined( 'WPCEV_CHANGELOG' ) && define( 'WPCEV_CHANGELOG', 'https://wordpress.org/plugins/wpc-external-variations/#developers' );
! defined( 'WPCEV_DISCUSSION' ) && define( 'WPCEV_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-external-variations' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCEV_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcev_init' ) ) {
	add_action( 'plugins_loaded', 'wpcev_init', 11 );

	function wpcev_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-external-variations', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcev_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcev' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcev {
				protected static $settings = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcev_settings', [] );

					// settings page
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 99 );

					// variation settings
					add_action( 'woocommerce_product_after_variable_attributes', [
						$this,
						'variation_settings'
					], 10, 3 );
					add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 10, 2 );

					// frontend
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );
					add_filter( 'woocommerce_available_variation', [ $this, 'available_variation' ], 10, 3 );
					add_filter( 'woocommerce_variation_is_purchasable', [ $this, 'variation_is_purchasable' ], 10, 2 );
					add_action( 'woocommerce_single_variation', [ $this, 'add_to_cart_button' ], 18 );

					// WPC Sticky Add To Cart
					add_action( 'wpcsb_before_product_action', [ $this, 'add_to_cart_button' ] );

					// WPC Variation Duplicator
					add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

					// WPC Variation Bulk Editor
					add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );
				}

				public static function get_settings() {
					return apply_filters( 'wpcev_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcev_' . $name, $default );
					}

					return apply_filters( 'wpcev_get_setting', $setting, $name, $default );
				}

				function register_settings() {
					// settings
					register_setting( 'wpcev_settings', 'wpcev_settings' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC External Variations', 'wpc-external-variations' ), esc_html__( 'External Variations', 'wpc-external-variations' ), 'manage_options', 'wpclever-wpcev', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html( esc_html__( 'WPC External Variations', 'wpc-external-variations' ) . ' ' . WPCEV_VERSION ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-external-variations' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCEV_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-external-variations' ); ?></a> |
                                <a href="<?php echo esc_url( WPCEV_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-external-variations' ); ?></a> |
                                <a href="<?php echo esc_url( WPCEV_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-external-variations' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-external-variations' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcev&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-external-variations' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-external-variations' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								$button_text       = self::get_setting( 'button_text', '' );
								$new_tab           = self::get_setting( 'new_tab', 'yes' );
								$out_of_stock      = self::get_setting( 'out_of_stock', 'no' );
								$out_of_stock_url  = self::get_setting( 'out_of_stock_url', '' );
								$out_of_stock_text = self::get_setting( 'out_of_stock_text', '' );
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th><?php esc_html_e( 'General', 'wpc-external-variations' ); ?></th>
                                            <td><?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-external-variations' ); ?></td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Button text', 'wpc-external-variations' ); ?>
                                            </th>
                                            <td>
                                                <label>
                                                    <input type="text" class="text large-text" name="wpcev_settings[button_text]" placeholder="<?php esc_attr_e( 'Buy product', 'wpc-external-variations' ); ?>" value="<?php echo esc_attr( $button_text ); ?>"/>
                                                </label>
                                                <span class="description"><?php esc_html_e( 'General text that is shown on all external variations\' buttons. You can customize the text for specific variations in the Variations tab of single product pages.', 'wpc-external-variations' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'Open in new tab', 'wpc-external-variations' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcev_settings[new_tab]">
                                                        <option value="yes" <?php selected( $new_tab, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-external-variations' ); ?></option>
                                                        <option value="no" <?php selected( $new_tab, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-external-variations' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
												<?php esc_html_e( 'External out-of-stock variations', 'wpc-external-variations' ); ?>
                                            </th>
                                            <td>
                                                <label> <select name="wpcev_settings[out_of_stock]">
                                                        <option value="yes" <?php selected( $out_of_stock, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-external-variations' ); ?></option>
                                                        <option value="no" <?php selected( $out_of_stock, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-external-variations' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Enable to convert all out-of-stock variations to external.', 'wpc-external-variations' ); ?></span>
                                                <div class="wpcev-inner-lines">
                                                    <div class="wpcev-inner-line">
                                                        <div class="wpcev-inner-label"><?php esc_html_e( 'Product URL', 'wpc-external-variations' ); ?></div>
                                                        <div class="wpcev-inner-value">
                                                            <label>
                                                                <input type="url" class="text large-text" name="wpcev_settings[out_of_stock_url]" value="<?php echo esc_attr( $out_of_stock_url ); ?>" placeholder="https://"/>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="wpcev-inner-line">
                                                        <div class="wpcev-inner-label"><?php esc_html_e( 'Button text', 'wpc-external-variations' ); ?></div>
                                                        <div class="wpcev-inner-value">
                                                            <label>
                                                                <input type="text" class="text large-text" name="wpcev_settings[out_of_stock_text]" value="<?php echo esc_attr( $out_of_stock_text ); ?>" placeholder="<?php esc_attr_e( 'Buy product', 'wpc-external-variations' ); ?>"/>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcev_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcev&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-external-variations' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCEV_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-external-variations' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function admin_enqueue_scripts() {
					wp_enqueue_style( 'wpcev-backend', WPCEV_URI . 'assets/css/backend.css', [], WPCEV_VERSION );
				}

				function enqueue_scripts() {
					wp_enqueue_script( 'wpcev-frontend', WPCEV_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCEV_VERSION, true );
					wp_localize_script( 'wpcev-frontend', 'wpcev_vars', apply_filters( 'wpcev_vars', [
							'new_tab' => self::get_setting( 'new_tab', 'yes' )
						] )
					);
				}

				function available_variation( $data, $variable, $variation ) {
					if ( ( $variation_id = $variation->get_id() ) && ( $btn = self::get_btn( $variation_id ) ) ) {
						$data['wpcev_btn'] = htmlentities( $btn );
					}

					return $data;
				}

				function variation_is_purchasable( $purchasable, $variation ) {
					if ( self::get_url( $variation ) ) {
						return false;
					}

					return $purchasable;
				}

				public static function get_url( $variation ) {
					if ( is_a( $variation, 'WC_Product_Variation' ) ) {
						$variation_id = $variation->get_id();
					} else {
						$variation_id = $variation;
					}

					$url = esc_url( get_post_meta( $variation_id, 'wpcev_url', true ) );

					if ( ! empty( $url ) && wp_http_validate_url( $url ) ) {
						return apply_filters( 'wpcev_url', $url );
					}

					return false;
				}

				public static function get_btn( $variation ) {
					if ( is_a( $variation, 'WC_Product_Variation' ) ) {
						$variation_id = $variation->get_id();
					} else {
						$variation_id = $variation;
						$variation    = wc_get_product( $variation_id );
					}

					if ( $url = self::get_url( $variation_id ) ) {
						$text = get_post_meta( $variation_id, 'wpcev_text', true );

						if ( empty( $text ) ) {
							$text = self::get_setting( 'button_text', '' );
						}

						if ( empty( $text ) ) {
							$text = esc_html__( 'Buy product', 'wpc-external-variations' );
						}

						$btn_class = apply_filters( 'wpcev_btn_class', 'single_add_to_cart_button wpcev_btn wpcev-btn button alt', $variation );

						return apply_filters( 'wpcev_btn', '<button type="button" class="' . esc_attr( $btn_class ) . '" data-url="' . esc_url( $url ) . '">' . esc_html( $text ) . '</button>', $variation );
					}

					if ( ! $variation->is_in_stock() && ( self::get_setting( 'out_of_stock', 'no' ) === 'yes' ) ) {
						$out_of_stock_url = esc_url( self::get_setting( 'out_of_stock_url', '' ) );

						if ( ! empty( $out_of_stock_url ) && wp_http_validate_url( $out_of_stock_url ) ) {
							$out_of_stock_text = self::get_setting( 'out_of_stock_text', '' );

							if ( empty( $out_of_stock_text ) ) {
								$out_of_stock_text = esc_html__( 'Buy product', 'wpc-external-variations' );
							}

							$btn_class = apply_filters( 'wpcev_btn_class', 'single_add_to_cart_button wpcev_btn wpcev-btn wpcev-btn-out-of-stock button alt', $variation );

							return apply_filters( 'wpcev_btn', '<button type="button" class="' . esc_attr( $btn_class ) . '" data-url="' . esc_url( $out_of_stock_url ) . '">' . esc_html( $out_of_stock_text ) . '</button>', $variation );
						}
					}

					return false;
				}

				function add_to_cart_button() {
					global $product;

					if ( $product && is_a( $product, 'WC_Product_Variable' ) ) {
						$product_id = $product->get_id();
						echo '<div class="' . esc_attr( 'wpcev-variation-add-to-cart wpcev-variation-add-to-cart-' . $product_id ) . '" data-id="' . esc_attr( $product_id ) . '"></div>';
					}
				}

				function variation_settings( $loop, $variation_data, $variation ) {
					if ( $variation_id = $variation->ID ) {
						$url = get_post_meta( $variation_id, 'wpcev_url', true );
						$btn = get_post_meta( $variation_id, 'wpcev_text', true );

						echo '<div class="form-row form-row-full wpcev-variation-settings">';
						echo '<label>' . esc_html__( 'WPC External Variations', 'wpc-external-variations' ) . '</label>';
						echo '<div class="wpcev-variation-wrap">';
						echo '<p class="form-field form-row">';
						echo '<label>' . esc_html__( 'Product URL', 'wpc-external-variations' ) . '</label>';
						echo '<input type="url" placeholder="https://" class="wpcev_url" name="wpcev_url[' . esc_attr( $variation_id ) . ']" value="' . esc_attr( $url ) . '"/>';
						echo '<span class="description">' . esc_html__( 'Enter the external URL to the product.', 'wpc-external-variations' ) . '</span>';
						echo '</p>';
						echo '<p class="form-field form-row">';
						echo '<label>' . esc_html__( 'Button text', 'wpc-external-variations' ) . '</label>';
						echo '<input type="text" class="wpcev_text" name="wpcev_text[' . esc_attr( $variation_id ) . ']" value="' . esc_attr( $btn ) . '"/>';
						echo '<span class="description">' . esc_html__( 'This text will be shown on the button linking to the external product.', 'wpc-external-variations' ) . '</span>';
						echo '</p>';
						echo '</div></div>';
					}

					return null;
				}

				function save_variation_settings( $post_id ) {
					if ( isset( $_POST['wpcev_url'][ $post_id ] ) ) {
						update_post_meta( $post_id, 'wpcev_url', sanitize_url( $_POST['wpcev_url'][ $post_id ] ) );
					} else {
						delete_post_meta( $post_id, 'wpcev_url' );
					}

					if ( isset( $_POST['wpcev_text'][ $post_id ] ) ) {
						update_post_meta( $post_id, 'wpcev_text', sanitize_text_field( $_POST['wpcev_text'][ $post_id ] ) );
					} else {
						delete_post_meta( $post_id, 'wpcev_text' );
					}
				}

				function duplicate_variation( $old_variation_id, $new_variation_id ) {
					if ( $url = get_post_meta( $old_variation_id, 'wpcev_url', true ) ) {
						update_post_meta( $new_variation_id, 'wpcev_url', sanitize_url( $url ) );
					}

					if ( $btn = get_post_meta( $old_variation_id, 'wpcev_text', true ) ) {
						update_post_meta( $new_variation_id, 'wpcev_text', sanitize_text_field( $btn ) );
					}
				}

				function bulk_update_variation( $variation_id, $fields ) {
					if ( ! empty( $fields['wpcev_url'] ) ) {
						update_post_meta( $variation_id, 'wpcev_url', sanitize_url( $fields['wpcev_url'] ) );
					}

					if ( ! empty( $fields['wpcev_text'] ) ) {
						update_post_meta( $variation_id, 'wpcev_text', sanitize_text_field( $fields['wpcev_text'] ) );
					}
				}
			}

			return WPCleverWpcev::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcev_notice_wc' ) ) {
	function wpcev_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC External Variations</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
