<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CMS_Controller class
 * Base controller ?
 *
 * @author Marknel Pineda
 */
class Client_Controller extends Api_Controller {
	protected
		$_account = null;

	protected
		$_oauth_bridge_id = null,
		$_oauth_bridge_parent_id = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize all configs, helpers, libraries from parent
		parent::__construct();
		
		$this->validate_token();
		$this->validate_access();
		$this->after_init();
	}

	private function validate_token() {
		$this->load->library("oauth2");
		$this->oauth2->get_resource();
	}

	public function get_balance() {
		$this->load->model("api/client_accounts_model", "accounts");

		$oauth_bridge_id = $this->_oauth_bridge_id;

		$row = $this->accounts->get_datum(
			'',
			array(
				'client_accounts.oauth_bridge_id' => $oauth_bridge_id
			),
			array(),
			array(
				array(
					'table_name' 	=> 'wallet_addresses',
					'condition'		=> 'wallet_addresses.oauth_bridge_id = client_accounts.oauth_bridge_id'
				)
			)
		)->row();

		$balance = 0;

		if ($row != "") {
			$balance = $this->decrypt_wallet_balance($row->wallet_balance);
		}

		return $balance;
	}
	
	private function validate_access() {
		$this->load->model("api/oauth_bridges_model", "bridges");

		$token_row = $this->get_token();
		$client_id = $token_row->client_id;

		$inner_joints = array(
			array(
				'table_name' 	=> 'client_accounts',
				'condition'		=> 'client_accounts.oauth_bridge_id = oauth_bridges.oauth_bridge_id'
			),
			array(
				'table_name' 	=> 'wallet_addresses',
				'condition'		=> 'wallet_addresses.oauth_bridge_id = client_accounts.oauth_bridge_id'
			)
		);

		$row = $this->bridges->get_datum(
			'',
			array(
				'client_accounts.oauth_bridge_id' => $client_id
			),
			array(),
			$inner_joints,
			array(
				'*',
				'client_accounts.oauth_bridge_id as account_oauth_bridge_id'
			)
		)->row();
		
		if ($row == "") {
			// unauthorized access
			$this->output->set_status_header(401);	
			die();
		}
		
		$this->_account = $row;

		$this->_oauth_bridge_id 		= $this->_account->account_oauth_bridge_id;
		$this->_oauth_bridge_parent_id 	= $this->_account->oauth_bridge_parent_id;
	}
}
