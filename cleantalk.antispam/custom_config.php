<?php

class CleantalkCustomConfig
{
	// Exclude urls from spam_check. List them separated by commas
	private $cleantalk_url_exclusions = '';

	function __construct()
	{
		$this->cleantalk_url_exclusions = explode(',', trim($this->cleantalk_url_exclusions));	
	}
	public function get_url_exclusions()
	{
		return $this->cleantalk_url_exclusions;
	}
}
