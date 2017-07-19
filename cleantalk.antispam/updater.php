<?php
$updater->CopyFiles('classes', 'modules/cleantalk.antispam/classes');
$updater->CopyFiles('docs', 'modules/cleantalk.antispam/docs');

//xdebug_break();

if( @file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/install/version.php') ){
    include($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/install/version.php');
	
    if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion) &&
		(	$arModuleVersion['VERSION'] == '1.0.1' ||
			$arModuleVersion['VERSION'] == '1.1.2' ||
			$arModuleVersion['VERSION'] == '1.1.3' ||
			$arModuleVersion['VERSION'] == '1.1.4' ||
			$arModuleVersion['VERSION'] == '1.1.5' ||
			$arModuleVersion['VERSION'] == '2.0.1' ||
			$arModuleVersion['VERSION'] == '2.0.2' ||
			$arModuleVersion['VERSION'] == '2.0.3' ||
			$arModuleVersion['VERSION'] == '2.0.5' ||
			$arModuleVersion['VERSION'] == '2.0.6' ||
			$arModuleVersion['VERSION'] == '2.0.7' ||
			$arModuleVersion['VERSION'] == '3.0.0' ||
			$arModuleVersion['VERSION'] == '3.1.0' ||
			$arModuleVersion['VERSION'] == '3.2.0' ||
			$arModuleVersion['VERSION'] == '3.3.0' ||
			$arModuleVersion['VERSION'] == '3.4.0' ||
			$arModuleVersion['VERSION'] == '3.5.0' ||
			$arModuleVersion['VERSION'] == '3.6.0' ||
			$arModuleVersion['VERSION'] == '3.7.0' ||
			$arModuleVersion['VERSION'] == '3.8.0' ||
			$arModuleVersion['VERSION'] == '3.8.1' ||
			$arModuleVersion['VERSION'] == '3.8.2' ||
			$arModuleVersion['VERSION'] == '3.8.3' ||
			$arModuleVersion['VERSION'] == '3.9.0' ||
			$arModuleVersion['VERSION'] == '3.9.1'
		)
    ){
		// Here is checking version of existing (old) code.
		if( @file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/install/index.php') ){
			include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/install/index.php');
			$obModule = new cleantalk_antispam();

			// Main problem is to make needed changes (like in install script) when module is INSTALLED ALREADY
			switch ($arModuleVersion['VERSION']) {
				case '1.0.1' : 
					// Version is too old, need to uninstall so user must run installation manually.
					if (IsModuleInstalled('blog'))
						UnRegisterModuleDependences('blog', 'OnBeforeCommentAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeCommentAddHandler');

					if (IsModuleInstalled('forum')){
						UnRegisterModuleDependences('forum', 'OnBeforeMessageAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeMessageAddHandler');
						UnRegisterModuleDependences('forum', 'OnAfterMessageAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnAfterMessageAddHandler');
						UnRegisterModuleDependences('forum', 'OnMessageModerate', 'cleantalk.antispam', 'CleantalkAntispam', 'OnMessageModerateHandler');
						UnRegisterModuleDependences('forum', 'OnBeforeMessageDelete', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeMessageDeleteHandler');
					}
					
					UnRegisterModuleDependences('main', 'OnBeforeUserRegister', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforeUserRegisterHandler');
					UnRegisterModuleDependences('main', 'OnEventLogGetAuditTypes', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEventLogGetAuditTypesHandler');
					UnRegisterModule('cleantalk.antispam');
					$obModule->UnInstallDB();
					$obModule->UnInstallFiles();
					$GLOBALS['errors'] = $obModule->errors;
					break;
				case '1.1.2' :
				case '1.1.3' :
				case '1.1.4' :
				case '1.1.5' :
					// We need to register just new dependencies and remove any template changes.
					if (IsModuleInstalled('cleantalk.antispam')){
						// New dependencies here are OnEndBufferContentHandler and treelikecomments
							RegisterModuleDependences('main', 'OnEndBufferContent', 'cleantalk.antispam', 'CleantalkAntispam', 'OnEndBufferContentHandler');
						if (IsModuleInstalled('prmedia.treelikecomments'))
							RegisterModuleDependences('prmedia.treelikecomments', 'OnBeforePrmediaCommentAdd', 'cleantalk.antispam', 'CleantalkAntispam', 'OnBeforePrmediaCommentAddHandler');
						// We don't need any template file changes, so just clear them all.
						$obModule->UnInstallFiles();
					}
					// break; -- NO break THERE
				case '2.0.1' :
				case '2.0.2' :
				case '2.0.3' :
					// We need to create additional table.
					// No need to run $obModule->InstallDB() because it drops existing module tables with useful data.
					if (IsModuleInstalled('cleantalk.antispam')){
						$DB->Query('DROP TABLE IF EXISTS cleantalk_checkjs');
						
						if(!$DB->Query('CREATE TABLE cleantalk_checkjs ( time_range varchar(10), js_values varchar(1024), PRIMARY KEY (time_range))'))
							$GLOBALS['errors'][] = GetMessage('CLEANTALK_ERROR_CREATE_SERVER');

					}
				case '2.0.5' :
				// No changes in tables and additional files since 2.0.5
				case '2.0.6' :
				// No changes in tables and additional files since 2.0.6
				case '2.0.7' :
				// No changes in tables and additional files since 2.0.7
				case '3.0.0' :
				// No changes in tables and additional files since 2.0.7
				case '3.1.0' :
				// No changes in tables and additional files since 3.0.0
				case '3.2.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.3.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.4.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.5.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.6.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.7.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.8.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.8.1' :
				// No changes in tables and additional files since 3.1.0
				case '3.8.2' :
				// No changes in tables and additional files since 3.1.0
				case '3.8.3' :
				// No changes in tables and additional files since 3.1.0
				case '3.9.0' :
				// No changes in tables and additional files since 3.1.0
				case '3.9.1' :
				// No changes in tables and additional files since 3.1.0
			}
			unset($obModule);
		}
		if( @file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/JSON.php') )
			unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/JSON.php');
		if( @file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/cleantalk.class.php') )
			unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/cleantalk.antispam/cleantalk.class.php');
	}
    unset($arModuleVersion);
}
