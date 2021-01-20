<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pastor_top_up extends Pastor_Controller {
	public function after_init() {
		$this->load->model("api/pastor_accounts_model", "accounts");
	}

	public function request() {
		$legder_desc 	= "top_up";
		$tx_type_id 	= "topup1";
		$tx_limits 		= $this->get_tx_limit($tx_type_id);
		$min			= $tx_limits["min"];
		$max			= $tx_limits["max"];

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$amount	= isset($post['amount']) ? $post['amount'] : "";
			$note	= isset($post['note']) ? $post['note'] : "";

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

			if ($amount < $min) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid Amount, Minimun amount is {$min}!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			if ($max != "") {
				if ($amount > $max) {
					echo json_encode(
						array(
							'error'		=> true,
							'message'	=> "Invalid Amount, Maximum amount is {$max}!",
							'timestamp'	=> $this->_today
						)
					);
					return;
				}
			}

			// $fee = $this->get_tx_fee();
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

			// tx logic
			$credit_oauth_bridge_id = $this->_oauth_bridge_id;
			$debit_oauth_bridge_id 	= $this->_oauth_bridge_parent_id;

			$debit_wallet_address		= $this->get_wallet_address($debit_oauth_bridge_id);
			$credit_wallet_address	    = $this->get_wallet_address($credit_oauth_bridge_id);

			$tx_row = $this->create_transaction(
				$amount, 
				$fee, 
				$tx_type_id, 
				$debit_oauth_bridge_id, 
				$credit_oauth_bridge_id,
				null,
				60,
				$note
			);

			$transaction_id = $tx_row['transaction_id'];
			$sender_ref_id  = $tx_row['sender_ref_id'];
			$pin            = $tx_row['pin'];
	
			$this->create_ledger(
				$legder_desc, 
				$transaction_id, 
				$amount, 
				$fee, 
				$debit_oauth_bridge_id, 
				$credit_oauth_bridge_id
			);

			// update tx status
			$this->transactions->update(
				$transaction_id,
				array(
					'transaction_status' 		=> 1,
					'transaction_date_approved' => $this->_today
				)
			);

			echo json_encode(
				array(
					'message'	=> "Successfully top-up!",
					'responser' => array(
						'amount' 	=> $amount,
						'fee' 		=> $fee,
						'to_receive'=> ($amount - $fee)
					),
					'timestamp'	=> $this->_today
				)
			);
			die();
		}

		// unauthorized access
		$this->output->set_status_header(401);	
	}
}
