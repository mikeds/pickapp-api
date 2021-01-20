<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Api_Controller extends MX_Controller {
	protected
		$_limit = 10,
		$_today = "",
		$_base_controller = "api",
		$_base_session = "session";

	protected
		$_upload_path = FCPATH . UPLOAD_PATH,
		$_ssl_method = "AES-128-ECB";

	protected
		$_oauth_bridge_parent_id = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		date_default_timezone_set("Asia/Manila");
		$this->_today = date("Y-m-d H:i:s");

		header('Content-Type: application/json');

		$this->after_init();
	}

	public function after_init() {
		$this->validate_parent_auth();
	}

	public function global_validate_token() {
		$this->load->library("oauth2");
		$this->oauth2->get_resource();
	}

	public function get_tx_fee($tx_type_id = "") {
		$this->load->model("api/income_shares_setup_model", "is_setup");
		$setup_data = $this->is_setup->get_data(
			array(
				'*'
			),
			array(
				'oauth_bridge_id' => $this->_oauth_bridge_parent_id
			),
			array(),
			array(),
			array(
				'filter' => "setup_fee",
				'sort'	 => "DESC"
			)
		);

		$fee = 0;
		
		// compute total fee
		foreach($setup_data as $datum) {
			$setup_id = $datum["setup_id"];

			if ($tx_type_id == "cashin1" && $setup_id == "church_leader") {
				continue;
			}
			
			$fee += $datum['setup_fee'];
		}

		return $fee;
	}

	public function setup_income_shares($tx_id, $cbranch_no = "") {
		$this->load->model("api/church_leaders_model", "leaders");
		$this->load->model("api/income_shares_setup_model", "is_setup");
		$this->load->model("api/transactions_model", "tx");
		$this->load->model("api/client_accounts_model", "client_accounts");

		$row = $this->tx->get_datum(
			'',
			array(
				'transaction_id' => $tx_id
			)
		)->row();

		if ($row == "") {
			return;
		}

		$setup_data = $this->is_setup->get_data(
			array(
				'*'
			),
			array(
				'oauth_bridge_id' => $this->_oauth_bridge_parent_id
			),
			array(),
			array(),
			array(
				'filter' => "setup_fee",
				'sort'	 => "DESC"
			)
		);

		$tx_type_id = $row->transaction_type_id;

		$fee 	= $row->transaction_fee;

		$accumulated_fee = 0;

		$data = array();
		
		// compute total fee
		foreach($setup_data as $datum) {
			$setup_id = $datum["setup_id"];

			if ($tx_type_id == "cashin1" && $setup_id == "church_leader") {
				continue;
			}
			
			$tmp_fee = $datum['setup_fee'];

			if ($setup_id == "church_leader") {
				if ($cbranch_no != "") {
					$row_leader = $this->leaders->get_datum(
						'',
						array(
							'cbranch_number' => $cbranch_no
						)
					)->row();
					
					if ($row_leader == "") {
						continue;
					}
					
					$data[] = array(
						'oauth_bridge_id' 	=> $row->oauth_bridge_id,
						'amount'			=> $tmp_fee
					);
				}
			} else if ($setup_id == "internal_service") {
				$isacc_oauth_bridge_id = $this->get_internal_service_account();

				if ($isacc_oauth_bridge_id) {
					$data[] = array(
						'oauth_bridge_id' 	=> $isacc_oauth_bridge_id,
						'amount'			=> $tmp_fee
					);
				} else {
					continue;
				}
			} else if ($setup_id == "pastor") {
				$tx_requested_by = $row->transaction_requested_by; // client requested

				$pastor_row = $this->client_accounts->get_datum(
					'',
					array(
						'client_accounts.oauth_bridge_id' => $tx_requested_by
					),
					array(),
					array(
						array(
							'table_name'	=> "pastor_accounts",
							'condition'		=> "pastor_accounts.account_number = client_accounts.pastor_account_number"
						)
					),
					array(
						'*',
						'pastor_accounts.oauth_bridge_id as pastor_oauth_bridge_id'
					)
				)->row();

				if ($pastor_row == "") {
					continue;
				}

				$data[] = array(
					'oauth_bridge_id' 	=> $pastor_row->pastor_oauth_bridge_id,
					'amount'			=> $tmp_fee
				);
			} else if ($setup_id == "processor") {
				$tx_requested_to = $row->transaction_requested_to;

				$data[] = array(
					'oauth_bridge_id' 	=> $tx_requested_to,
					'amount'			=> $tmp_fee
				);
			} else if ($setup_id == "sm_ofc") {
				$data[] = array(
					'oauth_bridge_id' 	=> $this->_oauth_bridge_parent_id,
					'amount'			=> $tmp_fee
				);
			} else {
				continue;
			}

			$fee += $tmp_fee;
		}

		// create transactions
		foreach($data as $i => $datum) {
			$credit_oauth_bridge_id = $datum["oauth_bridge_id"];
			$debit_oauth_bridge_id	= $row->transaction_requested_to;

			$receiving_amount 		= $datum["amount"];
			$time = new DateTime($this->_today);
			$time->add(new DateInterval('PT' . ($i + 1) . 'S'));
			$stamp = $time->format('Y-m-d H:i:s');

			$tx_row = $this->create_transaction(
				$receiving_amount, 
				0, 
				"income_shares", 
				$this->_oauth_bridge_parent_id, 
				$credit_oauth_bridge_id,
				$this->_oauth_bridge_parent_id,
				60,
				"",
				$tx_id,
				$stamp
			);

			$transaction_id = $tx_row['transaction_id'];

			// auto approved
			$this->tx->update(
				$transaction_id,
				array(
					'transaction_status' 		=> 1,
					// 'transaction_date_approved' => $this->_today
				)
			);

			$this->create_ledger(
				"income_shares", 
				$transaction_id, 
				$receiving_amount, 
				0, 
				$debit_oauth_bridge_id, 
				$credit_oauth_bridge_id
			);
		}
	}

	public function get_internal_service_account() {
		$this->load->model("api/internal_service_accounts_model", "isacc");

		$row = $this->isacc->get_datum(
			'',
			array(
				'oauth_bridges.oauth_bridge_parent_id' => $this->_oauth_bridge_parent_id
			),
			array(),
			array(
				array(
					'table_name' 	=> "oauth_bridges",
					'condition'		=> "oauth_bridges.oauth_bridge_id = internal_service_accounts.oauth_bridge_id"
				)
			)
		)->row();
		
		if ($row == "") {
			return false;
		}

		return $row->oauth_bridge_id;
	}

	public function create_ledger(
			$legder_desc, 
			$transaction_id, 
			$amount, 
			$fee, 
			$debit_oauth_bridge_id, 
			$credit_oauth_bridge_id
		) {
		// create ledger
		$debit_amount	= $amount + $fee;
		$credit_amount 	= $amount;
		$fee_amount		= $fee;

		$debit_total_amount 	= 0 - $debit_amount; // make it negative
		$credit_total_amount	= $credit_amount;

		$debit_wallet_address		= $this->get_wallet_address($debit_oauth_bridge_id);
		$credit_wallet_address	    = $this->get_wallet_address($credit_oauth_bridge_id);
		
		if ($credit_wallet_address == "" || $debit_wallet_address == "") {
			echo json_encode(
				array(
					'error'		=> true,
					'message'	=> "Wallet address not found!",
					'timestamp'	=> $this->_today
				)
			);
			die();
		}

		$debit_new_balances = $this->update_wallet($debit_wallet_address, $debit_total_amount);
		if ($debit_new_balances) {
			// record to ledger
			$this->new_ledger_datum(
				"{$legder_desc}_debit", 
				$transaction_id, 
				$credit_wallet_address, // request from credit wallet
				$debit_wallet_address, // requested to debit wallet
				$debit_new_balances
			);
		}

		$credit_new_balances = $this->update_wallet($credit_wallet_address, $credit_total_amount);
		if ($credit_new_balances) {
			// record to ledger
			$this->new_ledger_datum(
				"{$legder_desc}_credit",
				$transaction_id, 
				$debit_wallet_address, // debit from wallet address
				$credit_wallet_address, // credit to wallet address
				$credit_new_balances
			);
		}
	}

	public function get_tx_limit($tx_type_id) {
		$this->load->model("api/transaction_types_model", "tx_types");

		$row = $this->tx_types->get_datum(
			'',
			array(
				'transaction_type_id' => $tx_type_id
			)
		)->row();

		if ($row == "") {
			return array(
				'min' => 1,
				'max' => ""
			);
		}

		return array(
			'min' => $row->transaction_min_limit,
			'max' => $row->transaction_max_limit,
		);
	}

	public function validate_parent_auth() {
		$this->global_validate_token();
		
		$this->load->model("api/oauth_bridges_model", "bridges");

		$token_row = $this->get_token();

		$admin_oauth_bridge_id = $token_row->client_id;

		$row = $this->bridges->get_datum(
			'',
			array(
				'oauth_bridges.oauth_bridge_id' => $admin_oauth_bridge_id
			),
			array(),
			array(
				array(
					'table_name' 	=> 'admins',
					'condition'		=> 'admins.oauth_bridge_id = oauth_bridges.oauth_bridge_id'
				)
			)
		)->row();

		if ($row == "") {
			// unauthorized access
			$this->output->set_status_header(401);	
			die();
		}

		$this->_oauth_bridge_parent_id = $admin_oauth_bridge_id;
	}

	public function validate_email_address($email_address, $not_id = "") {
		$this->load->model("api/pastor_accounts_model", "pastor_accounts");
		$this->load->model("api/client_accounts_model", "client_accounts");

		$where = array(
			'account_email_address'	=> $email_address
		);

		if ($not_id != "") {
			$where = array_merge(
				$where,
				array(
					'account_number !=' => $not_id
				)
			);
		}

		$row = $this->client_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row != "") {
			return true;
		}
		
		$row = $this->pastor_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row != "") {
			return true;
		}

		return false;
	}

	public function validate_mobile_no($mobile_no, $not_id = "") {
		$this->load->model("api/pastor_accounts_model", "pastor_accounts");
		$this->load->model("api/client_accounts_model", "client_accounts");

		$where = array(
			'account_mobile_no'	=> $mobile_no
		);

		if ($not_id != "") {
			$where = array_merge(
				$where,
				array(
					'account_number !=' => $not_id
				)
			);
		}

		$row = $this->client_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row != "") {
			return true;
		}
		
		$row = $this->pastor_accounts->get_datum(
			'',
			$where
		)->row();

		if ($row != "") {
			return true;
		}

		return false;
	}

	public function new_ledger_datum($description = "", $transaction_id, $from_wallet_address, $to_wallet_address, $balances) {
		$this->load->model("api/ledger_data_model", "ledger");
		$this->load->model("api/wallet_addresses_model", "wallet_addresses");

		$to_oauth_bridge_id 	= getenv("SYSADD");
		$from_oauth_bridge_id 	= getenv("SYSADD");


		$from_row = $this->wallet_addresses->get_datum(
			'',
			array(
				'wallet_address' => $from_wallet_address
			)
		)->row();

		if ($from_row != "") {
			$from_oauth_bridge_id 	= $from_row->oauth_bridge_id;
		}

		$to_row = $this->wallet_addresses->get_datum(
			'',
			array(
				'wallet_address' => $to_wallet_address
			)
		)->row();

		if ($to_row != "") {
			$to_oauth_bridge_id 	= $to_row->oauth_bridge_id;
		}

		$old_balance = $balances['old_balance'];
		$new_balance = $balances['new_balance'];
		$amount		 = $balances['amount'];

		$ledger_type = 0; // unknown

		if ($amount < 0) {
			$ledger_type = 1; // debit
		} else if ($amount >= 0) {
			$ledger_type = 2; // credit
		}

		// add new ledger data
		$ledger_data = array(
			'tx_id'                         => $transaction_id,
			'ledger_datum_type'				=> $ledger_type,
			'ledger_datum_bridge_id'		=> $to_oauth_bridge_id,
			'ledger_datum_desc'             => $description,
			'ledger_from_wallet_address'    => $from_wallet_address,
			'ledger_to_wallet_address'      => $to_wallet_address,
			'ledger_from_oauth_bridge_id'   => $from_oauth_bridge_id,
			'ledger_to_oauth_bridge_id'     => $to_oauth_bridge_id,
			'ledger_datum_old_balance'      => $old_balance,
			'ledger_datum_new_balance'      => $new_balance,
			'ledger_datum_amount'           => $amount,
			'ledger_datum_date_added'       => $this->_today
		);

		$ledger_datum_id = $this->generate_code(
			$ledger_data,
			"crc32"
		);

		$ledger_data = array_merge(
			$ledger_data,
			array(
				'ledger_datum_id'   => $ledger_datum_id,
			)
		);

		$ledger_datum_checking_data = $this->generate_code($ledger_data);

		$this->ledger->insert(
			array_merge(
				$ledger_data,
				array(
					'ledger_datum_checking_data' => $ledger_datum_checking_data
				)
			)
		);
	}

	public function update_wallet($wallet_address, $amount) {
		$this->load->model("api/wallet_addresses_model", "wallet_addresses");

		$row = $this->wallet_addresses->get_datum(
			'',
			array(
				'wallet_address'	=> $wallet_address
			)
		)->row();

		if ($row == "") {
			return false;
		}

		$wallet_balance         = $this->decrypt_wallet_balance($row->wallet_balance);

		$old_balance            = $wallet_balance;
		$encryted_old_balance   = $this->encrypt_wallet_balance($old_balance);

		$new_balance            = $old_balance + $amount;
		$encryted_new_balance   = $this->encrypt_wallet_balance($new_balance);

		$wallet_data = array(
			'wallet_balance'                => $encryted_new_balance,
			'wallet_address_date_updated'   => $this->_today
		);

		// update wallet balances
		$this->wallet_addresses->update(
			$wallet_address,
			$wallet_data
		);

		return array(
			'old_balance'	=> $old_balance,
			'new_balance'	=> $new_balance,
			'amount'		=> $amount
		);
	}

	public function encrypt_wallet_balance($balance) {
		return openssl_encrypt($balance, $this->_ssl_method, getenv("BPKEY"));
	}

	public function decrypt_wallet_balance($encrypted_balance) {
		return openssl_decrypt($encrypted_balance, $this->_ssl_method, getenv("BPKEY"));
	}

	public function get_wallet_address($bridge_id) {
		$this->load->model('api/wallet_addresses_model', 'wallet_addresses');

		$row = $this->wallet_addresses->get_datum(
			'',
			array(
				'oauth_bridge_id' => $bridge_id
			)
		)->row();

		if ($row == "") {
			return "";
		}

		return $row->wallet_address;
	}

	public function send_email_activation($send_to_email, $pin, $expiration_date = "") {
		// send email activation
		$email_message = $this->load->view("templates/email_activation", array(
			"activation_pin" 	=> $pin,
			"expiration_date"	=> $expiration_date
		), true);

		send_email(
			getenv("SMTPUSER"),
			$send_to_email,
			"Account Registration Activation PIN",
			$email_message
		);
	}

	public function generate_code($data, $hash = "sha256") {
		$json = json_encode($data);
		return hash_hmac($hash, $json, getenv("SYSKEY"));
	}

	public function create_transaction(
		$amount, 
		$fee, 
		$transaction_type_id, 
		$requested_by_oauth_bridge_id, 
		$requested_to_oauth_bridge_id, 
		$created_by_oauth_bridge_id = null, 
		$expiration_minutes = 60, 
		$message = "",
		$tx_parent_id = "",
		$date = ""
	) {

		if ($date == "") {
			$date = $this->_today;
		}

		$this->load->model("api/transactions_model", "transactions");
		
		if (is_null($created_by_oauth_bridge_id)) {
			$created_by_oauth_bridge_id = $requested_by_oauth_bridge_id;
		}

        // expiration timestamp
        $minutes_to_add = $expiration_minutes;
        $time = new DateTime($date);
        $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
        $stamp = $time->format('Y-m-d H:i:s');

        $total_amount = $amount + $fee;

        $data_insert = array(
			'transaction_message'			=> $message,
            'transaction_amount' 		    => $amount,
            'transaction_fee'		        => $fee,
            'transaction_total_amount'      => $total_amount,
            'transaction_type_id'           => $transaction_type_id,
            'transaction_requested_by'      => $requested_by_oauth_bridge_id,
            'transaction_requested_to'	    => $requested_to_oauth_bridge_id,
            'transaction_created_by'        => $created_by_oauth_bridge_id,
            'transaction_date_created'      => $date,
			'transaction_date_expiration'   => $stamp,
			'transaction_otp_status'		=> 1 // temporary activated
        );

        // generate sender ref id
        $sender_ref_id = $this->generate_code(
            $data_insert,
            "crc32"
        );

        $data_insert = array_merge(
            $data_insert,
            array(
                'transaction_sender_ref_id' => $sender_ref_id
            )
        );

        // generate transaction id
        $transaction_id = $this->generate_code(
            $data_insert,
            "crc32"
        );

        // generate OTP Pin
        $pin 	= generate_code(4, 2);

        $data_insert = array_merge(
            $data_insert,
            array(
                'transaction_id'        => $transaction_id,
                'transaction_otp_pin'   => $pin
            )
		);
		
		if ($tx_parent_id != "") {
			$data_insert = array_merge(
				$data_insert,
				array(
					'transaction_parent_id'	=> $tx_parent_id
				)
			);

			if ($transaction_type_id == "income_shares") {
				$data_insert = array_merge(
					$data_insert,
					array(
						'transaction_status' => 1 // approved
					)
				);
			}
		}

        $this->transactions->insert(
            $data_insert
		);
		
		return array(
			'transaction_id'=> $transaction_id,
			'sender_ref_id'	=> $sender_ref_id,
			'pin'			=> $pin
		);
	}

	public function create_wallet_address($account_number, $bridge_id, $oauth_bridge_parent_id) {
		$this->load->model('api/wallet_addresses_model', 'wallet_addresses');

		// add address
		$wallet_address = $this->generate_code(
			array(
				'account_number' 				=> $account_number,
				'oauth_bridge_id'				=> $bridge_id,
				'wallet_address_date_created'	=> $this->_today,
				'admin_oauth_bridge_id'			=> $oauth_bridge_parent_id
			)
		); 

		// create wallet address
		$this->wallet_addresses->insert(
			array(
				'wallet_address' 				=> $wallet_address,
				'wallet_balance'				=> openssl_encrypt(0, $this->_ssl_method, getenv("BPKEY")),
				'wallet_hold_balance'			=> openssl_encrypt(0, $this->_ssl_method, getenv("BPKEY")),
				'oauth_bridge_id'				=> $bridge_id,
				'wallet_address_date_created'	=> $this->_today
			)
		);
	}

	public function create_token_auth($account_number, $bridge_id) {
		$this->load->model('api/oauth_clients_model', 'oauth_clients');

		$row = $this->oauth_clients->get_datum(
			'',
			array(
				'oauth_bridge_id'	=> $bridge_id
			)
		)->row();

		if ($row != "") {
			return;
		}

		// create api token
		$this->oauth_clients->insert(
			array(
				'client_id' 		=> $bridge_id,
				'client_secret'		=> $this->generate_code(
					array(
						'account_number'	=> $account_number,
						'date_added'		=> $this->_today,
						'oauth_bridge_id'	=> $bridge_id
					)
				),
				'oauth_bridge_id'	=> $bridge_id,
				'client_date_added'	=> $this->_today
			)
		);
	}

	public function get_post() {
		$post = json_decode($this->input->raw_input_stream, true);

		if (count($post) == 0) {
			return array();
		}

		return filter_var_array($post, FILTER_SANITIZE_STRING);
	}

	public function JSON_POST() {
		$content_type = $this->input->get_request_header('Content-Type', TRUE);
		$json = "application/json";
		
		if (preg_match("/\bjson\b/", $content_type)) {
			return true;
		}

		return false;
	}

	public function get_token() {
		$this->load->model('api/tokens_model', 'tokens');

		$token = get_bearer_token();
		// error code E001
		if (is_null($token)) {
			echo json_encode(array(
				"error" 	=> true,
				"message"	=> "Invalid token."
			));
			die();
		}

		$token_row = $this->tokens->get_datum(
			'',
			array(
				'access_token'	=> $token
			)
		)->row();

		// error code E002
		if ($token_row == "") {
			echo json_encode(array(
				"error" 	=> true,
				"message"	=> "Invalid token not found."
			));
			die();
		}

		return $token_row;
	}

	public function upload_files($folder_name, $files, $title = "", $is_data = false, $file_size_limit = 20, $allowed_types = "") {
		$upload_path = "{$this->_upload_path}/uploads/{$folder_name}";

		if (!file_exists($upload_path)) {
			mkdir($upload_path, 0755, true);
		}

        $config = array(
            'upload_path'   => $upload_path,
            'overwrite'     => 1,                       
		);
		
		if ($allowed_types != "") {
			$config = array_merge(
				$config,
				array(
					'allowed_types' => $allowed_types
				)
			);
		} else {
			$config = array_merge(
				$config,
				array(
					'allowed_types' => "*"
				)
			);
		}

        $this->load->library('upload', $config);

        $items = array();
		$error_uploads = array();
		$data = array();

		if (!is_array($files['name'])) {
			$tmp_file = $files;
			$files = array();

			$files['name'][]= $tmp_file['name'];
			$files['type'][]= $tmp_file['type'];
			$files['tmp_name'][]= $tmp_file['tmp_name'];
			$files['error'][]= $tmp_file['error'];
			$files['size'][]= $tmp_file['size'];
		}

        foreach ($files['name'] as $key => $file) {
            $_FILES['files[]']['name']= $files['name'][$key];
            $_FILES['files[]']['type']= $files['type'][$key];
            $_FILES['files[]']['tmp_name']= $files['tmp_name'][$key];
            $_FILES['files[]']['error']= $files['error'][$key];
			$_FILES['files[]']['size']= $files['size'][$key];
			
			$file_size = $files['size'][$key];

			if ($file_size > ($file_size_limit * MB)) {
				$error_uploads[] = array(
					'error_image' => $files['name'][$key],
					'error_message' => "The file size is over-limit from {$file_size_limit}MB limit!"
				);

				continue;
			}

			$ext = explode(".", $file);
			$ext = isset($ext[count($ext) - 1]) ? $ext[count($ext) - 1] : ""; 

			$today = strtotime($this->_today);

			if ($title != "") {
				$file_name = "{$title}_{$key}_{$today}";
				$file_name =  "{$file_name}.{$ext}";
			} else {
				$file_name = $file;
			}

            $items[] = $file_name;

            $config['file_name'] = $file_name;

            $this->upload->initialize($config);

            if ($this->upload->do_upload('files[]')) {
				$this->upload->data();

				// get file uploaded
				$full_path 		= "{$upload_path}/{$file_name}";

				if ($is_data) {
					$filecontent 	= file_get_contents($full_path);

					// update image save base64
					$data[] = array(
						'file_name' => $file_name,
						'base64_image' => rtrim(base64_encode($filecontent))
					);

					// delete uploaded image
					if(file_exists($full_path)){
						unlink($full_path);
					}
				} else {
					$data[] = array(
						'file_name' => $file_name,
						'full_path'	=> $full_path
					);
				}
            } else {
				$error_uploads[] = array(
					'error_image' => $files['name'][$key],
					'error_message' => $this->upload->display_errors()
				);
            }
        }

		return array(
			'results' => array(
				'is_data' 	=> $is_data,
				'data'		=> $data
			),
			'errors' => $error_uploads
		);
	}
	
	public function get_oauth_info($oauth_bridge_id) {
		$this->load->model("api/client_accounts_model", "clients");
		$this->load->model("api/pastor_accounts_model", "pastors");
		$this->load->model("api/church_branches_model", "branches");
		$this->load->model("api/admins_model", "admins");

		$client_row = $this->clients->get_datum(
			'',
			array(
				'oauth_bridge_id' => $oauth_bridge_id
			)
		)->row();

		if ($client_row != "") {
			return array(
				'name' => "{$client_row->account_fname} {$client_row->account_mname} {$client_row->account_lname}"
			);
		}

		$pastor_row = $this->pastors->get_datum(
			'',
			array(
				'oauth_bridge_id' => $oauth_bridge_id
			)
		)->row();

		if ($pastor_row != "") {
			return array(
				'name' => "{$pastor_row->account_fname} {$pastor_row->account_mname} {$pastor_row->account_lname}"
			);
		}

		$branch_row = $this->branches->get_datum(
			'',
			array(
				'oauth_bridge_id' => $oauth_bridge_id
			)
		)->row();

		if ($branch_row != "") {
			return array(
				'name' => $branch_row->cbranch_name
			);
		}

		$admin_row = $this->admins->get_datum(
			'',
			array(
				'oauth_bridge_id' => $oauth_bridge_id
			)
		)->row();

		if ($admin_row != "") {
			return array(
				'name' => $admin_row->admin_name
			);
		}

		return false;
	}

	public function get_pagination_offset($page = 1, $limit = 10, $num_rows = 10) {
		$page 	= ($page < 1 ? 1 : $page);
		$offset = ($page - 1) * $limit;
		$offset = ($offset >= $num_rows && $page == 1 ? 0 : $offset);
		return $offset;
	}
}
