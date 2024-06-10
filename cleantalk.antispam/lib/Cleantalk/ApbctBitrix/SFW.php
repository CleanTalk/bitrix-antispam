<?php

namespace Cleantalk\ApbctBitrix;

use Cleantalk\Common\Variables\Server;

class SFW extends \Cleantalk\Common\Firewall\Modules\SFW
{
	public function __construct($data_table, $params = array())
    {
		parent::__construct($data_table, $params);
	}

    /**
     * @inheritdoc
     */
	public function _die( $result )
    {
		// Statistics
		if( ! empty( $this->blocked_ips ) ){
			reset($this->blocked_ips);
		}
		
		// File exists?
		if( file_exists( __DIR__ . "/die_page_sfw.html" ) ){
			
			$sfw_die_page = file_get_contents( __DIR__ . "/die_page_sfw.html" );

            $net_count = $this->db->fetch( 'SELECT COUNT(*) as net_count FROM ' . $this->db_data_table_name );

            $status = $result['status'] === 'PASS_SFW__BY_WHITELIST' ? '1' : '0';
            $cookie_val = md5( $result['ip'] . $this->api_key ) . $status;

			// Translation
			$replaces = array(
				'{SFW_DIE_NOTICE_IP}'              => $this->__('SpamFireWall is activated for your IP ', 'cleantalk-spam-protect'),
				'{SFW_DIE_MAKE_SURE_JS_ENABLED}'   => $this->__( 'To continue working with the web site, please make sure that you have enabled JavaScript.', 'cleantalk-spam-protect' ),
				'{SFW_DIE_CLICK_TO_PASS}'          => $this->__('Please click the link below to pass the protection,', 'cleantalk-spam-protect'),
				'{SFW_DIE_YOU_WILL_BE_REDIRECTED}' => sprintf( $this->__('Or you will be automatically redirected to the requested page after %d seconds.', 'cleantalk-spam-protect'), 3),
				'{CLEANTALK_TITLE}'                => ($this->test ? $this->__('This is the testing page for SpamFireWall', 'cleantalk-spam-protect') : ''),
				'{REMOTE_ADDRESS}'                 => $result['ip'],
				'{SERVICE_ID}'                     => $net_count['net_count'],
				'{HOST}'                           => '',
				'{GENERATED}'                      => '<p>The page was generated at&nbsp;' . date( 'D, d M Y H:i:s' ) . "</p>",
				'{REQUEST_URI}'                    => Server::get( 'REQUEST_URI' ),
				
				// Cookie
				'{COOKIE_PREFIX}'      => '',
				'{COOKIE_DOMAIN}'      => $this->cookie_domain,
				'{COOKIE_SFW}'         => $this->test ? $this->test_ip : $cookie_val,
				
				// Test
				'{TEST_TITLE}'      => '',
				'{REAL_IP__HEADER}' => '',
				'{TEST_IP__HEADER}' => '',
				'{TEST_IP}'         => '',
				'{REAL_IP}'         => '',
			);
			
			// Test
			if($this->test){
				$replaces['{TEST_TITLE}']      = $this->__( 'This is the testing page for SpamFireWall', 'cleantalk-spam-protect' );
				$replaces['{REAL_IP__HEADER}'] = 'Real IP:';
				$replaces['{TEST_IP__HEADER}'] = 'Test IP:';
				$replaces['{TEST_IP}']         = $this->test_ip;
				$replaces['{REAL_IP}']         = $this->real_ip;
			}

			$form_sfw_uniq_get_option = \COption::GetOptionInt('cleantalk.antispam', 'form_sfw_uniq_get_option', 1);
			$sfwgetoption = "<script>var form_sfw_uniq_get_option_js = $form_sfw_uniq_get_option</script>";
			$replaces['{SFWGETOPTION}'] = $sfwgetoption;
			
			// Debug
			if($this->debug){
				$debug = '<h1>Headers</h1>'
				         . var_export( apache_request_headers(), true )
				         . '<h1>REMOTE_ADDR</h1>'
				         . Server::get( 'REMOTE_ADDR' )
				         . '<h1>SERVER_ADDR</h1>'
				         . Server::get( 'REMOTE_ADDR' )
				         . '<h1>IP_ARRAY</h1>'
				         . var_export( $this->ip_array, true )
				         . '<h1>ADDITIONAL</h1>'
				         . var_export( $this->debug_data, true );
			}
			$replaces['{DEBUG}'] = isset( $debug ) ? $debug : '';
			
			foreach( $replaces as $place_holder => $replace ){
				$sfw_die_page = str_replace( $place_holder, $replace, $sfw_die_page );
			}
			
			die( $sfw_die_page );
			
		}

        die( "IP BLACKLISTED. Blocked by SFW " . $result['ip'] );

    }

}
