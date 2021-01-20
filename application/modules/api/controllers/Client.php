<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client extends Client_Controller {
	public function after_init() {}

	public function balance() {
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {

			$balance = $this->get_balance();

			echo json_encode(
				array(
					'message'	=> "Successfully retrieve balance!",
					'response' => array(
						'balance'		=> $balance
					),
					'timestamp'	=> $this->_today
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);	
	}
}
