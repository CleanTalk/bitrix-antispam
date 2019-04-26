<?php

class CleantalkCustomConfig
{
	// Exclude urls from spam_check. List them separated by commas
	public static $cleantalk_url_exclusions = '';

	//Excludes fields from filtering. List them separated by commas
	public static $cleantalk_fields_exclusions = '';

	//Webforms id's to check. List them separated by commas
	public static $cleantalk_webforms_checking = '';

	public static function get_url_exclusions()
	{
		return (!empty(self::$cleantalk_url_exclusions) ? explode(',', trim(self::$cleantalk_url_exclusions)) : null);
	}
	public static function get_fields_exclusions()
	{
		return (!empty(self::$cleantalk_fields_exclusions) ? explode(',', trim(self::$cleantalk_fields_exclusions)) : null);
	}
	public static function get_webforms_ids()
	{
		return (!empty(self::$cleantalk_webforms_checking) ? explode(',', trim(self::$cleantalk_webforms_checking)) : null);
	}
}