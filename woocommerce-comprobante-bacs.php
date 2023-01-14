<?php
/*
Plugin Name: WooCommerce Comprobante BACS
Plugin URI: https://github.com/24hwww
Description: Adjunta una imagen como comprobante de pago para el metodo de pago transferencia bancaria.
Version: 1.5.2
Author: Leonardo Reyes
Author URI: https://facebook.com/24hwww
Copyright: 2023
*/

defined( 'ABSPATH' ) or die( 'Prohibido acceso directo.' );

define('WC_CBACS_BASE', plugin_basename( __FILE__ ));
define('WC_CBACS_BASE_PATH', dirname(__FILE__));

add_action('admin_init', function(){
	if ( !class_exists( 'WooCommerce' ) ):
		deactivate_plugins( WC_CBACS_BASE );
		if ( isset( $_GET['activate'] ) ){
		unset( $_GET['activate'] );
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		add_action( 'admin_notices', function(){
			$class = 'notice notice-error';
			$message = __( 'No se puede activar el plugin, debe estar activado el WooCommerce.' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		});
		return;
		}
	endif;
});

if (!class_exists('Class_WC_CBACS_Diurvan')) {
    	
    class Class_WC_CBACS_Diurvan{
        
        private static $_instance = null;
        public $cbacs_id = '';
        public $option_group = '';
        
        public static function instance() {
            $instance = is_null( self::$_instance ) ? new self() : self::$_instance;
        	return $instance;
        }
        
        public function __construct() {
            $this->cbacs_id = 'comprobante_bacs';
            $this->option_group = 'wc_cbacs_settings';
        }
    
        public static function init() {
            $instance = self::instance();
            
            add_filter('woocommerce_gateway_description',[$instance,'bacs_woocommerce_gateway_method_description_func'],10,2);
            add_action( 'woocommerce_checkout_update_order_meta', [$instance,'bacs_wc_upload_file_gateway_method_description_func'], 10, 3);
            
            add_action( 'add_meta_boxes', [$instance, 'comprobante_bacs_order_meta_boxes_func']);
            
            add_action( 'wp_footer', [$instance, 'js_scripts_comprobante_bacs_func'], 100 );
        
			add_filter( 'woocommerce_get_sections_checkout', [$instance, 'comprobante_bacs_add_section_func']);
			add_filter( 'woocommerce_get_settings_checkout', [$instance, 'comprobante_bacs_settings_func'], 10, 2 );
			
			add_action('woocommerce_checkout_process', [$instance, 'validar_campo_carga_comprobante_func']);
			add_action('woocommerce_after_checkout_validation', [$instance, 'wc_check_validando_checkout_func'], 10, 2 );
			
			add_filter('woocommerce_bacs_accounts', [$instance, 'ocultar_cuentas_bancarias_func'], 10, 2 );
			
        }

        public function mime2ext($mime) {
            $mime_map = [
                'video/3gpp2'                                                               => '3g2',
                'video/3gp'                                                                 => '3gp',
                'video/3gpp'                                                                => '3gp',
                'application/x-compressed'                                                  => '7zip',
                'audio/x-acc'                                                               => 'aac',
                'audio/ac3'                                                                 => 'ac3',
                'application/postscript'                                                    => 'ai',
                'audio/x-aiff'                                                              => 'aif',
                'audio/aiff'                                                                => 'aif',
                'audio/x-au'                                                                => 'au',
                'video/x-msvideo'                                                           => 'avi',
                'video/msvideo'                                                             => 'avi',
                'video/avi'                                                                 => 'avi',
                'application/x-troff-msvideo'                                               => 'avi',
                'application/macbinary'                                                     => 'bin',
                'application/mac-binary'                                                    => 'bin',
                'application/x-binary'                                                      => 'bin',
                'application/x-macbinary'                                                   => 'bin',
                'image/bmp'                                                                 => 'bmp',
                'image/x-bmp'                                                               => 'bmp',
                'image/x-bitmap'                                                            => 'bmp',
                'image/x-xbitmap'                                                           => 'bmp',
                'image/x-win-bitmap'                                                        => 'bmp',
                'image/x-windows-bmp'                                                       => 'bmp',
                'image/ms-bmp'                                                              => 'bmp',
                'image/x-ms-bmp'                                                            => 'bmp',
                'application/bmp'                                                           => 'bmp',
                'application/x-bmp'                                                         => 'bmp',
                'application/x-win-bitmap'                                                  => 'bmp',
                'application/cdr'                                                           => 'cdr',
                'application/coreldraw'                                                     => 'cdr',
                'application/x-cdr'                                                         => 'cdr',
                'application/x-coreldraw'                                                   => 'cdr',
                'image/cdr'                                                                 => 'cdr',
                'image/x-cdr'                                                               => 'cdr',
                'zz-application/zz-winassoc-cdr'                                            => 'cdr',
                'application/mac-compactpro'                                                => 'cpt',
                'application/pkix-crl'                                                      => 'crl',
                'application/pkcs-crl'                                                      => 'crl',
                'application/x-x509-ca-cert'                                                => 'crt',
                'application/pkix-cert'                                                     => 'crt',
                'text/css'                                                                  => 'css',
                'text/x-comma-separated-values'                                             => 'csv',
                'text/comma-separated-values'                                               => 'csv',
                'application/vnd.msexcel'                                                   => 'csv',
                'application/x-director'                                                    => 'dcr',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
                'application/x-dvi'                                                         => 'dvi',
                'message/rfc822'                                                            => 'eml',
                'application/x-msdownload'                                                  => 'exe',
                'video/x-f4v'                                                               => 'f4v',
                'audio/x-flac'                                                              => 'flac',
                'video/x-flv'                                                               => 'flv',
                'image/gif'                                                                 => 'gif',
                'application/gpg-keys'                                                      => 'gpg',
                'application/x-gtar'                                                        => 'gtar',
                'application/x-gzip'                                                        => 'gzip',
                'application/mac-binhex40'                                                  => 'hqx',
                'application/mac-binhex'                                                    => 'hqx',
                'application/x-binhex40'                                                    => 'hqx',
                'application/x-mac-binhex40'                                                => 'hqx',
                'text/html'                                                                 => 'html',
                'image/x-icon'                                                              => 'ico',
                'image/x-ico'                                                               => 'ico',
                'image/vnd.microsoft.icon'                                                  => 'ico',
                'text/calendar'                                                             => 'ics',
                'application/java-archive'                                                  => 'jar',
                'application/x-java-application'                                            => 'jar',
                'application/x-jar'                                                         => 'jar',
                'image/jp2'                                                                 => 'jp2',
                'video/mj2'                                                                 => 'jp2',
                'image/jpx'                                                                 => 'jp2',
                'image/jpm'                                                                 => 'jp2',
                'image/jpg'                                                                 => 'jpg',
    			'image/jpeg'                                                                => 'jpeg',
                'image/pjpeg'                                                               => 'jpeg',
                'application/x-javascript'                                                  => 'js',
                'application/json'                                                          => 'json',
                'text/json'                                                                 => 'json',
                'application/vnd.google-earth.kml+xml'                                      => 'kml',
                'application/vnd.google-earth.kmz'                                          => 'kmz',
                'text/x-log'                                                                => 'log',
                'audio/x-m4a'                                                               => 'm4a',
                'application/vnd.mpegurl'                                                   => 'm4u',
                'audio/midi'                                                                => 'mid',
                'application/vnd.mif'                                                       => 'mif',
                'video/quicktime'                                                           => 'mov',
                'video/x-sgi-movie'                                                         => 'movie',
                'audio/mpeg'                                                                => 'mp3',
                'audio/mpg'                                                                 => 'mp3',
                'audio/mpeg3'                                                               => 'mp3',
                'audio/mp3'                                                                 => 'mp3',
                'video/mp4'                                                                 => 'mp4',
                'video/mpeg'                                                                => 'mpeg',
                'application/oda'                                                           => 'oda',
                'application/vnd.oasis.opendocument.text'                                   => 'odt',
                'application/vnd.oasis.opendocument.spreadsheet'                            => 'ods',
                'application/vnd.oasis.opendocument.presentation'                           => 'odp',
                'audio/ogg'                                                                 => 'ogg',
                'video/ogg'                                                                 => 'ogg',
                'application/ogg'                                                           => 'ogg',
                'application/x-pkcs10'                                                      => 'p10',
                'application/pkcs10'                                                        => 'p10',
                'application/x-pkcs12'                                                      => 'p12',
                'application/x-pkcs7-signature'                                             => 'p7a',
                'application/pkcs7-mime'                                                    => 'p7c',
                'application/x-pkcs7-mime'                                                  => 'p7c',
                'application/x-pkcs7-certreqresp'                                           => 'p7r',
                'application/pkcs7-signature'                                               => 'p7s',
                'application/pdf'                                                           => 'pdf',
                'application/octet-stream'                                                  => 'pdf',
                'application/x-x509-user-cert'                                              => 'pem',
                'application/x-pem-file'                                                    => 'pem',
                'application/pgp'                                                           => 'pgp',
                'application/x-httpd-php'                                                   => 'php',
                'application/php'                                                           => 'php',
                'application/x-php'                                                         => 'php',
                'text/php'                                                                  => 'php',
                'text/x-php'                                                                => 'php',
                'application/x-httpd-php-source'                                            => 'php',
                'image/png'                                                                 => 'png',
                'image/x-png'                                                               => 'png',
                'application/powerpoint'                                                    => 'ppt',
                'application/vnd.ms-powerpoint'                                             => 'ppt',
                'application/vnd.ms-office'                                                 => 'ppt',
                'application/msword'                                                        => 'doc',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'application/x-photoshop'                                                   => 'psd',
                'image/vnd.adobe.photoshop'                                                 => 'psd',
                'audio/x-realaudio'                                                         => 'ra',
                'audio/x-pn-realaudio'                                                      => 'ram',
                'application/x-rar'                                                         => 'rar',
                'application/rar'                                                           => 'rar',
                'application/x-rar-compressed'                                              => 'rar',
                'audio/x-pn-realaudio-plugin'                                               => 'rpm',
                'application/x-pkcs7'                                                       => 'rsa',
                'text/rtf'                                                                  => 'rtf',
                'text/richtext'                                                             => 'rtx',
                'video/vnd.rn-realvideo'                                                    => 'rv',
                'application/x-stuffit'                                                     => 'sit',
                'application/smil'                                                          => 'smil',
                'text/srt'                                                                  => 'srt',
                'image/svg+xml'                                                             => 'svg',
                'application/x-shockwave-flash'                                             => 'swf',
                'application/x-tar'                                                         => 'tar',
                'application/x-gzip-compressed'                                             => 'tgz',
                'image/tiff'                                                                => 'tiff',
                'text/plain'                                                                => 'txt',
                'text/x-vcard'                                                              => 'vcf',
                'application/videolan'                                                      => 'vlc',
                'text/vtt'                                                                  => 'vtt',
                'audio/x-wav'                                                               => 'wav',
                'audio/wave'                                                                => 'wav',
                'audio/wav'                                                                 => 'wav',
                'application/wbxml'                                                         => 'wbxml',
                'video/webm'                                                                => 'webm',
                'audio/x-ms-wma'                                                            => 'wma',
                'application/wmlc'                                                          => 'wmlc',
                'video/x-ms-wmv'                                                            => 'wmv',
                'video/x-ms-asf'                                                            => 'wmv',
                'application/xhtml+xml'                                                     => 'xhtml',
                'application/excel'                                                         => 'xl',
                'application/msexcel'                                                       => 'xls',
                'application/x-msexcel'                                                     => 'xls',
                'application/x-ms-excel'                                                    => 'xls',
                'application/x-excel'                                                       => 'xls',
                'application/x-dos_ms_excel'                                                => 'xls',
                'application/xls'                                                           => 'xls',
                'application/x-xls'                                                         => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
                'application/vnd.ms-excel'                                                  => 'xlsx',
                'application/xml'                                                           => 'xml',
                'text/xml'                                                                  => 'xml',
                'text/xsl'                                                                  => 'xsl',
                'application/xspf+xml'                                                      => 'xspf',
                'application/x-compress'                                                    => 'z',
                'application/x-zip'                                                         => 'zip',
                'application/zip'                                                           => 'zip',
                'application/x-zip-compressed'                                              => 'zip',
                'application/s-compressed'                                                  => 'zip',
                'multipart/x-zip'                                                           => 'zip',
                'text/x-scriptzsh'                                                          => 'zsh',
            ];
    
            return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
        }
		
		public function get_comprobante_bacs(){
			$comprobante_bacs = get_option( $this->cbacs_id );
			$comprobante_bacs_enabled = isset($comprobante_bacs['enabled']) ? $comprobante_bacs['enabled'] : false;	
			$comprobante_bacs_accounts_display = isset($comprobante_bacs['accounts_display']) ? $comprobante_bacs['accounts_display'] : false;	
			$comprobante_bacs_required = isset($comprobante_bacs['required']) ? $comprobante_bacs['required'] : false;
			$comprobante_bacs_accounts_display_end = isset($comprobante_bacs['accounts_display_end']) ? $comprobante_bacs['accounts_display_end'] : false;	
			
			$comprobante_bacs_enabled = $comprobante_bacs_enabled > 0 ? true : false;
			$comprobante_bacs_accounts_display = $comprobante_bacs_accounts_display > 0 ? true : false;
			$comprobante_bacs_required = $comprobante_bacs_required > 0 ? true : false;
			$comprobante_bacs_accounts_display_end = $comprobante_bacs_accounts_display_end > 0 ? true : false;
			
			return [
				'enabled' => $comprobante_bacs_enabled,
				'accounts_display' => $comprobante_bacs_accounts_display,
				'required' => $comprobante_bacs_required,
				'accounts_display_end' => $comprobante_bacs_accounts_display_end,
			];
		}
        
        public function bacs_woocommerce_gateway_method_description_func($description, $id){
            if($id !== 'bacs'): return $description; endif;
            	if(is_checkout() !== true): return $description; endif;
            	ob_start();
            	$gateway    = new WC_Gateway_BACS();
            	$country    = WC()->countries->get_base_country();
                $locale     = $gateway->get_country_locale();
                $bacs_info  = is_array(get_option( 'woocommerce_bacs_accounts')) ? get_option( 'woocommerce_bacs_accounts') : [];
            	
            	$sort_code_label = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce' );
			
				$enabled = $this->get_comprobante_bacs()['enabled'];
				$accounts_display = $this->get_comprobante_bacs()['accounts_display'];
				$required = $this->get_comprobante_bacs()['required'];
            	
            	?>
            	
				<?php if($enabled !== false): ?>
            	<style type="text/css">
            		.woocommerce-bacs-bank-details{display:block;clear:both;margin:0 0 20px 0;}
            	</style>
            
            	<div class="woocommerce-bacs-bank-details">
					
            	<?php if($accounts_display !== false): ?>
					<?php
					$i = -1;
					if ( count($bacs_info) > 0 ):
					echo sprintf('<h2 class="wc-bacs-bank-details-heading">%s</h2>', __('Detalles bancarios','woocommerce') );
					foreach ( $bacs_info as $account ) :
					$i++;

					$account_name   = isset($account['account_name']) ? esc_attr( wp_unslash( $account['account_name'] ) ) : '';
					$bank_name      = isset($account['bank_name']) ? esc_attr( wp_unslash( $account['bank_name'] ) ) : '';
					$account_number = isset($account['account_number']) ? esc_attr( $account['account_number'] ) : '';
					$sort_code      = isset($account['sort_code']) ? esc_attr( $account['sort_code'] ) : '';
					$iban_code      = isset($account['iban']) ? esc_attr( $account['iban'] ) : '';
					$bic_code       = isset($account['bic']) ? esc_attr( $account['bic'] ) : '';
					?>
					<?php if($account_name !== ''): ?>	
					<h3 class="wc-bacs-bank-details-account-name"><?php echo $account_name; ?>:</h3>
					<?php endif; ?>

					<ul class="wc-bacs-bank-details order_details bacs_details">
						<?php if($bank_name !== ''): ?>
						<li class="bank_name"><?php _e('Bank'); ?>: <strong><?php echo $bank_name; ?></strong></li>
						<?php endif; ?>
						<?php if($account_number !== ''): ?>
						<li class="account_number"><?php _e('Account number'); ?>: <strong><?php echo $account_number; ?></strong></li>
						<?php endif; ?>
						<?php if($sort_code !== ''): ?>
						<li class="sort_code"><?php echo $sort_code_label; ?>: <strong><?php echo $sort_code; ?></strong></li>
						<?php endif; ?>
						<?php if($iban_code !== ''): ?>
						<li class="iban"><?php _e('IBAN'); ?>: <strong><?php echo $iban_code; ?></strong></li>
						<?php endif; ?>
						<?php if($bic_code !== ''): ?>
						<li class="bic"><?php _e('BIC'); ?>: <strong><?php echo $bic_code; ?></strong></li>
						<?php endif; ?>
					</ul>
					<?php endforeach; endif; ?>
				<?php endif; ?><fieldset class="field-comprobante-bacs form-group"><label for="input-comprobante-bacs"><?php echo __('Subir comprobante de pago','woocommerce'); ?></label><input id="input-comprobante-bacs" class="form-control" type="file" accept="image/*"/><input type="hidden" id="comprobante_bacs_img" name="comprobante_bacs[file]"/><input type="hidden" id="comprobante_bacs_type" name="comprobante_bacs[type]"/><input type="hidden" id="comprobante_bacs_b64" name="comprobante_bacs[b64]"/><?php wp_nonce_field( 'comprobante_bacs_upload', 'comprobante_bacs_upload_nonce' ); ?></fieldset>
            	
            </div>
			<?php endif; ?>
            
            <?php
            	
            	echo $description;
            	$tmp = ob_get_contents();
				$str = str_replace("\r\n",'',trim($tmp));
				$str = preg_replace("/<br\W*?\/>/", "\n", $str);
            	ob_end_clean();
            	
            	return $str;
        }
        
        public function bacs_wc_upload_file_gateway_method_description_func($order_id){
			
			$enabled = $this->get_comprobante_bacs()['enabled'];
			$accounts_display = $this->get_comprobante_bacs()['accounts_display'];
			$required = $this->get_comprobante_bacs()['required'];
			
			if($enabled !== true): return false; endif;
			
        	require_once( ABSPATH . 'wp-admin/includes/image.php' );
        	require_once( ABSPATH . 'wp-admin/includes/file.php' );
        	require_once( ABSPATH . 'wp-admin/includes/media.php' );

	        $comprobante_bacs = isset($_POST['comprobante_bacs']) ? $_POST['comprobante_bacs'] : [];
			$attach_id = '';
	
        	if(is_array($comprobante_bacs) && count($comprobante_bacs) > 0){
        	
				$file = isset($comprobante_bacs['file']) ? $comprobante_bacs['file'] : '';
				$type = isset($comprobante_bacs['type']) ? $comprobante_bacs['type'] : '';

				$b64 = isset($comprobante_bacs['b64']) ? $comprobante_bacs['b64'] : '';	

				/*if($file !== ''){
					$upload_dir  = wp_upload_dir();
					$upload_path = str_replace( '/', '', $upload_dir['path'] ) . '';

					$img             = str_replace( 'data:'.$type.';base64,', '', $b64);
					$img             = str_replace( ' ', '+', $img );
					$decoded         = base64_decode( $img );	
					$filename = $order_id .'.'. $this->mime2ext($type);	
					$hashed_filename = 'comprobante_bacs_'.md5( $order_id . microtime() ) . '_' . $filename;	

					$upload_file = file_put_contents( $upload_path . $hashed_filename, $decoded );

					$attachment = array(
						'post_mime_type' => $type,
						'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $hashed_filename ) ),
						'post_content'   => $b64,
						'post_status'    => 'inherit',
						'guid'           => $upload_dir['url'] . '/' . basename( $hashed_filename )
					);

					$attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $hashed_filename, $order_id );	
					$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
					wp_update_attachment_metadata( $attach_id, $attach_data );		

					$comprobante_bacs['attach_id'] = $attach_id;
				}*/
        		
        	update_post_meta($order_id, '_comprobante_bacs', json_encode($comprobante_bacs));
        		
        	}

        }
        
        public function comprobante_bacs_order_meta_boxes_func(){
        	global $post;
        	$order = wc_get_order( $post->ID );
        	if($order):
        		$method = $order->get_payment_method();
        	
        		if($method !== 'bacs'): return false; endif;
        
        		add_meta_box(
        			'wc-shop-order-comprobante-bacs',
        			__( 'Comprobante: '. $method ),
        			array($this,'order_meta_box_content_func'),
        			'shop_order',
        			'side',
        			'default'
        		);
        	endif;
        }
        
        public function order_meta_box_content_func( $order_id ) {
        	global $post;
        	$order = wc_get_order( $order_id );
        	$method = $order->get_payment_method();
        	
        	if($method !== 'bacs'): return false; endif;
        	
        	$comprobante_bacs = get_post_meta($post->ID, '_comprobante_bacs', true);
        	$comprobante_bacs = $comprobante_bacs !== '' ? json_decode($comprobante_bacs,true) : [];
        	$type = isset($comprobante_bacs['type']) ? $comprobante_bacs['type'] : '';
        	$file = isset($comprobante_bacs['file']) ? $comprobante_bacs['file'] : '';
			
			#print_r($comprobante_bacs);
			
			if($file !== ''):
        	
        	echo sprintf('<img src="data:%s;base64,%s" style="%s"/>',$type, $file,'max-width:100%;margin:0 auto;display:block;');
			
			else:
			
			echo '<p>No ha adjuntado comprobante de pago.</p>';
			
			endif;
        	
        }
        
        public function js_scripts_comprobante_bacs_func(){
			
			$enabled = $this->get_comprobante_bacs()['enabled'];
			$accounts_display = $this->get_comprobante_bacs()['accounts_display'];
			$required = $this->get_comprobante_bacs()['required'];
			
			if($enabled !== true): return false; endif;			
			
            if(!is_checkout()){return false;}
            ?>
            
            <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function(event) {
            jQuery(function($, undefined){
            	$(document).on("change", "#input-comprobante-bacs", function(){ 
            		var input = this;
            		var files = input.files[0] || '';
            		var ifile = $(this)[0].files[0] || '';
            		if(ifile !== ''){
            			
            			var filetype = ifile.type;
            			var filesize = ifile.size;
            			var filename = ifile.name;
            			
            			//console.log(filetype);
            			
            			//console.log(filesize);
            			
            			//console.log(filename);
            			
            			var reader = new FileReader();
            			reader.onload = function (e) {
            				var tresult = e.target.result || '';
            				const base64String = tresult.replace('data:', '').replace(/^.+,/, '');
            				$('input#comprobante_bacs_img').val(base64String);
            				$('input#comprobante_bacs_type').val(filetype);
            				$('input#comprobante_bacs_b64').val(tresult);
            			}
            		   	reader.readAsDataURL(files);
            			
            			$( document.body ).trigger( 'update_checkout' );
            			
            		}
            		
            	});
            });
            });		
            </script>
            
            <?php
        }
		
		public function comprobante_bacs_add_section_func($sections){
			$sections[$this->cbacs_id] = __( 'Comprobante BACS', 'woocommerce' );
			return $sections;
		}
		
		public function comprobante_bacs_settings_func( $settings, $current_section ) {
			global $woocommerce;
			if($current_section !== $this->cbacs_id){return $settings;}

			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			$gateway_bacs = isset($gateways['bacs']) ? $gateways['bacs'] : '';

			$gateway_bacs_enabled = !empty($gateway_bacs->enabled) ? $gateway_bacs->enabled : '';
			$gateway_bacs_title = !empty($gateway_bacs->title) ? $gateway_bacs->title : '';

			$desc_section = 'Mostrar las cuentas bancarias y un formulario de carga de imagen como comprobante de pago.';
			$desc_section .= '';
			$custom_attributes = [];
			if($gateway_bacs_enabled !== 'yes'){
				$desc_section .= "El metodo de pago: {$gateway_bacs_title} debe estar activo.";
				$custom_attributes['disabled'] = 'disabled';
			}
			
			#print_r( $this->get_comprobante_bacs() );

			$enabled = $this->get_comprobante_bacs()['enabled'];
			$accounts_display = $this->get_comprobante_bacs()['accounts_display'];
			$required = $this->get_comprobante_bacs()['required'];
			$accounts_display_end = $this->get_comprobante_bacs()['accounts_display_end'];

			$comprobante_bacs_enabled_array = [];
			if($enabled !== false){
				$comprobante_bacs_enabled_array['checked'] = 'checked';
			}

			$comprobante_bacs_accounts_display_array = [];
			if($accounts_display !== false){
				$comprobante_bacs_accounts_display_array['checked'] = 'checked';
			}	

			$comprobante_bacs_required_array = [];
			if($required !== false){
				$comprobante_bacs_required_array['checked'] = 'checked';
			}	
			
			$comprobante_bacs_accounts_display_end_array = [];
			if($accounts_display_end !== false){
				$comprobante_bacs_accounts_display_end_array['checked'] = 'checked';
			}				

			$settings_comprobante_bacs = array();

			$settings_comprobante_bacs[] = array( 
				'name' => __( 'Comprobante BACS', 'woocommerce' ), 
				'type' => 'title', 
				'desc' => $desc_section, 
				'id' => 'comprobante_bacs' 
			);

			$settings_comprobante_bacs[] = array(
				'name'     => __( 'Carga de imagen', 'woocommerce' ),
				'desc_tip' => __( 'Carga de imagen comprobante en checkout', 'woocommerce' ),
				'id'       => 'comprobante_bacs[enabled]',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => __( 'Activar/Desactivar', 'woocommerce' ),
				'custom_attributes' => array_merge($custom_attributes,$comprobante_bacs_enabled_array),
			);

			$settings_comprobante_bacs[] = array(
				'name'     => __( 'Cuentas bancarias', 'woocommerce' ),
				'desc_tip' => __( 'Carga de imagen comprobante en checkout', 'woocommerce' ),
				'id'       => 'comprobante_bacs[accounts_display]',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => __( 'Mostrar/Ocultar', 'woocommerce' ),
				'custom_attributes' => array_merge($custom_attributes,$comprobante_bacs_accounts_display_array),
			);	

			$settings_comprobante_bacs[] = array(
				'name'     => __( 'Requerir carga de imagen', 'woocommerce' ),
				'desc_tip' => __( 'Hacer obligatorio la carga de comprobante en checkout', 'woocommerce' ),
				'id'       => 'comprobante_bacs[required]',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => __( 'Requerido/Opcional', 'woocommerce' ),
				'custom_attributes' => array_merge($custom_attributes,$comprobante_bacs_required_array),
			);	
			
			$settings_comprobante_bacs[] = array(
				'name'     => __( 'Cuentas bancarias en thank-you', 'woocommerce' ),
				'desc_tip' => __( 'Ocultar cuentas al final de realizar el pedido', 'woocommerce' ),
				'id'       => 'comprobante_bacs[accounts_display_end]',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => __( 'Ocultar/Mostrar', 'woocommerce' ),
				'custom_attributes' => array_merge($custom_attributes,$comprobante_bacs_accounts_display_end_array),
			);				
			
			$settings_comprobante_bacs[] = array( 'type' => 'sectionend', 'id' => 'comprobante_bacs' );
			return $settings_comprobante_bacs;
	
		}
		
		public function validar_campo_carga_comprobante_func(){
			$enabled = $this->get_comprobante_bacs()['enabled'];
			$accounts_display = $this->get_comprobante_bacs()['accounts_display'];
			$required = $this->get_comprobante_bacs()['required'];
			$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
			
			$comprobante_bacs = isset($_POST['comprobante_bacs']) ? $_POST['comprobante_bacs'] : '';
        	$type = isset($comprobante_bacs['type']) ? $comprobante_bacs['type'] : '';
        	$file = isset($comprobante_bacs['file']) ? $comprobante_bacs['file'] : '';
			
			if($required !== false){
				if($payment_method == 'bacs' && $type == ''){

				wc_add_notice('Debe cargar una imagen del comprobante de pago por transferencia bancaria', 'error');

				}
			}
		}
		
		public function wc_check_validando_checkout_func($posted){
			$checkout = WC()->checkout;
		}
		
		public function ocultar_cuentas_bancarias_func($account_details, $order_id){
			$enabled = $this->get_comprobante_bacs()['enabled'];
			$accounts_display = $this->get_comprobante_bacs()['accounts_display'];
			$required = $this->get_comprobante_bacs()['required'];
			$accounts_display_end = $this->get_comprobante_bacs()['accounts_display_end'];
			if($accounts_display_end !== false && $enabled !== false){
				$account_details = '';
			}
			return $account_details;
		}

    }
$GLOBALS["wc_bacs_diurvan"] = Class_WC_CBACS_Diurvan::instance();	
add_action( 'plugins_loaded', [ 'Class_WC_CBACS_Diurvan', 'init' ]);
}
