<?php
class ControllerModuleMetrilo extends Controller {

	private $error = array();

	public function index() {


		$this->load->language('module/metrilo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
			$this->model_setting_setting->editSetting('metrilo', $this->request->post);

			if($this->request->post['metrilo_is_enabled'] == '1') {
				if(strlen($this->request->post['metrilo_api_key']) > 0) {
					$this->session->data['success'] = $this->language->get('message_enabled');
					$this->response->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'));
				} else {
					$this->error['warning'] = $this->language->get('message_warning');
				}
			} else {
				$this->error['warning'] = $this->language->get('message_disabled');
			}

		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_metrilo_api_key'] = $this->language->get('text_metrilo_api_key');

		$data['text_enabled'] = $this->language->get('text_enabled');

		$data['option_enable'] = $this->language->get('option_enable');
		$data['option_disable'] = $this->language->get('option_disable');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_home'),
		'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_module'),
		'href'      => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
    		'separator' => ' :: '
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('heading_title'),
		'href'      => $this->url->link('module/metrilo', 'token=' . $this->session->data['token'], 'SSL'),
    		'separator' => ' :: '
 		);

		$data['action'] = $this->url->link('module/metrilo', 'token=' . $this->session->data['token'], 'SSL');

		$data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');


		if (isset($this->request->post['metrilo_api_key'])) {
			$data['metrilo_api_key'] = $this->request->post['metrilo_api_key'];
		} else {
			$data['metrilo_api_key'] = $this->config->get('metrilo_api_key');
		}

		if (isset($this->request->post['metrilo_is_enabled'])) {
			$data['metrilo_is_enabled'] = $this->request->post['metrilo_is_enabled'];
		} else {
			$data['metrilo_is_enabled'] = $this->config->get('metrilo_is_enabled');
		}

		$this->load->model('design/layout');

		$data['layouts'] = $this->model_design_layout->getLayouts();
		
		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('module/metrilo.tpl', $data));

	}

}
?>
