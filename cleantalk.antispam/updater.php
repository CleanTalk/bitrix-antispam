<?php
if(IsModuleInstalled('cleantalk.antispam'))
{
	// Values for all templates
	$ct_template_addon_tag = 'CLEANTALK template addon';
	$ct_template_addon_body = "\n" . '<?php \Bitrix\Main\Page\Frame::getInstance()->startDynamicWithID("area"); if(CModule::IncludeModule("cleantalk.antispam")) echo CleantalkAntispam::FormAddon(); \Bitrix\Main\Page\Frame::getInstance()->finishDynamicWithID("area", "Loading..."); ?>' . "\n";

	// Values for templates folder
	$template_file = 'footer.php';
	//...with ending slash
	$local_template_dir = $DOCUMENT_ROOT.'/bitrix/templates/';
	$pattern = '/(<\/body>)/i';
	// Check system folders
	if(!file_exists($local_template_dir)){
		// No required system folders
		return;
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
			return;
		}

		// Here we are sure that
		// bitrix/templates/<template>/components/bitrix/<component>/<template>/<file>.php
		// exists and writable

		// Try to get template PHP file content
		$template_content = file_get_contents($template_file_path);
		if($template_content === FALSE){
			// Cannot read from template PHP file
			return;
		}

		// Check is it parsable
		if(!preg_match($pattern, $template_content) === 1){
			// Cannot find pattern for addon inserting in template PHP file
			return;
		}			
		// First clean all previous CLEANTALK template addons
		$ct_template_addon_begin = '<!-- ' . $ct_template_addon_tag . ' -->';	// don't change this!
		$ct_template_addon_end   = '<!-- /' . $ct_template_addon_tag . ' -->';	// don't change this!

		$pos_begin = strpos($template_content, $ct_template_addon_begin);
		$pos_end   = strpos($template_content, $ct_template_addon_end);

		if($pos_begin !== FALSE && $pos_end === FALSE){
			// Cannot parse template PHP file - old CLEANTALK open tag exists only
			return;
		}elseif($pos_begin === FALSE && $pos_end !== FALSE){
			// Cannot parse template PHP file - old CLEANTALK close tag exists only
			return;
		}elseif($pos_begin !== FALSE && $pos_end !== FALSE){
			if($pos_begin < $pos_end){
				// Cleaning needed
				$template_content = substr($template_content, 0, $pos_begin) . substr($template_content, $pos_end + strlen($ct_template_addon_end));
			}else{
				// Cannot parse template PHP file - old CLEANTALK close tag before open tag
				return;
			}
		//}elseif($pos_begin === FALSE && $pos_end === FALSE){
		//	// Nothing to clean
		}
		// Second add current CLEANTALK template addon

		$ct_template_addon = $ct_template_addon_begin . $ct_template_addon_body . $ct_template_addon_end . "\n\n";

		$template_content = preg_replace($pattern, $ct_template_addon . '${1}', $template_content, 1);

		if(!file_put_contents($template_file_path, $template_content)){
			// Cannot write new content to template PHP file
			return;
		}

	}	
}
