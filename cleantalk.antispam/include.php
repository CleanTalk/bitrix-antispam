<?php
global $MESS;
IncludeModuleLangFile(__FILE__);

// Fixes for unexisting functions
require_once(dirname(__FILE__) . '/lib/phpFix.php');

// Base classes
require_once(dirname(__FILE__) . '/lib/Cleantalk/Antispam/Cleantalk.php');
require_once(dirname(__FILE__) . '/lib/Cleantalk/Antispam/CleantalkRequest.php');
require_once(dirname(__FILE__) . '/lib/Cleantalk/Antispam/CleantalkResponse.php');
require_once(dirname(__FILE__) . '/lib/Cleantalk/Antispam/SFW.php');

// Common classes
require_once(dirname(__FILE__) . '/lib/Cleantalk/Common/Helper.php');
require_once(dirname(__FILE__) . '/lib/Cleantalk/Common/API.php');

// SFW class
require_once(dirname(__FILE__) . '/lib/Cleantalk/ApbctBitrix/SFW.php');

// Custom config
require_once(dirname(__FILE__) . '/custom_config.php');


//Antispam classes
use Cleantalk\Antispam\Cleantalk as Cleantalk;
use Cleantalk\Antispam\CleantalkRequest as CleantalkRequest;
use Cleantalk\Antispam\CleantalkRequest as CleantalkResponse;

//Bitrix classes
use Cleantalk\ApbctBitrix\SFW as CleantalkSFW;

//Common classes
use Cleantalk\Common\API as CleantalkAPI;
use Cleantalk\Common\Helper as CleantalkHelper;


if ( ! defined( 'CLEANTALK_USER_AGENT' ) )
    define( 'CLEANTALK_USER_AGENT', 'bitrix-3118' );

/**
 * CleanTalk module class
 *
 * @author  CleanTalk team <http://cleantalk.org>
 */

class CleantalkAntispam {

    const KEYS_NUM = 12; // 12 last JS keys are valid

    const APBCT_REMOTE_CALL_SLEEP = 10;
    
    /*
     * Updates SFW local database
     */
    static public function sfw_update($access_key)
    {
      $sfw = new CleantalkSFW($access_key);

      $file_urls = isset($_GET['file_urls']) ? urldecode( $_GET['file_urls'] ) : null;
      $file_urls = isset($file_urls) ? explode(',', $file_urls) : null;

      if (!$file_urls) {
        $result = $sfw->sfw_update();
      } else {
        if (is_array($file_urls) && count($file_urls)) {

          $result = $sfw->sfw_update($file_urls[0]);

          if(empty($result['error'])){

            array_shift($file_urls);

            if (count($file_urls)) {
              CleantalkHelper::http__request(
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'],
                array(
                  'spbc_remote_call_token'  => md5($access_key),
                  'spbc_remote_call_action' => 'sfw_update',
                  'plugin_name'             => 'apbct',
                  'file_urls'               => implode(',', $file_urls),
                ),
                array('get', 'async')
              );
            } else {
                COption::SetOptionInt( 'cleantalk.antispam', 'sfw_last_update', time());
            }
          } else
            return array('error' => 'ERROR_WHILE_INSERTING_SFW_DATA');
        }
      }
      return $result;
    }

    /*
     * Sends and clean local logs storage
     */
    static public function sfw_send_logs($access_key)
    {
      $sfw = new CleantalkSFW($access_key);
      $result = $sfw->send_logs();
      COption::SetOptionInt( 'cleantalk.antispam', 'sfw_last_send_log', time());
    }
    
    /**
     * Show message when spam is blocked
     * @param string message
     */
    
    static function CleantalkDie($message)
    {
        if( isset( $_POST['feedback_type'] ) && $_POST['feedback_type'] == 'buyoneclick' ) {
	
	        $result = Array( 'error' => true, 'msg' => 'js_kr_error_send' );
	        print json_encode( $result );
	        
        // AJAX response
        }elseif( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest'){
	
	        die(json_encode(array(
	        	'apbct' => array(
			        'blocked' => true,
			        'comment' => $message,
	            ),
		        'error' => array(
		        	'msg' => $message,
		        )
	        )));
        
        }else{
	
	        $error_tpl = file_get_contents( dirname( __FILE__ ) . "/error.html" );
	        print str_replace( '%ERROR_TEXT%', $message, $error_tpl );
         
        }
        
        die();
    }
    /*
    * Get data from submit recursively
    */
    static public function CleantalkGetFields($arr, $message=array(), $email = null, $nickname = array('nick' => '', 'first' => '', 'last' => ''), $subject = null, $contact = true, $prev_name = '')
    {
      //Skip request if fields exists
      $skip_params = array(
          'ipn_track_id',   // PayPal IPN #
          'txn_type',     // PayPal transaction type
          'payment_status',   // PayPal payment status
          'ccbill_ipn',     // CCBill IPN 
        'ct_checkjs',     // skip ct_checkjs field
        'api_mode',         // DigiStore-API
        'loadLastCommentId', // Plugin: WP Discuz. ticket_id=5571
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
        'avatar__file_image_data',
        'sessid',
        'soa-action',
        'location_type',
        'BUYER_STORE',
        'PAY_SYSTEM_ID',
        'PERSON_TYPE',
        'PERSON_TYPE_OLD',
        'ORDER_PROP_18',
        'RECENT_DELIVERY_VALUE',
        'DELIVERY_ID',
        'via_ajax',
        'action',
        'SITE_ID',
        'signedParamsString',
      );
        $fields_exclusions = CleantalkCustomConfig::get_fields_exclusions();
        if ($fields_exclusions)
            $skip_fields_with_strings = array_merge($skip_fields_with_strings,$fields_exclusions);
      // Reset $message if we have a sign-up data
        $skip_message_post = array(
            'edd_action', // Easy Digital Downloads
        );
      
        foreach($skip_params as $value){
          if(@array_key_exists($value,$_GET)||@array_key_exists($value,$_POST))
            $contact = false;
        } unset($value);
        
      if(count($arr)){
        foreach($arr as $key => $value){
          
          if(gettype($value)=='string'){
            $decoded_json_value = json_decode($value, true);
            if($decoded_json_value !== null)
              $value = $decoded_json_value;
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
                $value = CleantalkAntispam::CleantalkObfuscateParam($value);
                continue(2);
              }
            }unset($needle);

              // Removes whitespaces
              $value = urldecode( trim( $value ) ); // Fully cleaned message
              $value_for_email = trim( $value );    // Removes shortcodes to do better spam filtration on server side.

              // Email
              if ( ! $email && preg_match( "/^\S+@\S+\.\S+$/", $value_for_email ) ) {
                  $email = $value_for_email;

                  // Names
              }elseif (preg_match("/name/i", $key)){
              
              preg_match("/((name.?)?(your|first|for)(.?name)?)$/", $key, $match_forename);
              preg_match("/((name.?)?(last|family|second|sur)(.?name)?)$/", $key, $match_surname);
              preg_match("/^(name.?)?(nick|user)(.?name)?$/", $key, $match_nickname);
              
              if(count($match_forename) > 1)
                $nickname['first'] = $value;
              elseif(count($match_surname) > 1)
                $nickname['last'] = $value;
              elseif(count($match_nickname) > 1)
                $nickname['nick'] = $value;
              else
                $nickname[$prev_name.$key] = $value;
            
            // Subject
            }elseif ($subject === null && preg_match("/subject/i", $key)){
              $subject = $value;
            
            // Message
            }else{
              $message[$prev_name.$key] = $value;         
            }
            
          }elseif(!is_object($value)){
            
            $prev_name_original = $prev_name;
            $prev_name = ($prev_name === '' ? $key.'_' : $prev_name.$key.'_');
            
            $temp = CleantalkAntispam::CleantalkGetFields($value, $message, $email, $nickname, $subject, $contact, $prev_name);
            
            $message  = $temp['message'];
            $email    = ($temp['email']     ? $temp['email'] : null);
            $nickname   = ($temp['nickname']  ? $temp['nickname'] : null);        
            $subject  = ($temp['subject']   ? $temp['subject'] : null);
            if($contact === true)
              $contact = ($temp['contact'] === false ? false : true);
            $prev_name  = $prev_name_original;
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
        'email'   => $email,
        'nickname'  => $nickname,
        'subject'   => $subject,
        'contact'   => $contact,
        'message'   => $message
      );  
      return $return_param;

    }

    /**
    * Masks a value with asterisks (*) Needed by the getFieldsAny()
    * @return string
    */
    static public function CleantalkObfuscateParam($value = null) {
      if ($value && (!is_object($value) || !is_array($value))) {
        $length = strlen($value);
        $value = str_repeat('*', $length);
      }

      return $value;
    }     
    
    /**
     * Checking all forms for spam
     * @return null|boolean NULL when success or FALSE when spam detected
     */
    public function OnPageStartHandler()
    {
        global $USER;

        // Set exclusions to the class
        CleantalkCustomConfig::$cleantalk_url_exclusions      = COption::GetOptionString( 'cleantalk.antispam', 'form_exclusions_url', '' );
        CleantalkCustomConfig::$cleantalk_fields_exclusions   = COption::GetOptionString( 'cleantalk.antispam', 'form_exclusions_fields', '' );
        CleantalkCustomConfig::$cleantalk_webforms_checking   = COption::GetOptionString( 'cleantalk.antispam', 'form_exclusions_webform', '' );

        if (!is_object($USER)) $USER = new CUser;
        $ct_status               = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_global               = COption::GetOptionInt('cleantalk.antispam', 'form_global_check', 0);
        $ct_global_without_email = COption::GetOptionInt('cleantalk.antispam', 'form_global_check_without_email', 0);
        $ct_key                  = COption::GetOptionInt( 'cleantalk.antispam', 'key', '' );
        $last_checked            = COption::GetOptionInt( 'cleantalk.antispam', 'last_checked', 0 );
        $show_review             = COption::GetOptionInt( 'cleantalk.antispam', 'show_review', 0 );
        $is_sfw                  = COption::GetOptionInt( 'cleantalk.antispam', 'form_sfw', 0 );
        $sfw_last_update         = COption::GetOptionInt( 'cleantalk.antispam',  'sfw_last_update', 0);
        $sfw_last_send_log       = COption::GetOptionInt( 'cleantalk.antispam',  'sfw_last_send_log', 0);
        $new_checked             = time();
        if (!$USER->IsAdmin()) {
            // Remote calls
            if(isset($_GET['spbc_remote_call_token'], $_GET['spbc_remote_call_action'], $_GET['plugin_name']) && in_array($_GET['plugin_name'], array('antispam','anti-spam', 'apbct'))){
                self::apbct_remote_call__perform();
            }   
            self::ct_cookie();           
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && $is_sfw == 1) {
                $sfw = new CleantalkSFW($ct_key);
                $sfw->check_ip();

                if (time() - $sfw_last_update > 86400)
                  CleantalkAntispam::sfw_update($ct_key);

                if (time() - $sfw_last_send_log > 3600)
                  CleantalkAntispam::sfw_send_logs($ct_key);
            }
            if ($ct_status == 1 && $ct_global == 1) {         
                // Exclusions
                if( empty($_POST) ||
                    (isset($_POST['AUTH_FORM'], $_POST['TYPE'], $_POST['USER_LOGIN'])) ||
                    (isset($_POST['order']['action']) && $_POST['order']['action'] == 'refreshOrderAjax')|| // Order AJAX refresh
                    (isset($_POST['order']['action']) && $_POST['order']['action'] == 'saveOrderAjax') ||
                    (isset($_POST['action']) && $_POST['action'] == 'refreshOrderAjax') ||
                    (isset($_POST['action']) && $_POST['action'] == 'saveOrderAjax') ||
                    strpos($_SERVER['REQUEST_URI'],'/user-profile.php?update=Y')!==false ||
                    ( isset( $_SERVER['USER_AGENT'] ) && strpos( $_SERVER['USER_AGENT'], '.NET Framework' )              !== false ) ||
                    ( isset( $_SERVER['USER_AGENT'] ) && strpos( $_SERVER['USER_AGENT'], 'Bitrix Telephony Controller' ) !== false )
                )
                {
                    return;
                }

                // Exclusion for web-forms ID
                $ct_webform= COption::GetOptionInt('cleantalk.antispam', 'web_form', 0);
                $webforms_id_checking = CleantalkCustomConfig::get_webforms_ids();
                if ($ct_webform == 1 && $webforms_id_checking && is_array($webforms_id_checking) && count($webforms_id_checking) > 0 && isset($_POST['WEB_FORM_ID']))
                    if (in_array($_POST['WEB_FORM_ID'], $webforms_id_checking))
                        return;

                $ct_temp_msg_data = CleantalkAntispam::CleantalkGetFields($_POST); // @todo Works via links need to be fixed
              
                if ($ct_temp_msg_data === null)
                    CleantalkAntispam::CleantalkGetFields($_GET);

                $arUser = array();
                $arUser["type"]                 = "feedback_general_contact_form";
                $arUser["sender_ip"]            = $_SERVER['REMOTE_ADDR'];
                $arUser["sender_email"]         = ($ct_temp_msg_data['email']    ? $ct_temp_msg_data['email']    : '');
                $arUser["sender_nickname"]      = ($ct_temp_msg_data['nickname'] ? $ct_temp_msg_data['nickname'] : '');
                $arUser["message_title"]        = ($ct_temp_msg_data['subject']  ? $ct_temp_msg_data['subject']  : '');
                $arUser["message_body"]         = ($ct_temp_msg_data['message']  ? $ct_temp_msg_data['message']  : array());  

                if (is_array($arUser["message_body"]))
                    $arUser["message_body"] = implode("\n", $arUser["message_body"]);
                foreach ($_POST as $key => $value) {
                  if (strpos(strtolower($key), 'smt') !== false)
                    $arUser['type'] = 'contact_form_bitrix_smt';
                  if (strpos(strtolower($key), 'iblock') !== false)
                    $arUser['type'] = 'contact_form_bitrix_iblock_ajax';
                }
                if(($arUser["sender_email"] != '' && $arUser['type'] == 'feedback_general_contact_form') || $ct_global_without_email == 1 || $arUser['type'] != 'feedback_general_contact_form') {
                  
                    $aResult =  CleantalkAntispam::CheckAllBefore($arUser,FALSE);
                    
                    if(isset($aResult) && is_array($aResult))
                    {
                        if($aResult['errno'] == 0)
                        {
                            if($aResult['allow'] == 1)
                            {
                                //Not spammer - just return;
                                return;
                            }
                            else
                            {
                              if ($arUser['type'] == 'contact_form_bitrix_smt') {
                                echo '<div class="smt-form smt-form_bordered">
                                  <div class="smt-alert smt-alert_warning">'.$aResult['ct_result_comment'].'</div>
                                </div>';
                                die();
                              }
                              elseif ($arUser['type'] == 'contact_form_bitrix_iblock_ajax') {
                                echo json_encode(array('STATUS' => 'success', 'MSG' => $aResult['ct_result_comment'], 'CAPTCHA' => ''));
                                die();
                              } else {
                                CleantalkAntispam::CleantalkDie($aResult['ct_result_comment']);
                                return false;
                              }
                            }
                        }
                    }
                }
            }            
        }
        else {
            if($ct_key!='' && $ct_key!='enter key') {
                $new_status=$show_review;

                if($new_checked-$last_checked>86400)
                {
                    
                    $result = CleantalkAPI::method__notice_paid_till($ct_key, preg_replace('/http[s]?:\/\//', '', $_SERVER['HTTP_HOST'], 1));

                    if(empty($result['error'])){
                        if (empty($result['error'])) {
                            if (isset($result['show_notice'], $result['trial']) && $result['show_notice'] == 1 && $result['trial'] == 1) {
                                CAdminNotify::Add(array(
                                    'MESSAGE' => GetMessage( 'CLEANTALK_TRIAL_NOTIFY' ),
                                    'TAG' => 'trial_notify',
                                    'MODULE_ID' => 'main',
                                'ENABLE_CLOSE' => 'Y'));
                            } else {
                                CAdminNotify::DeleteByTag('trial_notify');
                            }
                            if (isset($result['show_notice'], $result['renew']) && $result['show_notice'] == 1 && $result['renew'] == 1) {
                                CAdminNotify::Add(array(
                                    'MESSAGE' => GetMessage( 'CLEANTALK_RENEW_NOTIFY' ),
                                    'TAG' => 'renew_notify',
                                    'MODULE_ID' => 'main',
                                'ENABLE_CLOSE' => 'Y'));
                            } else {
                                CAdminNotify::DeleteByTag('renew_notify');
                            }
                        }
                        if(isset($result['show_review']) && $result['show_review'] == 1)
                        {
                            $new_status = intval($result['show_review']);
                            if($show_review !=1 && $new_status == 1)
                            {
                                COption::SetOptionInt( 'cleantalk.antispam', 'show_review', 1 );
                                $show_notice=1;
                                if(LANGUAGE_ID=='ru')
                                {
                                    $review_message = "Нравится Анти-спам от CleanTalk? Помогите другим узнать о CleanTalk! <a target='_blank' href='http://marketplace.1c-bitrix.ru/solutions/cleantalk.antispam/#rating'>Оставить отзыв на Bitrix.Marketplace</a>";
                                }
                                else
                                {
                                    $review_mess = "Like Anti-spam by CleanTalk? Help others learn about CleanTalk! <a  target='_blank' href='http://marketplace.1c-bitrix.ru/solutions/cleantalk.antispam/#rating'>Leave a review at the Bitrix.Marketplace</a>";
                                }
                                CAdminNotify::Add(array(          
                                    'MESSAGE' => $review_mess,          
                                    'TAG' => 'review_notify',          
                                    'MODULE_ID' => 'main',          
                                'ENABLE_CLOSE' => 'Y'));
                            }
                        }
                    }
                    
                    COption::SetOptionInt( 'cleantalk.antispam', 'last_checked', $new_checked );
                }                
            }
        }                                
    }
    
    /**
     * *** Sale section ***
     */
    
    /**
     * Checking Order forms for spam
     * @param &array Comment fields to check
     * @return null|boolean NULL when success or FALSE when spam detected
     */
    
    function OnBeforeOrderAddHandler(&$arFields)
    {
        global $APPLICATION, $USER;
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_order= COption::GetOptionInt('cleantalk.antispam', 'form_order', 0);
        if ($ct_status == 1 && $ct_order == 1)
        {
            $sender_email = null;
            $message = '';
            foreach ($_POST as $key => $value)
            {
                if(strpos($key,'ORDER_PROP_')!==false)
                {
                    if ($sender_email === null && preg_match("/^\S+@\S+\.\S+$/", $value))
                    {
                        $sender_email = $value;
                    }
                    else
                    {
                        $message.="$value\n";
                    }
                }
            }
            $message.=$_POST['ORDER_DESCRIPTION'];
            
            $arUser = array();
            $arUser["type"] = "order";
            $arUser["sender_email"] = $sender_email;
            $arUser["sender_nickname"] = '';
            $arUser["sender_ip"] = $_SERVER['REMOTE_ADDR'];
            $arUser["message_title"] = "";
            $arUser["message_body"] = $message;
            $arUser["example_title"] = "";
            $arUser["example_body"] = "";
            $arUser["example_comments"] = "";
            
            $aResult =  CleantalkAntispam::CheckAllBefore($arUser,FALSE);
            if(isset($aResult) && is_array($aResult))
            {
                if($aResult['errno'] == 0)
                {
                    if($aResult['allow'] == 1)
                    {
                        //Not spammer - just return;
                        return;
                    }
                    else
                    {
                        $APPLICATION->ThrowException($aResult['ct_result_comment']);
                        return false;
                    }
                }
            }
        }
    }
   
    /**
     * *** Web forms section ***
     */
   
   /**
     * Checking web forms
     * @param $WEB_FORM_ID, &$arFields, &$arrVALUES Comment fields to check
     * @return null|boolean NULL when success or FALSE when spam detected
     */
    
    function OnBeforeResultAddHandler($WEB_FORM_ID, &$arFields, &$arrVALUES)
    {
        global $APPLICATION;
        
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_webform= COption::GetOptionInt('cleantalk.antispam', 'web_form', 0);

        $webforms_id_checking = CleantalkCustomConfig::get_webforms_ids();
        if ($webforms_id_checking && is_array($webforms_id_checking) && count($webforms_id_checking) > 0)
            if (in_array($WEB_FORM_ID, $webforms_id_checking))
                return;      

        if ($ct_status == 1 && $ct_webform == 1){
            
            $sender_email = null;
            $message = '';
            
            $skip_keys = array(
                'WEB_FORM_ID',
                'RESULT_ID',
                'formresult',
                'sessid',
                'captcha_',
                'web_form_submit',
                'AJAX_CALL',
                'bxajaxid',
            );
            
            foreach ($arrVALUES as $key => $value){
                
                // Skipping keys
                foreach($skip_keys as $skip){
                    if(strpos($key, $skip) !== false)
                        continue 2;
                }
                
                if ($sender_email === null && preg_match("/^\S+@\S+\.\S+$/", $value))
                    $sender_email = $value;
                else
                    $message.="$value\n";
            }
            
            $arUser = array();
            $arUser["type"] = "webform";
            $arUser["sender_email"] = $sender_email;
            $arUser["sender_nickname"] = '';
            $arUser["sender_ip"] = $_SERVER['REMOTE_ADDR'];
            $arUser["message_title"] = "";
            $arUser["message_body"] = $message;
            $arUser["example_title"] = "";
            $arUser["example_body"] = "";
            $arUser["example_comments"] = "";
            
            $aResult =  CleantalkAntispam::CheckAllBefore($arUser,FALSE);

            if(isset($aResult) && is_array($aResult)){
                
                if($aResult['errno'] == 0){
                    
                    if($aResult['allow'] == 1){
                        return; //Not spammer - just return;
                    }else{
                        $APPLICATION->ThrowException($aResult['ct_result_comment']);
                        return false;
                    }
                }
            }
        }
    }
   
    /**
     * *** TreeLike comments section ***
     */
    
    /**
     * Checking treelike comment for spam
     * @param &array Comment fields to check
     * @return null|boolean NULL when success or FALSE when spam detected
     */
    function OnBeforePrmediaCommentAddHandler(&$arFields) {
        global $APPLICATION, $USER;
        
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_comment_treelike = COption::GetOptionInt('cleantalk.antispam', 'form_comment_treelike', 0);
        if ($ct_status == 1 && $ct_comment_treelike == 1) {

            $aComment = array();

            // Skip authorized user with more than 5 approved comments
            if($USER->IsAuthorized()){
                $approved_comments = CTreelikeComments::GetList(
                    array('ID' => 'ASC'),
                    array('USER_ID'=>$arFields['USER_ID'], 'ACTIVATED' => 1),
                    '',
                    TRUE    // return count(*)
                );
                if(intval($approved_comments) > 5) {
                    return;
                }
                $aComment['sender_email'] = $USER->GetEmail();
            } else {
                $aComment['sender_email'] = isset($arFields['EMAIL']) ? $arFields['EMAIL'] : '';
            }

            $aComment['type'] = 'comment';
            $aComment['sender_nickname'] = isset($arFields['AUTHOR_NAME']) ? $arFields['AUTHOR_NAME'] : '';
            $aComment['message_title'] = '';
            $aComment['message_body'] = isset($arFields['COMMENT']) ? $arFields['COMMENT'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';

            if(COption::GetOptionInt('cleantalk.antispam', 'form_send_example', 0) == 1){
                // Find last 10 approved comments
                $db_res = CTreelikeComments::GetList(
                    array('DATE' => 'DESC'),
                    array(
                        //'OBJECT_ID'=> $arFields['OBJECT_ID'],
                        'OBJECT_ID_NUMBER'=> $arFields['OBJECT_ID'], // works
                        'ACTIVATED' => 1 // works
                    ),
                    10
                );
                while($ar_res = $db_res->Fetch()){
                    $aComment['example_comments'] .= $ar_res['COMMENT'] . "\n\n";
                }
            }

            $aResult = self::CheckAllBefore($aComment, TRUE);

            if(isset($aResult) && is_array($aResult)){
                if($aResult['errno'] == 0){
                    if($aResult['allow'] == 1){
                        // Not spammer - just return;
                        return;
                    }else{
                        
                        if (preg_match('//u', $aResult['ct_result_comment'])){
                            $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $aResult['ct_result_comment']);
                            $err_str = preg_replace('/<[^<>]*>/iu', '', $err_str);
                        }else{
                            $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $aResult['ct_result_comment']);
                            $err_str = preg_replace('/<[^<>]*>/i', '', $err_str);
                        }
                        
                        if($aResult['stop_queue'] == 1){
                            // Spammer and stop_queue - die
                            CleantalkAntispam::CleantalkDie($err_str);
                        }else{
                            // if($aResult['stop_words']){
                                // $APPLICATION->ThrowException($err_str);
                                // return FALSE;
                            // }else{
                                CleantalkAntispam::CleantalkDie($err_str);
                            // }
/*                          if
                            // Spammer and NOT stop_queue - to manual approvement
                            // ACTIVATED = 0
                            // doesn't work - TreeLike Comments uses
                            // deprecated ExecuteModuleEvent
                            // instead of ExecuteModuleEventEx
                            // $arFields are not passwd by ref
                            // (See source - $args[] = func_get_arg($i))
                            // so I cannot change 'ACTIVATED'
                            $arFields['ACTIVATED'] = 0;
                            return;
//*/
                        }
                    }
                }
            }
        }
    }

    /**
     * *** Blog section ***
     */
    
    /**
     * Checking blog comment for spam
     * @param &array Comment fields to check
     * @return null|boolean NULL when success or FALSE when spam detected
     */
    function OnBeforeCommentAddHandler(&$arFields) {
        global $APPLICATION, $USER;
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_comment_blog = COption::GetOptionInt('cleantalk.antispam', 'form_comment_blog', 0);
        if ($ct_status == 1 && $ct_comment_blog == 1) {

            $aComment = array();

            // Skip authorized user with more than 5 approved comments
            if($USER->IsAuthorized()){
                $approved_comments = CBlogComment::GetList(
                    array('ID' => 'ASC'),
                    array('AUTHOR_ID'=>$arFields['AUTHOR_ID'], 'PUBLISH_STATUS' => BLOG_PUBLISH_STATUS_PUBLISH),
                    array()    // return count(*)
                );
                if(intval($approved_comments) > 5) {
                    return;
                }
                $aComment['sender_email'] = $USER->GetEmail();
            } else {
                $aComment['sender_email'] = isset($arFields['EMAIL']) ? $arFields['EMAIL'] : '';
            }


            $aComment['type'] = 'comment';
            $aComment['sender_nickname'] = isset($arFields['AUTHOR_NAME']) ? $arFields['AUTHOR_NAME'] : '';
            $aComment['message_title'] = '';
            $aComment['message_body'] = isset($arFields['POST_TEXT']) ? $arFields['POST_TEXT'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';
            
        if(COption::GetOptionInt('cleantalk.antispam', 'form_send_example', 0) == 1){
            $arPost = CBlogPost::GetByID($arFields['POST_ID']);
            if(is_array($arPost)){
                    $aComment['example_title'] = $arPost['TITLE'];
                    $aComment['example_body'] = $arPost['DETAIL_TEXT'];
                    // Find last 10 approved comments
                    $db_res = CBlogComment::GetList(
                    array('DATE_CREATE' => 'DESC'),
                    array('POST_ID'=> $arFields['POST_ID'], 'PUBLISH_STATUS' => BLOG_PUBLISH_STATUS_PUBLISH),
                    false,
                    array('nTopCount' => 10),
                    array('POST_TEXT')
                    );
                    while($ar_res = $db_res->Fetch())
                    $aComment['example_comments'] .= $ar_res['TITLE'] . "\n\n" . $ar_res['POST_TEXT'] . "\n\n";
        }
            }

            $aResult = self::CheckAllBefore($aComment, TRUE);

            if(isset($aResult) && is_array($aResult)){
                if($aResult['errno'] == 0){
                    if($aResult['allow'] == 1){
                        // Not spammer - just return;
                        return;
                    }else{
                        if($aResult['stop_queue'] == 1){
                            // Spammer and stop_queue - return false and throw
                if (preg_match('//u', $aResult['ct_result_comment'])){
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/iu', '', $err_str);
                }else{
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/i', '', $err_str);
                }
                            $APPLICATION->ThrowException($err_str);
                            return FALSE;
                        }else{
                            // Spammer and NOT stop_queue - to manual approvement
                            // BLOG_PUBLISH_STATUS_READY
                            // It doesn't work
                            // values below results in endless 'Loading' AJAX message :(
                            //$arFields['PUBLISH_STATUS'] = BLOG_PUBLISH_STATUS_READY;
                            //$arFields['PUBLISH_STATUS'] = BLOG_PUBLISH_STATUS_DRAFT;
                            //return;

                            // It doesn't work too
                            // Status setting in OnCommentAddHandler still results in endless 'Loading' AJAX message :(
                            //$GLOBALS['ct_after_CommentAdd_status'] = BLOG_PUBLISH_STATUS_READY;
                            //return;
                            
                if (preg_match('//u', $aResult['ct_result_comment'])){
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/iu', '', $err_str);
                }else{
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/i', '', $err_str);
                }
                            $APPLICATION->ThrowException($err_str);
                            return FALSE;
                        }
                    }
                }
            }
        }
    }

    /**
     * *** Forum section ***
     */

    /**
     * Checking forum comment for spam - part 1 - checking itself
     * @param &array Comment fields to check
     * @return null|boolean NULL when success or FALSE when spam detected
     */
    function OnBeforeMessageAddHandler(&$arFields) {
        // works
        global $APPLICATION, $USER;
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_comment_forum = COption::GetOptionInt('cleantalk.antispam', 'form_comment_forum', 0);
        if ($ct_status == 1 && $ct_comment_forum == 1) {

            $aComment = array();

            // Skip authorized user with more than 5 approved messages
            if($USER->IsAuthorized()){
                $approved_messages = CForumMessage::GetList(
                    array('ID'=>'ASC'),
                    array('AUTHOR_ID'=>$arFields['AUTHOR_ID'], 'APPROVED'=>'Y'),
                    TRUE
                );
                if(intval($approved_messages) > 5) {
                    return;
                }
                $aComment['sender_email'] = $USER->GetEmail();
            } else {
                $aComment['sender_email'] = isset($arFields['EMAIL']) ? $arFields['EMAIL'] : '';
            }

            $aComment['type'] = 'comment';
            $aComment['sender_nickname'] = isset($arFields['AUTHOR_NAME']) ? $arFields['AUTHOR_NAME'] : '';
            $aComment['message_title'] = '';
            $aComment['message_body'] = isset($arFields['POST_MESSAGE']) ? $arFields['POST_MESSAGE'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';
            
        if(COption::GetOptionInt('cleantalk.antispam', 'form_send_example', 0) == 1){
            $arTopic = CForumTopic::GetByID($arFields['TOPIC_ID']);
            if(is_array($arTopic)){
                    $aComment['example_title'] = $arTopic['TITLE'];

                    // Messages contains both topic bodies and comment bodies
                    // First find topic body
                    $db_res = CForumMessage::GetList(
                    array('ID'=>'ASC'),
                    array('TOPIC_ID'=>$arFields['TOPIC_ID'], 'NEW_TOPIC'=>'Y', 'APPROVED'=>'Y'),
                    FALSE,
                    1
                    );
                    $ar_res = $db_res->Fetch();
                    if($ar_res)
                    $aComment['example_body'] = $ar_res['POST_MESSAGE'];

                    // Second find last 10 approved comment bodies
                    $comments = array();
                    $db_res = CForumMessage::GetList(
                    array('POST_DATE'=>'DESC'),
                    array('TOPIC_ID'=>$arFields['TOPIC_ID'], 'NEW_TOPIC'=>'N', 'APPROVED'=>'Y'),
                    FALSE,
                    10
                    );
                    while($ar_res = $db_res->Fetch())
                    $aComment['example_comments'] .= $ar_res['POST_MESSAGE'] . "\n\n";
        }
            }
            
            $aResult = self::CheckAllBefore($aComment, TRUE);

            if(isset($aResult) && is_array($aResult)){
                if($aResult['errno'] == 0){
                    if($aResult['allow'] == 1){
                        // Not spammer - just return;
                        return;
                    }else{
                        if($aResult['stop_queue'] == 1){
                            // Spammer and stop_queue - return false and throw
                            if (preg_match('//u', $aResult['ct_result_comment'])){
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/iu', '', $err_str);
                            }else{
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/i', '', $err_str);
                            }
                            $APPLICATION->ThrowException($err_str);
                            return FALSE;
                        }else{
                            // Spammer and NOT stop_queue - to manual approvement
                            // It works!
                            $arFields['APPROVED'] = 'N';
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * Checking forum comment for spam - part 2 - stores needed data and logs event
     * @param int ID of added comment
     * @param array Comment fields
     */
    function OnAfterMessageAddHandler($id, $arFields) {
        // works
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_comment_forum = COption::GetOptionInt('cleantalk.antispam', 'form_comment_forum', 0);
        if ($ct_status == 1 && $ct_comment_forum == 1) {
            self::CheckCommentAfter('forum', $id, GetMessage('CLEANTALK_MESSAGE') . ' ID=' . $id);
        }
    }
    
    /**
     * Sending admin's decision (show or hide comment) to CleanTalk server
     * @param int ID of added comment
     * @param string Type of action - must be 'SHOW' or 'HIDE' only
     * @param array Comment fields
     */
    function OnMessageModerateHandler( $id, $type, $arFields){
        // works
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_comment_forum = COption::GetOptionInt('cleantalk.antispam', 'form_comment_forum', 0);
        if ($ct_status == 1 && $ct_comment_forum == 1) {
            if ($type == 'SHOW') {
                //send positive feedback
                self::SendFeedback('forum', $id, 'Y');
            }else if ($type == 'HIDE'){
                // send negative feedback
                self::SendFeedback('forum', $id, 'N');
            }
        }
    }

    /**
     * Sending admin's decision (delete comment) to CleanTalk server
     * @param int ID of added comment
     * @param array Comment fields
     */
    function OnBeforeMessageDeleteHandler($id, $arFields) {
        // works
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_comment_forum = COption::GetOptionInt('cleantalk.antispam', 'form_comment_forum', 0);
        if ($ct_status == 1 && $ct_comment_forum == 1) {
            // send negative feedback
            self::SendFeedback('forum', $id, 'N');
        }
    }
    
    /**
     * Check forum private messages
     * @param array Comment fields
     */
    function onBeforePMSendHandler($arFields) {
        
        global $APPLICATION, $USER;
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_forum_private_messages = COption::GetOptionInt('cleantalk.antispam', 'form_forum_private_messages', 0);
        if ($ct_status == 1 && $ct_forum_private_messages == 1) {

            $aComment = array();
            $aComment['type'] = 'comment';
            $aComment['sender_email'] = $USER->GetEmail();
            $aComment['sender_nickname'] = $USER->GetLogin();
            $aComment['message_title'] = isset($arFields['POST_SUBJ']) ? $arFields['POST_SUBJ'] : '';
            $aComment['message_body'] = isset($arFields['POST_MESSAGE']) ? $arFields['POST_MESSAGE'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';
            
            $aResult = self::CheckAllBefore($aComment, TRUE);
            
            if(isset($aResult) && is_array($aResult)){
                if($aResult['errno'] == 0){
                    if($aResult['allow'] == 1){
                        return;
                    }else{
                        if (preg_match('//u', $aResult['ct_result_comment'])){
                            $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $aResult['ct_result_comment']);
                            $err_str = preg_replace('/<[^<>]*>/iu', '', $err_str);
                        }else{
                            $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $aResult['ct_result_comment']);
                            $err_str = preg_replace('/<[^<>]*>/i', '', $err_str);
                        }
                        $APPLICATION->ThrowException($err_str);
                        return FALSE;
                    }
                }
            }
        }
    }
    
    /**
     * *** User registration section ***
     */

    /**
     * Checking new user for spammer/bot
     * @param &array New user fields to check
     * @return null|boolean NULL when success or FALSE when spammer/bot detected
     */
    function OnBeforeUserRegisterHandler(&$arFields) {
        global $APPLICATION;
        
        $ct_status = COption::GetOptionInt('cleantalk.antispam', 'status', 0);
        $ct_new_user = COption::GetOptionInt('cleantalk.antispam', 'form_new_user', 0);

        if ($ct_status == 1 && $ct_new_user == 1) {
            $aUser = array();
            $aUser['type'] = 'register';
            $aUser['sender_email'] = isset($arFields['EMAIL']) ? $arFields['EMAIL'] : '';
            $aUser['sender_nickname'] = (isset($arFields['NAME']) ? $arFields['NAME'] : '') . ' ' . (isset($arFields['LAST_NAME']) ? $arFields['LAST_NAME'] : '');

            if (empty($arFields['LOGIN']))
                $form_errors[] = 'Логин должен быть не менее 3 символов';
            if (empty($arFields['PASSWORD']))
                $form_errors[] = 'Пароль должен быть не менее 6 символов длиной';
            if (empty($arFields['EMAIL']))
                $form_errors[] = 'Неверный E-Mail';
            if ($arFields['PASSWORD'] != $arFields['CONFIRM_PASSWORD'])
                $form_errors[] = 'Неверное подтверждение пароля';

            if (!self::ExceptionList($aUser))
            {
                $aResult = self::CheckAllBefore($aUser, TRUE, ($form_errors && count($form_errors)) ? $form_errors : null);

                if(isset($aResult) && is_array($aResult)){
                    if($aResult['errno'] == 0){
                        if($aResult['allow'] == 1){
                            // Not spammer - just return;
                            return;
                        }else{
                            // Spammer - return false and throw
                            // Note: 'stop_queue' is ignored in user checking
                            if (preg_match('//u', $aResult['ct_result_comment'])){
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/iu', '', $err_str);
                            }else{
                                $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $aResult['ct_result_comment']);
                                $err_str = preg_replace('/<[^<>]*>/i', '', $err_str);
                            }
                            $APPLICATION->ThrowException($err_str);

                            return false;
                        }
                    }
                }               
            }

        }
    }

    /**
     * *** Common section ***
     */
    
    /**
     * CleanTalk additions to logging types
     */
    function OnEventLogGetAuditTypesHandler(){
        return array(
            'CLEANTALK_EVENT' => '[CLEANTALK_EVENT] ' . GetMessage('CLEANTALK_EVENT'),
            'CLEANTALK_E_SERVER' => '[CLEANTALK_E_SERVER] ' . GetMessage('CLEANTALK_E_SERVER'),
            'CLEANTALK_E_INTERNAL' => '[CLEANTALK_E_INTERNAL] ' . GetMessage('CLEANTALK_E_INTERNAL')
        );
    }
    
    /**
     * *** Universal methods section - for using in other modules ***
     */

    /**
     * Content modification - adding JavaScript code to final content
     * @param string Content to modify
     */
    function OnEndBufferContentHandler(&$content) {
        if(!defined("ADMIN_SECTION") && COption::GetOptionInt( 'cleantalk.antispam', 'status', 0 ) == 1 && strpos($content,'<!-- CLEANTALK template addon -->') === false && strpos($content,'</body>') !== false)
            $content = preg_replace('/(<\/body[^>]*>(?!.*<\/body[^>]*>))/i', '${1}'."\n".self::FormAddon(), $content, 1);
    }
    /**
     * Deprecated!
     */
    static function FormAddon() {

        if(!defined("ADMIN_SECTION") && COption::GetOptionInt( 'cleantalk.antispam', 'status', 0 ) == 1 )
            {
                $field_name = 'ct_checkjs';
                $ct_check_def = '0';
                if (!isset($_COOKIE[$field_name])) setcookie($field_name, $ct_check_def, 0, '/');

                $ct_check_values = self::SetCheckJSValues();  
                
                $js_template = "<script>
                    var ct_checkjs_val = '".$ct_check_values[0]."', ct_date = new Date(), 
                    ctTimeMs = new Date().getTime(),
                    ctMouseEventTimerFlag = true, //Reading interval flag
                    ctMouseData = [],
                    ctMouseDataCounter = 0;

                    function ctSetCookie(c_name, value) {
                        document.cookie = c_name + '=' + encodeURIComponent(value) + '; path=/';
                    }

                    ctSetCookie('ct_ps_timestamp', Math.floor(new Date().getTime()/1000));
                    ctSetCookie('ct_fkp_timestamp', '0');
                    ctSetCookie('ct_pointer_data', '0');
                    ctSetCookie('ct_timezone', '0');

                    setTimeout(function(){
                        ctSetCookie('ct_timezone', ct_date.getTimezoneOffset()/60*(-1));
                        ctSetCookie('ct_checkjs', ct_checkjs_val);  
                    },1000);

                    //Writing first key press timestamp
                    var ctFunctionFirstKey = function output(event){
                        var KeyTimestamp = Math.floor(new Date().getTime()/1000);
                        ctSetCookie('ct_fkp_timestamp', KeyTimestamp);
                        ctKeyStopStopListening();
                    }

                    //Reading interval
                    var ctMouseReadInterval = setInterval(function(){
                        ctMouseEventTimerFlag = true;
                    }, 150);
                        
                    //Writting interval
                    var ctMouseWriteDataInterval = setInterval(function(){
                        ctSetCookie('ct_pointer_data', JSON.stringify(ctMouseData));
                    }, 1200);

                    //Logging mouse position each 150 ms
                    var ctFunctionMouseMove = function output(event){
                        if(ctMouseEventTimerFlag == true){
                            
                            ctMouseData.push([
                                Math.round(event.pageY),
                                Math.round(event.pageX),
                                Math.round(new Date().getTime() - ctTimeMs)
                            ]);
                            
                            ctMouseDataCounter++;
                            ctMouseEventTimerFlag = false;
                            if(ctMouseDataCounter >= 100){
                                ctMouseStopData();
                            }
                        }
                    }

                    //Stop mouse observing function
                    function ctMouseStopData(){
                        if(typeof window.addEventListener == 'function'){
                            window.removeEventListener('mousemove', ctFunctionMouseMove);
                        }else{
                            window.detachEvent('onmousemove', ctFunctionMouseMove);
                        }
                        clearInterval(ctMouseReadInterval);
                        clearInterval(ctMouseWriteDataInterval);                
                    }

                    //Stop key listening function
                    function ctKeyStopStopListening(){
                        if(typeof window.addEventListener == 'function'){
                            window.removeEventListener('mousedown', ctFunctionFirstKey);
                            window.removeEventListener('keydown', ctFunctionFirstKey);
                        }else{
                            window.detachEvent('mousedown', ctFunctionFirstKey);
                            window.detachEvent('keydown', ctFunctionFirstKey);
                        }
                    }

                    if(typeof window.addEventListener == 'function'){
                        window.addEventListener('mousemove', ctFunctionMouseMove);
                        window.addEventListener('mousedown', ctFunctionFirstKey);
                        window.addEventListener('keydown', ctFunctionFirstKey);
                    }else{
                        window.attachEvent('onmousemove', ctFunctionMouseMove);
                        window.attachEvent('mousedown', ctFunctionFirstKey);
                        window.attachEvent('keydown', ctFunctionFirstKey);
                    }
                    // Ready function
                    function ct_ready(){
                      ctSetCookie('ct_visible_fields', 0);
                      ctSetCookie('ct_visible_fields_count', 0);
                      setTimeout(function(){
                        for(var i = 0; i < document.forms.length; i++){
                          var form = document.forms[i];
                          
                          form.onsubmit_prev = form.onsubmit;
                          form.onsubmit = function(event){

                            // Get only fields
                            var elements = [];
                            for(var key in this.elements){
                              if(!isNaN(+key))
                                elements[key] = this.elements[key];
                            }

                            // Filter fields
                            elements = elements.filter(function(elem){

                              var pass = true;

                              // Filter fields
                              if( getComputedStyle(elem).display    === 'none' ||   // hidden
                                getComputedStyle(elem).visibility === 'hidden' || // hidden
                                getComputedStyle(elem).opacity    === '0' ||      // hidden
                                elem.getAttribute('type')         === 'hidden' || // type == hidden
                                elem.getAttribute('type')         === 'submit' || // type == submit
                                elem.value                        === ''       || // empty value
                                elem.getAttribute('name')         === null
                              ){
                                return false;
                              }

                              // Filter elements with same names for type == radio
                              if(elem.getAttribute('type') === 'radio'){
                                elements.forEach(function(el, j, els){
                                  if(elem.getAttribute('name') === el.getAttribute('name')){
                                    pass = false;
                                    return;
                                  }
                                });
                              }

                              return true;
                            });

                            // Visible fields count
                            var visible_fields_count = elements.length;

                            // Visible fields
                            var visible_fields = '';
                            elements.forEach(function(elem, i, elements){
                              visible_fields += '' + elem.getAttribute('name');
                            });
                            visible_fields = visible_fields.trim();

                            ctSetCookie('ct_visible_fields', visible_fields);
                            ctSetCookie('ct_visible_fields_count', visible_fields_count);

                            // Call previous submit action
                            if(event.target.onsubmit_prev instanceof Function){
                              setTimeout(function(){
                                event.target.onsubmit_prev.call(event.target, event);
                              }, 500);
                            }
                          };
                        }
                      }, 1000);
                    }

                    function ct_attach_event_handler(elem, event, callback){
                      if(typeof window.addEventListener === 'function') elem.addEventListener(event, callback);
                      else                                              elem.attachEvent(event, callback);
                    }

                    function ct_remove_event_handler(elem, event, callback){
                      if(typeof window.removeEventListener === 'function') elem.removeEventListener(event, callback);
                      else                                                 elem.detachEvent(event, callback);
                    }
                    
                    if(typeof jQuery !== 'undefined') {

						// Capturing responses and output block message for unknown AJAX forms
						jQuery(document).ajaxComplete(function (event, xhr, settings) {
							if (xhr.responseText && xhr.responseText.indexOf('\"apbct') !== -1) {
								var response = JSON.parse(xhr.responseText);
								if (typeof response.apbct !== 'undefined') {
									response = response.apbct;
									if (response.blocked) {
										alert(response.comment);
										if(+response.stop_script == 1)
											window.stop();
									}
								}
							}
						});
						
					}
                    </script>";

                    return $js_template;                               
            }
            else return '';
    }

    /**
     * Universal method for checking comment or new user for spam
     * It makes checking itself
     * Use it in your modules
     * You must call it from OnBefore* events
     * @param &array Entity to check (comment or new user)
     * @param boolean Notify admin about errors by email or not (default FALSE)
     * @return array|null Checking result or NULL when bad params
     */
    static function CheckAllBefore(&$arEntity, $bSendEmail = FALSE, $form_errors = null) {
        global $DB, $USER;

        if (class_exists('Bitrix\Main\Context')) {
          $isAdminSection = \Bitrix\Main\Context::getCurrent()->getRequest()->isAdminSection();
        } else {
            $isAdminSection = (strpos($_SERVER['REQUEST_URI'], 'bitrix/admin') !== false) ? true : false;
        }
        
        if ($USER->IsAdmin() || $isAdminSection)
            return;

        if(!is_array($arEntity) || !array_key_exists('type', $arEntity)){
            CEventLog::Add(array(
                'SEVERITY' => 'SECURITY',
                'AUDIT_TYPE_ID' => 'CLEANTALK_E_INTERNAL',
                'MODULE_ID' => 'cleantalk.antispam',
                'DESCRIPTION' => GetMessage('CLEANTALK_E_PARAM')
            ));
            return;
        }

        $type = $arEntity['type'];
        if($type != 'comment' && $type != 'webform' && $type != 'register' && $type != 'order' && $type != 'feedback_general_contact_form' && $type != 'private_message' && strpos($type, 'contact_form_bitrix') === false){
            CEventLog::Add(array(
                'SEVERITY' => 'SECURITY',
                'AUDIT_TYPE_ID' => 'CLEANTALK_E_INTERNAL',
                'MODULE_ID' => 'cleantalk.antispam',
                'DESCRIPTION' => GetMessage('CLEANTALK_E_TYPE')
            ));
            return;
        }

        $url_exclusion = CleantalkCustomConfig::get_url_exclusions();
        if ($url_exclusion)
        {
            foreach ($url_exclusion as $key=>$value)
                if (strpos($_SERVER['REQUEST_URI'],$value) !== false)
                    return;         
        }

        $ct_key = COption::GetOptionString('cleantalk.antispam', 'key', '');
        $ct_ws = self::GetWorkServer();

        if (!isset($_COOKIE['ct_checkjs']))
            $checkjs = NULL;
        elseif (in_array($_COOKIE['ct_checkjs'], self::GetCheckJSValues()))
            $checkjs = 1;
        else
            $checkjs = 0;
        
        $pointer_data        = (isset($_COOKIE['ct_pointer_data'])  ? json_decode($_COOKIE['ct_pointer_data']) : '');
        $js_timezone         = (isset($_COOKIE['ct_timezone'])      ? $_COOKIE['ct_timezone']                  : 'none');
        $first_key_timestamp = (isset($_COOKIE['ct_fkp_timestamp']) ? $_COOKIE['ct_fkp_timestamp']             : 0);
        $page_set_timestamp  = (isset($_COOKIE['ct_ps_timestamp'])  ? $_COOKIE['ct_ps_timestamp']              : 0);

        if(isset($_SERVER['HTTP_USER_AGENT']))
            $user_agent = htmlspecialchars((string) $_SERVER['HTTP_USER_AGENT']);
        else
            $user_agent = NULL;

        if(isset($_SERVER['HTTP_REFERER']))
            $refferrer = htmlspecialchars((string) $_SERVER['HTTP_REFERER']);
        else
            $refferrer = NULL;

        $ct_options=Array(
            'access_key' => COption::GetOptionString('cleantalk.antispam', 'key', ''),
            'form_new_user' => COption::GetOptionInt('cleantalk.antispam', 'form_new_user', 0),
            'form_comment_blog' => COption::GetOptionInt('cleantalk.antispam', 'form_comment_blog', 0),
            'form_comment_forum' => COption::GetOptionInt('cleantalk.antispam', 'form_comment_forum', 0),
            'form_forum_private_messages' => COption::GetOptionInt('cleantalk.antispam', 'form_forum_private_messages', 0),
            'form_comment_treelike' => COption::GetOptionInt('cleantalk.antispam', 'form_comment_treelike', 0),
            'form_send_example' => COption::GetOptionInt('cleantalk.antispam', 'form_send_example', 0),
            'form_order' => COption::GetOptionInt('cleantalk.antispam', 'form_order', 0),
            'web_form' => COption::GetOptionInt('cleantalk.antispam', 'web_form', 0),
            'form_global_check' => COption::GetOptionInt('cleantalk.antispam', 'form_global_check', 0),
            'form_global_check_without_email' => COption::GetOptionInt('cleantalk.antispam', 'form_global_check_without_email', 0),
            'form_sfw' => COption::GetOptionInt('cleantalk.antispam', 'form_sfw', 0),
        );

        $sender_info = array(
            'cms_lang' => 'ru',
            'REFFERRER' => $refferrer,
            'post_url' => $refferrer,
            'USER_AGENT' => $user_agent,
            'js_timezone' => $js_timezone,
            'mouse_cursor_positions' => $pointer_data,
            'key_press_timestamp' => $first_key_timestamp,
            'page_set_timestamp' => $page_set_timestamp,
            'REFFERRER_PREVIOUS' => isset($_COOKIE['ct_prev_referer']) ? $_COOKIE['ct_prev_referer'] : null,
            'cookies_enabled' => self::ct_cookies_test(),
            'ct_options' => json_encode($ct_options),
            'form_validation' => ($form_errors && is_array($form_errors)) ? json_encode(array('validation_notice' => json_encode($form_errors), 'page_url' => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])) : null,
            'apbct_visible_fields'   => !empty($_COOKIE['ct_visible_fields']) ? self::ct_visibile_fields__process($_COOKIE['ct_visible_fields'])  : null,
        );
        $sender_info = json_encode($sender_info);

        $ct = new Cleantalk();
        $ct->work_url = $ct_ws['work_url'];
        $ct->server_url = $ct_ws['server_url'];
        $ct->server_ttl = $ct_ws['server_ttl'];
        $ct->server_changed = $ct_ws['server_changed'];

        if(defined('BX_UTF'))
            $logicalEncoding = "utf-8";
        elseif(defined("SITE_CHARSET") && (strlen(SITE_CHARSET) > 0))
            $logicalEncoding = SITE_CHARSET;
        elseif(defined("LANG_CHARSET") && (strlen(LANG_CHARSET) > 0))
            $logicalEncoding = LANG_CHARSET;
        elseif(defined("BX_DEFAULT_CHARSET"))
            $logicalEncoding = BX_DEFAULT_CHARSET;
        else
            $logicalEncoding = "windows-1251";

        $logicalEncoding = strtolower($logicalEncoding);
        $ct->data_codepage = $logicalEncoding == 'utf-8' ? NULL : $logicalEncoding;

        $ct_request = new CleantalkRequest();
        $ct_request->auth_key = $ct_key;
        $ct_request->sender_email = isset($arEntity['sender_email']) ? $arEntity['sender_email'] : '';
        $ct_request->sender_nickname = isset($arEntity['sender_nickname']) ? $arEntity['sender_nickname'] : '';
        $ct_request->sender_ip = CleantalkHelper::ip_get(array('real'), false);
        $ct_request->x_forwarded_for = CleantalkHelper::ip_get(array('x_forwarded_for'), false);
        $ct_request->x_real_ip       = CleantalkHelper::ip_get(array('x_real_ip'), false);
        $ct_request->agent = CLEANTALK_USER_AGENT;
        $ct_request->response_lang = 'ru';
        $ct_request->js_on = $checkjs;
        $ct_request->sender_info = $sender_info;
        $ct_request->submit_time = self::ct_cookies_test() == 1 ? time() - (int)$_COOKIE['ct_timestamp'] : null;
        if (isset($arEntity['message_title']) && is_array($arEntity['message_title']))
            $arEntity['message_title'] = implode("\n", $arEntity['message_title']);
        if (isset($arEntity['message_body']) && is_array($arEntity['message_body']))
            $arEntity['message_body'] = implode("\n", $arEntity['message_body']);
        switch ($type) {
            case 'comment':
                $timelabels_key = 'mail_error_comment';

                $ct_request->message = isset($arEntity['message_title']) ? $arEntity['message_title'] : '';
                $ct_request->message .= "\n\n";
                $ct_request->message .= isset($arEntity['message_body']) ? $arEntity['message_body'] : '';

                $ct_request->example = isset($arEntity['example_title']) ? $arEntity['example_title'] : '';
                $ct_request->example .= empty($ct_request->example) ? '' :"\n\n";
                $ct_request->example .= isset($arEntity['example_body']) ? $arEntity['example_body'] : '';
                $ct_request->example .= empty($ct_request->example) ? '' :"\n\n";
                $ct_request->example .= isset($arEntity['example_comments']) ? $arEntity['example_comments'] : '';
                
                if(empty($ct_request->example))
                    $ct_request->example = NULL;

                $a_post_info['comment_type'] = 'comment';
                $post_info = json_encode($a_post_info);
                if($post_info === FALSE)
                    $post_info = '';
                $ct_request->post_info = $post_info;

                $ct_result = $ct->isAllowMessage($ct_request);
                break;
                
            case 'order':
                
                $a_post_info['comment_type'] = 'order';
                $post_info = json_encode($a_post_info);
                $ct_request->post_info = $post_info;
                
                $timelabels_key = 'mail_error_comment';

                $ct_request->message = isset($arEntity['message_title']) ? $arEntity['message_title'] : '';
                $ct_request->message .= "\n\n";
                $ct_request->message .= isset($arEntity['message_body']) ? $arEntity['message_body'] : '';
                
                $ct_result = $ct->isAllowMessage($ct_request);
                break;
                
            case 'feedback_general_contact_form':
                
                $a_post_info['comment_type'] = 'feedback_general_contact_form';
                $post_info = json_encode($a_post_info);
                $ct_request->post_info = $post_info;
                
                $timelabels_key = 'mail_error_comment';

                $ct_request->message .= isset($arEntity['message_body']) ? $arEntity['message_body'] : '';
                
                $ct_result = $ct->isAllowMessage($ct_request);
                break;

            case strpos($type, 'contact_form_bitrix') !== false:
                $a_post_info['comment_type'] = $type;
                $post_info = json_encode($a_post_info);
                $ct_request->post_info = $post_info;
                
                $timelabels_key = 'mail_error_comment';

                $ct_request->message .= isset($arEntity['message_body']) ? $arEntity['message_body'] : '';
                
                $ct_result = $ct->isAllowMessage($ct_request);
                break;
                
            case 'webform':
                
                $a_post_info['comment_type'] = 'webform';
                $post_info = json_encode($a_post_info);
                $ct_request->post_info = $post_info;
                
                $timelabels_key = 'mail_error_comment';

                $ct_request->message .= isset($arEntity['message_body']) ? $arEntity['message_body'] : '';
                
                $ct_result = $ct->isAllowMessage($ct_request);
                break;
                
            case 'register':
            
                $timelabels_key = 'mail_error_reg';
                $ct_request->tz = isset($arEntity['user_timezone']) ? $arEntity['user_timezone'] : NULL;

                $ct_result = $ct->isAllowUser($ct_request);
                break;
                
            case 'private_message':
            
                $a_post_info['comment_type'] = 'private_message';
                $post_info = json_encode($a_post_info);
                if($post_info === FALSE)
                    $post_info = '';
                $ct_request->post_info = $post_info;
            
                $timelabels_key = 'mail_error_comment';
                $ct_request->tz = isset($arEntity['user_timezone']) ? $arEntity['user_timezone'] : NULL;

                $ct_result = $ct->isAllowMessage($ct_request);
        }
        
        $ret_val = array();
        $ret_val['ct_request_id'] = $ct_result->id;

        if($ct->server_change)
            self::SetWorkServer(
                $ct->work_url, $ct->server_url, $ct->server_ttl, time()
            );

        // First check errstr flag.
        if(!empty($ct_result->errstr) || (!empty($ct_result->inactive) && $ct_result->inactive == 1)){
            // Cleantalk error so we go default way (no action at all).
            $ret_val['errno'] = 1;
            // Just inform admin.
            $err_title = 'CleanTalk module error';
            
            if(isset($ct_result->inactive) && intval($ct_result->inactive) == 1)
                COption::SetOptionInt( 'cleantalk.antispam', 'key_is_ok', 0);
            
            if(!empty($ct_result->errstr)){
                
                if (preg_match('//u', $ct_result->errstr))
                    $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $ct_result->errstr);
                else
                    $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $ct_result->errstr);
                
            }else{
                
                if (preg_match('//u', $ct_result->comment))
                    $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/iu', '', $ct_result->comment);
                else
                    $err_str = preg_replace('/^[^\*]*?\*\*\*|\*\*\*[^\*]*?$/i', '', $ct_result->comment);
                
            }

            $ret_val['errstr'] = $err_str;
            
            if(!empty($ct_result->errstr)){
                if($ct_request->js_on == 1){
                    $ct_result->allow = 0;
                    $ct_result->comment = str_replace('*** ', '*** JavaScript disabled. ', $ct_result->comment);
                }else
                    $ct_result->allow = 1;
            }
            
            CEventLog::Add(array(
                'SEVERITY' => 'SECURITY',
                'AUDIT_TYPE_ID' => 'CLEANTALK_E_SERVER',
                'MODULE_ID' => 'cleantalk.antispam',
                'DESCRIPTION' => $err_str
            ));

            if($bSendEmail){
                $send_flag = FALSE;
                $insert_flag = FALSE;
                $time = $DB->Query('SELECT ct_value FROM cleantalk_timelabels WHERE ct_key=\''. $timelabels_key .'\'')->Fetch();
                if($time === FALSE){
                    $send_flag = TRUE;
                    $insert_flag = TRUE;
                }elseif(time()-900 > $time['ct_value']) {       // 15 minutes
                    $send_flag = TRUE;
                    $insert_flag = FALSE;
                }
                if($send_flag){
                    if($insert_flag){
                        $arInsert = $DB->PrepareInsert('cleantalk_timelabels', array('ct_key'=>$timelabels_key, 'ct_value' => time()));
                        $strSql = 'INSERT INTO cleantalk_timelabels('.$arInsert[0].') VALUES ('.$arInsert[1].')';
                    }else{
                        $strUpdate = $DB->PrepareUpdate('cleantalk_timelabels', array('ct_value' => time()));
                        $strSql = 'UPDATE cleantalk_timelabels SET '.$strUpdate.' WHERE ct_key = \''. $timelabels_key .'\'';
                    }
                    $DB->Query($strSql);
                    bxmail(
                        COption::GetOptionString("main", "email_from"),
                        $err_title,
                        $err_str
                    );
                }
            }
            // return $ret_val;
        }

        $ret_val['errno'] = 0;
        if ($ct_result->allow == 1) {
            // Not spammer.
            $ret_val['allow'] = 1;
            $GLOBALS['ct_request_id'] = $ct_result->id;
        }else{
            $ret_val['allow'] = 0;
            $ret_val['ct_result_comment'] = $ct_result->comment;
            // Spammer.
            // Check stop_queue flag.
            if($type == 'comment' && $ct_result->stop_queue == 0) {
                // Spammer and stop_queue == 0 - to manual approvement.
                $ret_val['stop_queue'] = 0;
                $GLOBALS['ct_request_id'] = $ct_result->id;
                $GLOBALS['ct_result_comment'] = $ct_result->comment;
            }else{
                // New user or Spammer and stop_queue == 1 - display message and exit.
                $ret_val['stop_queue'] = 1;
            }
        }
        return $ret_val;
    }

    /**
     * Addon to CheckAllBefore method after comments/messages checking
     * It fills special CleanTalk tables according to CleanTalk result
     *  for better spam accounting 
     *  and logs CleanTalk events
     * Use it in your modules
     * You must call it from OnAfter* events in comment/messages checking
     * @param string Name of event generated module ('blog', 'forum', etc.)
     * @param int ID of added entity (comment, message, etc)
     * @param string System log event prefix, for logging
     */
    static function CheckCommentAfter($module, $cid, $log_event = '') {
        global $DB;
        if(empty($module))
            return;
        if(empty($cid) || intval($cid) < 0)
            return;

        if(isset($GLOBALS['ct_request_id'])) {
            try {
                $arInsert = $DB->PrepareInsert(
                    'cleantalk_cids',
                    array(
                        'module' => $module,
                        'cid' => intval($cid),
                        'ct_request_id' => $GLOBALS['ct_request_id'],
                        'ct_result_comment' => isset($GLOBALS['ct_result_comment']) ? $GLOBALS['ct_result_comment'] : ''
                    )
                );
                $strSql = 'INSERT INTO cleantalk_cids('.$arInsert[0].') VALUES ('.$arInsert[1].')';
                $DB->Query($strSql);
            } catch (Exception $e){}
            // Log CleanTalk event
            if(isset($GLOBALS['ct_result_comment'])){
                CEventLog::Add(array(
                    'SEVERITY' => 'SECURITY',
                    'AUDIT_TYPE_ID' => 'CLEANTALK_EVENT',
                    'MODULE_ID' => $module,
                    'ITEM_ID' => (empty($log_event) ? $module . ', mess[' . $cid . ']' : $log_event),
                    'DESCRIPTION' => $GLOBALS['ct_result_comment']
                ));
            }
            unset($GLOBALS['ct_request_id']);
        }
    }
    /**
     * Process visible fields for specific form to match the fields from request
     *
     * @param string $visible_fields
     *
     * @return string
     */
    private static function ct_visibile_fields__process($visible_fields) {
        if(strpos($visible_fields, 'wpforms') !== false){
        $visible_fields = preg_replace(
          array('/\[/', '/\]/'),
          '',
          str_replace(
            '][',
            '_',
            str_replace(
              'wpforms[fields]',
              '',
              $visible_fields
            )
          )
        );
      }
      
      return $visible_fields;
    }
    /**
     * Sending of manual moderation result to CleanTalk server
     * It makes CleanTalk service better
     * Use it in your modules
     * @param string Name of event generated module ('blog', 'forum', etc.)
     * @param int ID of added entity (comment, message, etc)
     * @param string Feedback type - 'Y' or 'N' only
     */
    static function SendFeedback($module, $id, $feedback) {
        global $DB;
        if(empty($module))
            return;
        if(empty($id) || intval($id) < 0)
            return;
        if(empty($feedback) || $feedback != 'Y' && $feedback != 'N')
            return;

        $request_id = $DB->Query('SELECT ct_request_id FROM cleantalk_cids WHERE module=\''. $module .'\' AND cid=' . $id)->Fetch();
        if($request_id !== FALSE){
            $DB->Query('DELETE FROM cleantalk_cids WHERE module=\''. $module .'\' AND cid=' . $id);

            $ct_key = COption::GetOptionString('cleantalk.antispam', 'key', '');
            $ct_ws = self::GetWorkServer();

            $ct = new Cleantalk();
            $ct->work_url = $ct_ws['work_url'];
            $ct->server_url = $ct_ws['server_url'];
            $ct->server_ttl = $ct_ws['server_ttl'];
            $ct->server_changed = $ct_ws['server_changed'];

            $ct_request = new CleantalkRequest();
            $ct_request->auth_key = $ct_key;
            $ct_request->agent = CLEANTALK_USER_AGENT;
            $ct_request->sender_ip = $ct->ct_session_ip($_SERVER['REMOTE_ADDR']);
            $ct_request->feedback = $request_id . ':' . ($feedback == 'Y' ? '1' : '0');

            $ct->sendFeedback($ct_request);
        }
    }
    
    /**
     * Gets CleanTalk resume for spam detection by id
     * Use it in your modules/templates, see example
     * @param string Name of event generated module ('blog', 'forum', etc.)
     * @param int ID of entity (comment, message, etc)
     * @return string|boolean Text of CleanTalk resume if any or FALSE if not
     */
    static function GetCleanTalkResume($module, $id) {
        global $DB;
        if(empty($module))
            return;
        if(empty($id) || intval($id) < 0)
            return;

        $ret_val = $DB->Query('SELECT ct_request_id, ct_result_comment FROM cleantalk_cids WHERE module=\''. $module .'\' AND cid=' . $id)->Fetch();
        return $ret_val;
    }
    
    /**
     * *** Inner methods section ***
     */

    /**
     * CleanTalk inner function - gets working server.
     */
    private static function GetWorkServer() {
        global $DB;
        $result = $DB->Query('SELECT work_url,server_url,server_ttl,server_changed FROM cleantalk_server LIMIT 1')->Fetch();
        if($result !== FALSE)
            return array(
                'work_url' => $result['work_url'],
                'server_url' => $result['server_url'],
                'server_ttl' => $result['server_ttl'],
                'server_changed' => $result['server_changed'],
            );
        else
            return array(
                'work_url' => 'http://moderate.cleantalk.org',
                'server_url' => 'http://moderate.cleantalk.org',
                'server_ttl' => 0,
                'server_changed' => 0,
            );
    }

    /**
     * CleanTalk inner function - check for exceptions.
     */    
    private static function ExceptionList($value = null)
    {
        if ($value && is_array($value))
        {
            if (isset($value['sender_email']))
            {
                if (preg_match('^user-\d+@shop\.kalyan-hut\.ru^', $value['sender_email']))
                    return true;               
            }

        }

        return false;
    }

    /**
     * CleanTalk inner function - sets working server.
     */
    private static function SetWorkServer($work_url = 'http://moderate.cleantalk.org', $server_url = 'http://moderate.cleantalk.org', $server_ttl = 0, $server_changed = 0) {
        global $DB;
        $result = $DB->Query('SELECT count(*) AS count FROM cleantalk_server')->Fetch();
        if($result['count'] == 0){
            $arInsert = $DB->PrepareInsert(
                'cleantalk_server',
                array(
                    'work_url' => $work_url,
                    'server_url' => $server_url,
                    'server_ttl' => $server_ttl,
                    'server_changed' => $server_changed,
                )
            );
            $strSql = 'INSERT INTO cleantalk_server('.$arInsert[0].') VALUES ('.$arInsert[1].')';
        }else{
            $strUpdate = $DB->PrepareUpdate(
                'cleantalk_server',
                array(
                    'work_url' => $work_url,
                    'server_url' => $server_url,
                    'server_ttl' => $server_ttl,
                    'server_changed' => $server_changed,
                )
            );
            $strSql = 'UPDATE cleantalk_server SET '.$strUpdate;
        }
        $DB->Query($strSql);
    }

    /**
     * CleanTalk inner function - sets JavaScript checking values and returns last added one.
     */
    private static function SetCheckJSValues() {
        global $DB;
    $current_time_range = date('H'); // time range key is current hour

    $flag_update = FALSE;
        $db_result = $DB->Query('SELECT time_range,js_values FROM cleantalk_checkjs LIMIT 1')->Fetch();
        if($db_result !== FALSE){
        $db_time_range = $db_result['time_range'];
        $db_js_values = array_slice(explode(' ', $db_result['js_values'], self::KEYS_NUM+1), 0, self::KEYS_NUM);
        if($db_time_range == $current_time_range){
        return $db_js_values;
        }else{
        $flag_update = TRUE;
        }
    }else{
        $db_js_values = array();
    }

        $arFields = array(
            'time_range' => $current_time_range,
            'js_values'  => implode(' ', array_merge( array(md5(date(DATE_RSS).'+'.(string)rand())), array_slice($db_js_values,0,self::KEYS_NUM-1) ))
        );
    if($flag_update){
            $strUpdate = $DB->PrepareUpdate(
                'cleantalk_checkjs',
                $arFields
            );
            $strSql = 'UPDATE cleantalk_checkjs SET '.$strUpdate . " WHERE time_range='" . $DB->ForSql($db_time_range)."'";
    }else{
            $arInsert = $DB->PrepareInsert(
                'cleantalk_checkjs',
                $arFields
            );
            $strSql = 'INSERT INTO cleantalk_checkjs('.$arInsert[0].') VALUES ('.$arInsert[1].')';
    }
        $res = $DB->Query($strSql, TRUE);
    return self::GetCheckJSValues();
    }
    /**
     * CleanTalk inner function - gets current JavaScript checking values.
     */
    private static function GetCheckJSValues() {
        global $DB;
        $db_result = $DB->Query('SELECT time_range,js_values FROM cleantalk_checkjs LIMIT 1')->Fetch();
        if($db_result !== FALSE){
            return array_slice(explode(' ', $db_result['js_values'], self::KEYS_NUM+1), 0, self::KEYS_NUM);
        }else{
            return array(md5(COption::GetOptionString('cleantalk.antispam', 'key', '') . '+' . COption::GetOptionString('main', 'email_from')));
        }
    }
    /*
     * Set Cookies test for cookie test
     * Sets cookies with pararms timestamp && landing_timestamp && pervious_referer
     * Sets test cookie with all other cookies
     */
    private static function ct_cookie(){
        
        // Cookie names to validate
        $cookie_test_value = array(
            'cookies_names' => array(),
            'check_value' => COption::GetOptionString('cleantalk.antispam', 'key', ''),
        );

        // Submit time
        $ct_timestamp = time();
        setcookie('ct_timestamp', $ct_timestamp, 0, '/');
        $cookie_test_value['cookies_names'][] = 'ct_timestamp';
        $cookie_test_value['check_value'] .= $ct_timestamp;

        // Pervious referer
        if(!empty($_SERVER['HTTP_REFERER'])){
            setcookie('ct_prev_referer', $_SERVER['HTTP_REFERER'], 0, '/');
            $cookie_test_value['cookies_names'][] = 'ct_prev_referer';
            $cookie_test_value['check_value'] .= $_SERVER['HTTP_REFERER'];
        }           
        // Cookies test
        $cookie_test_value['check_value'] = md5($cookie_test_value['check_value']);
        setcookie('ct_cookies_test', json_encode($cookie_test_value), 0, '/');
    } 
    /**
     * Cookies test for sender 
     * Also checks for valid timestamp in $_COOKIE['apbct_timestamp'] and other apbct_ COOKIES
     * @return null|0|1;
     */
    private static function ct_cookies_test()
    {       
        if(isset($_COOKIE['ct_cookies_test'])){
            
            $cookie_test = json_decode(stripslashes($_COOKIE['ct_cookies_test']), true);
            
            $check_srting = COption::GetOptionString('cleantalk.antispam', 'key', '');
            foreach($cookie_test['cookies_names'] as $cookie_name){
                $check_srting .= isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
            } unset($cokie_name);
            
            if($cookie_test['check_value'] == md5($check_srting)){
                return 1;
            }else{
                return 0;
            }
        }else{
            return null;
        }
    }

    /**
     * CleanTalk inner function - check for exceptions.
     */    
    private static function apbct_remote_call__perform()
    {
        $remote_calls_config = json_decode(COption::GetOptionString('cleantalk.antispam','remote_calls', ''),true);

        $remote_action = $_GET['spbc_remote_call_action'];
        $auth_key = trim(COption::GetOptionString('cleantalk.antispam', 'key', ''));

        if(array_key_exists($remote_action, $remote_calls_config)){
                    
            if(time() - $remote_calls_config[$remote_action]['last_call'] > self::APBCT_REMOTE_CALL_SLEEP || ($remote_action == 'sfw_update' && isset($_GET['file_urls']))) {
                
                $remote_calls_config[$remote_action]['last_call'] = time();
                COption::SetOptionString('cleantalk.antispam', 'remote_calls', json_encode($remote_calls_config));

                if(strtolower($_GET['spbc_remote_call_token']) == strtolower(md5($auth_key))){
                    // Close renew banner
                    if($remote_action == 'close_renew_banner'){
                        die('OK');
                    // SFW update
                    }elseif($remote_action == 'sfw_update'){
                        $result = CleantalkAntispam::sfw_update($auth_key);
                        die(($result) ? 'OK' : 'FAIL ');
                    // SFW send logs
                    }elseif($remote_action == 'sfw_send_logs'){
                        $result = CleantalkAntispam::sfw_send_logs($auth_key);
                        die(($result) ? 'OK' : 'FAIL ');
                    // Update plugin
                    }elseif($remote_action == 'update_plugin'){
                        //add_action('wp', 'apbct_update', 1);
                    }else
                        die('FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION_2')));
                }else
                    die('FAIL '.json_encode(array('error' => 'WRONG_TOKEN')));
            }else
                die('FAIL '.json_encode(array('error' => 'TOO_MANY_ATTEMPTS')));
        }else
            die('FAIL '.json_encode(array('error' => 'UNKNOWN_ACTION')));
    }           
}
