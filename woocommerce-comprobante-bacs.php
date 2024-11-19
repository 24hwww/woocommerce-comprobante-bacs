<?php
/**
 * Plugin Name:       WooCommerce Comprobante BACS
 * Plugin URI:        https://github.com/24hwww/woocommerce-comprobante-bacs/
 * Description:       Attach an image as proof of payment for the bank transfer payment method.
 * Version:           1.10.1
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            24hwww
 * Author URI:        https://github.com/24hwww/
 * Text Domain:       woocommerce-comprobante-bacs
 */

defined( 'ABSPATH' ) || exit;
define('WC_CBACS_BASE', plugin_basename( __FILE__ ));
define('WC_CBACS_BASE_PATH', dirname(__FILE__));
/* Init Class */

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!class_exists('WC_CBACS')) {   	

    class WC_CBACS{

        private static $_instance = null;
		public $id;
        public $cbacs_id = '';
        public $option_group = '';
        public $campos_checkout;
        
        public static function instance() {
            $instance = is_null( self::$_instance ) ? new self() : self::$_instance;
        	return $instance;
        }
        
        public function __construct() {
			$this->id = "bacs";
            $this->cbacs_id = 'comprobante_bacs';
            $this->option_group = 'wc_cbacs_settings';
            $this->campos_checkout = [
                'billing_comprobante_bacs_imagen_formato' => 'Formato comprobante BACS',
                'billing_comprobante_bacs_imagen_base' => 'Imagen comprobante BACS'
            ];

        }

        public static function init() {
            $instance = self::instance();
            // Declare that this plugin supports WooCommerce HPOS.
            add_action('before_woocommerce_init', [$instance, 'supports_woocommerce_hpos_fn']);
            add_action('admin_init', [$instance, 'need_to_have_woocommerce_active_fn']);

            //Frontend
            add_filter('woocommerce_checkout_fields', [$instance, 'add_custom_woocommerce_billing_fields_fn']);
            add_filter('woocommerce_gateway_description',[$instance,'add_file_woocommerce_gateway_method_bacs_description_func'],10,2);
            add_action('wp_footer', [$instance, 'js_scripts_comprobante_bacs_func'], 100 );
            add_action('woocommerce_checkout_update_order_meta', [$instance,'save_custom_woocommerce_billing_fields_fn'], 10, 3);

            //Backend
            add_action( 'admin_notices', [$instance, 'comprobante_bacs_admin_notice_func']);
            add_action( 'add_meta_boxes', [$instance, 'comprobante_bacs_order_meta_boxes_func'], 10, 2 );
            add_action( 'woocommerce_update_order', [$instance, 'save_order_meta_box_content_func'], 10, 2 );
            add_action('admin_notices', [$instance, 'notificar_si_num_operacion_duplicado_func']);
            
            add_action( 'manage_shop_order_posts_custom_column', [$instance, 'display_info_bacs_in_table_column_func'], 25, 2 );
            add_action( 'manage_woocommerce_page_wc-orders_custom_column', [$instance, 'display_info_bacs_in_table_column_func'], 25, 2 );
            add_filter('woocommerce_settings_api_form_fields_bacs', [$instance, 'add_comprobante_bacs_setting_field_func']);
            add_action('woocommerce_update_options_bacs', [$instance, 'save_comprobante_bacs_setting_field_func']);
            
            

        }

        /* General */

		public function bacs(){
			global $woocommerce;
			$installed_payment_methods = WC()->payment_gateways()->payment_gateways();
			$bacs = isset($installed_payment_methods[$this->id]) ? $installed_payment_methods[$this->id] : '';
			$data_bacs = !empty($bacs->settings) ? $bacs->settings : [];
			return (object)$data_bacs;
		}

		public function bacs_activo(){
			global $woocommerce;
			$gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
			$gateway_bacs = isset($gateways[$this->id]) ? $gateways[$this->id] : '';
			$gateway_bacs_enabled = !empty($gateway_bacs->enabled) ? $gateway_bacs->enabled : false;
			return $gateway_bacs_enabled == 'yes' ? true : false;
		}        

        public function supports_woocommerce_hpos_fn(){
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
            function is_hpos() {
                return OrderUtil::custom_orders_table_usage_is_enabled();
            }    
        }

        public function need_to_have_woocommerce_active_fn(){
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            if ( !class_exists( 'WooCommerce' ) ):
                deactivate_plugins( WC_CBACS_BASE );
                if ( isset( $_GET['activate'] ) ){
                unset( $_GET['activate'] );
                add_action( 'admin_notices', function(){
                    $class = 'notice notice-error';
                    $message = __( 'No se puede activar el plugin, debe estar activado el WooCommerce.', 'default' );
                    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
                });
                return;
                }
            endif;
        }

        public function compra_realizada_con_bacs($order_id=''){
            $compra_realizada_con_bacs = false;
            $order = wc_get_order( $order_id );
            if($order){
                $method = $order->get_payment_method();
                $compra_realizada_con_bacs = $method == 'bacs' ? true : false;
            }

            return $compra_realizada_con_bacs;
        }        

        public function check_base64_image($data, $valid_mime) {
            $output = false;
            try{
                $array= is_array(getimagesize("data:{$valid_mime};base64, {$data}")) ? getimagesize("data:{$valid_mime};base64, {$data}") : [];
                $mime = isset($array['mime']) ? $array['mime'] : '';
                $e=explode("/",$mime);
                if(isset($e[0]) && $e[0] =="image"){
                $output = true;
                }
            }catch (Exception $e){
                $output = false;
            }
            return $output;
        }        

        public function numero_de_operacion_bacs_existe($order_id,$numero_operacion){
            if($numero_operacion == ''): return false; endif;
                $orders = wc_get_orders( array(
                    'orderby'   => 'date',
                    'order'     => 'DESC',
                    'exclude' => array( $order_id ),
                    'return' => 'ids',
                    'meta_query' => array(
                        array(
                            'key' => 'comprobante_bacs_num_operacion',
                            'value' => $numero_operacion,
                            'compare' => '=',
                        )
                    )
                ));
            $validado =  is_array($orders) && count($orders) > 0 ? true : false;
            return $validado;
        }        

        public function activar_carga_imagen(){
            $bacs = $this->bacs();
            $activar_carga_imagen = !empty($bacs->enabled_upload_comprobante) ? $bacs->enabled_upload_comprobante : 'no';
            $activar_carga_imagen = $activar_carga_imagen !== 'yes' ? false : true;
            return $activar_carga_imagen;
        }

        /* Frontend */

        public function add_custom_woocommerce_billing_fields_fn($fields){

            if($this->bacs_activo() !== true): return $fields; endif;

            if(is_array($this->campos_checkout) && count($this->campos_checkout) > 0){
                foreach($this->campos_checkout as $k => $v){
                    $fields['billing'][$k] = array(
                        'label' => '',
                        'placeholder' => _x('', 'placeholder', 'woocommerce'),
                        'required' => false,
                        'clear' => true,
                        'autocomplete' => false,
                        'type' => 'hidden',
                        'custom_attributes' => array('readonly'=> true),
                        'priority' => 999,
                        'class' => array('')
                    );
                }
            }      
        
            return $fields;
        }

        public function add_file_woocommerce_gateway_method_bacs_description_func($description, $id){
            if($id !== 'bacs'): return $description; endif;
            if(is_checkout() !== true): return $description; endif;
            if($this->bacs_activo() !== true): return $description; endif;
            if($this->activar_carga_imagen() !== true): return $description; endif;

            ob_start();
            ?>

            <fieldset class="field-comprobante-bacs form-group"><label for="input_file_comprobante_bacs"><strong><?php echo __('Agregar comprobante de pago','woocommerce'); ?></strong><br/><input id="input_file_comprobante_bacs" name="billing_comprobante_bacs_file" class="form-control" type="file" accept="image/*"/><figure style="display:block;clear:both;max-width:300px;"><img id="comprobante_bacs_preview" style="max-width: 100%;max-height: inherit;margin:10px 0;padding: 0;"/></figure></label></fieldset>

            <?php
            echo $description;
            $tmp = ob_get_contents();
            $html = str_replace("\r\n",'',trim($tmp));
            $html = preg_replace("/<br\W*?\/>/", "\n", $html);
            ob_end_clean();
            
            return $html;
        }

        public function js_scripts_comprobante_bacs_func(){
            if(is_checkout() !== true): return false; endif;
            if($this->bacs_activo() !== true): return false; endif;
            ?>

            <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function(event) {
            jQuery(function($, undefined){

                const comprobante_bacs_imagen_base64 = $('[name="billing_comprobante_bacs_imagen_base"]');
                const comprobante_bacs_imagen_formato = $('[name="billing_comprobante_bacs_imagen_formato"]');
                const comprobante_bacs_file = $('input[name="billing_comprobante_bacs_file"]');
                comprobante_bacs_imagen_base64.val('');
                comprobante_bacs_imagen_formato.val('');
                comprobante_bacs_file.val('');
                
                $(document).on("change", "input#input_file_comprobante_bacs", function(){ 
                    const image_preview = $('img#comprobante_bacs_preview');

                    var input = this;
            		var files = input.files[0] || '';
                    var archivo = $(this)[0].files[0] || '';
                    if(archivo == ''){return false;}

                    var filetype = archivo.type;
                    var filesize = archivo.size;
                    var filename = archivo.name;
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var data = e.target.result || '';
                        var base64String = data.replace('data:', '').replace(/^.+,/, '');

                        image_preview.attr('src',data);
                        comprobante_bacs_imagen_formato.val(filetype);
                        comprobante_bacs_imagen_base64.val(base64String);
                        
                    }
                    reader.readAsDataURL(files);
                    
                    $( document.body ).trigger( 'update_checkout' );                    
                });

            });
            });		
            </script>            

            <?php
        }

        public function save_custom_woocommerce_billing_fields_fn($order_id){
            if($this->bacs_activo() !== true): return false; endif;
            $billing_comprobante_bacs_imagen_base = isset($_POST['billing_comprobante_bacs_imagen_base']) ? $_POST['billing_comprobante_bacs_imagen_base'] : '';
            update_post_meta($order_id, '_billing_comprobante_bacs_imagen_base', json_encode($billing_comprobante_bacs_imagen_base));
        }

        /* Backend */

		public function comprobante_bacs_admin_notice_func(){
			global $pagenow;
			$screen = get_current_screen();
			$get_page = !empty($screen->id) ? esc_attr($screen->id) : null;
			$get_tab = isset($_GET['tab']) ? esc_attr($_GET['tab']) : null;
			$get_section = isset($_GET['section']) ? esc_attr($_GET['section']) : null;

			if($get_page !== 'woocommerce_page_wc-settings'){return false;}
			if($get_tab !== 'checkout'){return false;}
			if($get_section !== $this->cbacs_id){return false;}
			if($this->bacs_activo() !== false){return false;}

			$bacs = $this->bacs();
			$bacs_title = !empty($bacs->title) ? $bacs->title : '';

			$class = 'notice notice-error';
			$message = sprintf("%s: <b>%s</b>", __( 'Tiene que activar el metodo de pago', 'default' ),$bacs_title);
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message);

		}        

        public function comprobante_bacs_order_meta_boxes_func($post_type, $post){
            if ( $post instanceof WC_Order ) {
                $order_id = $post->get_id();
            }else{
                $order_id = $post->ID;
            }

            if(!$this->compra_realizada_con_bacs($order_id)){
                return false;
            }

            if ( is_hpos() ) {
                add_meta_box( 'comprobante_bacs_meta_box', 'Comprobante BACS (Transferencia bancaria)', array($this,'order_meta_box_content_func'), 'woocommerce_page_wc-orders', 'side', 'default' );
            }else {
                add_meta_box( 'comprobante_bacs_meta_box', 'Comprobante BACS (Transferencia bancaria)', array($this,'order_meta_box_content_func'), 'shop_order', 'side', 'default' );
            }

        }
        
        public function order_meta_box_content_func( $post ) {

            wp_nonce_field( plugin_basename(__FILE__), 'seguridad_info_nonce' );

            $order_id = is_hpos() ? $post->get_id() : $post->ID;
            $order = wc_get_order($order_id);
            $detalles_cuentas_bancarias  = is_array(get_option( 'woocommerce_bacs_accounts')) ? get_option( 'woocommerce_bacs_accounts') : [];
            $metodo_de_pago_order = wc_get_payment_gateway_by_order( $order );

            if(!$this->compra_realizada_con_bacs($order_id)){
                return false;
            }

            /* Image */

        if($this->activar_carga_imagen() !== false){

            $imagen_comprobante = [];
            if(is_array($this->campos_checkout) && count($this->campos_checkout) > 0){
                foreach($this->campos_checkout as $k => $v){
                    $post_meta = str_replace('"','', get_post_meta($order_id, "_{$k}",true));
                    $imagen_comprobante[] = $post_meta;
                }
            }
            $imagen_comprobante = array_filter($imagen_comprobante);
            if(is_array($imagen_comprobante) && count($imagen_comprobante) == 2){

                if($this->check_base64_image($imagen_comprobante[1], $imagen_comprobante[0])){

                $img_src = sprintf("data:%s", implode(';base64,',$imagen_comprobante));
                add_thickbox();   
                ?>

                <div class="wp-core-ui">
                    <div class="attachment">
                        <div class="thumbnail">
                            <div class="centered">
                                <?php
                                echo sprintf("<div id='image' style='display:none;'><img src='%s' style='%s'></div>",$img_src, 'width:100%'); 
                                ?>
                                <a href="#TB_inline?&width=700&height=auto&inlineId=image" class="thickbox" style="padding: 8px;border: 1px solid var(--wp-admin-theme-color,#2271b1);#2271b1;display: block;margin: 15px 0 0 0;">
                                    <img src="<?php echo $img_src; ?>" draggable="false" width="300" height="auto" style="width:100%;max-width:300px;display:block;object-fit: contain;" alt="Comprobante BACS">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php   
                }else{
                    echo 'Imagen comprobante con error.';
                }
            }else{
                echo 'Imagen comprobante no encontrada.';
            }

        }

            /* Fields */

            if($order){

                $comprobante_bacs_banco = $order->get_meta( 'comprobante_bacs_banco', true );
                $comprobante_bacs_num_operacion = $order->get_meta( 'comprobante_bacs_num_operacion', true );            

                $nombres_bancos = array_column($detalles_cuentas_bancarias,'account_name');
                $nombres_bancos = array_merge(['' => '---'],array_combine($nombres_bancos,$nombres_bancos));

                woocommerce_form_field('comprobante_bacs_banco', 
                    array(
                        'type'        => 'select',
                        'required'    => false,
                        'label'       => 'Banco',
                        'description' => '',
                        'options'     => $nombres_bancos
                    ),
                    $comprobante_bacs_banco
                );

                woocommerce_form_field('comprobante_bacs_num_operacion', 
                    array(
                        'type'        => 'text',
                        'required'    => false,
                        'label'       => 'Número de operación',
                        'description' => '',
                    ),
                    $comprobante_bacs_num_operacion
                );                

            }

        }        

        public function save_order_meta_box_content_func($order_id, $order){
            $comprobante_bacs_banco = isset($_POST['comprobante_bacs_banco']) ? esc_attr($_POST['comprobante_bacs_banco']) : [];
            $comprobante_bacs_num_operacion = isset($_POST['comprobante_bacs_num_operacion']) ? intval($_POST['comprobante_bacs_num_operacion']) : [];

            $order->update_meta_data( 'comprobante_bacs_banco', $comprobante_bacs_banco );
            $order->update_meta_data( 'comprobante_bacs_num_operacion', $comprobante_bacs_num_operacion );
            
        }

        public function notificar_si_num_operacion_duplicado_func(){
            global $post;
            $action = isset($_REQUEST['action']) ? esc_attr($_REQUEST['action']) : null;
            if($action !== 'edit'): return false; endif;

            if($post){
                $order_id = intval($post->ID);
            }else{
                $order_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : false;
            }

            $order = wc_get_order($order_id);
            if ($order) {

                $_comprobante_bacs_numero_operacion = $order->get_meta( 'comprobante_bacs_num_operacion', true );

                if($this->numero_de_operacion_bacs_existe($order_id,$_comprobante_bacs_numero_operacion) !== true){ return false; }

                    echo '<div class="notice notice-error"><p>';
                    echo __("Ya existe una orden con el número de operación: <strong>{$_comprobante_bacs_numero_operacion}</strong>, edite el campo.", 'woocommerce');
                    echo '</p></div>';                      

            }
        }
        
        public function display_info_bacs_in_table_column_func($column_name, $post){
            if ( $post instanceof WC_Order ) {
                $order_id = $post->get_id();
            }else{
                $order_id = $post;
            }

            if(!$this->compra_realizada_con_bacs($order_id)): return false; endif;
            if($column_name !== 'billing_address'){ return false; }

            $html_comprobante_bacs = '';
            
            
                $order = wc_get_order($order_id);

                $_comprobante_bacs_banco = $order->get_meta( 'comprobante_bacs_banco', true );
                $_comprobante_bacs_numero_operacion = $order->get_meta( 'comprobante_bacs_num_operacion', true );

                $banco = $_comprobante_bacs_banco !== '' ? $_comprobante_bacs_banco : '—';
                $numero_operacion = $_comprobante_bacs_numero_operacion !== '' ? $_comprobante_bacs_numero_operacion : '—';

                    $html_comprobante_bacs .= '<ul class="description">';
                    $html_comprobante_bacs .= sprintf('<li><strong>Banco:</strong> %1$s</li>',$banco);
                    $html_comprobante_bacs .= sprintf('<li><strong>Número Operación:</strong> %1$s</li>',$numero_operacion);
                    $html_comprobante_bacs .= '</ul>';

                echo $html_comprobante_bacs;
        }

        public function add_comprobante_bacs_setting_field_func($fields){
            $fields['enabled_upload_comprobante'] = array(
                'title'       => __('Activar carga de imagen comprobante en el checkout', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Mostrar campo de carga de imagen debajo de los datos bancarios en el checkout.', 'woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            );
        
            return $fields;
        }

        public function save_comprobante_bacs_setting_field_func(){
            woocommerce_update_option('woocommerce_bacs_enabled_upload_comprobante');
        }

    }
    $GLOBALS["wc_bacs"] = WC_CBACS::instance();	
    add_action( 'plugins_loaded', [ 'WC_CBACS', 'init' ]);
    }