<?php
/**
 * Plugin Name: WooCommerce Product Importer
 * Description: Import top-level categories and review products from another WooCommerce store, one product at a time.
 * Version: 0.1.0
 * Author: Octoopus
 * Requires Plugins: woocommerce
 * Text Domain: wc-product-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WCPI_Importer {
	const OPTION = 'wcpi_state';
	const NONCE  = 'wcpi_admin';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_wcpi_fetch_categories', array( $this, 'ajax_fetch_categories' ) );
		add_action( 'wp_ajax_wcpi_import_categories', array( $this, 'ajax_import_categories' ) );
		add_action( 'wp_ajax_wcpi_get_product', array( $this, 'ajax_get_product' ) );
		add_action( 'wp_ajax_wcpi_product_action', array( $this, 'ajax_product_action' ) );
		add_action( 'wp_ajax_wcpi_reset', array( $this, 'ajax_reset' ) );
	}

	public function menu() {
		add_submenu_page(
			'woocommerce',
			'واردکننده محصولات',
			'واردکننده محصولات',
			'manage_woocommerce',
			'wcpi-importer',
			array( $this, 'page' )
		);
	}

	public function assets( $hook ) {
		if ( 'woocommerce_page_wcpi-importer' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wcpi-admin', false, array(), '0.1.0' );
		wp_add_inline_style( 'wcpi-admin', '
			.wcpi-wrap{max-width:950px}.wcpi-panel{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin:16px 0}
			.wcpi-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.wcpi-url{width:min(520px,100%)}
			.wcpi-cats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin:16px 0}
			.wcpi-cat{padding:10px;border:1px solid #dcdcde;border-radius:6px}.wcpi-product{display:grid;grid-template-columns:180px 1fr;gap:20px}
			.wcpi-product img{width:100%;height:auto;object-fit:contain}.wcpi-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
			.wcpi-muted{color:#646970}.wcpi-status{margin:12px 0;padding:10px;background:#f0f6fc;border-left:4px solid #2271b1}
			@media(max-width:600px){.wcpi-product{grid-template-columns:1fr}.wcpi-product img{max-width:220px}}
		' );
		wp_enqueue_script( 'wcpi-admin', false, array( 'jquery' ), '0.1.0', true );
		wp_add_inline_script( 'wcpi-admin', 'window.WCPI='.wp_json_encode( array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
		) ).';', 'before' );
		wp_add_inline_script( 'wcpi-admin', $this->javascript() );
	}

	private function javascript() {
		return <<<'JS'
jQuery(function($){
	function request(action, data, done){
		data = data || {}; data.action = action; data.nonce = WCPI.nonce;
		$('#wcpi-status').text('در حال انجام...');
		$.post(WCPI.ajax, data).done(function(r){
			if(r && r.success){ $('#wcpi-status').text(r.data.message || 'انجام شد'); if(done) done(r.data); }
			else $('#wcpi-status').text((r && r.data && r.data.message) ? r.data.message : 'خطا در درخواست');
		}).fail(function(){ $('#wcpi-status').text('ارتباط با سرور ناموفق بود.'); });
	}
	$('#wcpi-fetch-cats').on('click', function(){
		request('wcpi_fetch_categories', {source:$('#wcpi-source').val()}, function(d){
			$('#wcpi-categories').html(d.html).show();
			$('#wcpi-import-cats').show();
		});
	});
	$('#wcpi-import-cats').on('click', function(){
		request('wcpi_import_categories', {}, function(d){
			$('#wcpi-progress').html(d.progress);
			$('#wcpi-categories').hide(); $('#wcpi-import-cats').hide();
			$('#wcpi-product-area').show();
			loadProduct();
		});
	});
	function loadProduct(){
		request('wcpi_get_product', {}, function(d){ $('#wcpi-product-area').html(d.html); });
	}
	$(document).on('click', '[data-wcpi-action]', function(){
		var action=$(this).data('wcpi-action');
		request('wcpi_product_action', {move:action}, function(d){
			$('#wcpi-progress').html(d.progress);
			$('#wcpi-product-area').html(d.html);
		});
	});
	$('#wcpi-reset').on('click', function(){
		if(!confirm('وضعیت واردکننده از ابتدا شروع شود؟ دسته‌های واردشده و محصولات قبلی حذف نمی‌شوند.')) return;
		request('wcpi_reset', {}, function(){ location.reload(); });
	});
});
JS;
	}

	public function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$state  = get_option( self::OPTION, array() );
		$source = isset( $state['source'] ) ? $state['source'] : '';
		?>
		<div class="wrap wcpi-wrap">
			<h1>واردکننده محصولات ووکامرس</h1>
			<p>آدرس فروشگاه ووکامرسی مبدأ را وارد کنید. این نسخه از API عمومی Store API ووکامرس استفاده می‌کند.</p>
			<div class="wcpi-panel">
				<label for="wcpi-source"><strong>آدرس سایت مبدأ</strong></label>
				<div class="wcpi-row" style="margin-top:8px">
					<input id="wcpi-source" class="regular-text wcpi-url" type="url" placeholder="https://example.com" value="<?php echo esc_attr( $source ); ?>">
					<button id="wcpi-fetch-cats" class="button button-primary">دریافت دسته‌بندی محصولات</button>
					<button id="wcpi-reset" class="button">شروع از ابتدا</button>
				</div>
				<div id="wcpi-status" class="wcpi-status" role="status">آماده دریافت دسته‌بندی‌ها.</div>
				<div id="wcpi-categories" style="display:none"></div>
				<button id="wcpi-import-cats" class="button button-primary" style="display:none">تأیید و افزودن دسته‌بندی‌ها</button>
			</div>
			<div id="wcpi-progress" class="wcpi-panel">
				<?php echo ! empty( $state['categories'] ) ? wp_kses_post( $this->progress_html( $state ) ) : 'پس از دریافت و تأیید دسته‌بندی‌ها، روند افزودن محصولات از این قسمت آغاز می‌شود.'; ?>
			</div>
			<div id="wcpi-product-area" class="wcpi-panel" style="<?php echo ! empty( $state['categories'] ) ? '' : 'display:none'; ?>">
				<?php
				if ( ! empty( $state['categories'] ) ) {
					echo wp_kses_post( $this->product_html( $state ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	private function verify() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'اجازه دسترسی ندارید.' ), 403 );
		}
	}

	private function clean_source( $url ) {
		$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'wcpi_bad_url', 'آدرس سایت معتبر نیست.' );
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'wcpi_bad_url', 'آدرس باید با http یا https شروع شود.' );
		}
		return untrailingslashit( $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) );
	}

	private function api_get( $source, $path, $args = array() ) {
		$url = trailingslashit( $source ) . 'wp-json/wc/store/v1/' . ltrim( $path, '/' );
		if ( $args ) {
			$url = add_query_arg( $args, $url );
		}
		$response = wp_safe_remote_get( $url, array(
			'timeout'     => 25,
			'redirection' => 3,
			'headers'     => array( 'Accept' => 'application/json' ),
			'user-agent'  => 'WCPI/0.1.0; ' . home_url(),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'wcpi_api_error', 'Store API در دسترس نیست یا پاسخ معتبری برنگرداند (HTTP ' . intval( $code ) . ').' );
		}
		return $body;
	}

	public function ajax_fetch_categories() {
		$this->verify();
		$source = $this->clean_source( isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '' );
		if ( is_wp_error( $source ) ) {
			wp_send_json_error( array( 'message' => $source->get_error_message() ) );
		}
		$categories = $this->api_get( $source, 'products/categories', array( 'per_page' => 100, 'page' => 1, 'hide_empty' => false ) );
		if ( is_wp_error( $categories ) ) {
			wp_send_json_error( array( 'message' => $categories->get_error_message() ) );
		}
		$top = array_values( array_filter( $categories, function( $cat ) {
			return isset( $cat['id'], $cat['name'] ) && ( empty( $cat['parent'] ) );
		} ) );
		if ( ! $top ) {
			wp_send_json_error( array( 'message' => 'دسته‌بندی سطح اولی پیدا نشد. ممکن است API عمومی غیرفعال باشد یا ساختار سایت متفاوت باشد.' ) );
		}
		$state = get_option( self::OPTION, array() );
		$state['source'] = $source;
		$state['source_categories'] = $top;
		$state['pending_categories'] = $top;
		update_option( self::OPTION, $state, false );

		$html = '<h3>دسته‌بندی‌های اصلی پیدا شده</h3><p class="wcpi-muted">فقط دسته‌های سطح اول نمایش داده شده‌اند؛ زیردسته‌ها بررسی نمی‌شوند.</p><div class="wcpi-cats">';
		foreach ( $top as $cat ) {
			$html .= '<label class="wcpi-cat"><strong>' . esc_html( $cat['name'] ) . '</strong><br><span class="wcpi-muted">' . intval( $cat['count'] ?? 0 ) . ' محصول در مبدأ</span></label>';
		}
		$html .= '</div><p>با تأیید، همین دسته‌ها در ووکامرس مقصد ساخته یا با دسته‌های موجود تطبیق داده می‌شوند.</p>';
		wp_send_json_success( array( 'html' => $html, 'message' => count( $top ) . ' دسته اصلی پیدا شد.' ) );
	}

	public function ajax_import_categories() {
		$this->verify();
		$state = get_option( self::OPTION, array() );
		if ( empty( $state['pending_categories'] ) || empty( $state['source'] ) ) {
			wp_send_json_error( array( 'message' => 'ابتدا دسته‌بندی‌ها را دریافت کنید.' ) );
		}
		$mapped = array();
		foreach ( $state['pending_categories'] as $cat ) {
			$existing = get_term_by( 'slug', sanitize_title( $cat['slug'] ), 'product_cat' );
			if ( ! $existing ) {
				$existing = get_term_by( 'name', $cat['name'], 'product_cat' );
			}
			if ( $existing ) {
				$term_id = (int) $existing->term_id;
			} else {
				$created = wp_insert_term( sanitize_text_field( $cat['name'] ), 'product_cat', array(
					'slug' => sanitize_title( $cat['slug'] ),
				) );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$term_id = (int) $created['term_id'];
			}
			$mapped[] = array(
				'source_id' => (int) $cat['id'],
				'name'      => sanitize_text_field( $cat['name'] ),
				'slug'      => sanitize_title( $cat['slug'] ),
				'term_id'   => $term_id,
			);
		}
		if ( ! $mapped ) {
			wp_send_json_error( array( 'message' => 'هیچ دسته‌ای در مقصد ساخته یا تطبیق داده نشد.' ) );
		}
		$state['categories'] = $mapped;
		$state['category_index'] = 0;
		$state['product_page'] = 1;
		$state['added'] = isset( $state['added'] ) ? (int) $state['added'] : 0;
		$state['skipped'] = isset( $state['skipped'] ) ? (int) $state['skipped'] : 0;
		update_option( self::OPTION, $state, false );
		wp_send_json_success( array(
			'message'  => count( $mapped ) . ' دسته آماده شد.',
			'progress' => $this->progress_html( $state ),
			'html'     => $this->product_html( $state ),
		) );
	}

	private function current_product( &$state ) {
		if ( empty( $state['categories'] ) ) {
			return new WP_Error( 'wcpi_no_categories', 'ابتدا دسته‌بندی‌ها را تأیید کنید.' );
		}
		while ( isset( $state['categories'][ $state['category_index'] ] ) ) {
			$cat = $state['categories'][ $state['category_index'] ];
			$items = $this->api_get( $state['source'], 'products', array(
				'category' => (int) $cat['source_id'],
				'per_page' => 1,
				'page'     => max( 1, (int) $state['product_page'] ),
			) );
			if ( is_wp_error( $items ) ) {
				return $items;
			}
			if ( ! empty( $items[0] ) ) {
				return array( 'product' => $items[0], 'category' => $cat );
			}
			$state['category_index']++;
			$state['product_page'] = 1;
		}
		update_option( self::OPTION, $state, false );
		return false;
	}

	public function ajax_get_product() {
		$this->verify();
		$state = get_option( self::OPTION, array() );
		$html = $this->product_html( $state );
		if ( false === $html ) {
			wp_send_json_error( array( 'message' => 'اطلاعات دسته‌بندی‌ها موجود نیست.' ) );
		}
		wp_send_json_success( array( 'html' => $html, 'message' => 'محصول آماده بررسی است.' ) );
	}

	private function product_html( $state ) {
		if ( empty( $state['categories'] ) ) {
			return '<p>دسته‌بندی‌ها هنوز تأیید نشده‌اند.</p>';
		}
		$current = $this->current_product( $state );
		if ( is_wp_error( $current ) ) {
			return '<p class="notice notice-error">' . esc_html( $current->get_error_message() ) . '</p>';
		}
		if ( false === $current ) {
			return '<h2>پایان واردکردن محصولات</h2><p>به آخرین دسته‌بندی رسیدیم. محصول دیگری برای بررسی باقی نمانده است.</p><p>ثبت‌شده: ' . intval( $state['added'] ?? 0 ) . ' | ردشده: ' . intval( $state['skipped'] ?? 0 ) . '</p>';
		}
		$product = $current['product'];
		$cat = $current['category'];
		$image = ! empty( $product['images'][0]['src'] ) ? esc_url( $product['images'][0]['src'] ) : '';
		$price = '';
		if ( ! empty( $product['prices']['price'] ) ) {
			$minor = isset( $product['prices']['currency_minor_unit'] ) ? (int) $product['prices']['currency_minor_unit'] : 2;
			$price = (string) ( (float) $product['prices']['price'] / pow( 10, $minor ) );
		}
		ob_start();
		?>
		<h2>بررسی محصول</h2>
		<p class="wcpi-muted">دسته فعلی: <strong><?php echo esc_html( $cat['name'] ); ?></strong> — محصول شماره <?php echo intval( $state['product_page'] ); ?></p>
		<div class="wcpi-product">
			<div><?php if ( $image ) : ?><img src="<?php echo $image; ?>" alt=""><?php else : ?><div class="wcpi-muted">بدون تصویر</div><?php endif; ?></div>
			<div>
				<h3><?php echo esc_html( $product['name'] ?? 'بدون عنوان' ); ?></h3>
				<?php if ( $price ) : ?><p><strong>قیمت مبدأ:</strong> <?php echo esc_html( $price ); ?> <span class="wcpi-muted">(واحد پول مبدأ)</span></p><?php endif; ?>
				<div><?php echo wp_kses_post( wpautop( wp_strip_all_tags( $product['short_description'] ?? $product['description'] ?? '' ) ) ); ?></div>
				<p><a href="<?php echo esc_url( $product['permalink'] ?? '#' ); ?>" target="_blank" rel="noopener">مشاهده محصول در سایت مبدأ</a></p>
			</div>
		</div>
		<div class="wcpi-actions">
			<button class="button button-primary" data-wcpi-action="add">افزودن محصول</button>
			<button class="button" data-wcpi-action="next">محصول بعدی</button>
			<button class="button" data-wcpi-action="category">گروه بعدی</button>
		</div>
		<?php
		return ob_get_clean();
	}

	public function ajax_product_action() {
		$this->verify();
		$move = isset( $_POST['move'] ) ? sanitize_key( wp_unslash( $_POST['move'] ) ) : '';
		$state = get_option( self::OPTION, array() );
		$current = $this->current_product( $state );
		if ( is_wp_error( $current ) ) {
			wp_send_json_error( array( 'message' => $current->get_error_message() ) );
		}
		if ( false === $current ) {
			wp_send_json_success( array( 'message' => 'به انتهای فهرست محصولات رسیدید.', 'progress' => $this->progress_html( $state ), 'html' => $this->product_html( $state ) ) );
		}
		if ( 'add' === $move ) {
			$result = $this->import_product( $current['product'], $current['category'], $state['source'] );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$state['added'] = (int) ( $state['added'] ?? 0 ) + 1;
		} elseif ( 'next' === $move ) {
			$state['skipped'] = (int) ( $state['skipped'] ?? 0 ) + 1;
		} elseif ( 'category' === $move ) {
			$state['category_index']++;
			$state['product_page'] = 1;
		} else {
			wp_send_json_error( array( 'message' => 'عملیات نامعتبر است.' ) );
		}
		if ( 'category' !== $move ) {
			$state['product_page'] = (int) $state['product_page'] + 1;
		}
		update_option( self::OPTION, $state, false );
		wp_send_json_success( array(
			'message'  => 'وضعیت ذخیره شد.',
			'progress' => $this->progress_html( $state ),
			'html'     => $this->product_html( $state ),
		) );
	}

	private function import_product( $source_product, $category, $source ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'wcpi_no_wc', 'ووکامرس فعال نیست.' );
		}
		$source_url = isset( $source_product['permalink'] ) ? esc_url_raw( $source_product['permalink'] ) : '';
		$source_id  = isset( $source_product['id'] ) ? (int) $source_product['id'] : 0;
		$unique     = md5( $source . '|' . ( $source_id ? $source_id : $source_url ) );
		$existing   = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'draft', 'publish', 'pending', 'private' ),
			'meta_key'       => '_wcpi_source_key',
			'meta_value'     => $unique,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		) );
		if ( $existing ) {
			return new WP_Error( 'wcpi_duplicate', 'این محصول قبلاً از همین سایت وارد شده است؛ برای جلوگیری از تکرار ردش کنید.' );
		}
		$product = new WC_Product_Simple();
		$product->set_name( sanitize_text_field( $source_product['name'] ?? 'Imported product' ) );
		$product->set_status( 'draft' );
		$product->set_description( wp_kses_post( $source_product['description'] ?? '' ) );
		$product->set_short_description( wp_kses_post( $source_product['short_description'] ?? '' ) );
		$product->set_catalog_visibility( 'visible' );
		$product->set_category_ids( array( (int) $category['term_id'] ) );
		if ( ! empty( $source_product['sku'] ) ) {
			$sku = wc_clean( $source_product['sku'] );
			if ( ! wc_get_product_id_by_sku( $sku ) ) {
				$product->set_sku( $sku );
			}
		}
		if ( ! empty( $source_product['prices']['price'] ) ) {
			$minor = isset( $source_product['prices']['currency_minor_unit'] ) ? (int) $source_product['prices']['currency_minor_unit'] : 2;
			$price = (float) $source_product['prices']['price'] / pow( 10, $minor );
			$product->set_regular_price( wc_format_decimal( $price ) );
		}
		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'wcpi_save_failed', 'ذخیره محصول در ووکامرس ناموفق بود.' );
		}
		update_post_meta( $product_id, '_wcpi_source_key', $unique );
		update_post_meta( $product_id, '_wcpi_source_url', $source_url );

		if ( ! empty( $source_product['images'][0]['src'] ) ) {
			$image_url = esc_url_raw( $source_product['images'][0]['src'] );
			$source_host = wp_parse_url( $source, PHP_URL_HOST );
			$image_host  = wp_parse_url( $image_url, PHP_URL_HOST );
			if ( $image_url && $source_host && $image_host && strtolower( $source_host ) === strtolower( $image_host ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$attachment_id = media_sideload_image( $image_url, $product_id, $product->get_name(), 'id' );
				if ( ! is_wp_error( $attachment_id ) ) {
					$product->set_image_id( (int) $attachment_id );
					$product->save();
				}
			}
		}
		return $product_id;
	}

	private function progress_html( $state ) {
		$total = isset( $state['categories'] ) ? count( $state['categories'] ) : 0;
		$index = (int) ( $state['category_index'] ?? 0 );
		$current = ( $total && isset( $state['categories'][ $index ] ) ) ? $state['categories'][ $index ]['name'] : 'پایان دسته‌بندی‌ها';
		return '<strong>وضعیت واردکردن</strong><p>دسته فعلی: ' . esc_html( $current ) . ' (' . min( $index + 1, max( 1, $total ) ) . ' از ' . $total . ')</p><p>محصولات ثبت‌شده: ' . intval( $state['added'] ?? 0 ) . ' | محصولات ردشده: ' . intval( $state['skipped'] ?? 0 ) . '</p>';
	}

	public function ajax_reset() {
		$this->verify();
		delete_option( self::OPTION );
		wp_send_json_success( array( 'message' => 'وضعیت پاک شد.' ) );
	}
}

add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		new WCPI_Importer();
	}
} );
