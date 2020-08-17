<?php

global $MESS;
IncludeModuleLangFile(__FILE__);

require_once(dirname(__FILE__) . '/../lib/Cleantalk/Common/API.php');

use Cleantalk\Common\API as CleantalkAPI;

/**
 * Installer for CleanTalk module
 *
 * @author 	Cleantalk team <http://cleantalk.org>
 */
class cleantalk_antispam extends CModule {

    var $MODULE_ID = 'cleantalk.antispam';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;
    var $PARTNER_NAME;
    var $PARTNER_URI;

    var $template_dir;
    var $template_file;
    var $system_template_dir;
    var $local_template_dir;
    var $local_compo_template_dir;
    var $pattern;
    var $ct_template_addon_tag;
    var $ct_template_addon_body;
    var $errors;
    var $messages;
    var $template_messages;

    function cleantalk_antispam() {
        global $DOCUMENT_ROOT;
        $arModuleVersion = array();

		$path = str_replace("\\", "/", __FILE__);
		$path = substr($path, 0, strlen($path) - strlen("/index.php"));
		include($path."/version.php");
			if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
				$this->MODULE_VERSION = $arModuleVersion["VERSION"];
				$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
			} else {
				$this->MODULE_VERSION = "3.3.0";
				$this->MODULE_VERSION_DATE = "2015-11-03 00:00:00";
			}
			$this->MODULE_NAME = GetMessage('CLEANTALK_MODULE_NAME');
			$this->MODULE_DESCRIPTION = GetMessage('CLEANTALK_MODULE_DESCRIPTION');
		$this->PARTNER_NAME = "CleanTalk"; 
		$this->PARTNER_URI = "http://www.cleantalk.org";

		// Values for all templates
		$this->ct_template_addon_tag = 'CLEANTALK template addon';
		$this->ct_template_addon_body = "\n" . '<?php \Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID("area"); if(CModule::IncludeModule("cleantalk.antispam")) echo CleantalkAntispam::FormAddon(); ?>' . "\n";

		// Values for templates folder
		$this->SAR_template_file = 'footer.php';
		//...with ending slash
        $this->SAR_bitrix_template_dir = $DOCUMENT_ROOT.'/bitrix/templates/';
		$this->SAR_local_template_dir  = $DOCUMENT_ROOT.'/local/templates/';
		$this->SAR_pattern = '/(<\/body>)/i';

		$this->errors = array();
		$this->messages = array();
		$this->template_messages = array();
    }

    function DoInstall() {
        global $DOCUMENT_ROOT, $APPLICATION;
		
		//Installng DB
        if($this->InstallDB() && $this->InstallFiles()){	
			RegisterModule('cleantalk.antispam');
            RegisterModuleDependences('main', 'OnPageStart', 'cleantalk.antispam', 'CleantalkAntispam', 'OnPageStartHandler');
            RegisterModuleDependences('main', 'OnEventLogGetAuditTypes', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEventLogGetAuditTypesHandler');
			RegisterModuleDependences('main', 'OnBeforeUserRegister', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
            RegisterModuleDependences('main', 'OnBeforeUserSimpleRegister', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
            RegisterModuleDependences('main', 'OnBeforeUserAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
			RegisterModuleDependences('main', 'OnEndBufferContent', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEndBufferContentHandler');
            if (IsModuleInstalled('blog')){
              RegisterModuleDependences('blog', 'OnBeforeCommentAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeCommentAddHandler');
            }
            if (IsModuleInstalled('forum')){
              RegisterModuleDependences('forum', 'OnBeforeMessageAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeMessageAddHandler');
              RegisterModuleDependences('forum', 'OnAfterMessageAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnAfterMessageAddHandler');
              RegisterModuleDependences('forum', 'OnMessageModerate', 'cleantalk.antispam', 'CleantalkAntispam', 'OnMessageModerateHandler');
              RegisterModuleDependences('forum', 'OnBeforeMessageDelete', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeMessageDeleteHandler');
			  RegisterModuleDependences('forum', 'onBeforePMSend', 'cleantalk.antispam', 'CleantalkAntispam', 'onBeforePMSendHandler');
            }
            if (IsModuleInstalled('prmedia.treelikecomments')){
              RegisterModuleDependences('prmedia.treelikecomments', 'OnBeforePrmediaCommentAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforePrmediaCommentAddHandler');
            }
            if (IsModuleInstalled('bitrix.eshop'))
			{
				RegisterModuleDependences('sale', 'OnBeforeOrderAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeOrderAddHandler');
			} 
			if (IsModuleInstalled('form'))
			{
				RegisterModuleDependences('form', 'OnBeforeResultAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeResultAddHandler');
			} 
		}
		
		//Checking API key if already set
		$api_key = COption::GetOptionString( 'cleantalk.antispam', 'key', '');
		$form_sfw = COption::GetOptionInt( 'cleantalk.antispam', 'form_sfw', 0 );
		
		$result = CleantalkAPI::method__notice_paid_till($api_key, preg_replace('/http[s]?:\/\//', '', $_SERVER['HTTP_HOST'], 1));
		COption::SetOptionInt( 'cleantalk.antispam', 'key_is_ok', isset($result['valid']) && $result['valid'] == '1' ? 1 : 0);

		//Remote calls
		if (!COption::GetOptionString('cleantalk.antispam', 'remote_calls', '')) {
			COption::SetOptionString('cleantalk.antispam', 'remote_calls', json_encode(array('close_renew_banner' => array('last_call' => 0), 'sfw_update' => array('last_call' => 0), 'sfw_send_logs' => array('last_call' => 0), 'update_plugin' => array('last_call' => 0))));
		}
		if(!empty($this->template_messages)){
			$this->messages[] = GetMessage("CLEANTALK_TEMPLATES_HEADER");
			foreach($this->template_messages as $val)
			$this->messages[] = $val;
			$this->messages[] = '<br />' . GetMessage("CLEANTALK_TEMPLATES_FOOTER") . '<br />';
		}
		$GLOBALS["errors"] = $this->errors;
		$GLOBALS["messages"] = $this->messages;
		$APPLICATION->IncludeAdminFile(GetMessage('CLEANTALK_INSTALL_TITLE'), $DOCUMENT_ROOT.'/bitrix/modules/cleantalk.antispam/install/step.php');		
    }

    function DoUninstall() {
        global $DOCUMENT_ROOT, $APPLICATION;

        if (IsModuleInstalled('blog')){
          UnRegisterModuleDependences('blog', 'OnBeforeCommentAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeCommentAddHandler');
        }
        if (IsModuleInstalled('forum')){
          UnRegisterModuleDependences('forum', 'OnBeforeMessageAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeMessageAddHandler');
          UnRegisterModuleDependences('forum', 'OnAfterMessageAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnAfterMessageAddHandler');
          UnRegisterModuleDependences('forum', 'OnMessageModerate', 'cleantalk.antispam', 'CleantalkAntispam', 'OnMessageModerateHandler');
          UnRegisterModuleDependences('forum', 'OnBeforeMessageDelete', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeMessageDeleteHandler');
        }
        if (IsModuleInstalled('prmedia.treelikecomments')){
          UnRegisterModuleDependences('prmedia.treelikecomments', 'OnBeforePrmediaCommentAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforePrmediaCommentAddHandler');
        }
        UnRegisterModuleDependences('main', 'OnBeforeUserRegister', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
        UnRegisterModuleDependences('main', 'OnBeforeUserSimpleRegister', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
        UnRegisterModuleDependences('main', 'OnBeforeUserAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
        UnRegisterModuleDependences('main', 'OnEventLogGetAuditTypes', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEventLogGetAuditTypesHandler');
        UnRegisterModuleDependences('main', 'OnEndBufferContent', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEndBufferContentHandler');
		if (IsModuleInstalled('form')){
			UnRegisterModuleDependences('form', 'OnBeforeResultAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeResultAddHandler');
		} 
        UnRegisterModule('cleantalk.antispam');
        $this->UnInstallDB();
        $this->UnInstallFiles();
		if(!empty($this->template_messages)){
			$this->messages[] = GetMessage("CLEANTALK_TEMPLATES_HEADER");
			foreach($this->template_messages as $val)
			$this->messages[] = $val;
			$this->messages[] = '<br />' . GetMessage("CLEANTALK_TEMPLATES_FOOTER") . '<br />';
		}
        $GLOBALS["errors"] = $this->errors;
        $GLOBALS["messages"] = $this->messages;
        $APPLICATION->IncludeAdminFile(GetMessage('CLEANTALK_UNINSTALL_TITLE'), $DOCUMENT_ROOT.'/bitrix/modules/cleantalk.antispam/install/unstep.php');
    }

    function InstallFiles() {
     
	    $results = $this->install_ct_template__in_dirs(
		    $this->SAR_template_file,
		    array(
			    $this->SAR_bitrix_template_dir,
			    $this->SAR_local_template_dir,
			    /** @todo (.../bitrix/templates/.default/components/bitrix/system.auth.registration/) research it */
			    // Copy system.auth.registration default template from system dir to local dir and insert addon into
		    ),
		    $this->SAR_pattern,
		    $this->ct_template_addon_tag,
		    $this->ct_template_addon_body
	    );
	    
	    foreach ($results as $dir => $result){
	    	if($result != 0){
			    error_log('CLEANTALK_ERROR: INSTALLING_IN_TEMPLATE_FILES: ' . $dir . sprintf('%02d', $result ));
		    }
	    }
	
		return true;
    }

    function UnInstallFiles() {
	
	    $results = $this->uninstall_ct_template__in_dirs(
		    $this->SAR_template_file,
		    array(
			    $this->SAR_bitrix_template_dir,
			    $this->SAR_local_template_dir,
			    /** @todo (.../bitrix/templates/.default/components/bitrix/system.auth.registration/) research it */
			    // Copy system.auth.registration default template from system dir to local dir and insert addon into
	        ),
		    $this->ct_template_addon_tag
	    );
	
	    foreach ($results as $dir => $result){
		    if($result != 0){
			    error_log('CLEANTALK_ERROR: UNINSTALLING_IN_TEMPLATE_FILES: ' . $dir . sprintf('%02d', $result ));
		    }
	    }

		return true;
    }

    function InstallDB() {
		
		global $DB;
		
		// Creating SFW DATA
		$result = $DB->Query(
			"CREATE 
			TABLE IF NOT EXISTS `cleantalk_sfw` (
				`network` int(11) unsigned NOT NULL,
				`mask` int(11) unsigned NOT NULL,
				`status` tinyint(1) NOT NULL DEFAULT 0,
				INDEX (  `network` ,  `mask` )
			)
			ENGINE = MYISAM ;"
		);
		if(!$result){
			$this->errors[] = GetMessage('CLEANTALK_ERROR_CREATE_SFW_DATA');
			return FALSE;
		}
		
		// Creating SFW LOGS
		$result = $DB->Query(
			"CREATE 
			TABLE IF NOT EXISTS `cleantalk_sfw_logs` (
				`ip` VARCHAR(15) NOT NULL,
				`all_entries` INT NOT NULL,
				`blocked_entries` INT NOT NULL,
				`entries_timestamp` INT NOT NULL,
				PRIMARY KEY (`ip`)
			)
			ENGINE = MYISAM ;"
		);
		if(!$result){
			$this->errors[] = GetMessage('CLEANTALK_ERROR_CREATE_SFW_LOGS');
			return FALSE;
		}
		
		// Creating TIMELABELS
		$result = $DB->Query(
			'CREATE 
			TABLE IF NOT EXISTS cleantalk_timelabels (
				ct_key varchar(255),
				ct_value int(11),
				PRIMARY KEY (ct_key)
			)'
		);
		if(!$result){
			$this->errors[] = GetMessage('CLEANTALK_ERROR_CREATE_TIMELABELS');
			return FALSE;
		}
		
		// Creating CIDS
		$result = $DB->Query(
			'CREATE
			TABLE IF NOT EXISTS cleantalk_cids (
				module varchar(255),
				cid int(11),
				ct_request_id varchar(255),
				ct_result_comment varchar(255),
				PRIMARY KEY (module, cid)
			);'
		);
		if(!$result){
			$this->errors[] = GetMessage('CLEANTALK_ERROR_CREATE_CIDS');
			return FALSE;
		}
		
		// Creating SERVER
		$result = $DB->Query(
			'CREATE 
			TABLE IF NOT EXISTS cleantalk_server (
				work_url varchar(255),
				server_url varchar(255),
				server_ttl int(11),
				server_changed int(11)
			);'
		);
		if(!$result){
			$this->errors[] = GetMessage('CLEANTALK_ERROR_CREATE_SERVER');
			return FALSE;
		}
		
		// Creating CHECKJS
		$result = $DB->Query(
			'CREATE 
			TABLE IF NOT EXISTS cleantalk_checkjs (
				time_range varchar(10),
				js_values varchar(1024),
				PRIMARY KEY (time_range)
			);'
		);
		if(!$result){
			$this->errors[] = GetMessage('CLEANTALK_ERROR_CREATE_SERVER');
			return FALSE;
		}
		return TRUE;
    }

    function UnInstallDB($arParams = Array()) {
		global $DB;
		$DB->Query('DROP TABLE IF EXISTS cleantalk_sfw');
		$DB->Query('DROP TABLE IF EXISTS cleantalk_sfw_logs');
		$DB->Query('DROP TABLE IF EXISTS cleantalk_timelabels');
		$DB->Query('DROP TABLE IF EXISTS cleantalk_cids');
		$DB->Query('DROP TABLE IF EXISTS cleantalk_server');
		$DB->Query('DROP TABLE IF EXISTS cleantalk_checkjs');
		return TRUE;
    }
	
	/**
	 * Wrapper for cleantalk_antispam::install_ct_template__in_dir()
	 * Allows to pass an array into it
	 *
	 * @param $template_file
	 * @param $template_dirs
	 * @param $pattern
	 * @param $ct_template_addon_tag
	 * @param $ct_template_addon_body
	 *
	 * @return array with error codes for each directory. 0 on success.
	 */
    function install_ct_template__in_dirs($template_file, $template_dirs, $pattern, $ct_template_addon_tag, $ct_template_addon_body){
    	$out = array();
	    foreach ( $template_dirs as $template_dir ){
    	    $out[$template_dir] =  $this->install_ct_template__in_dir($template_file, $template_dir, $pattern, $ct_template_addon_tag, $ct_template_addon_body);
    	}
	    return $out;
    }
	
	/**
	 * Copies needed template from system dir to local dir and inserts CleanTalk addon into it
	 *
	 * @param string $template_file            Name of component's template file (template.php)
	 * @param string $template_dir             Name of component's template dir (.default) with ending slash
	 * @param string $pattern                  PCRE pattern to find place to insert CleanTalk addon before
	 * @param string $ct_template_addon_tag    Tag string to mark CleanTalk addon body
	 * @param string $ct_template_addon_body   HTML text of CleanTalk addon itself
	 *
	 * @return    int Returns error code or 0 when success
	 */
	function install_ct_template__in_dir( $template_file, $template_dir, $pattern, $ct_template_addon_tag, $ct_template_addon_body ){
		
		// Check system folders
		if(!file_exists($template_dir)){
			// No required system folders
			return 0;
		}
		$all_templates_folder = glob( $template_dir . '*' , GLOB_ONLYDIR);
		
		if (file_exists( $template_dir . '.default'))
			$all_templates_folder[] = $template_dir . '.default';
		
		foreach ($all_templates_folder as $current_template){
			
			// Exception for template mail templates
			// By type
			$description_file = $current_template . '/description.php';
			if( file_exists( $description_file ) ){
				require_once( $description_file );
				if( isset( $arTemplate, $arTemplate['TYPE'] ) && $arTemplate['TYPE'] == 'mail' )
					continue;
			}
			// By name
			// Deleting mail template
			if( in_array( $current_template, array( 'mail_user' ) ) )
				continue;
			
			$template_file_path = $current_template.'/'.$template_file;
			
			$start_pattern = '<!-- ' . $ct_template_addon_tag . ' -->'; // don't change this!
			$end_pattern   = '<!-- /' . $ct_template_addon_tag . ' -->'; // don't change this!
			
			$result = $this->ct_file__clean_up( $template_file_path, $start_pattern, $end_pattern );
			if( $result === true ){
				
				// Check is it parsable
				$template_content = file_get_contents( $template_file_path );
				if( $template_content ){
					
					if( preg_match( $pattern, $template_content ) === 1 ){
						
						$ct_template_addon = $start_pattern . $ct_template_addon_body . $end_pattern . "\n";
						$template_content = preg_replace($pattern, $ct_template_addon . '${1}', $template_content, 1);
						
						if( file_put_contents( $template_file_path, $template_content ) ){
						
						}else
							return 9; // Cannot write new content to template PHP file
					}else
						return 10;
				}else
					return 5;
			}else
				return $result;
		}
		// Here all is OK - new template PHP file with CLEANTALK addon inserted is ready
		return 0;
    }
	
	/**
	 * Wrapper for cleantalk_antispam::install_ct_template__in_dir()
	 * Allows to pass an array into it
	 *
	 * @param $template_file
	 * @param $template_dirs
	 * @param $ct_template_addon_tag
	 *
	 * @return array with error codes for each directory. 0 on success.
	 */
	function uninstall_ct_template__in_dirs( $template_file, $template_dirs, $ct_template_addon_tag ){
		$out = array();
		foreach ( $template_dirs as $template_dir ){
			$out[$template_dir] =  $this->uninstall_ct_template__in_dir( $template_file, $template_dir, $ct_template_addon_tag );
		}
		return $out;
	}
	
	/**
	 * Remove addon from needed local component template
	 *
	 * @param string $template_file         Name of component's template file (template.php)
	 * @param string $template_dir          Name of component's template dir (.default)
	 * @param string $ct_template_addon_tag Tag string to mark CleanTalk addon body
	 *
	 * @return    int Returns error code or 0 when success
	 */
	function uninstall_ct_template__in_dir( $template_file, $template_dir, $ct_template_addon_tag ){
		
		// Check system folders
		if(!file_exists($template_dir)){
			// No required system folders
			return 1;
		}
		$all_templates_folder = glob( $template_dir . '*' , GLOB_ONLYDIR);

		if (file_exists( $template_dir . '.default'))
			$all_templates_folder[] = $template_dir . '.default';
		foreach ($all_templates_folder as $current_template){
			
			$template_file_path = $current_template.'/'.$template_file;
			
			$start_pattern = '<!-- ' . $ct_template_addon_tag . ' -->'; // don't change this!
			$end_pattern   = '<!-- /' . $ct_template_addon_tag . ' -->'; // don't change this!
			
			$result = $this->ct_file__clean_up( $template_file_path, $start_pattern, $end_pattern );
			if($result !== true )
				return $result;

		}
		// Here all is OK - new template PHP file with CLEANTALK addon inserted is ready
		return 0;
	}
	
	/**
	 * @param $file_path
	 * @param $start_pattern
	 * @param $end_pattern
	 *
	 * @return bool|int
	 */
	function ct_file__clean_up( $file_path, $start_pattern, $end_pattern ){
		
		// Last check - template PHP file
		if( is_file( $file_path ) || is_writable( $file_path ) ){
			
			// Try to get template PHP file content
			$file_content = file_get_contents( $file_path );
			
			if( $file_content ){
					
				// Clean all CLEANTALK template addons
				$pos_begin = strpos( $file_content, $start_pattern );
				$pos_end   = strpos( $file_content, $end_pattern   );
				
				// Nothing to clean
				if($pos_begin === false && $pos_end === false)
					return true;
				
				if( $pos_begin !== false ){
					if( $pos_end !== false ){
						if( $pos_begin < $pos_end ){
							
							// Cleaning up
							$file_content = substr( $file_content, 0, $pos_begin ) . substr( $file_content, $pos_end + strlen( $end_pattern ) );
							// $file_content = substr( $file_content, 0, $pos_begin ) . substr( $file_content, $pos_end + strlen( '<!-- /' . $tag . ' -->' ) );
							
							if( file_put_contents( $file_path, $file_content ) ){
								return true;
							}else
								return 9; // Cannot write new content to template PHP file
						}else
							return 8; // Can't parse template PHP file
					}else
						return 7; // Can't find end
				}else
					return 6; // Can't find start
			}else
				return 5; // Can't read from template PHP file
		}else
			return 4; // No template PHP file
	}
	
}
