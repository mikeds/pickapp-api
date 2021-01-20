<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Client_gift extends Client_Controller {
	public function after_init() {}

	public function send() {
		$this->load->model("api/branches_model", "branches");

		$legder_desc 	= "gift";
		$tx_type_id 	= "gift1";
		$tx_limits 		= $this->get_tx_limit($tx_type_id);
		$min			= $tx_limits["min"];
		$max			= $tx_limits["max"];

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$amount			= isset($post['amount']) ? $post['amount'] : "";
			$note			= isset($post['note']) ? $post['note'] : "";
			$branch_no		= isset($post['branch_no']) ? $post['branch_no'] : "";

			$row = $this->branches->get_datum(
				'',
				array(
					'cbranch_number' => $branch_no
				),
				array(),
				array(
					array(
						"table_name"	=> "wallet_addresses",
						"condition"		=> "wallet_addresses.oauth_bridge_id = church_branches.oauth_bridge_id",
					)
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid Branch no.",
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

			// if ($amount < $min) {
			// 	echo json_encode(
			// 		array(
			// 			'error'		=> true,
			// 			'message'	=> "Invalid Amount, Minimun amount is {$min}!",
			// 			'timestamp'	=> $this->_today
			// 		)
			// 	);
			// 	return;
			// }

			// $fee = $this->get_tx_fee($tx_type_id);

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
					'message'	=> "Successfully send gift!",
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
