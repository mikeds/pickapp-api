<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lookup extends Api_Controller {

	public function after_init() {
        $this->global_validate_token();

        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            // unauthorized access
		    $this->output->set_status_header(401);	
        }
    }

    public function pastor_list() {
        $this->load->model("api/pastor_accounts_model", "accounts");

        $like = array();

        if (isset($_GET['q'])) {
            $query = $_GET['q'];
            
            if ($query != "") {
                $like = array(
                    'field' => 'CONCAT(account_fname, " ", account_mname, " ", account_lname)',
                    'value' => $query
                );
            }
        }

        $results = $this->accounts->get_data(
            array(
                'account_number',
                'account_fname',
                'account_mname',
                'account_lname',
                'account_email_address',
                'account_mobile_no'
            ),
            array(
                'account_status' => 1
            ),
            $like,
            array(),
            array(
                'filter'    => 'account_fname',
                'sort'      => 'ASC'
            )
        );

        $results = array_merge(
            array(
                array(
                    'account_number'        => '0',
                    'account_fname'         => 'I just researched by myself',
                    'account_mname'         => '',
                    'account_lname'         => '',
                    'account_email_address' => '',
                    'account_mobile_no'     => '',
                )
            ),
            $results
        );
        
        echo json_encode(
            array(
                'message'	=> "Successfully fetch merchant accounts!",
                'timestamp'	=> $this->_today,
                'response'  => $results
            )
        );
    }

    public function church_branches() {
        $this->load->model("api/church_branches_model", "branches");

        $like = array();

        if (isset($_GET['q'])) {
            $query = $_GET['q'];
            
            if ($query != "") {
                $like = array(
                    'field' => 'CONCAT(cbranch_mobile_no, " ", cbranch_email_address)',
                    'value' => $query
                );
            }
        }

        $results = $this->branches->get_data(
            array(
                'cbranch_number as branch_no',
                'cbranch_name as branch_name',
                'cbranch_mobile_no as mobile_no',
                'cbranch_email_address as email_address'
            ),
            array(
                'cbranch_status' => 1
            ),
            $like,
            array(),
            array(
                'filter'    => 'cbranch_email_address',
                'sort'      => 'ASC'
            )
        );

        echo json_encode(
            array(
                'message'	=> "Successfully fetch branches!",
                'timestamp'	=> $this->_today,
                'response'  => $results
            )
        );
    }

    public function countries() {
        $this->load->model("api/countries_model", "countries");

        $results = $this->countries->get_data(
            array(
                'country_id',
                'country_name'
            ),
            array(
                'country_status' => 1
            ),
            array(),
            array(),
            array(
                'filter'    => 'country_name',
                'sort'      => 'ASC'
            )
        );

        echo json_encode(
            array(
                'message'	=> "Successfully fetch Countries!",
                'timestamp'	=> $this->_today,
                'response'  => $results
            )
        );
    }

    public function provinces($country_id) {
        $this->load->model("api/provinces_model", "provinces");

        $results = $this->provinces->get_data(
            array(
                'province_id',
                'province_name'
            ),
            array(
                'province_status'   => 1,
                'country_id'        => $country_id
            ),
            array(),
            array(),
            array(
                'filter'    => 'province_name',
                'sort'      => 'ASC'
            )
        );

        echo json_encode(
            array(
                'message'	=> "Successfully fetch Provinces!",
                'timestamp'	=> $this->_today,
                'response'  => $results
            )
        );
    }
}
