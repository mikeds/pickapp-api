<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client_transfer extends Client_Controller {
	public function after_init() {}

	public function send() {
		$legder_desc 	= "transfer";
		$tx_type_id 	= "transfer1";

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->load->model("api/client_accounts_model", "clients");

			$post = $this->get_post();

			$email_address	= isset($post['email_address']) ? $post['email_address'] : "";
			$amount			= isset($post['amount']) ? $post['amount'] : "";
			$note			= isset($post['note']) ? $post['note'] : "";

			if ($email_address == "") {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Email Address is required!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			if (!is_numeric($amount)) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid amount format!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			if (is_decimal($amount)) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Not accepting float value!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$fee = 0;

			if ($amount < $fee) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid Amount, amount is not enough to cover the fees!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}
			
			$row_email = $this->clients->get_datum(
				'',
				array(
					'account_email_address'		=> $email_address
				)
			)->row();

			if ($row_email == "") {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid, Cannot find client email address!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$row = $row_email;

			if ($row->account_email_address == $this->_account->account_email_address) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid, Unable to send transfer to your self!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			// tx logic
			$tx_by = $this->_oauth_bridge_id;
			$tx_to = $row->oauth_bridge_id;

			// calculate receiving fee
			$receiving_amount = ($amount - $fee);

			$tx_row = $this->create_transaction(
				$receiving_amount, 
				$fee, 
				$tx_type_id, 
				$tx_by, 
				$tx_to,
				$tx_by,
				60,
				$note
			);

			$transaction_id	= $tx_row['transaction_id'];
			$sender_ref_id  = $tx_row['sender_ref_id'];

			$debit_oauth_bridge_id 	= $tx_by;
			$credit_oauth_bridge_id = $tx_to;

			$this->create_ledger(
				$legder_desc, 
				$transaction_id, 
				$receiving_amount, 
				$fee,
				$debit_oauth_bridge_id, 
				$credit_oauth_bridge_id
			);

			echo json_encode(
				array(
					'message'	=> "Successfully transferred!",
					'response' => array(
						'ref_id' 	=> $sender_ref_id,
						'amount' 	=> $amount,
						'fee'		=> $fee,
						'to_receive'=> $receiving_amount
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
