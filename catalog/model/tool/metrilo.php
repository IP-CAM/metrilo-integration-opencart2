<?php

class ModelToolMetrilo extends Model {

	private $metrilo_is_enabled;
	private $metrilo_api_key;
	private $events_queue = array();

	private function init() {

		$this->load->model('setting/setting');

		$this->model_setting_setting->getSetting('metrilo');

		$this->metrilo_is_enabled = $this->config->get('metrilo_is_enabled');

		$this->metrilo_api_key = $this->config->get('metrilo_api_key');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('account/order');
		$this->load->model('tool/image');

	}
	
	public function orderUpdated($order_id) {
		
		$this->init();
		
		$data = array();

		$order_info = $this->model_account_order->getOrder($order_id);
		$order_products = $this->model_account_order->getOrderProducts($order_id);
		
		$data = array(
			'order_id' => $order_info['order_id'],
			'order_status' => $order_info['order_status_id'],
			'amount' => (float)$order_info['total'],
			'shipping_amount'   => (float)0,
            'tax_amount'        => 0,
            'items'             => array(),
			'shipping_method'   => $order_info['shipping_method'],
            'payment_method'    => $order_info['payment_method'],
		);
		
		foreach ($order_products as $order_product)
		{
			$product_data = array(
				'id' => $order_product['product_id'],
				'price' => $order_product['price'],
				'name' => $order_product['name'],
				'url' => $this->url->link('product/product', 'product_id=' . $order_product['product_id']),
				'quantity' => $order_product['quantity']
			);
			
			array_push($data['items'], $product_data);
		}
		
		$events = array(
			'token'  => $this->config->get('metrilo_api_key'),
			'events' => array (
				'event_type' => 'order',
				'identity'   =>  array(
					'email'     => $order_info['email'],
					'name'      =>  $order_info['customer'],
					'firstname' => $order_info['firstname'],
					'lastname'  => $order_info['lastname']
				),
				'params'     => $data,
				'uid'        => $order_info['customer_id']
			)	
		);
		
		
		ksort($events);
		$eventsJson = json_encode($events);
		$basedCall = base64_encode($eventsJson);
		$signature = md5($basedCall.$this->metrilo_api_key);

		$requestBody = array(
			's' => $signature,
			'hs' => $basedCall
		);
		
		$this->post('http://p.metrilo.com/bt', $requestBody, true);
	}
	
	public function getAllOrders()
	{
		$this->init();
		
		$orders = $this->model_account_order->getOrders();
		
		return $orders;
	}
	
	
	public function syncAllOrders($orders_ids)
	{
		$data = array();
		
		foreach ($orders_ids as $order_id)
		{
			$this->orderPlaced($order_id);
		}
		
		if($this->hasEventsInQueue())
		{
			echo 'hasEventsInQueue<br/>';
			file_put_contents('events_queue.txt', print_r($this->events_queue, true), FILE_APPEND);
			foreach($this->events_queue as $event)
			{
				$eventsJson = json_encode($event['params']);
				$basedCall = base64_encode($eventsJson);
				$signature = md5($basedCall.$this->metrilo_api_key);

				$requestBody = array(
					's' => $signature,
					'hs' => $basedCall
				);
				
				$this->post('http://p.metrilo.com/bt', $requestBody, true);
			}
		}
	}
	
	public function prepareOrderDetails($order_id) {
		
		$this->init();
		
		$data = array();

		$order_info = $this->model_account_order->getOrder($order_id);
		$order_products = $this->model_account_order->getOrderProducts($order_id);
		
		$data = array(
			'order_id' => $order_info['order_id'],
			'order_status' => $order_info['order_status_id'],
			'amount' => (float)$order_info['total'],
			'shipping_amount'   => (float)0,
            'tax_amount'        => 0,
            'items'             => array(),
			'date_added'   => $order_info['date_added'],
			'shipping_method'   => $order_info['shipping_method'],
            'payment_method'    => $order_info['payment_method'],
            'billing_country'    => $order_info['payment_country'],
            'billing_zone'    => $order_info['payment_zone'],
            'billing_postcode'    => $order_info['payment_postcode'],
            'billing_city'    => $order_info['payment_city'],
            'billing_address_1'    => $order_info['payment_address_1'],
            'billing_address_2'    => $order_info['payment_address_2'],
            'billing_company'    => $order_info['payment_company']
		);
		
		foreach ($order_products as $order_product)
		{
			$product_data = array(
				'id' => $order_product['product_id'],
				'price' => $order_product['price'],
				'name' => $order_product['name'],
				'url' => $this->url->link('product/product', 'product_id=' . $order_product['product_id']),
				'quantity' => $order_product['quantity']
			);
			
			array_push($data['items'], $product_data);
		}
		
		$events = array(
			'token'  => $this->config->get('metrilo_api_key'),
			'events' => array (
				'event_type' => 'order',
				'identity'   =>  array(
					'email'     => $order_info['email'],
					'name'      =>  $order_info['customer'],
					'firstname' => $order_info['firstname'],
					'lastname'  => $order_info['lastname']
				),
				'params'     => $data,
				'uid'        => $order_info['customer_id']
			)	
		);
		
		return $events;
	}

	public function hasEventsInQueue(){
		if(count($this->events_queue) > 0){
			return true;
		}
		return false;
	}

	public function addProductViewEvent(){
		$this->init();

		$product_id = $this->request->get['product_id'];
		if(isset($product_id) && $product_id){
			$product = $this->model_catalog_product->getProduct($product_id);
			$productData = array(
				'id'		=> $product['product_id'],
				'sku'		=> $product['sku'],
				'name'		=> $product['name'],
				'price'		=> $product['price']
			);
			if(isset($product['image']) && !empty($product['image'])){
				$image_url = $this->model_tool_image->resize($product['image'], 500, 500);
				$productData['image_url'] = $image_url;
			}

			$this->addEventInQueue('event', 'view_product', $productData);
		}
	}

	public function addEventInQueue($method, $event_type, $params = false, $in_session = false){

		$tracking_event = array(
			'method' 			=> $method, 
			'event_type'		=> $event_type, 
			'params'			=> $params
			);

		if($in_session){
			$this->addEventInSession($tracking_event);
		}else{
			array_push($this->events_queue, $tracking_event);
		}

		return true;
	}

	public function addEventInSession($tracking_event){
		$metrilo_queue = array();
		if(isset($this->session->data['metrilo_queue'])){
			$metrilo_queue = json_decode($this->session->data['metrilo_queue']);
		}
		array_push($metrilo_queue, $tracking_event);
		$this->session->data['metrilo_queue'] = json_encode($metrilo_queue, true);
	}

	public function flushEventsFromSession(){
		if(isset($this->session->data['metrilo_queue'])){
			$metrilo_queue = json_decode($this->session->data['metrilo_queue'], true);
			if(count($metrilo_queue) > 0){
				foreach($metrilo_queue as $k => $tracking_event){
					array_push($this->events_queue, $tracking_event);
				}
			}
			$this->session->data['metrilo_queue'] = json_encode(array(), true);
		}
	}

	public function renderTrackingScript(){
		$rendered_script = '';
		if($this->hasEventsInQueue()){
			foreach($this->events_queue as $event){
				$event_script = '';
				if($event['method'] == 'event'){
					$event_script = 'metrilo.event("'.$event['event_type'].'", '.json_encode($event['params']).'); ';
				}
				if($event['method'] == 'identify'){
					$event_script = 'metrilo.identify("'.$event['event_type'].'", '.json_encode($event['params']).'); ';
				}
				$rendered_script .= $event_script;
			}
		}
		return $rendered_script;
	}

	public function addToCartEvent($product_info = false, $quantity = 1){
		if($product_info){
			$productData = array(
				'id'		=> $product_info['product_id'],
				'sku'		=> $product_info['sku'],
				'name'		=> $product_info['name'],
				'price'		=> $product_info['price'], 
				'quantity'	=> $quantity
			);
			$this->addEventInQueue('event', 'add_to_cart', $productData, true);
		}
	}

	public function addCategoryViewEvent($category_info){
		if($category_info){
			$categoryData = array(
				'id'		=> $category_info['category_id'],
				'name'		=> $category_info['name']
			);
			$this->addEventInQueue('event', 'view_category', $categoryData);
		}
	}

	public function orderPlaced($order_id){

		$this->init();

		$order_info = $this->model_account_order->getOrder($order_id);
		$order_products = $this->model_account_order->getOrderProducts($order_id);

		// prepare order tracking event

		$tracking_event = array(
			'order_id'				=> $order_id, 
			'amount' 				=> $order_info['total'], 
			'items' 				=> array(),
			'shipping_method'		=> $order_info['shipping_method'], 
			'payment_method'		=> $order_info['payment_method']			
		);

		// prepare products for order event

		foreach ($order_products as $product) {

			$product_hash = array(
				'id' 		=> $product['product_id'],
				'quantity' 	=> $product['quantity'],
				'name' 		=> $product['name']
			);
			array_push($tracking_event['items'], $product_hash);

		}
		
		// prepare identify event

		$identify_params = array(
			'email'			=> $order_info['email'], 
			'first_name'	=> $order_info['payment_firstname'], 
			'last_name'		=> $order_info['payment_lastname'], 
			'name'			=> $order_info['payment_firstname'] .' '.$order_info['payment_lastname']
		);
		
		$tracking_event['identity'] = $identify_params;

		// add order event in queue
		
		$this->addEventInQueue('event', 'order', $tracking_event);

		// add identify event in queue

		$this->addEventInQueue('identify', $identify_params['email'], $identify_params);

	}


	// Ensure logged in user is identified

	public function ensureCustomerIdentify($customer_object){

		if($customer_object->getEmail() && !isset($this->session->data['metrilo_identify'])){

			$identify_params = array(
				'email'			=> $customer_object->getEmail(), 
				'first_name'	=> $customer_object->getFirstName(), 
				'last_name'		=> $customer_object->getLastName(), 
				'name'			=> $customer_object->getFirstName().' '.$customer_object->getLastName()
			);

			$this->addEventInQueue('identify', $identify_params['email'], $identify_params);
			$this->session->data['metrilo_identify'] = true;
		}

	}


	// Fetch Metrilo API key for JavaScript tracking librari

	public function getMetriloApiKey() {

		$this->init();

		if (isset($this->metrilo_is_enabled) && $this->metrilo_is_enabled && isset($this->metrilo_api_key) && ($this->metrilo_api_key != '')) {
			return $this->metrilo_api_key;
		} else {
			return null;
		}

	}
	
	public function get($url, $async = true)
    {
        $parsedUrl = parse_url($url);
        $raw = $this->_buildRawGet($parsedUrl['host'], $parsedUrl['path']);

        $this->_executeRequest($parsedUrl, $raw, $async);
    }
	
    public function post($url, $bodyArray = false, $async = true)
    {
        $parsedUrl = parse_url($url);
        $encodedBody = $bodyArray ? json_encode($bodyArray) : '';

        $raw = $this->_buildRawPost($parsedUrl['host'], $parsedUrl['path'], $encodedBody);

        $this->_executeRequest($parsedUrl, $raw, $async);
    }

    public function _buildRawGet($host, $path)
    {
        $out  = "GET ".$path." HTTP/1.1\r\n";
        $out .= "Host: ".$host."\r\n";
        // $out .= "Accept: application/json\r\n";
        $out .= "Connection: Close\r\n\r\n";

        return $out;
    }

    public function _buildRawPost($host, $path, $encodedCall)
    {
        $out  = "POST ".$path." HTTP/1.1\r\n";
        $out .= "Host: ".$host."\r\n";
        $out .= "Content-Type: application/json\r\n";
        $out .= "Content-Length: ".strlen($encodedCall)."\r\n";
        $out .= "Accept: */*\r\n";
        $out .= "User-Agent: AsyncHttpClient/1.0.0\r\n";
        $out .= "Connection: Close\r\n\r\n";

        $out .= $encodedCall;

        return $out;
    }

    public function _executeRequest($parsedUrl, $raw, $async = true)
    {
        $fp = fsockopen($parsedUrl['host'],
                        isset($parsedUrl['port']) ? $parsedUrl['port'] : 80,
                        $errno, $errstr, 30);				

        if ($fp) {
			
            fwrite($fp, $raw);

            if (!$async) {
                $this->_waitForResponse($fp);
            }

            fclose($fp);
        }
    }

    public function _waitForResponse($fp) {
        while (!feof($fp)) {
            fgets($fp, 1024);
        }
    }

}
?>
