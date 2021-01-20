<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pastor_login extends Api_Controller {
	public function index() {
		$this->load->model("api/pastor_accounts_model", "accounts");

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$username = isset($post['username']) ? $post['username'] : "";

			$password = isset($post['password']) ? $post['password'] : "";
			$password = hash("sha256", $password);
			
			if (strlen($username) < 7) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid username!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$row_email_address = $this->accounts->get_datum(
				'',
				array(
					'account_email_address' => $username,
					'account_password' 		=> $password,
					'account_status'		=> 1
				),
				array(),
				array(
					array(
						"table_name"	=> "oauth_clients",
						"condition"		=> "oauth_clients.client_id = pastor_accounts.oauth_bridge_id",
					)
				)
			)->row();

			$row_mobile_no = $this->accounts->get_datum(
				'',
				array(
					'account_mobile_no' => $username,
					'account_password' 	=> $password,
					'account_status'	=> 1
				),
				array(),
				array(
					array(
						"table_name"	=> "oauth_clients",
						"condition"		=> "oauth_clients.client_id = pastor_accounts.oauth_bridge_id",
					)
				)
			)->row();

			$row = ($row_email_address != "" ? $row_email_address : $row_mobile_no);

			if ($row == "") {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Incorrect login!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$qr_code = md5($row->oauth_bridge_id);

			echo json_encode(
				array(
					'response' => array(
						'id'			=> $row->account_number,
						'first_name'	=> $row->account_fname,
						'last_name'		=> $row->account_lname,
						'email_address'	=> $row->account_email_address,
						'secret_code'	=> $row->client_id,
						'secret_key'	=> $row->client_secret,
						'qr_code'		=> base_url() . "qr-code/pastor/{$qr_code}",
					)
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);	
	}
}
