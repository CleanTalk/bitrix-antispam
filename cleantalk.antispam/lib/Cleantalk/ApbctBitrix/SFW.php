<?php

namespace Cleantalk\ApbctBitrix;

/*
 * CleanTalk SpamFireWall Bitrix class
 * author Cleantalk team (welcome@cleantalk.org)
 * copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * see https://github.com/CleanTalk/php-antispam
*/

class SFW extends \Cleantalk\Antispam\SFW
{
	public function __construct($api_key) {
		global $DB;
		parent::__construct($api_key, $DB, "");
	}

	protected function universal_query($query) {
		$this->db_query = $this->db->Query($query);
	}

	protected function universal_fetch() {
		return $this->db_query->Fetch();
	}
	
	protected function universal_fetch_all() {
		$result = array();
		while ($row = $this->db_query->Fetch()){
			$result[] = $row;
		}
		return $result;
	}
}
