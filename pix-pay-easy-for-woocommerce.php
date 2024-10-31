<?php

/**
 *
 * @link              https://payeasy.tech
 * @since             1.0.0
 * @package           PIX Pay Easy for WooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:       PIX Pay Easy for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/pix-pay-easy-for-woocommerce
 * Description:       Automático com liquidação instantânea.
 * Version:           1.0.1
 * Author:            payeasy
 * Author URI:        https://payeasy.tech
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pix-pay-easy-for-woocommerce
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to: 8.2
 */

if (!defined('ABSPATH')) {
    exit();
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;
add_action('plugins_loaded', 'payeasy_payment_init');

function payeasy_payment_init()
{
    WC_Payeasy::get_instance();
}


class WC_Payeasy
{
    protected static $instance = null;

    private function __construct()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        $this->includes();

        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_ajax_payeasycheck', [$this, 'payeasy_check_order_status']);
        add_action('wp_ajax_nopriv_payeasycheck', [$this, 'payeasy_check_order_status']);
        add_filter('woocommerce_payment_gateways', [$this, 'add_to_woo_payeasy_gateway']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        add_action('admin_menu',  [$this, 'add_payeasy_menu']);

        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }


    private function includes()
    {
        include_once dirname(__FILE__) . '/includes/class-wc-payeasy-pix.php';
        include_once dirname(__FILE__) . '/includes/class-wc-payeasy-boleto.php'; 
    }

    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // check order status through api.
    public function payeasy_check_order_status()
    {
        check_ajax_referer('my-ajax-nonce', '_ajax_nonce');
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        if ($order) {
            $order_status = $order->get_status();
            echo esc_html("wc-" . $order_status);
        } else {
            echo 'error';
        }
        wp_die();
    }

    public function add_to_woo_payeasy_gateway($gateways)
    {
        $gateways[] = 'PPEFW_Payeasy_Gateway';
        $gateways[] = 'PPEFW_Boleto_Gateway';
        return $gateways;
    }

    public function add_settings_link($links)
    {
        $sign_up_link = '<a href="https://view.forms.app/payeasy/onboarding-e-commerce" target="_blank">' . __('Sign up') . '</a>';
        array_unshift($links, $sign_up_link);

        $docs_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payeasy') . '">' . __('Documentation') . '</a>';
        array_unshift($links, $docs_link);

        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payeasy') . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_payeasy_menu()
    {
        add_menu_page(
            'Boleto por Pay Easy',
            'Boleto por Pay Easy',
            'manage_options',
            'payeasy-boleto-settings',
            [$this, 'redirect_to_boleto_woocommerce_settings'],
            ppefw_payeasy_assets_url() . 'images/boleto.svg', // Icon
            76
        );

        add_menu_page(
            'PIX por Pay Easy',
            'PIX por Pay Easy',
            'manage_options',
            'payeasy-pix-settings',
            [$this, 'redirect_to_woocommerce_settings'],
            'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNi45MiAyNi45MiI+PHBhdGggZD0iTTIzLjM1LDIzLjM5YTMuOTMsMy45MywwLDAsMS0yLjgtMS4xNmwtNC00YS43NS43NSwwLDAsMC0xLjA2LDBMMTEuNCwyMi4yNWEzLjk0LDMuOTQsMCwwLDEtMi43OSwxLjE2aC0uOGw1LjEyLDUuMTFhNC4wOCw0LjA4LDAsMCwwLDUuNzgsMGw1LjEzLTUuMTNaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMi4zNiAtMi44KSIgc3R5bGU9ImZpbGw6bm9uZSIvPjxwYXRoIGQ9Ik04LjYxLDkuMTFhMy45LDMuOSwwLDAsMSwyLjc5LDEuMTZsNC4wNiw0LjA1YS43NS43NSwwLDAsMCwxLjA2LDBsNC00YTQsNCwwLDAsMSwyLjgtMS4xNWguNDlMMTguNzEsNGE0LjA4LDQuMDgsMCwwLDAtNS43OCwwTDcuODEsOS4xMVoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0yLjM2IC0yLjgpIiBzdHlsZT0iZmlsbDpub25lIi8+PHBhdGggZD0iTTI4LjA4LDEzLjM3LDI1LDEwLjI3YS41NC41NCwwLDAsMS0uMjIsMEgyMy4zNWEyLjgyLDIuODIsMCwwLDAtMiwuODFsLTQsNGExLjk0LDEuOTQsMCwwLDEtMS4zNy41NywxLjkxLDEuOTEsMCwwLDEtMS4zNy0uNTdsLTQuMDYtNC4wNWEyLjc0LDIuNzQsMCwwLDAtMi0uODFINi44OGEuNjUuNjUsMCwwLDEtLjIxLDBMMy41NiwxMy4zN2E0LjA4LDQuMDgsMCwwLDAsMCw1Ljc4bDMuMTEsMy4xMWEuNjUuNjUsMCwwLDEsLjIxLDBIOC42MWEyLjc4LDIuNzgsMCwwLDAsMi0uODFsNC4wNi00LjA1YTIsMiwwLDAsMSwyLjc0LDBsNCw0YTIuNzgsMi43OCwwLDAsMCwyLC44MWgxLjQxYS41NC41NCwwLDAsMSwuMjIuMDVsMy4xLTMuMWE0LjEsNC4xLDAsMCwwLDAtNS43OCIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTIuMzYgLTIuOCkiIHN0eWxlPSJmaWxsOm5vbmUiLz48L3N2Zz4=', // Icon
            75
        );
    }

    public function redirect_to_woocommerce_settings()
    {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payeasy'));
        exit;
    }
    public function redirect_to_boleto_woocommerce_settings()
    {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=payeasy_boleto'));
        exit;
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('pix-pay-easy-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    // cpf validation
    public static function validateCpf($valorLimpo)
    {
        $strCPF = preg_replace('/[^\d]/', '', strval($valorLimpo));
        $Soma = 0;
        $Resto = 0;

        if (
            $strCPF === "00000000000" ||
            $strCPF === "11111111111" ||
            $strCPF === "22222222222" ||
            $strCPF === "33333333333" ||
            $strCPF === "44444444444" ||
            $strCPF === "55555555555" ||
            $strCPF === "66666666666" ||
            $strCPF === "77777777777" ||
            $strCPF === "88888888888" ||
            $strCPF === "99999999999"
        ) {
            return false;
        }

        for ($i = 1; $i <= 9; $i++) {
            $Soma += intval(substr($strCPF, $i - 1, 1)) * (11 - $i);
        }
        $Resto = ($Soma * 10) % 11;

        if ($Resto === 10 || $Resto === 11) {
            $Resto = 0;
        }
        if ($Resto !== intval(substr($strCPF, 9, 1))) {
            return false;
        }

        $Soma = 0;
        for ($i = 1; $i <= 10; $i++) {
            $Soma += intval(substr($strCPF, $i - 1, 1)) * (12 - $i);
        }
        $Resto = ($Soma * 10) % 11;

        if ($Resto === 10 || $Resto === 11) {
            $Resto = 0;
        }
        if ($Resto !== intval(substr($strCPF, 10, 1))) {
            return false;
        }

        return true;
    }
}


