<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Otp_client_registration extends Api_Controller {
    public function submit() {
        $this->load->model("api/client_pre_registration_model", "client_pre_registration");
        $this->load->model("api/oauth_bridges_model", "bridges");
        $this->load->model("api/client_accounts_model", "accounts");

        $post = $this->get_post();

        $username   = isset($post["username"]) ? $post["username"] : "";
        $otp_pin    = isset($post["otp_pin"]) ? $post["otp_pin"] : "";

        $row = $this->client_pre_registration->get_datum(
            '',
            array(
                'account_email_address' => $username,
                'account_otp_pin'       => $otp_pin,
                'account_status'        => 0
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'		=> true,
                    'message'	=> "Invalid OTP Pin!",
                    'timestamp'	=> $this->_today
                )
            );
            return;
        }

        $expiration_date = $row->account_otp_expiration_date;

        if (strtotime($this->_today) > strtotime($expiration_date)) {
            echo json_encode(
                array(
                    'error'		=> true,
                    'message'	=> "OTP Pin is expired!",
                    'timestamp'	=> $this->_today
                )
            );
            return;
        }

        $bridge_id = $this->generate_code(
            array(
                "client",
                $row->account_number,
                $this->_today
            )
        );

        $account_number = $row->account_number;

        // create account
        $this->accounts->insert(
            array(
                'pastor_account_number'	=> $row->pastor_account_number,
                'account_number'		=> $account_number,
                'oauth_bridge_id'		=> $bridge_id,
                'account_fname'			=> $row->account_fname,
                'account_lname'			=> $row->account_lname,
                'account_mobile_no'		=> $row->account_mobile_no,
                'account_password'		=> $row->account_password,
                'account_email_address'	=> $row->account_email_address,
                'account_dob'			=> $row->account_dob,
                'account_gender'		=> $row->account_gender,
                'account_date_added'	=> $this->_today,
                'account_house_no'		=> $row->account_house_no,
                'account_street'		=> $row->account_street,
                'account_brgy'			=> $row->account_brgy,
                'account_city'			=> $row->account_city,
                'province_id'			=> $row->province_id,
                'account_others'		=> $row->account_others,
                'account_status'        => 1 // activated
            )
        );

        // delete from pre registration
        $this->client_pre_registration->delete($account_number);

        // create bridge access
        $this->bridges->insert(
            array(
                'oauth_bridge_id' 			=> $bridge_id,
                'oauth_bridge_parent_id'	=> $this->_oauth_bridge_parent_id,
                'oauth_bridge_date_added'	=> $this->_today
            )
        );

        // create wallet address
        $this->create_wallet_address($account_number, $bridge_id, $this->_oauth_bridge_parent_id);

        // create token auth for api
        $this->create_token_auth($account_number, $bridge_id);

        echo json_encode(
            array(
                'message'	=> "Successfully activated!",
                'timestamp'	=> $this->_today
            )
        );
    }

    public function resend() {
        $this->load->model("api/client_pre_registration_model", "client_pre_registration");
        $this->load->model("api/client_accounts_model", "accounts");

        $post = $this->get_post();

        $username = isset($post["username"]) ? $post["username"] : "";

        $row = $this->client_pre_registration->get_datum(
            '',
            array(
                'account_email_address' => $username,
                'account_status'        => 0
            )
        )->row();

        if ($row == "") {
            echo json_encode(
                array(
                    'error'		=> true,
                    'message'	=> "Invalid request otp!",
                    'timestamp'	=> $this->_today
                )
            );
            return;
        }

        $email_address = $row->account_email_address;

        $pin 	= generate_code(4, 2);

        // generate expiration datetime
        $time = new DateTime($this->_today);
        $time->add(new DateInterval('PT' . 3 . 'M'));
        $expiration_date = $time->format('Y-m-d H:i:s');

        // update otp pin
        $this->client_pre_registration->update(
            $row->account_number,
            array(
                'account_otp_pin'               => $pin,
                'account_otp_expiration_date'   => $expiration_date
            )
        );

        // send email otp
        $this->send_email_activation(
            $email_address,
            $pin,
            $expiration_date
        );

        echo json_encode(
            array(
                'message'	=> "Successfully resend otp!",
                'timestamp'	=> $this->_today
            )
        );
    }
}
