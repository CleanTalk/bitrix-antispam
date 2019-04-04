<?php
global $MESS;
IncludeModuleLangFile(__FILE__);

// Fixes for unexisting functions
require_once(dirname(__FILE__) . '/classes/general/phpFix.php');

// Base classes
require_once(dirname(__FILE__) . '/classes/general/Cleantalk.php');
require_once(dirname(__FILE__) . '/classes/general/CleantalkRequest.php');
require_once(dirname(__FILE__) . '/classes/general/CleantalkResponse.php');
require_once(dirname(__FILE__) . '/classes/general/CleantalkHelper.php');

// SFW class
require_once(dirname(__FILE__) . '/classes/general/CleantalkSFW.php');

// Custom config
require_once(dirname(__FILE__) . '/custom_config.php');
/**
 * CleanTalk module class
 *
 * @author  CleanTalk team <http://cleantalk.org>
 */

RegisterModuleDependences('main', 'OnPageStart', 'cleantalk.antispam', 'CleantalkAntispam', 'OnPageStartHandler',1); 
class CleantalkAntispam {

    const KEYS_NUM = 12; // 12 last JS keys are valid
    
    /*
     * Updates SFW local database
     */
    static public function sfw_update()
    {
        
        $is_sfw    = COption::GetOptionString( 'cleantalk.antispam', 'form_sfw',  0 );
        $key       = COption::GetOptionString( 'cleantalk.antispam', 'key',       '' );
        $key_is_ok = COption::GetOptionString( 'cleantalk.antispam', 'key_is_ok', '0');
        
        if(!empty($is_sfw) && !empty($key) && !empty($key_is_ok)){
            
            $sfw = new CleantalkSFW();
            $result = $sfw->sfw_update($key);
            unset($sfw);
            
            COption::SetOptionString( 'cleantalk.antispam', 'sfw_update_result', json_encode($result === true ? true : $result) );
            
        }else{
            COption::SetOptionString( 'cleantalk.antispam', 'sfw_update_result', json_encode(array('error'=>true, 'error_string'=>'SFW_DISABLED')));            
        }
        
        return "CleantalkAntispam::sfw_update();";
    }

    /*
     * Sends and clean local logs storage
     */
    static public function sfw_send_logs()
    {
        
        $is_sfw    = COption::GetOptionString( 'cleantalk.antispam', 'form_sfw',  0 );
        $key       = COption::GetOptionString( 'cleantalk.antispam', 'key',       '' );
        $key_is_ok = COption::GetOptionString( 'cleantalk.antispam', 'key_is_ok', '0');
        
        if(!empty($is_sfw) && !empty($key) && !empty($key_is_ok)){
            
            $sfw = new CleantalkSFW();
            $result = $sfw->send_logs($key);
            unset($sfw);
            
            COption::SetOptionString( 'cleantalk.antispam', 'send_logs_result', json_encode($result === true ? true : $result) );
            
        }else{
            COption::SetOptionString( 'cleantalk.antispam', 'send_logs_result', json_encode(array('error'=>true, 'error_string'=>'SFW_DISABLED' )));
        }
        
        return "CleantalkAntispam::sfw_send_logs();";
    }
    
    /**
     * Show message when spam is blocked
     * @param string message
     */
    
    static function CleantalkDie($message)
    {
        if (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'buyoneclick')
        {
            $result=Array('error'=>true,'msg'=>'js_kr_error_send');
            print json_encode($result);
        }
        else
        {
            $error_tpl=file_get_contents(dirname(__FILE__)."/error.html");
            print str_replace('%ERROR_TEXT%',$message,$error_tpl);          
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
      );
        $fields_exclusions = CleantalkCustomConfig::get_fields_exclusions();
        if ($fields_exclusions)
            array_merge($skip_fields_with_strings,$fields_exclusions);  
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
            

            // Decodes URL-encoded data to string.
            $value = urldecode($value); 

            // Email
            if (!$email && preg_match("/^\S+@\S+\.\S+$/", $value)){
              $email = $value;
              
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
                $message[$prev_name.$key] = $value;
            
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
        self::ct_cookie();
        if (!is_object($USER)) $USER = new CUser;
        $ct_status               = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_global               = COption::GetOptionString('cleantalk.antispam', 'form_global_check', 0);
        $ct_global_without_email = COption::GetOptionString('cleantalk.antispam', 'form_global_check_without_email', 0);
        $ct_key                     = COption::GetOptionString( 'cleantalk.antispam', 'key', '' );
        $last_checked            = COption::GetOptionString( 'cleantalk.antispam', 'last_checked', 0 );
        $last_status             = COption::GetOptionString( 'cleantalk.antispam', 'is_paid', 0 );
        $is_sfw                  = COption::GetOptionString( 'cleantalk.antispam', 'form_sfw', 0 );
        $new_checked             = time();
        
        if($is_sfw==1 && !$USER->IsAdmin())
        {
            $sfw = new CleantalkSFW();
            $is_sfw_check = true;
            $sfw->ip_array = (array)CleantalkSFW::ip_get(array('real'), true);  

                foreach($sfw->ip_array as $key => $value)
                {
                  if(isset($_COOKIE['ct_sfw_pass_key']) && $_COOKIE['ct_sfw_pass_key'] == md5($value . trim($ct_key)))
                  {
                    $is_sfw_check=false;
                    if(isset($_COOKIE['ct_sfw_passed']))
                    {
                      @setcookie ('ct_sfw_passed'); //Deleting cookie
                      $sfw->sfw_update_logs($value, 'passed');
                    }
                  }
              } unset($key, $value);  

            if($is_sfw_check)
            {
              $sfw->check_ip();
              if($sfw->result)
              {
                $sfw->sfw_update_logs($sfw->blocked_ip, 'blocked');
                $sfw->sfw_die(trim($ct_key));
              }
            }
        }
        

        if($ct_key!='' && $ct_key!='enter key' && $USER->IsAdmin())
        {
            $new_status=$last_status;
            if($new_checked-$last_checked>86400)
            {
                
                $result = CleantalkHelper::api_method__get_account_status($ct_key);
                
                if(empty($result['error'])){
                    
                    if(isset($result['paid']))
                    {
                        $new_status = intval($result['paid']);
                        if($last_status !=1 && $new_status == 1)
                        {
                            COption::SetOptionString( 'cleantalk.antispam', 'is_paid', 1 );
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
                
                $result = CleantalkHelper::api_method__notice_paid_till($ct_key);
                
                if(empty($result['error'])){
                    
                    if (isset($result['moderate_ip']) && $result['moderate_ip'] == 1)
                    {
                        COption::SetOptionString( 'cleantalk.antispam', 'moderate_ip', 1 );
                        COption::SetOptionString( 'cleantalk.antispam', 'ip_license', $result['ip_license'] );
                    }
                    else
                    {
                        COption::SetOptionString( 'cleantalk.antispam', 'moderate_ip', 0 );
                        COption::SetOptionString( 'cleantalk.antispam', 'ip_license', 0 );
                    }
                }
                
                COption::SetOptionString( 'cleantalk.antispam', 'last_checked', $new_checked );
            }
        
        }       
        
        
        if (!$USER->IsAdmin() && $ct_status == 1 && $ct_global == 1)
        {
            
            // Exclusions
            if( empty($_POST) ||
                (isset($_POST['AUTH_FORM'], $_POST['TYPE'], $_POST['USER_LOGIN'])) ||
                (isset($_POST['order']['action']) && $_POST['order']['action'] == 'refreshOrderAjax')|| // Order AJAX refresh
                (isset($_POST['order']['action']) && $_POST['order']['action'] == 'saveOrderAjax') ||
                (isset($_POST['action']) && $_POST['action'] == 'refreshOrderAjax') ||
                (isset($_POST['action']) && $_POST['action'] == 'saveOrderAjax') ||
                strpos($_SERVER['REQUEST_URI'],'/user-profile.php?update=Y')!==false
            )
            {
                return;
            }
            
            $ct_temp_msg_data = CleantalkAntispam::CleantalkGetFields($_POST); //Works via links need to be fixed
          
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
            
            if($arUser["sender_email"] != '' || $ct_global_without_email == 1)
            {                                               
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
                            CleantalkAntispam::CleantalkDie($aResult['ct_result_comment']);
                            return false;
                        }
                    }
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_order= COption::GetOptionString('cleantalk.antispam', 'form_order', '0');
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
        
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_webform= COption::GetOptionString('cleantalk.antispam', 'web_form', '0');
        
        if ($ct_status == 1 && $ct_webform == 1){
            
            $sender_email = null;
            $message = '';
            
            $skip_keys = array(
                'WEB_FORM_ID',
                'RESULT_ID',
                'formresult',
                'sessid',
                'captcha_',
                'web_form_submit'
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
        
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_comment_treelike = COption::GetOptionString('cleantalk.antispam', 'form_comment_treelike', '0');
        if ($ct_status == 1 && $ct_comment_treelike == 1) {
            if($USER->IsAdmin())
                return;
            // Skip authorized user with more than 5 approved comments
            if($USER->IsAuthorized()){
                $approved_comments = CTreelikeComments::GetList(
                    array('ID' => 'ASC'),
                    array('USER_ID'=>$arFields['USER_ID'], 'ACTIVATED' => 1),
                    '',
                    TRUE    // return count(*)
                );
                if(intval($approved_comments) > 5)
                    return;
            }
            $aComment = array();
            $aComment['type'] = 'comment';
            $aComment['sender_email'] = isset($arFields['EMAIL']) ? $arFields['EMAIL'] : '';
            $aComment['sender_nickname'] = isset($arFields['AUTHOR_NAME']) ? $arFields['AUTHOR_NAME'] : '';
            $aComment['message_title'] = '';
            $aComment['message_body'] = isset($arFields['COMMENT']) ? $arFields['COMMENT'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';

            if(COption::GetOptionString('cleantalk.antispam', 'form_send_example', '0') == 1){
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_comment_blog = COption::GetOptionString('cleantalk.antispam', 'form_comment_blog', '0');
        if ($ct_status == 1 && $ct_comment_blog == 1) {
            if($USER->IsAdmin())
                return;
            // Skip authorized user with more than 5 approved comments
            if($USER->IsAuthorized()){
                $approved_comments = CBlogComment::GetList(
                    array('ID' => 'ASC'),
                    array('AUTHOR_ID'=>$arFields['AUTHOR_ID'], 'PUBLISH_STATUS' => BLOG_PUBLISH_STATUS_PUBLISH),
                    array()    // return count(*)
                );
                if(intval($approved_comments) > 5)
                    return;
            }
            $aComment = array();
            $aComment['type'] = 'comment';
            $aComment['sender_email'] = isset($arFields['AUTHOR_EMAIL']) ? $arFields['AUTHOR_EMAIL'] : '';
            $aComment['sender_nickname'] = isset($arFields['AUTHOR_NAME']) ? $arFields['AUTHOR_NAME'] : '';
            $aComment['message_title'] = '';
            $aComment['message_body'] = isset($arFields['POST_TEXT']) ? $arFields['POST_TEXT'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';
            
        if(COption::GetOptionString('cleantalk.antispam', 'form_send_example', '0') == 1){
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_comment_forum = COption::GetOptionString('cleantalk.antispam', 'form_comment_forum', '0');
        if ($ct_status == 1 && $ct_comment_forum == 1) {
            if($USER->IsAdmin())
                return;
            // Skip authorized user with more than 5 approved messages
            if($USER->IsAuthorized()){
                $approved_messages = CForumMessage::GetList(
                    array('ID'=>'ASC'),
                    array('AUTHOR_ID'=>$arFields['AUTHOR_ID'], 'APPROVED'=>'Y'),
                    TRUE
                );
                if(intval($approved_messages) > 5)
                    return;
            }
            $aComment = array();
            $aComment['type'] = 'comment';
            $aComment['sender_email'] = isset($arFields['AUTHOR_EMAIL']) ? $arFields['AUTHOR_EMAIL'] : '';
            $aComment['sender_nickname'] = isset($arFields['AUTHOR_NAME']) ? $arFields['AUTHOR_NAME'] : '';
            $aComment['message_title'] = '';
            $aComment['message_body'] = isset($arFields['POST_MESSAGE']) ? $arFields['POST_MESSAGE'] : '';
            $aComment['example_title'] = '';
            $aComment['example_body'] = '';
            $aComment['example_comments'] = '';
            
        if(COption::GetOptionString('cleantalk.antispam', 'form_send_example', '0') == 1){
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_comment_forum = COption::GetOptionString('cleantalk.antispam', 'form_comment_forum', '0');
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_comment_forum = COption::GetOptionString('cleantalk.antispam', 'form_comment_forum', '0');
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_comment_forum = COption::GetOptionString('cleantalk.antispam', 'form_comment_forum', '0');
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
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_forum_private_messages = COption::GetOptionString('cleantalk.antispam', 'form_forum_private_messages', '0');
        if ($ct_status == 1 && $ct_forum_private_messages == 1) {
            
            if($USER->IsAdmin())
                return;
            
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
        
        $ct_status = COption::GetOptionString('cleantalk.antispam', 'status', '0');
        $ct_new_user = COption::GetOptionString('cleantalk.antispam', 'form_new_user', '0');

        if ($ct_status == 1 && $ct_new_user == 1) {
            $aUser = array();
            $aUser['type'] = 'register';
            $aUser['sender_email'] = isset($arFields['EMAIL']) ? $arFields['EMAIL'] : '';
            $aUser['sender_nickname'] = isset($arFields['LOGIN']) ? $arFields['LOGIN'] : '';
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
        if(!defined("ADMIN_SECTION") && COption::GetOptionString( 'cleantalk.antispam', 'status', 0 ) == 1 && strpos($content,'<!-- CLEANTALK template addon -->') === false && strpos($content,'</body>') !== false)           
            $content = preg_replace('/(<\/body[^>]*>)/i', '${1}'."\n".self::FormAddon(), $content, 1);
    }
    /**
     * Deprecated!
     */
    static function FormAddon() {

        if(!defined("ADMIN_SECTION") && COption::GetOptionString( 'cleantalk.antispam', 'status', 0 ) == 1 )
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
        global $DB;
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
        if($type != 'comment' && $type != 'webform' &&$type != 'register' && $type != 'order' && $type != 'feedback_general_contact_form' && $type != 'private_message'){
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

        $ct_key = COption::GetOptionString('cleantalk.antispam', 'key', '0');
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
            'access_key' => COption::GetOptionString('cleantalk.antispam', 'key', '0'),
            'form_new_user' => COption::GetOptionString('cleantalk.antispam', 'form_new_user', '0'),
            'form_comment_blog' => COption::GetOptionString('cleantalk.antispam', 'form_comment_blog', '0'),        
            'form_comment_forum' => COption::GetOptionString('cleantalk.antispam', 'form_comment_forum', '0'),
            'form_forum_private_messages' => COption::GetOptionString('cleantalk.antispam', 'form_forum_private_messages', '0'),
            'form_comment_treelike' => COption::GetOptionString('cleantalk.antispam', 'form_comment_treelike', '0'),
            'form_send_example' => COption::GetOptionString('cleantalk.antispam', 'form_send_example', '0'),
            'form_order' => COption::GetOptionString('cleantalk.antispam', 'form_order', '0'),
            'web_form' => COption::GetOptionString('cleantalk.antispam', 'web_form', '0'),        
            'form_global_check' => COption::GetOptionString('cleantalk.antispam', 'form_global_check', '0'),
            'form_global_check_without_email' => COption::GetOptionString('cleantalk.antispam', 'form_global_check_without_email', '0'),
            'form_sfw' => COption::GetOptionString('cleantalk.antispam', 'form_sfw', '0'),
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
        $ct_request->agent = 'bitrix-3113';
        $ct_request->response_lang = 'ru';
        $ct_request->js_on = $checkjs;
        $ct_request->sender_info = $sender_info;
        $ct_request->submit_time = self::ct_cookies_test() == 1 ? time() - (int)$_COOKIE['ct_timestamp'] : null;
        if (isset($arEntity['message_title']) && is_array($arEntity))
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
                COption::SetOptionString( 'cleantalk.antispam', 'key_is_ok', '0');
            
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

            $ct_key = COption::GetOptionString('cleantalk.antispam', 'key', '0');
            $ct_ws = self::GetWorkServer();

            $ct = new Cleantalk();
            $ct->work_url = $ct_ws['work_url'];
            $ct->server_url = $ct_ws['server_url'];
            $ct->server_ttl = $ct_ws['server_ttl'];
            $ct->server_changed = $ct_ws['server_changed'];

            $ct_request = new CleantalkRequest();
            $ct_request->auth_key = $ct_key;
            $ct_request->agent = 'bitrix-3113';
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
            return array(md5(COption::GetOptionString('cleantalk.antispam', 'key', '0') . '+' . COption::GetOptionString('main', 'email_from')));
        }
    }
    /*
     * Set Cookies test for cookie test
     * Sets cookies with pararms timestamp && landing_timestamp && pervious_referer
     * Sets test cookie with all other cookies
     */
    private function ct_cookie(){
        
        // Cookie names to validate
        $cookie_test_value = array(
            'cookies_names' => array(),
            'check_value' => COption::GetOptionString('cleantalk.antispam', 'key', '0'),
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
    private function ct_cookies_test()
    {       
        if(isset($_COOKIE['ct_cookies_test'])){
            
            $cookie_test = json_decode(stripslashes($_COOKIE['ct_cookies_test']), true);
            
            $check_srting = COption::GetOptionString('cleantalk.antispam', 'key', '0');
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
}
