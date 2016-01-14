<?php
class CleanTalkSFW
{
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $ip_str_array = Array();
	public $blocked_ip = '';
	public $result = false;
	
	public function cleantalk_get_real_ip()
	{
		if ( function_exists( 'apache_request_headers' ) )
		{
			$headers = apache_request_headers();
		}
		else
		{
			$headers = $_SERVER;
		}
		if ( array_key_exists( 'X-Forwarded-For', $headers ) )
		{
			$the_ip=explode(",", trim($headers['X-Forwarded-For']));
			$the_ip = trim($the_ip[0]);
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ))
		{
			$the_ip=explode(",", trim($headers['HTTP_X_FORWARDED_FOR']));
			$the_ip = trim($the_ip[0]);
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$this->ip_str_array[]=$the_ip;
		$this->ip_array[]=sprintf("%u", ip2long($the_ip));

		if(isset($_GET['sfw_test_ip']))
		{
			$the_ip=$_GET['sfw_test_ip'];
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		//$this->ip_str=$the_ip;
		//$this->ip=sprintf("%u", ip2long($the_ip));
		//print sprintf("%u", ip2long($the_ip));
	}
	
	public function check_ip()
	{
		global $DB;
		$passed_ip='';
		for($i=0;$i<sizeof($this->ip_array);$i++)
		{
			$r = $DB->Query("select count(network) as cnt from `cleantalk_sfw` where network = ".$this->ip_array[$i]." & mask;");
			
			$sfw_log=COption::GetOptionString( 'cleantalk.antispam', 'sfw_log', '' );
			
			if($sfw_log=='')
			{
				$sfw_log=Array();
			}
			else
			{
				$sfw_log=json_decode($sfw_log, true);
			}
			$cnt=$r->Fetch();
			if($cnt['cnt']>0)
			{
				$this->result=true;
				$this->blocked_ip=$this->ip_str_array[$i];
				if(isset($sfw_log[$this->ip_str_array[$i]]))
				{
					$sfw_log[$this->ip_str_array[$i]]['all']++;
				}
				else
				{
					$sfw_log[$this->ip_str_array[$i]] = Array('datetime'=>time(), 'all' => 1, 'allow' => 0);
				}
			}
			else
			{
				$passed_ip = $this->ip_str_array[$i];
			}
		}
		if($passed_ip!='')
		{
			$key=COption::GetOptionString( 'cleantalk.antispam', 'key', '' );
			@setcookie ('ct_sfw_pass_key', md5($passed_ip.$key), 0, "/");
		}
		COption::SetOptionString( 'cleantalk.antispam', 'sfw_log', json_encode($sfw_log));
	}
	
	public function sfw_die()
	{
		$key=COption::GetOptionString( 'cleantalk.antispam', 'key', '' );
		$sfw_die_page=file_get_contents(dirname(__FILE__)."/sfw_die_page.html");
		$sfw_die_page=str_replace("{REMOTE_ADDRESS}",$this->blocked_ip,$sfw_die_page);
		$sfw_die_page=str_replace("{REQUEST_URI}",$_SERVER['REQUEST_URI'],$sfw_die_page);
		$sfw_die_page=str_replace("{SFW_COOKIE}",md5($this->blocked_ip.$key),$sfw_die_page);
		@header('HTTP/1.0 403 Forbidden');
		print $sfw_die_page;
		die();
	}
	
	public function send_logs()
	{
		$is_sfw=COption::GetOptionString( 'cleantalk.antispam', 'form_sfw', 0 );
		$sfw_log=COption::GetOptionString( 'cleantalk.antispam', 'sfw_log', '' );

	    if($is_sfw==1 && $sfw_log!='')
	    {
	    	$sfw_log=json_decode($sfw_log, true);
	    	$data=Array();
	    	foreach($sfw_log as $key=>$value)
	    	{
	    		$data[]=Array($key, $value['all'], $value['allow'], $value['datetime']);
	    	}
	    	$qdata = array (
				'data' => json_encode($data),
				'rows' => count($data),
				'timestamp' => time()
			);
			if(!function_exists('sendRawRequest'))
			{
				require_once('classes/general/cleantalk.class.php');
			}
			
			$result = sendRawRequest('https://api.cleantalk.org/?method_name=sfw_logs&auth_key='.$ct_options['apikey'],$qdata);
			$result=CleantalkAntispam::CleantalkSendRequest('https://api.cleantalk.org/?method_name=sfw_logs&auth_key='.$key, $qdata, false);
			$result = json_decode($result);
			if(isset($result->data) && isset($result->data->rows))
			{
				if($result->data->rows == count($data))
				{
					COption::SetOptionString( 'cleantalk.antispam', 'sfw_log', '');
				}
			}
			
	    }
	}
}