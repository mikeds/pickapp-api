<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token extends Api_Controller {
	public function after_init() {}

	public function index() {
		$this->load->library('OAuth2', 'oauth2');
		$this->oauth2->get_token();
	}
}
