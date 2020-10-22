<?php

namespace Cleantalk\Common;

/**
 * Cleantalk's hepler class
 * 
 * Mostly contains request's wrappers.
 *
 * @version 2.4
 * @package Cleantalk
 * @subpackage Helper
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam 
 *
 */

class Helper
{
	/**
	 * Default user agent for HTTP requests
	 */
	const AGENT = 'Cleatalk-Helper/3.2';

	const URL = 'https://api.cleantalk.org';

	/**
	 * @var array Set of private networks IPv4 and IPv6
	 */
	public static $private_networks = array(
		'v4' => array(
			'10.0.0.0/8',
			'100.64.0.0/10',
			'172.16.0.0/12',
			'192.168.0.0/16',
			'127.0.0.1/32',
		),
		'v6' => array(
			'0:0:0:0:0:0:0:1/128', // localhost
			'0:0:0:0:0:0:a:1/128', // ::ffff:127.0.0.1
		),
	);
	
	/**
	 * @var array Set of CleanTalk servers
	 */
	public static $cleantalks_servers = array(
		// MODERATE
		'moderate1.cleantalk.org' => '162.243.144.175',
		'moderate2.cleantalk.org' => '159.203.121.181',
		'moderate3.cleantalk.org' => '88.198.153.60',
		'moderate4.cleantalk.org' => '159.69.51.30',
		'moderate5.cleantalk.org' => '95.216.200.119',
		'moderate6.cleantalk.org' => '138.68.234.8',
		// APIX
		'apix1.cleantalk.org' => '35.158.52.161',
		'apix2.cleantalk.org' => '18.206.49.217',
		'apix3.cleantalk.org' => '3.18.23.246',
	);
	
	/**
	 * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	 *
	 * @param array $ip_types Type of IP you want to receive
	 * @param bool  $v4_only
	 *
	 * @return array|mixed|null
	 */
	static public function ip__get($ip_types = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true)
	{
		$ips = array_flip($ip_types); // Result array with IPs
		$headers = self::apache_request_headers();
		
		// REMOTE_ADDR
		if(isset($ips['remote_addr'])){
			$ip_type = self::ip__validate( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
			if($ip_type){
				$ips['remote_addr'] = ($ip_type == 'v6' ? self::ip__v6_normalize(isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) : isset( $_SERVER['REMOTE_ADDR'] )) ? $_SERVER['REMOTE_ADDR'] : '';
			}
		}
		
		// X-Forwarded-For
		if(isset($ips['x_forwarded_for'])){
			if(isset($headers['X-Forwarded-For'])){
				$tmp = explode(",", trim($headers['X-Forwarded-For']));
				$tmp = trim($tmp[0]);
				$ip_type = self::ip__validate($tmp);
				if($ip_type){
					$ips['x_forwarded_for'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
				}
			}
		}
		
		// X-Real-Ip
		if(isset($ips['x_real_ip'])){
			if(isset($headers['X-Real-Ip'])){
				$tmp = explode(",", trim($headers['X-Real-Ip']));
				$tmp = trim($tmp[0]);
				$ip_type = self::ip__validate($tmp);
				if($ip_type){
					$ips['x_forwarded_for'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
				}
			}
		}
		
		// Cloud Flare
		if(isset($ips['cloud_flare'])){
			if(isset($headers['CF-Connecting-IP'], $headers['CF-IPCountry'], $headers['CF-RAY']) || isset($headers['Cf-Connecting-Ip'], $headers['Cf-Ipcountry'], $headers['Cf-Ray'])){
				$tmp = isset($headers['CF-Connecting-IP']) ? $headers['CF-Connecting-IP'] : $headers['Cf-Connecting-Ip'];
				$tmp = strpos($tmp, ',') !== false ? explode(',', $tmp) : (array)$tmp;
				$ip_type = self::ip__validate(trim($tmp[0]));
				if($ip_type){
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize(trim($tmp[0])) : trim($tmp[0]);
				}
			}
		}
		
		// Getting real IP from REMOTE_ADDR or Cf_Connecting_Ip if set or from (X-Forwarded-For, X-Real-Ip) if REMOTE_ADDR is local.
		if(isset($ips['real'])){
			
			// Detect IP type
			$ip_type = self::ip__validate(isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '');
			if($ip_type)
				$ips['real'] = ($ip_type == 'v6' ? self::ip__v6_normalize(isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) : isset( $_SERVER['REMOTE_ADDR'] )) ? $_SERVER['REMOTE_ADDR'] : '';
			
			// Cloud Flare
			if(isset($headers['CF-Connecting-IP'], $headers['CF-IPCountry'], $headers['CF-RAY']) || isset($headers['Cf-Connecting-Ip'], $headers['Cf-Ipcountry'], $headers['Cf-Ray'])){
				$tmp = isset($headers['CF-Connecting-IP']) ? $headers['CF-Connecting-IP'] : $headers['Cf-Connecting-Ip'];
				$tmp = strpos($tmp, ',') !== false ? explode(',', $tmp) : (array)$tmp;
				$ip_type = self::ip__validate(trim($tmp[0]));
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize(trim($tmp[0])) : trim($tmp[0]);
				
				// Sucury
			}elseif(isset($headers['X-Sucuri-Clientip'], $headers['X-Sucuri-Country'])){
				$ip_type = self::ip__validate($headers['X-Sucuri-Clientip']);
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($headers['X-Sucuri-Clientip']) : $headers['X-Sucuri-Clientip'];
				
				// OVH
			}elseif(isset($headers['X-Cdn-Any-Ip'], $headers['Remote-Ip'])){
				$ip_type = self::ip__validate($headers['X-Cdn-Any-Ip']);
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($headers['X-Cdn-Any-Ip']) : $headers['X-Cdn-Any-Ip'];
				
				// Incapsula proxy
			}elseif(isset($headers['Incap-Client-Ip'])){
				$ip_type = self::ip__validate($headers['Incap-Client-Ip']);
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($headers['Incap-Client-Ip']) : $headers['Incap-Client-Ip'];
			}
			
			// Is private network
			if($ip_type === false ||
				($ip_type && 
					(self::ip__is_private_network($ips['real'], $ip_type) || 
						self::ip__mask_match(
							$ips['real'],
							(isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] . '/24' : '127.0.0.1/24'),
							$ip_type
						)
					)
				)
			){
				
				// X-Forwarded-For
				if(isset($headers['X-Forwarded-For'])){
					$tmp = explode(',', trim($headers['X-Forwarded-For']));
					$tmp = trim($tmp[0]);
					$ip_type = self::ip__validate($tmp);
					if($ip_type)
						$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
					
					// X-Real-Ip
				}elseif(isset($headers['X-Real-Ip'])){
					$tmp = explode(',', trim($headers['X-Real-Ip']));
					$tmp = trim($tmp[0]);
					$ip_type = self::ip__validate($tmp);
					if($ip_type)
						$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
				}
			}
		}
		
		// Validating IPs
		$result = array();
		foreach($ips as $key => $ip){
			$ip_version = self::ip__validate($ip);
			if($ip && (($v4_only && $ip_version == 'v4') || !$v4_only)){
				$result[$key] = $ip;
			}
		}
		
		$result = array_unique($result);
		return count($result) > 1
			? $result
			: (reset($result) !== false
				? reset($result)
				: null);
	}

	/**
	 * Validating IPv4, IPv6
	 *
	 * @param string $ip
	 *
	 * @return string|bool
	 */
	static public function ip__validate($ip)
	{
		if(!$ip) return false; // NULL || FALSE || '' || so on...
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ip != '0.0.0.0') return 'v4';  // IPv4
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && self::ip__v6_reduce($ip) != '0::0') return 'v6';  // IPv6
		return false; // Unknown
	}

	/*
	*	Checking api_key
	*	returns (boolean)
	*/

	static public function apbct_key_is_correct($api_key = '') {

		return preg_match('/^[a-z\d]{3,15}$|^$/', $api_key);

	}
	
	/**
	 * Reduce IPv6
	 *
	 * @param string $ip
	 *
	 * @return string IPv6
	 */
	static public function ip__v6_reduce($ip)
	{
		if(strpos($ip, ':') !== false){
			$ip = preg_replace('/:0{1,4}/', ':', $ip);
			$ip = preg_replace('/:{2,}/', '::', $ip);
			$ip = strpos($ip, '0') === 0 ? substr($ip, 1) : $ip;
		}
		return $ip;
	}

	/**
	 * Checks if the IP is in private range
	 *
	 * @param string $ip
	 * @param string $ip_type
	 *
	 * @return bool
	 */
	static function ip__is_private_network($ip, $ip_type = 'v4')
	{
		return self::ip__mask_match($ip, self::$private_networks[$ip_type], $ip_type);
	}

	/**
	 * Get URL form IP. Check if it's belong to cleantalk.
	 *
	 * @param string $ip
	 *
	 * @return false|int|string
	 */
	static public function ip__is_cleantalks($ip)
	{
		if(self::ip__validate($ip)){
			$url = array_search($ip, self::$cleantalks_servers);
			return $url
				? true
				: false;
		}else
			return false;
	}			
	/*
	 * Check if the IP belong to mask. Recursivly if array given
	 * @param ip string  
	 * @param cird mixed (string|array of strings)
	*/
	static public function ip_mask_match($ip, $cidr){
		if(is_array($cidr)){
			foreach($cidr as $curr_mask){
				if(self::ip_mask_match($ip, $curr_mask)){
					return true;
				}
			} unset($curr_mask);
			return false;
		}
		$exploded = explode ('/', $cidr);
		$net = $exploded[0];
		$mask = 4294967295 << (32 - $exploded[1]);
		return (ip2long($ip) & $mask) == (ip2long($net) & $mask);
	}
	
	/*
	*	Validating IPv4, IPv6
	*	param (string) $ip
	*	returns (string) 'v4' || (string) 'v6' || (bool) false
	*/
	static public function ip_validate($ip)
	{
		if(!$ip)                                                  return false; // NULL || FALSE || '' || so on...
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return 'v4';  // IPv4
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return 'v6';  // IPv6
		                                                          return false; // Unknown
	}

	/**
	 * Expand IPv6
	 *
	 * @param string $ip
	 *
	 * @return string IPv6
	 */
	static public function ip__v6_normalize($ip)
	{
		$ip = trim($ip);
		// Searching for ::ffff:xx.xx.xx.xx patterns and turn it to IPv6
		if(preg_match('/^::ffff:([0-9]{1,3}\.?){4}$/', $ip)){
			$ip = dechex(sprintf("%u", ip2long(substr($ip, 7))));
			$ip = '0:0:0:0:0:0:' . (strlen($ip) > 4 ? substr('abcde', 0, -4) : '0') . ':' . substr($ip, -4, 4);
			// Normalizing hextets number
		}elseif(strpos($ip, '::') !== false){
			$ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
			$ip = strpos($ip, ':') === 0 ? '0' . $ip : $ip;
			$ip = strpos(strrev($ip), ':') === 0 ? $ip . '0' : $ip;
		}
		// Simplifyng hextets
		if(preg_match('/:0(?=[a-z0-9]+)/', $ip)){
			$ip = preg_replace('/:0(?=[a-z0-9]+)/', ':', strtolower($ip));
			$ip = self::ip__v6_normalize($ip);
		}
		return $ip;
	}
	/**
	 * Check if the IP belong to mask.  Recursive.
	 * Octet by octet for IPv4
	 * Hextet by hextet for IPv6
	 *
	 * @param string $ip
	 * @param string $cidr       work to compare with
	 * @param string $ip_type    IPv6 or IPv4
	 * @param int    $xtet_count Recursive counter. Determs current part of address to check.
	 *
	 * @return bool
	 */
	static public function ip__mask_match($ip, $cidr, $ip_type = 'v4', $xtet_count = 0)
	{
		if(is_array($cidr)){
			foreach($cidr as $curr_mask){
				if(self::ip__mask_match($ip, $curr_mask, $ip_type)){
					return true;
				}
			}
			unset($curr_mask);
			return false;
		}
		
		$xtet_base = ($ip_type == 'v4') ? 8 : 16;
		
		// Calculate mask
		$exploded = explode('/', $cidr);
		$net_ip = $exploded[0];
		$mask = $exploded[1];
		
		// Exit condition
		$xtet_end = ceil($mask / $xtet_base);
		if($xtet_count == $xtet_end)
			return true;
		
		// Lenght of bits for comparsion
		$mask = $mask - $xtet_base * $xtet_count >= $xtet_base ? $xtet_base : $mask - $xtet_base * $xtet_count;
		
		// Explode by octets/hextets from IP and Net
		$net_ip_xtets = explode($ip_type == 'v4' ? '.' : ':', $net_ip);
		$ip_xtets = explode($ip_type == 'v4' ? '.' : ':', $ip);
		
		// Standartizing. Getting current octets/hextets. Adding leading zeros.
		$net_xtet = str_pad(decbin($ip_type == 'v4' ? $net_ip_xtets[$xtet_count] : hexdec($net_ip_xtets[$xtet_count])), $xtet_base, 0, STR_PAD_LEFT);
		$ip_xtet = str_pad(decbin($ip_type == 'v4' ? $ip_xtets[$xtet_count] : hexdec($ip_xtets[$xtet_count])), $xtet_base, 0, STR_PAD_LEFT);
		
		// Comparing bit by bit
		for($i = 0, $result = true; $mask != 0; $mask--, $i++){
			if($ip_xtet[$i] != $net_xtet[$i]){
				$result = false;
				break;
			}
		}
		
		// Recursing. Moving to next octet/hextet.
		if($result)
			$result = self::ip__mask_match($ip, $cidr, $ip_type, $xtet_count + 1);
		
		return $result;
		
	}
	
	/**
	 * Converts long mask like 4294967295 to number like 32
	 *
	 * @param int $long_mask
	 *
	 * @return int
	 */
	static function ip__mask__long_to_number($long_mask)
	{
		$num_mask = strpos((string)decbin($long_mask), '0');
		return $num_mask === false ? 32 : $num_mask;
	}
	
	/**
	 * Function convert anything to UTF8 and removes non UTF8 characters
	 *
	 * @param array|object|string $obj
	 * @param string              $data_codepage
	 *
	 * @return mixed(array|object|string)
	 */
	static public function toUTF8($obj, $data_codepage = null)
	{

		// Array || object
		if(is_array($obj) || is_object($obj)){
			foreach($obj as $key => &$val){
				$val = self::toUTF8($val, $data_codepage);
			}
			unset($key, $val);
			
			//String
		}else{
			if(!preg_match('//u', $obj) && function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')){
				if ($data_codepage !== null) {
					return mb_convert_encoding($obj, 'UTF-8', $data_codepage);
				}
	            $encoding = mb_detect_encoding($obj);
	            if ($encoding)
	                return mb_convert_encoding($obj, 'UTF-8', $encoding);
			}
		}

		return $obj;
	}

	/**
	 * Function removing non UTF8 characters from array|string|object
	 *
	 * @param array|object|string $data
	 *
	 * @return array|object|string
	 */
	public static function removeNonUTF8($data)
	{
		// Array || object
		if(is_array($data) || is_object($data)){
			foreach($data as $key => &$val){
				$val = self::removeNonUTF8($val);
			}
			unset($key, $val);
			
			//String
		}else{
			if(!preg_match('//u', $data))
				$data = 'Nulled. Not UTF8 encoded or malformed.';
		}
		return $data;
	}

	/**
	 * Merging arrays without reseting numeric keys
	 *
	 * @param array $arr1 One-dimentional array
	 * @param array $arr2 One-dimentional array
	 *
	 * @return array Merged array
	 */
	static public function array_merge__save_numeric_keys($arr1, $arr2)
	{
		foreach($arr2 as $key => $val){
			$arr1[$key] = $val;
		}
		return $arr1;
	}
	/**
	 * Function sends raw http request
	 *
	 * May use 4 presets(combining possible):
	 * get_code - getting only HTTP response code
	 * async    - async requests
	 * get      - GET-request
	 * ssl      - use SSL
	 *
	 * @param string       $url     URL
	 * @param array        $data    POST|GET indexed array with data to send
	 * @param string|array $presets String or Array with presets: get_code, async, get, ssl, dont_split_to_array
	 * @param array        $opts    Optional option for CURL connection
	 *
	 * @return array|bool (array || array('error' => true))
	 */	
	static public function http__request($url, $data = array(), $presets = null, $opts = array())
	{
		if(function_exists('curl_init')){
			
			$ch = curl_init();
			
			if(!empty($data)){
				// If $data scalar converting it to array
				$data = is_string($data) || is_int($data) ? array($data => 1) : $data;
				// Build query
				$opts[CURLOPT_POSTFIELDS] = $data;
			}
			
			// Merging OBLIGATORY options with GIVEN options
			$opts = self::array_merge__save_numeric_keys(
				array(
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CONNECTTIMEOUT_MS => 3000,
					CURLOPT_FORBID_REUSE => true,
					CURLOPT_USERAGENT => self::AGENT . '; ' . ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN_HOST' ),
					CURLOPT_POST => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_HTTPHEADER => array('Expect:'), // Fix for large data and old servers http://php.net/manual/ru/function.curl-setopt.php#82418
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS => 5,
				),
				$opts
			);
			
			// Use presets
			$presets = is_array($presets) ? $presets : explode(' ', $presets);
			foreach($presets as $preset){
				
				switch($preset){
					
					// Do not follow redirects
					case 'dont_follow_redirects':
						$opts[CURLOPT_FOLLOWLOCATION] = false;
						$opts[CURLOPT_MAXREDIRS] = 0;
						break;
					
					// Get headers only
					case 'get_code':
						$opts[CURLOPT_HEADER] = true;
						$opts[CURLOPT_NOBODY] = true;
						break;
					
					// Make a request, don't wait for an answer
					case 'async':
						$opts[CURLOPT_CONNECTTIMEOUT_MS] = 1000;
						$opts[CURLOPT_TIMEOUT_MS] = 1000;
						break;
					
					case 'get':
						$opts[CURLOPT_URL] .= $data ? '?' . str_replace("&amp;", "&", http_build_query($data)) : '';
						$opts[CURLOPT_CUSTOMREQUEST] = 'GET';
						$opts[CURLOPT_POST] = false;
						$opts[CURLOPT_POSTFIELDS] = null;
						break;
					
					case 'ssl':
						$opts[CURLOPT_SSL_VERIFYPEER] = true;
						$opts[CURLOPT_SSL_VERIFYHOST] = 2;
						if(defined('CLEANTALK_CASERT_PATH') && CLEANTALK_CASERT_PATH)
							$opts[CURLOPT_CAINFO] = CLEANTALK_CASERT_PATH;
						break;
					
					default:
						
						break;
				}
				
			}
			unset($preset);
			
			curl_setopt_array($ch, $opts);
			$result = curl_exec($ch);
			
			// RETURN if async request
			if(in_array('async', $presets))
				return true;
			
			if($result){
				
				if(strpos($result, PHP_EOL) !== false && !in_array('dont_split_to_array', $presets))
					$result = explode(PHP_EOL, $result);
				
				// Get code crossPHP method
				if(in_array('get_code', $presets)){
					$curl_info = curl_getinfo($ch);
					$result = $curl_info['http_code'];
				}
				curl_close($ch);
				$out = $result;
			}else
				$out = array('error' => curl_error($ch));
		}else
			$out = array('error' => 'CURL_NOT_INSTALLED');
		
		/**
		 * Getting HTTP-response code without cURL
		 */
		if($presets && ($presets == 'get_code' || (is_array($presets) && in_array('get_code', $presets)))
			&& isset($out['error']) && $out['error'] == 'CURL_NOT_INSTALLED'
		){
			$headers = get_headers($url);
			$out = (int)preg_replace('/.*(\d{3}).*/', '$1', $headers[0]);
		}
		
		return $out;
	}
	
	static public function is_json($string)
	{
		return is_string($string) && is_array(json_decode($string, true)) ? true : false;
	}

	/*
	* Get data from submit recursively
	*/

	static public function get_fields_any($arr, $message = array(), $email = null, $nickname = array('nick' => '', 'first' => '', 'last' => ''), $subject = null, $contact = true, $prev_name = '')
	{

		//Skip request if fields exists
		$skip_params = array(
		    'ipn_track_id', 	// PayPal IPN #
		    'txn_type', 		// PayPal transaction type
		    'payment_status', 	// PayPal payment status
		    'ccbill_ipn', 		// CCBill IPN 
			'ct_checkjs', 		// skip ct_checkjs field
			'api_mode',         // DigiStore-API
			'loadLastCommentId' // Plugin: WP Discuz. ticket_id=5571
	    );
		
		// Fields to replace with ****
	    $obfuscate_params = array(
	        'password',
	        'pass',
	        'pwd',
			'pswd'
	    );
		
		// Skip feilds with these strings and known service fields
		$skip_fields_with_strings = array( 
			// Common
			'ct_checkjs', //Do not send ct_checkjs
			'nonce', //nonce for strings such as 'rsvp_nonce_name'
			'security',
			// 'action',
			'http_referer',
			'referer-page',
			'timestamp',
			'captcha',
			// Formidable Form
			'form_key',
			'submit_entry',
			// Custom Contact Forms
			'form_id',
			'ccf_form',
			'form_page',
			// Qu Forms
			'iphorm_uid',
			'form_url',
			'post_id',
			'iphorm_ajax',
			'iphorm_id',
			// Fast SecureContact Froms
			'fs_postonce_1',
			'fscf_submitted',
			'mailto_id',
			'si_contact_action',
			// Ninja Forms
			'formData_id',
			'formData_settings',
			'formData_fields_\d+_id',
			'formData_fields_\d+_files.*',		
			// E_signature
			'recipient_signature',
			'output_\d+_\w{0,2}',
			// Contact Form by Web-Settler protection
	        '_formId',
	        '_returnLink',
			// Social login and more
			'_save',
			'_facebook',
			'_social',
			'user_login-',
			// Contact Form 7
			'_wpcf7',
			'ebd_settings',
			'ebd_downloads_',
			'ecole_origine',
			'signature',
		);
		
		// Reset $message if we have a sign-up data
	    $skip_message_post = array(
	        'edd_action', // Easy Digital Downloads
	    );
		
		foreach ($skip_params as $value)
		{
			if (@array_key_exists($value, $_GET) || @array_key_exists($value, $_POST))
				$contact = false;
		}
		unset($value);
		
		if(count($arr)){
			
			foreach($arr as $key => $value){
							
				if(gettype($value) == 'string'){

					$tmp = strpos($value, '\\') !== false ? stripslashes($value) : $value;
					$decoded_json_value = json_decode($tmp, true);
					
					// Decoding JSON
					if($decoded_json_value !== null){
						$value = $decoded_json_value;
						
					// Ajax Contact Forms. Get data from such strings:
						// acfw30_name %% Blocked~acfw30_email %% s@cleantalk.org
						// acfw30_textarea %% msg
					}elseif(preg_match('/^\S+\s%%\s\S+.+$/', $value)){
						$value = explode('~', $value);
						foreach ($value as &$val){
							$tmp = explode(' %% ', $val);
							$val = array($tmp[0] => $tmp[1]);
						}
					}
				}
				
				if(!is_array($value) && !is_object($value)){
					
					if (in_array($key, $skip_params, true) && $key != 0 && $key != '' || preg_match("/^ct_checkjs/", $key))
						$contact = false;
					
					if($value === '')
						continue;
					
					// Skipping fields names with strings from (array)skip_fields_with_strings
					foreach($skip_fields_with_strings as $needle){
						if (preg_match("/".$needle."/", $prev_name.$key) == 1){
							continue(2);
						}
					}unset($needle);

					// Obfuscating params
					foreach($obfuscate_params as $needle){
						if (strpos($key, $needle) !== false){
							$value = self::obfuscate_param($value);
							continue(2);
						}
					}unset($needle);
					
	                // Removes whitespaces
	                $value = urldecode( trim( $value) ); // Fully cleaned message
					
					// Email
					if ( ! $email && preg_match( "/^\S+@\S+\.\S+$/", $value ) ) {
						$email = $value;
						
					// Names
					}elseif (preg_match("/name/i", $key)){
						
						preg_match("/((name.?)?(your|first|for)(.?name)?)/", $key, $match_forename);
						preg_match("/((name.?)?(last|family|second|sur)(.?name)?)/", $key, $match_surname);
						preg_match("/(name.?)?(nick|user)(.?name)?/", $key, $match_nickname);
						
						if(count($match_forename) > 1)
							$nickname['first'] = $value;
						elseif(count($match_surname) > 1)
							$nickname['last'] = $value;
						elseif(count($match_nickname) > 1)
							$nickname['nick'] = $value;
						elseif (is_array($message))
							$message[$prev_name.$key] = $value;
							
					// Subject
					}elseif ($subject === null && preg_match("/subject/i", $key)){
						$subject = $value;
					
					// Message
					}else{
						if (is_array($message)) {
							$message[$prev_name.$key] = $value;					
						}
					}
					
				}elseif(!is_object($value)){
					
					$prev_name_original = $prev_name;
					$prev_name = ($prev_name === '' ? $key.'_' : $prev_name.$key.'_');
					
					$temp = self::get_fields_any($value, $message, $email, $nickname, $subject, $contact, $prev_name);
					
					$message 	= $temp['message'];
					$email 		= ($temp['email'] 		? $temp['email'] : null);
					$nickname 	= ($temp['nickname'] 	? $temp['nickname'] : null);				
					$subject 	= ($temp['subject'] 	? $temp['subject'] : null);
					if($contact === true)
						$contact = ($temp['contact'] === false ? false : true);
					$prev_name 	= $prev_name_original;
				}
			} unset($key, $value);
		}
		
	    foreach ($skip_message_post as $v) {
	        if (isset($_POST[$v])) {
	            $message = null;
	            break;
	        }
	    } unset($v);
		
		//If top iteration, returns compiled name field. Example: "Nickname Firtsname Lastname".
		if($prev_name === ''){
			if(!empty($nickname)){
				$nickname_str = '';
				foreach($nickname as $value){
					$nickname_str .= ($value ? $value." " : "");
				}unset($value);
			}
			$nickname = $nickname_str;
		}
		
	    $return_param = array(
			'email' 	=> $email,
			'nickname' 	=> $nickname,
			'subject' 	=> $subject,
			'contact' 	=> $contact,
			'message' 	=> $message
		);	

		return $return_param;
	}

	/**
	 * Masks a value with asterisks (*) Needed by the getFieldsAny()
	 * @return string
	 */
	static public function obfuscate_param($value = null)
	{
		if ($value && (!is_object($value) || !is_array($value)))
		{
			$length = strlen($value);
			$value  = str_repeat('*', $length);
		}

		return $value;
	}

	/**
	 * Print html form for external forms()
	 * @return string
	 */
	static public function print_form($arr, $k)
	{
		foreach ($arr as $key => $value)
		{
			if (!is_array($value))
			{

				if ($k == '')
					print '<textarea name="' . $key . '" style="display:none;">' . htmlspecialchars($value) . '</textarea>';
				else
					print '<textarea name="' . $k . '[' . $key . ']" style="display:none;">' . htmlspecialchars($value) . '</textarea>';
			}
		}
	}

	/**
	 * Valids email
	 * @return bool
	 * @since 1.5
	 */
	static public function validEmail($string)
	{
		if (!isset($string) || !is_string($string))
		{
			return false;
		}

		return preg_match("/^\S+@\S+$/i", $string);
	}
	/* 
	 * If Apache web server is missing then making
	 * Patch for apache_request_headers() 
	 */
	static function apache_request_headers(){
		
		$headers = array();	
		foreach($_SERVER as $key => $val){
			if(preg_match('/\AHTTP_/', $key)){
				$server_key = preg_replace('/\AHTTP_/', '', $key);
				$key_parts = explode('_', $server_key);
				if(count($key_parts) > 0 and strlen($server_key) > 2){
					foreach($key_parts as $part_index => $part){
						$key_parts[$part_index] = mb_strtolower($part);
						$key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);					
					}
					$server_key = implode('-', $key_parts);
				}
				$headers[$server_key] = $val;
			}
		}
		return $headers;
	}
	
	/** 
	 * Check if api key is correct
	 * @return bool 
	 */
	static function api_key__is_correct($api_key) {
	    return $api_key && preg_match('/^[a-z\d]{3,15}$/', $api_key) ? true : false;
	}

	/**
	 * Get URL form IP. Check if it's belong to cleantalk.
	 *
	 * @param $ip
	 *
	 * @return false|int|string
	 */
	static public function ip__resolve__cleantalks($ip)
	{
		if(self::ip__validate($ip)){
			$url = array_search($ip, self::$cleantalks_servers);
			return $url
				? $url
				: self::ip__resolve($ip);
		}else
			return $ip;
	}
	
	/**
	 * Get URL form IP
	 *
	 * @param $ip
	 *
	 * @return string
	 */
	static public function ip__resolve($ip)
	{
		if(self::ip__validate($ip)){
			$url = gethostbyaddr($ip);
			if($url)
				return $url;
		}
		return $ip;
	}

	/** 
	 * Setting cookie
	 */
	static function cookie__set($name, $value = '', $expires = 0, $path = '', $domain = null, $secure = false, $httponly = false, $samesite = null ){
		
		// For PHP 7.3+ and above
		if( version_compare( phpversion(), '7.3.0', '>=' ) ){
			
			$params = array(
				'expires'  => $expires,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => $httponly,
			);
			
			if($samesite)
				$params['samesite'] = $samesite;
			
			setcookie( $name, $value, $params );
			
		// For PHP 5.6 - 7.2
		}else
			setcookie( $name, $value, $expires, $path, $domain, $secure, $httponly );
	}		
}
