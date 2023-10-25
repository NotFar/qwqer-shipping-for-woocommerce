<?php
/*
 * QWQER: Settings plugin
 */
defined('ABSPATH') || exit;

class WC_Qwqer_Shipping_Method extends WC_Shipping_Method
{
    public const QWQER_API_URL = 'https://api.qwqer.lv/';
    public const QWQER_GET_COORDINATES_URL = 'v1/places/geocode';
    public const QWQER_GET_PRICE_URL = 'v1/clients/auth/trading-points/{trading-point-id}/delivery-orders/get-price';

    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);

        $this->id = 'qwqer_shipping_method';
        $this->instance_id = absint($instance_id);
        $this->title = __('QWQER Shipping Method', 'qwqer');
        $this->method_title = __('QWQER Shipping Method', 'qwqer');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    public function init(): void
    {
        $this->init_form_fields();
        $this->init_instance_settings();
        $this->init_settings();

        $this->qwqer_title = $this->get_option('qwqer_title');
        $this->qwqer_key = $this->get_option('qwqer_key');
        $this->qwqer_id = $this->get_option('qwqer_id');
        $this->qwqer_phone = $this->get_option('qwqer_phone');
        $this->qwqer_category = $this->get_option('qwqer_category');

        $this->store_address = get_option('woocommerce_store_address');
        $this->store_city = get_option('woocommerce_store_city');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function arrayFields(): array
    {
        $fields = [];
        $fields['qwqer_key'] = $this->qwqer_key;
        $fields['qwqer_id'] = $this->qwqer_id;
        $fields['qwqer_category'] = $this->qwqer_category;

        return $fields;
    }

    public function init_form_fields(): void
    {
        $this->instance_form_fields = array(
            'qwqer_title' => array(
                'title' => __('Method Title', 'qwqer'),
                'type' => 'text',
                'default' => __('QWQER Shipping', 'qwqer'),
            ),
            'qwqer_id' => array(
                'title' => __('Trading-Point ID', 'qwqer'),
                'type' => 'text',
            ),
            'qwqer_key' => array(
                'title' => __('API token', 'qwqer'),
                'type' => 'text',
            ),
            'qwqer_phone' => array(
                'title' => __('Shop phone number', 'qwqer'),
                'type' => 'text',
            ),
            'qwqer_category' => array(
                'title' => __('Select category', 'qwqer'),
                'description' => __('Category to which your shop`s products belong', 'qwqer'),
                'type' => 'select',
                'default' => 'Other',
                'options' => array(
                    'Other' => __('Other', 'qwqer'),
                    'Flowers' => __('Flowers', 'qwqer'),
                    'Food' => __('Food', 'qwqer'),
                    'Electronics' => __('Electronics', 'qwqer'),
                    'Cake' => __('Cake', 'qwqer'),
                    'Present' => __('Present', 'qwqer'),
                    'Clothes' => __('Clothes', 'qwqer'),
                    'Document' => __('Document', 'qwqer'),
                    'Jewelry' => __('Jewelry', 'qwqer'),
                ),
            ),
        );
    }

    public function get_instance_form_fields(): array
    {
        return parent::get_instance_form_fields();
    }

    public function getResponse($params, $url, $token)
    {
        $curl = curl_init($url);
        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer " . $token,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        /*
         * Debug only
         */
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($curl);
        curl_close($curl);

        return $result = json_decode($result, true);
    }

    public function calculate_shipping($package = array())
    {
        /*
         * Check Trading-Point ID
         */
        if (!$this->qwqer_id) {
            return false;
        }

        /*
         * Check API token
         */
        if (!$this->qwqer_key) {
            return false;
        }

        $params = [];

        $billing_address_1 = WC()->session->get('shipping_address_1', WC()->checkout->get_value('shipping_address_1'));
        $billing_city = WC()->session->get('shipping_city', WC()->checkout->get_value('shipping_city'));

        /*
         * Get store coordinates and address
         */
        $data_info_strore = [
            "address" => get_option('woocommerce_store_address') . ' ' . get_option('woocommerce_store_city'),
        ];
        $info_store = $this->getResponse($data_info_strore, self::QWQER_API_URL . self::QWQER_GET_COORDINATES_URL, $this->qwqer_key);

        $storeOwnerAddress = $params;
        $storeOwnerAddress["address"] = $info_store['data']['address'];
        $storeOwnerAddress["coordinates"] = $info_store['data']['coordinates'];

        /*
         * Get client coordinates and address
         */
        $data_info_client = [
            "address" => $billing_address_1 . ' ' . $billing_city,
        ];
        $info_client = $this->getResponse($data_info_client, self::QWQER_API_URL . self::QWQER_GET_COORDINATES_URL, $this->qwqer_key);

        $clientOwnerAddress = $params;
        if (is_checkout()) {
            if(isset($info_client['data']['address']) && isset($info_client['data']['coordinates'])) {
                $clientOwnerAddress["address"] = $info_client['data']['address'];
                $clientOwnerAddress["coordinates"] = $info_client['data']['coordinates'];
            }
        }

        /*
         * Get delivery price
         */
        $data_price = array(
            'type' => 'Regular',
            'category' => $this->qwqer_category,
            'real_type' => 'ScheduledDelivery',
            'origin' => $storeOwnerAddress,
            'destinations' => [$clientOwnerAddress],
        );
        $url_api_price = str_replace('{trading-point-id}', $this->qwqer_id, self::QWQER_GET_PRICE_URL);
        $price = $this->getResponse($data_price, self::QWQER_API_URL . $url_api_price, $this->qwqer_key);

        /*
         * Check field address and city in woocommerce checkout
         */
        if ($billing_address_1 && $billing_city) {
            /*
             * Check error delivery
             */
            if ($price['message']) {
                $this->add_rate(array(
                    'id' => $this->id,
                    //'label' => $this->qwqer_title . ' ' . $price['message'],
                    'label' => $this->qwqer_title . ' ' . __('One or more of the destinations is out of orgin`s delivery area!', 'qwqer'),
                ));
                return false;
            }

            /*
             * Add price
             */
            $total_price = $price['data']['client_price'];
            $this->add_rate(array(
                'id' => $this->id,
                'label' => $this->qwqer_title,
                'cost' => $total_price/100,
            ));
        } else {
            $this->add_rate(array(
                'id' => $this->id,
                'label' => sprintf(__('%s. Please fill in the address and city field', 'qwqer'), $this->qwqer_title),
            ));
        }
    }
}