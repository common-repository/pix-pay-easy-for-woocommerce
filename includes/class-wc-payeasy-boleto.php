<?php

if (!defined('ABSPATH')) {
    exit();
}

class PPEFW_Boleto_Gateway extends WC_Payment_Gateway
{
    public $instructions;
    public $testmode;
    public $api_key;
    public $order_status_pending;
    public $order_status_paid;

    public function __construct()
    {

        $this->id   = 'payeasy_boleto';
        $this->icon = apply_filters('woocommerce_payeasy_icon',  ppefw_payeasy_assets_url() . 'images/logo-boleto.png');
        $this->has_fields = false;
        $this->method_title = __('Pay Easy Boleto', 'pix-pay-easy-for-woocommerce');
        $this->method_description = __('Automatic with instant settlement', 'pix-pay-easy-for-woocommerce');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('api_key');
        $this->order_status_pending = $this->get_option('order_status_pending');
        $this->order_status_paid = $this->get_option('order_status_paid');

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_payeasy', array($this, 'webhook'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'custom_thankyou_qrcode_content'), 5, 2);
        add_action('wp_enqueue_scripts', array($this, 'custom_thankyou_qrcode_enqueue_script'));
        add_action('wp_enqueue_scripts', array($this, 'custom_thankyou_qrcode_enqueue_style'));
        add_filter('woocommerce_cancelled_order_title', array($this, 'custom_cancelled_title'), 10, 2);
    }

    public function init_form_fields()
    {

        $registerLabel = sprintf(
            /* translators: %s:  link for Registrar*/
            __('Open your account now %s', 'pix-pay-easy-for-woocommerce'),
            '<a target="_blank" href="https://view.forms.app/payeasy/onboarding-e-commerce">Registrar</a>'
        );

        $documentationLabel = sprintf(
            /* translators: %s:  link documentation */
            __(
                'Pay Easy integration %s with Woocommerce',
                'pix-pay-easy-for-woocommerce'
            ),
            '<a target="_blank" href="https://payeasy.tech/docs/">documentation</a>'
        );

        $supportLabel = sprintf(
            /* translators: %s: Email link for contacting support */
            __(
                'For support, questions or suggestions, contact us via %s',
                'pix-pay-easy-for-woocommerce'
            ),
            '<a href="mailto:contato@payeasy.tech">contato@payeasy.tech</a>'
        );

        $order_statuses = wc_get_order_statuses();
        $this->form_fields = apply_filters('woo_payeasy_fields', array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'pix-pay-easy-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable or Disable Pay Easy Payment', 'pix-pay-easy-for-woocommerce'),
                'default' => 'no',
                'description' => "<p>$registerLabel</p><p>$documentationLabel</p>",
            ),
            'title' => array(
                'title' => __('Title', 'pix-pay-easy-for-woocommerce'),
                'type' => 'text',
                'default' => __('Pay Easy Boleto', 'pix-pay-easy-for-woocommerce'),
                'desc_tip' => true,
                'description' => __('Add a new title for the Pay Easy Boleto that customers will see when they are in the checkout page.', 'pix-pay-easy-for-woocommerce')
            ),
            'description' => array(
                'title' => __('Pay Easy Boleto Description', 'pix-pay-easy-for-woocommerce'),
                'type' => 'textarea',
                'default' => 'Você será redirecionado para realizar o pagamento',
                'desc_tip' => true,
                'description' => __('Add a new description for the Pay Easy Boleto that customers will see when they are in the checkout page.', 'pix-pay-easy-for-woocommerce')
            ),
            'instructions' => array(
                'title' => __('Instructions', 'pix-pay-easy-for-woocommerce'),
                'type' => 'textarea',
                'default' => __('Abra o aplicativo do seu banco e scaneie o código de barras', 'pix-pay-easy-for-woocommerce'),
                'desc_tip' => true,
                'description' => __('Instructions that will be added to the thank you page about how they can make the payment.', 'pix-pay-easy-for-woocommerce')
            ),
            'testmode' => array(
                'title'       => __('Enable Test Mode', 'pix-pay-easy-for-woocommerce'),
                'label'       => __('Enable Test Mode', 'pix-pay-easy-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'pix-pay-easy-for-woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_api_key' => array(
                'title'       => __('Test Api Key', 'pix-pay-easy-for-woocommerce'),
                'type'        => 'text'
            ),
            'api_key' => array(
                'title'       => __('Production Api Key', 'pix-pay-easy-for-woocommerce'),
                'type'        => 'text'
            ),
            'order_status_pending' => array(
                'title' => __('Order Status for Pending Payments', 'pix-pay-easy-for-woocommerce'),
                'type' => 'select',
                'default' => 'wc-pending',
                'options' => $order_statuses,
                'desc_tip' => true,
                'description' => __('Select the order status to which the payment should be marked when it is pending.', 'pix-pay-easy-for-woocommerce')
            ),
            'order_status_paid' => array(
                'title' => __('Order Status for Paid Payments', 'pix-pay-easy-for-woocommerce'),
                'type' => 'select',
                'default' => 'wc-processing',
                'options' => $order_statuses,
                'desc_tip' => true,
                'description' => __('Select the order status to which the payment should be marked when it is considered paid.', 'pix-pay-easy-for-woocommerce')
            ),
            'support' => array(
                'title' => __('Support Information', 'pix-pay-easy-for-woocommerce'),
                'type' => 'title',
                'description' => $supportLabel,
            ),
        ));
    }

    public function process_payment($order_id)
    {
        if (!isset($_POST['payeasy_payment_boleto_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['payeasy_payment_boleto_nonce'])), 'payeasy_payment_process')) {
            wc_add_notice(__('Error: Nonce verification failed, please try again.', 'pix-pay-easy-for-woocommerce'), 'error');
            return;
        }
        $order = wc_get_order($order_id);
        $payeasy_cpf = isset($_POST['payeasy_boleto_cpf']) ? sanitize_text_field($_POST['payeasy_boleto_cpf']) : '';

        if (empty($payeasy_cpf)) {
            wc_add_notice(__('CPF/CPNJ is required.', 'pix-pay-easy-for-woocommerce'), 'error');
            return;
        }

        if ($order->get_total() == 0) {
            $order->payment_complete();
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        if (WC_Payeasy::validateCpf($payeasy_cpf) === false) {
            wc_add_notice(__('Not a valid CPF NO', 'pix-pay-easy-for-woocommerce'), 'error');
            return;
        }

        $base_url = $this->testmode ? 'https://api-sandbox.payeasy.tech' : 'https://api.payeasy.tech';

        // prepare request body data
        $address_1 = $order->get_billing_address_1();
        $address_2 = $order->get_billing_address_2();
        $city = $order->get_billing_city();
        $state = $order->get_billing_state();
        $postcode = $order->get_billing_postcode();
        $country = $order->get_billing_country();
        $address_parts = array_filter(array($address_1, $address_2, $city, $state, $postcode, $country));
        $full_address = implode(', ', $address_parts);

        $args = array(
            'body' => wp_json_encode(array(
                "amount" => $order->get_total(),
                "country" => "BR",
                "shop_order_id" => $order->get_id(),
                "full_Name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                "email" => $order->get_billing_email(),
                "full_address" => $full_address,
                "id" => $payeasy_cpf,
                "ip_address" => $order->get_customer_ip_address(),
                "ecommerce" => "woocommerce"
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );

        // call payeasy api
        $response = wp_remote_post($base_url . '/add/deposit-boleto?api_key=' . $this->api_key, $args);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            if ($body['status'] === 'created') {
                $barcode = $body['barcode'];
                $converted_amount = $body['converted_amount'];
                $order_no = $body['order_no'];
                $payment_pdf= $body['payment_pdf'];

                $status = $this->order_status_pending !== "" ? $this->order_status_pending : "wc-pending";
                $order->set_status($status);
                
                $order->update_meta_data('payeasy_boleto_barcode', $barcode);
                $order->update_meta_data('payeasy_pix_amount', $converted_amount);
                $order->update_meta_data('payeasy_order_no', $order_no);
                $order->update_meta_data('payeasy_boleto_pdf', $payment_pdf);
                
                $order->save();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                wc_add_notice(__('The BOLETO was not generated. Contact with the payment provider.', 'pix-pay-easy-for-woocommerce'), 'error');
                return;
            }
        } else {
            wc_add_notice(__('Enable to connect with payeasy server.', 'pix-pay-easy-for-woocommerce'), 'error');
            return;
        }
    }

    // custom input field in checkout page
    public function payment_fields()
    {
?>
        <script>
            jQuery(document).ready(function($) {
                const formatCPF = (cpf) => {
                    let cpfInput = document.getElementById("payeasy_boleto_cpf");
                    let value = cpfInput.value.replace(/\D/g, "");
                    if (value.length > 11) {
                        value = value.substr(0, 11);
                    }
                    if (value.length <= 3) {
                        cpfInput.value = value;
                    } else if (value.length <= 6) {
                        cpfInput.value = value.replace(/^(\d{3})(\d{0,3})/, "$1.$2");
                    } else if (value.length <= 9) {
                        cpfInput.value = value.replace(/^(\d{3})(\d{3})(\d{0,3})/, "$1.$2.$3");
                    } else {
                        cpfInput.value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})/, "$1.$2.$3-$4");
                    }
                }

                var billingCpfValue = $("#billing_cpf").val();
                if (billingCpfValue?.length > 0) {
                    $("#payeasy_boleto_cpf").val(billingCpfValue);
                }
                $("#payeasy_boleto_cpf").on("input", function() {
                    formatCPF()
                });
                $("#billing_cpf").on("input", function() {
                    var value = $("#billing_cpf").val();
                    $("#payeasy_boleto_cpf").val(value);
                });
            });
        </script>
<?php

        wp_nonce_field('payeasy_payment_process', 'payeasy_payment_boleto_nonce');

        if ($this->description) {
            if ($this->testmode) {
                $this->description  = trim($this->description);
            }
            echo wp_kses_post($this->description);
        }
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

        do_action('woocommerce_credit_card_form_start', $this->id);

        echo '
        <div class="form-row form-row-wide"><label>CPF / CNPJ<span class="required">*</span></label>
            <input id="payeasy_boleto_cpf" type="text" placeholder="' . esc_attr(__('Enter your CPF / CNPJ ', 'pix-pay-easy-for-woocommerce')) . '" name="payeasy_boleto_cpf">
            </div>
            <br>
            <div class="form-row form-row-wide">
            <input id="payeasy_boleto_terms" type="checkbox" name="payeasy_terms" checked>
            ' . esc_attr(__('I declare that I have read and accept the', 'pix-pay-easy-for-woocommerce')) . ' <a href="https://checkout.payeasy.tech/Terms_BR.pdf" target="_blank">' . esc_attr(__('Terms & Conditions & Privacy Policy', 'pix-pay-easy-for-woocommerce')) . '</a> ' . esc_attr(__('for this purchase', 'pix-pay-easy-for-woocommerce')) .
            '.<div class="clear"></div>';

        do_action('woocommerce_credit_card_form_end', $this->id);
        echo '<div class="clear"></div></fieldset>';
    }

    //cutsom thank you page to show payment info
    public function custom_thankyou_qrcode_content($str, $order_id)
    {
        $order = wc_get_order($order_id);
        $order_status = $order->get_status();
        $order_status_pending = $this->order_status_pending !== "" ? $this->order_status_pending : "wc-pending";
        $order_status_paid = $this->order_status_paid !== "" ? $this->order_status_paid : "wc-processing";

        if ($order && "wc-" . $order_status === $order_status_pending) {
            $payeasy_boleto_barcode = $order->get_meta('payeasy_boleto_barcode');
            $payeasy_pix_amount = $order->get_meta('payeasy_pix_amount');
            $payeasy_boleto_pdf = $order->get_meta('payeasy_boleto_pdf');

            if ($payeasy_boleto_barcode) {
                echo esc_attr(__('Please complete your payment', 'pix-pay-easy-for-woocommerce')) . '<div id="payeasy-send-payment">
                            <div class="payeasy-status">
                                <span><strong>' . esc_attr(__('BOLETO Payment', 'pix-pay-easy-for-woocommerce')) . '</strong></span>
                                <span id="blinkText" class="message">Aguardando pagamento</span>
                            </div>
                            
                            <div class="payeasy-address">
                                <span>' . esc_attr(__('Bar Code', 'pix-pay-easy-for-woocommerce')) . '</span>
                                <span><strong>' .  esc_html($payeasy_boleto_barcode) . '</strong></span>
                                <div class="payeasy-copy">
                                    <a href="#" data-copy="' . esc_html($payeasy_boleto_barcode) . '"></a>
                                    <span class="payeasy-copy__tooltip">' . esc_attr(__('Copy code', 'pix-pay-easy-for-woocommerce')) . '</span>
                                </div>
                            </div>
                            <div class="payeasy-total">
                                <span>' . esc_attr(__('Amount', 'pix-pay-easy-for-woocommerce')) . '</span>
                                <span><strong>R$ ' . number_format($payeasy_pix_amount, 2, ',', '.') . '</strong></span>
                                <div class="payeasy-copy">
                                    <a href="#" data-copy="' . esc_html($payeasy_pix_amount) . '">
                                    </a>
                                    <span class="payeasy-copy__tooltip">' . esc_attr(__('Copy amount', 'pix-pay-easy-for-woocommerce')) . '</span>
                                </div>
                            </div>
                            <div class="payeasy-qr">
                                    <a href="' . esc_html($payeasy_boleto_pdf) . '" download="boleto.pdf" class="download-button" target="_blank">
                                        Download PDF
                                    </a>
                                <span>' . esc_html($this->instructions) . '</span>
                            </div>
                    </div> <br>';
            }
        } else if ('wc-' . $order_status === $order_status_paid) {
            echo  esc_attr(__("We have received your payment, Your order is in processing", 'pix-pay-easy-for-woocommerce'));
        } else if ($order_status === 'cancelled') {
            echo  esc_attr(__("Since we haven't received your payment, Your order has been cancelled", 'pix-pay-easy-for-woocommerce'));
        } else if ($order_status === 'refunded') {
            echo  esc_attr(__("Your order has been refunded", 'pix-pay-easy-for-woocommerce'));
        } else {
            echo esc_html($str);
        }
    }
    /*
    * Webhook handler
    */
    public function webhook()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $shop_order_id = isset($_GET['shop_order_id']) ? sanitize_text_field(wp_unslash($_GET['shop_order_id'])) : '';
        $api_key = isset($_GET['api_key']) ? sanitize_text_field(wp_unslash($_GET['api_key'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $response = array();
        if ($shop_order_id) {
            $order = wc_get_order($shop_order_id);
            if($order){
                if($api_key !== $this->api_key){
                    $response['success'] = false;
                    $response['message'] = 'Not a valid request.';
                }else{
                    $order_status = $order->get_status();

                    //completed or processing
                    $order_status_pending = $this->order_status_pending !== "" ? $this->order_status_pending : "wc-pending";
                    $order_status_paid = $this->order_status_paid !== "" ? $this->order_status_paid : "wc-processing";
        
                    if ($order && ("wc-" . $order_status === $order_status_pending)) {
        
                        $order->set_status($order_status_paid);
                        $order->payment_complete();
                        $order->save();
        
                        $response['success'] = true;
                        $response['message'] = 'Webhook processing successful.';
                    } else {
                        $response['success'] = false;
                        $response['message'] = 'Order is already marked as completed or processing.';
                    }
                }
            }else{
                $response['success'] = false;
                $response['message'] = 'order not found.';
            }
            
        } else {
            $response['success'] = false;
            $response['message'] = 'Missing or invalid shop_order_id parameter.';
        }
        header('Content-Type: application/json');
        echo wp_json_encode($response);
        http_response_code(200);
        exit;
    }

    // insert script for thank you page
    public function custom_thankyou_qrcode_enqueue_script()
    {
        if (is_order_received_page()) {
            $order_id = absint(get_query_var('order-received'));
            $order = wc_get_order($order_id);
            $order_status = $order->get_status();

            wp_enqueue_script('custom-qrcode-script', ppefw_payeasy_assets_url() . 'js/script.js', array(), '1.0', true);
            $ajax_nonce = wp_create_nonce('my-ajax-nonce');

            $order_status_pending = $this->order_status_pending !== "" ? $this->order_status_pending : "wc-pending";
            $order_status_paid = $this->order_status_paid !== "" ? $this->order_status_paid : "wc-processing";
            $payeasy_boleto_barcode = $order->get_meta('payeasy_boleto_barcode');
            wp_localize_script('custom-qrcode-script', 'payeasy_params', array(
                'payeasy_ajax_check_nonce' => $ajax_nonce,
                'order_id' => $order_id,
                'status' => "wc-" . $order_status,
                'order_status_pending' => $order_status_pending,
                'order_status_paid' => $order_status_paid,
                'barcode' => $payeasy_boleto_barcode

            ));
        }
    }


    // insert style for thank you page
    public function custom_thankyou_qrcode_enqueue_style()
    {
        if (is_order_received_page()) {
            wp_enqueue_style('custom-qrcode-style', ppefw_payeasy_assets_url() . 'css/style.css', array(), '1.0.1', 'all');
        }
    }


    // custom order cancelled titpe
    public function custom_cancelled_title($title, $order)
    {
        if ($order) {
            $title = __('Order has been cancelled', 'pix-pay-easy-for-woocommerce');
        }
        return $title;
    }
}
