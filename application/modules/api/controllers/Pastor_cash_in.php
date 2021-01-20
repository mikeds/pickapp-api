<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pastor_cash_in extends Pastor_Controller {
	public function after_init() {
		$this->load->model("api/transactions_model", "tx");
	}

	public function accept() {
		$legder_desc 	= "cash_in";
		$tx_type_id 	= "cashin1";

		if ($this->JSON_POST() && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$post = $this->get_post();

			$ref_id = isset($post['ref_id']) ? $post['ref_id'] : "";

			$balance = $this->get_balance();

			$row = $this->tx->get_datum(
				'',
				array(
					'transaction_sender_ref_id' => $ref_id,
					'transaction_type_id'		=> $tx_type_id
				)
			)->row();

			if ($row == "") {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Invalid reference No.!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$to_receive_amount 	= $row->transaction_amount;
			$fee				= $row->transaction_fee;
			$total_amount 		= $to_receive_amount + $fee;

			if ($total_amount > $balance) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Insufficient balance!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$expiration_date = $row->transaction_date_expiration;

			if (strtotime($expiration_date) < strtotime($this->_today)) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Transaction request is expired!",
						'response'	=> array(
							'expiration_date' => $expiration_date
						),
						'timestamp'	=> $this->_today
					)
				);
				return;
			}
			
			$tx_status = $row->transaction_status;

			if ($tx_status != 0) {
				echo json_encode(
					array(
						'error'		=> true,
						'message'	=> "Transaction is done or cancelled!",
						'timestamp'	=> $this->_today
					)
				);
				return;
			}

			$credit_oauth_bridge_id 	= $row->transaction_requested_by;
			$debit_oauth_bridge_id 		= $this->_oauth_bridge_id;

			$transaction_id = $row->transaction_id;
	
			$this->create_ledger(
				$legder_desc, 
				$transaction_id, 
				$to_receive_amount, 
				$fee, 
				$debit_oauth_bridge_id, 
				$credit_oauth_bridge_id
			);

			// update tx status
			$this->tx->update(
				$transaction_id,
				array(
					'transaction_requested_to'	=> $this->_oauth_bridge_id,
					'transaction_status' 		=> 1,
					'transaction_date_approved' => $this->_today
				)
			);

			// $this->setup_income_shares($row->transaction_id);

			echo json_encode(
				array(
					'message'	=> "Successfully accepted cash-in!",
					'response' => array(
						'ref_id' 	=> $ref_id,
						'amount' 	=> $total_amount,
						'fee'		=> $fee,
						'to_receive'=> $to_receive_amount
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
