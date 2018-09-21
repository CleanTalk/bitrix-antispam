<?php

global $MESS;
IncludeModuleLangFile(__FILE__);

require_once(dirname(__FILE__) . '/../classes/general/CleantalkHelper.php');

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
		$this->ct_template_addon_body = "\n" . '<?php \Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID("area"); if(CModule::IncludeModule("cleantalk.antispam")) echo CleantalkAntispam::FormAddon(); \Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID("area", "Loading..."); ?>' . "\n";

		// Values for templates folder
		$this->SAR_template_file = 'footer.php';
		//...with ending slash
		$this->SAR_local_template_dir = $DOCUMENT_ROOT.'/bitrix/templates/';
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
		
		//Adding agents
		if(COption::GetOptionString( 'cleantalk.antispam', 'form_sfw', 0 )){
			CAgent::AddAgent("CleantalkAntispam::sfw_send_logs();", "cleantalk.antispam", "N", 3600);
			CAgent::AddAgent("CleantalkAntispam::sfw_update();",    "cleantalk.antispam", "N", 86400);
		}
		//Checking API key if already set
		$api_key = COption::GetOptionString( 'cleantalk.antispam', 'key', '');
		$form_sfw = COption::GetOptionString( 'cleantalk.antispam', 'form_sfw', 0 );
		
		if($api_key && $api_key != ''){
			$result = CleantalkHelper::api_method__notice_validate_key($api_key);
			COption::SetOptionString( 'cleantalk.antispam', 'key_is_ok', isset($result['valid']) && $result['valid'] == '1' ? '1' : '0');
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
		CAgent::RemoveModuleAgents("cleantalk.antispam");
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
        UnRegisterModuleDependences('main', 'OnEventLogGetAuditTypes', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEventLogGetAuditTypesHandler');
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
		$ret_val = TRUE;
		// Copy system.auth.registration default template from system dir to local dir and insert addon into
		$SAR_res = $this->install_ct_template(
				$this->SAR_template_file,
				$this->SAR_local_template_dir,
				$this->SAR_pattern,
				$this->ct_template_addon_tag,
				$this->ct_template_addon_body
		);
		if($SAR_res != 0){
		    $this->errors[] = GetMessage('CLEANTALK_ERROR_FILES_'.sprintf('%02d', $SAR_res));
		    $ret_val = FALSE;
		}
	
		return $ret_val;
    }

    function UnInstallFiles() {
    	$ret_val = TRUE;
		// Remove addon from local system.auth.registration default template
		$SAR_res = $this->uninstall_ct_template(
				$this->SAR_template_file,
				$this->SAR_local_template_dir,
				$this->SAR_pattern,
				$this->ct_template_addon_tag,
				$this->ct_template_addon_body
		);
		if($SAR_res != 0){
		    $this->errors[] = GetMessage('CLEANTALK_ERROR_FILES_'.sprintf('%02d', $SAR_res));
		    $ret_val = FALSE;
		}

		return $ret_val;
    }

    function InstallDB() {
		
		global $DB;
		
		// Creating SFW DATA
		$result = $DB->Query(
			"CREATE 
			TABLE IF NOT EXISTS `cleantalk_sfw` (
				`network` int(11) unsigned NOT NULL,
				`mask` int(11) unsigned NOT NULL,
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
     * Copies needed template from system dir to local dir and inserts CleanTalk addon into it
     *
     * @param 	&string $template_dir			Name of component's template dir (.default)
     * @param 	&string $template_file			Name of component's template file (template.php)
     * @param 	&string $system_template_dir		Full system dir of component templates (.../bitrix/components/bitrix/system.auth.registration/templates/)
     * @param 	&string $local_template_dir		Full local dir of templates (.../bitrix/templates/.default/)
     * @param 	&string $local_compo_template_dir	Full local dir of component template (.../bitrix/templates/.default/components/bitrix/system.auth.registration/)
     * @param 	&string $pattern			PCRE pattern to find place to insert CleanTalk addon before
     * @param 	&string $ct_template_addon_tag		Tag string to mark CleanTalk addon body
     * @param 	&string $ct_template_addon_body		HTML text of CleanTalk addon itself
     * @return 	int Returns error code or 0 when success
     */
    function install_ct_template(
			     $template_file,
			     $local_template_dir,	// with ending slash
			     $pattern,
			     $ct_template_addon_tag,
			     $ct_template_addon_body)
    {
		// Check system folders
		if(!file_exists($local_template_dir)){
			// No required system folders
			return 1;
		}
		$all_templates_folder = glob($local_template_dir . '/*' , GLOB_ONLYDIR);

		if (file_exists($local_template_dir .'/.default'))
			$all_templates_folder[] = $local_template_dir .'/.default';

		foreach ($all_templates_folder as $current_template)
		{
			$template_file_path = $current_template.'/'.$template_file;
			// Last check - template PHP file
			if(!file_exists($template_file_path) || !is_file($template_file_path) || !is_writable($template_file_path)){
				// No template PHP file
				return 4;
			}

			// Here we are sure that
			// bitrix/templates/<template>/components/bitrix/<component>/<template>/<file>.php
			// exists and writable

			// Try to get template PHP file content
			$template_content = file_get_contents($template_file_path);
			if($template_content === FALSE){
				// Cannot read from template PHP file
				return 5;
			}

			// Check is it parsable
			if(!preg_match($pattern, $template_content) === 1){
				// Cannot find pattern for addon inserting in template PHP file
				return 6;
			}			
			// First clean all previous CLEANTALK template addons
			$ct_template_addon_begin = '<!-- ' . $ct_template_addon_tag . ' -->';	// don't change this!
			$ct_template_addon_end   = '<!-- /' . $ct_template_addon_tag . ' -->';	// don't change this!

			$pos_begin = strpos($template_content, $ct_template_addon_begin);
			$pos_end   = strpos($template_content, $ct_template_addon_end);

			if($pos_begin !== FALSE && $pos_end === FALSE){
				// Cannot parse template PHP file - old CLEANTALK open tag exists only
				return 7;
			}elseif($pos_begin === FALSE && $pos_end !== FALSE){
				// Cannot parse template PHP file - old CLEANTALK close tag exists only
				return 8;
			}elseif($pos_begin !== FALSE && $pos_end !== FALSE){
				if($pos_begin < $pos_end){
					// Cleaning needed
					$template_content = substr($template_content, 0, $pos_begin) . substr($template_content, $pos_end + strlen($ct_template_addon_end));
				}else{
					// Cannot parse template PHP file - old CLEANTALK close tag before open tag
					return 9;
				}
			//}elseif($pos_begin === FALSE && $pos_end === FALSE){
			//	// Nothing to clean
			}
			// Second add current CLEANTALK template addon

			$ct_template_addon = $ct_template_addon_begin . $ct_template_addon_body . $ct_template_addon_end . "\n\n";

			$template_content = preg_replace($pattern, $ct_template_addon . '${1}', $template_content, 1);

			if(!file_put_contents($template_file_path, $template_content)){
				// Cannot write new content to template PHP file
				return 10;
			}

		}
		// Here all is OK - new template PHP file with CLEANTALK addon inserted is ready
		return 0;
    }
    /**
     * Remove addon from needed local component template
     *
     * @param 	&string $template_dir			Name of component's template dir (.default)
     * @param 	&string $template_file			Name of component's template file (template.php)
     * @param 	&string $local_compo_template_dir	Full local dir of component template (.../bitrix/templates/.default/components/bitrix/system.auth.registration/)
     * @param 	&string $ct_template_addon_tag		Tag string to mark CleanTalk addon body
     * @return 	int Returns error code or 0 when success
     */
    function uninstall_ct_template(
    			 $template_file,
			     $local_template_dir,
			     $pattern, 
			     $ct_template_addon_tag,
			     $ct_template_addon_body)
    {
		// Check system folders
		if(!file_exists($local_template_dir)){
			// No required system folders
			return 1;
		}
		$all_templates_folder = glob($local_template_dir . '/*' , GLOB_ONLYDIR);

		if (file_exists($local_template_dir .'/.default'))
			$all_templates_folder[] = $local_template_dir .'/.default';
		foreach ($all_templates_folder as $current_template)
		{
			$template_file_path = $current_template.'/'.$template_file;
			// Last check - template PHP file
			if(!file_exists($template_file_path) || !is_file($template_file_path) || !is_writable($template_file_path)){
				// No template PHP file
				return 4;
			}

			// Here we are sure that
			// bitrix/templates/<template>/components/bitrix/<component>/<template>/<file>.php
			// exists and writable

			// Try to get template PHP file content
			$template_content = file_get_contents($template_file_path);
			if($template_content === FALSE){
				// Cannot read from template PHP file
				return 5;
			}

			// Check is it parsable
			if(!preg_match($pattern, $template_content) === 1){
				// Cannot find pattern for addon inserting in template PHP file
				return 6;
			}			
			// Clean all CLEANTALK template addons
			$ct_template_addon_begin = '<!-- ' . $ct_template_addon_tag . ' -->';	// don't change this!
			$ct_template_addon_end   = '<!-- /' . $ct_template_addon_tag . ' -->';	// don't change this!

			$pos_begin = strpos($template_content, $ct_template_addon_begin);
			$pos_end   = strpos($template_content, $ct_template_addon_end);

			if($pos_begin !== FALSE && $pos_end === FALSE){
				// Cannot parse template PHP file
				return 7;
			}elseif($pos_begin === FALSE && $pos_end !== FALSE){
				// Cannot parse template PHP file
				return 8;
			}elseif($pos_begin !== FALSE && $pos_end !== FALSE){
				if($pos_begin < $pos_end){
					// Cleaning needed
					$template_content = substr($template_content, 0, $pos_begin) . substr($template_content, $pos_end + strlen($ct_template_addon_end));
				}else{
					// Cannot parse template PHP file
					return 9;
				}
			//}elseif($pos_begin === FALSE && $pos_end === FALSE){
			//	// Nothing to clean
			}
			if(!file_put_contents($template_file_path, $template_content)){
				// Cannot write new content to template PHP file
				return 10;
			}

		}
		// Here all is OK - new template PHP file with CLEANTALK addon inserted is ready
		return 0;
	}
	
}