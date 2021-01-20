<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pastor_tx extends Pastor_Controller {
	public function after_init() {}

	public function history() {
		$this->load->model("api/transactions_model", "transactions");

		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			$oauth_bridge_id = $this->_oauth_bridge_id;

			$limit 	= $this->_limit;
			$page	= 1;
			$sort	= 'DESC';

			if (isset($_GET['limit'])) {
				$limit = is_numeric($_GET['limit']) ? $_GET['limit'] : $this->_limit;
			}

			if (isset($_GET['page'])) {
				$page = is_numeric($_GET['page']) ? $_GET['page'] : 1;
			}

			if (isset($_GET['sort'])) {
				$sort = $_GET['sort'] == 'ASC' ? 'ASC' : 'DESC';
			}

			$select = array(
				'*'
			);
			$select = ARRtoSTR($select);

			$query = "(c1.oauth_bridge_id = '{$oauth_bridge_id}') OR (c2.oauth_bridge_id = '{$oauth_bridge_id}')";

$sql = <<<SQL
SELECT count(*) as count FROM `transactions` as tx 
inner join transaction_types
on transaction_types.transaction_type_id = tx.transaction_type_id
left join pastor_accounts as c1 
on tx.transaction_requested_by = c1.oauth_bridge_id
left join pastor_accounts as c2
on tx.transaction_requested_to = c2.oauth_bridge_id
where
$query
LIMIT 1
SQL;
	
			$query_count 	= $this->db->query($sql);
			$count_result 	= $query_count->row();
			$total_rows 	= isset($count_result->count) ? $count_result->count : 0;
			$offset 		= $this->get_pagination_offset($page, $limit, $total_rows);
	
			$query_limit = $offset == "" || $offset == 0 ? "LIMIT {$limit}" : "LIMIT {$offset}, {$limit}";
	
$sql = <<<SQL
SELECT $select FROM `transactions` as tx 
inner join transaction_types
on transaction_types.transaction_type_id = tx.transaction_type_id
left join pastor_accounts as c1 
on tx.transaction_requested_by = c1.oauth_bridge_id
left join pastor_accounts as c2
on tx.transaction_requested_to = c2.oauth_bridge_id
where
$query
ORDER BY transaction_date_created $sort
$query_limit
SQL;
	
			$query 		= $this->db->query($sql);
			$results 	= $query->result_array();
			$response 	= $this->filter_results($results);

			$num_pages = ceil($total_rows / $limit);
			$num_pages = round($num_pages);

			echo json_encode(
				array(
					'message'	=> "Successfully retrieve transaction history!",
					'response' 	=> $response,
					'pagination'=> array(
						'limit'			=> $limit,
						'page'			=> $page,
						'total_pages' 	=> $num_pages
					),
					'timestamp'	=> $this->_today
				)
			);
			return;
		}

		// unauthorized access
		$this->output->set_status_header(401);	
	}

	private function filter_results($results) {
		$array = array();

		foreach ($results as $row) {
			$requested_by = $row['transaction_requested_by'];
			$requested_to = $row['transaction_requested_to'];

			$requested_by_row = $this->get_oauth_info($requested_by);
			$requested_to_row = $this->get_oauth_info($requested_to);

			$array[] = array(
				'transaction_id'		=> $row['transaction_id'],
				'sender_ref_id'			=> $row['transaction_sender_ref_id'],
				'transaction_type_name'	=> $row['transaction_type_name'],
				'amount'				=> $row['transaction_amount'],
				'fee'					=> $row['transaction_fee'],
				'total_amount'			=> $row['transaction_total_amount'],
				'transaction_by' 		=> isset($requested_by_row['name']) ? preg_replace('/\s+/', ' ', $requested_by_row['name']) : "",
				'transaction_to' 		=> isset($requested_to_row['name']) ? preg_replace('/\s+/', ' ', $requested_to_row['name']) : "",
				'transaction_date'		=> $row['transaction_date_created']
			);
		}

		return $array;
	}
}
