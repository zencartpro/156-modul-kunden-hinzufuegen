<?php
/**
 * @copyright Copyright 2003-2019 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 *
 * add_customers module modified by Garden 2012-07-20
 * www.inzencart.cz Czech forum for ZenCart
 * Modified for Zen Cart 1.5.0, lat9 2012-08-31
 * Modified for Zen Cart German 1.5.6, webchills 2019-09-14
 */
$row_positions = array(); //must be kept global

function check_file_upload() {
  global $db, $row_positions, $post;

  $files = $_FILES['bulk_upload'];
  $errors = array();
  $to_insert = array();

  if (!zen_not_null($files['name'])) {
    $errors[] = ERROR_NO_UPLOAD_FILE;
  
  } else {
    if ($files['error'] != 0) {
    $errors[] = sprintf (ERROR_FILE_UPLOAD, $files['error']);
    
    } else {
      $pos = strrpos($files['name'],'.')+1;
      $allowed_extensions = array ( 'TXT', 'CSV' );
      $extension = substr ($files['name'], $pos);
      if ( (strlen ($extension) < 1 ) || !in_array(strtoupper($extension), $allowed_extensions) ) {
      $errors[] = sprintf (ERROR_BAD_FILE_EXTENSION, $extension) . implode(', ', $allowed_extensions);
    
      } else {
//        if (!defined('DIR_FS_UPLOADS')) define('DIR_FS_UPLOADS', DIR_FS_CATALOG . DIR_WS_IMAGES . 'uploads/');  /*v2.0.5a,v2.0.6d*/
        $path = DIR_FS_BACKUP . $files['name'];  /*v2.0.6c*/

        if (!move_uploaded_file($files['tmp_name'], $path)) {
          $errors[] = ERROR_CANT_MOVE_FILE;
      
        } else {
          chmod($path, 0775);
          ini_set("auto_detect_line_endings", true);  /*v2.0.3a*/
          $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);  /*v2.0.3c*/
          
          $headerTags = explode (',', 'email,first_name,last_name,dob,gender,company,street_address,suburb,city,state,postcode,country,telephone,fax,newsletter,send_welcome,zone_id,customers_group_pricing');  /*v2.0.5c*/
      
          for ($i=0, $n = count($lines), $foundHeader = false; $i < $n; $i++) {  /*v2.0.3c*/
            if (strlen($lines[$i]) > 0) {
              $line = explode(',', $lines[$i]);
              
              // Process header row
              if (!$foundHeader) {
                $foundHeader = true;
                foreach ($line as $key => $value) {
                  $value = strtolower( trim ($value) );
                  if (in_array ($value, $headerTags)) {
                    $row_positions[$value] = $key;
                  }
                }
 
              // Process data row
              } else {
                if (count($row_positions) == 0) {
                  $errors[] = ERROR_BAD_FILE_HEADER;
                  break;
          
                } else {
                  $country = trim(strtoupper(get_row_id('country', $line)));

                  if ($country == 'UK') $country = 'GB';
                  $country_query = $db->Execute("SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . $country . "'");
                  $country_id = ($country_query->RecordCount() > 0) ? $country_query->fields['countries_id'] : false;

                  $gender = strtolower(get_row_id('gender', $line));
                  $gender = substr($gender, 0, 1);

                  //dynamic type
                  $state   = get_row_id ('state', $line);
                  $zone_id = get_row_id ('zone_id', $line);
                  if ($country_id !== false) {
                    $zones   = $db->Execute("SELECT zone_id, zone_name, zone_code
                                             FROM " . TABLE_ZONES . "
                                             WHERE zone_country_id = " . (int)$country_id . "
                                             AND ( zone_name = '" . $state . "' OR zone_code = '" . strtoupper ($state) . "' OR zone_id = " . (int)$zone_id . ")"); /*v2.0.3c*/
                  }
                  // Country doesn't have zones (or country isn't valid), use state/province name
                  if ($country_id !== false && $zones->RecordCount() != 1) {
                    $zone_id = 0;
          
                  // Country has zones, use the zone_id
                  } else {
                    $state = '';
                    $zone_id = $zones->fields['zone_id'];
          
                  }
          
                  $post = array( 'customers_gender'        => $gender,
                                 'customers_firstname'     => get_row_id('first_name', $line),
                                 'customers_lastname'      => get_row_id('last_name', $line),
                                 'customers_dob'           => get_row_id('dob', $line),
                                 'customers_email_address' => get_row_id('email', $line),
                                 'entry_company'           => get_row_id('company', $line),
                                 'entry_street_address'    => get_row_id('street_address', $line),
                                 'entry_suburb'            => get_row_id('suburb', $line),
                                 'entry_city'              => get_row_id('city', $line),
                                 'entry_state'             => $state,
                                 'entry_postcode'          => get_row_id('postcode', $line),
                                 'entry_country_id'        => $country_id,
                                 'entry_zone_id'           => $zone_id,
                                 'customers_telephone'     => get_row_id('telephone', $line),
                                 'customers_fax'           => get_row_id('fax', $line),
                                 'customers_newsletter'    => (get_row_id('newsletter', $line) == '0' ? 0 : 1),
                                 'send_welcome'            => (substr(get_row_id('send_welcome', $line), 0, 1) == '1' ? 1 : 0),
                                 'customers_authorization' => 0,
                                 'customers_group_pricing' => (get_row_id('customers_group_pricing', $line) === false) ? 0 : get_row_id('customers_group_pricing', $line),  /*v2.0.5c*/
                                 'customers_referral'      => '',
                                 'customers_email_format'  => (ACCOUNT_EMAIL_PREFERENCE == '1' ? 'HTML' : 'TEXT'),
                               );

                  $array = validate_customer($post, 'date_format_m');

                  if (isset($array['errors'][0])) {
                    $errors[$i] = $array['errors'];
          
                  } else {
                    $to_insert[$i] = $post;
                    if ($_POST['insert_mode'] == 'part') {
                      insert_customer($post);
                    }        
                  }
                }          
              }
            }
          }
//-bof-v2.0.3a
          if (count($errors) == 0) {          
            if (!$foundHeader) {
              $errors[] = ERROR_BAD_FILE_HEADER;
              
            } elseif (count($to_insert) == 0) {
              $errors[] = ERROR_NO_RECORDS;
              
            }
          }
//-eof-v2.0.3a

          if ($_POST['insert_mode'] == 'file' && !count ($errors) ) {
            foreach ($to_insert as $line_no=>$post) {
              insert_customer($post);
            }
          }

          unlink($path); //delete the file
      
        }
      }
    }
  }

  $return['errors'] = $errors;
  $return['feedback'] = $to_insert;

  return $return;
}

function get_row_id($field_name, $line) {
  global $row_positions;

  return (isset ($row_positions[$field_name]) && $row_positions[$field_name] !== false) ? $line[$row_positions[$field_name]] : false;
}

function insert_customer($inArray) {
  global $db, $messageStack;
  
//-bof-20151224-lat9-Use updated random number generator, if available.
  $customers_password = (function_exists ('zen_create_PADSS_password')) ? zen_create_PADSS_password ((ENTRY_PASSWORD_MIN_LENGTH > 0) ? ENTRY_PASSWORD_MIN_LENGTH : 5) : zen_create_random_value (ENTRY_PASSWORD_MIN_LENGTH);
//-eof-20151224-lat9

  $customers_firstname = (isset ($inArray['customers_firstname'])) ? zen_db_prepare_input(zen_sanitize_string($inArray['customers_firstname'])) : '';
  $customers_lastname = (isset ($inArray['customers_lastname'])) ? zen_db_prepare_input(zen_sanitize_string($inArray['customers_lastname'])) : '';
  if (!isset ($inArray['customers_email_address'])) {
    trigger_error ("insert_customer, missing email address: " . var_export ($inArray, true), E_USER_ERROR);
    exit ();
  }
  $customers_email_address = zen_db_prepare_input($inArray['customers_email_address']);
  $customers_telephone = (isset ($inArray['customers_telephone'])) ? zen_db_prepare_input($inArray['customers_telephone']) : '';
  $customers_fax = (isset ($inArray['customers_fax'])) ? zen_db_prepare_input($inArray['customers_fax']) : '';
  $customers_newsletter = (isset ($inArray['customers_newsletter'])) ? zen_db_prepare_input($inArray['customers_newsletter']) : '0';
  $customers_group_pricing = (isset ($inArray['customers_group_pricing'])) ? (int)zen_db_prepare_input($inArray['customers_group_pricing']) : 0;
  $customers_email_format = (isset ($inArray['customers_email_format'])) ? zen_db_prepare_input($inArray['customers_email_format']) : ((ACCOUNT_EMAIL_PREFERENCE == '1') ? 'HTML' : 'TEXT');
  $customers_gender = (isset ($inArray['customers_gender'])) ? zen_db_prepare_input($inArray['customers_gender']) : '';
  $customers_dob = (isset($inArray['customers_dob'])) ? zen_db_prepare_input('0001-01-01 00:00:00') : zen_db_prepare_input($inArray['customers_dob']);


  $customers_authorization = (isset($inArray['customers_authorization'])) ? (int)zen_db_prepare_input($inArray['customers_authorization']) : 0;
  $customers_referral= (isset($inArray['customers_referral'])) ? zen_db_prepare_input($inArray['customers_referral']) : '';

  $send_welcome = (isset($inArray['send_welcome'])) ? zen_db_prepare_input($inArray['send_welcome']) : 0;

  if (CUSTOMERS_APPROVAL_AUTHORIZATION == 2 and $customers_authorization == 1) {
    $customers_authorization = 2;
    $messageStack->add_session(ERROR_CUSTOMER_APPROVAL_CORRECTION2, 'caution');
  }

  if (CUSTOMERS_APPROVAL_AUTHORIZATION == 1 and $customers_authorization == 2) {
    $customers_authorization = 1;
    $messageStack->add_session(ERROR_CUSTOMER_APPROVAL_CORRECTION1, 'caution');
  }

  $default_address_id = (isset($inArray['default_address_id'])) ? (int)zen_db_prepare_input($inArray['default_address_id']) : 0;
  $entry_street_address = (isset($inArray['entry_street_address'])) ? zen_db_prepare_input($inArray['entry_street_address']) : '';
  $entry_suburb = (isset($inArray['entry_suburb'])) ? zen_db_prepare_input($inArray['entry_suburb']) : '';
  $entry_postcode = (isset($inArray['entry_postcode'])) ? zen_db_prepare_input($inArray['entry_postcode']) : '';
  $entry_city = (isset($inArray['entry_city'])) ? zen_db_prepare_input($inArray['entry_city']) : '';
  $entry_country_id = (isset($inArray['entry_country_id'])) ? (int)zen_db_prepare_input($inArray['entry_country_id']) : 0;

  $entry_company = (isset($inArray['entry_company'])) ? zen_db_prepare_input($inArray['entry_company']) : '';
  $entry_state = (isset($inArray['entry_state'])) ? zen_db_prepare_input($inArray['entry_state']) : '';

  $entry_zone_id = (isset($inArray['entry_zone_id'])) ? (int)zen_db_prepare_input($inArray['entry_zone_id']) : 0;

  $sql_data_array = array('customers_firstname'     => $customers_firstname,
                          'customers_lastname'      => $customers_lastname,
                          'customers_email_address' => $customers_email_address,
                          'customers_telephone'     => $customers_telephone,
                          'customers_fax'           => $customers_fax,
                          'customers_group_pricing' => $customers_group_pricing,
                          'customers_newsletter'    => $customers_newsletter,
                          'customers_email_format'  => $customers_email_format,
                          'customers_authorization' => $customers_authorization,
                          'customers_password'      => zen_encrypt_password($customers_password),
  );

  if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $customers_gender;
  if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = $customers_dob;
  if ((CUSTOMERS_REFERRAL_STATUS == '2' and $customers_referral != '')) $sql_data_array['customers_referral'] = $customers_referral;
  
  zen_db_perform(TABLE_CUSTOMERS, $sql_data_array);
  $customer_id = $db->Insert_ID();

  $sql_data_array = array( 'customers_id'         => $customer_id,
                           'entry_firstname'      => $customers_firstname,
                           'entry_lastname'       => $customers_lastname,
                           'entry_street_address' => $entry_street_address,
                           'entry_postcode'       => $entry_postcode,
                           'entry_city'           => $entry_city,
                           'entry_country_id'     => $entry_country_id);

  if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $entry_company;
  if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = $entry_suburb;
  if (ACCOUNT_STATE == 'true') {
    if ($entry_zone_id > 0) {
      $sql_data_array['entry_zone_id'] = $entry_zone_id;
      $sql_data_array['entry_state'] = '';
    } else {
      $sql_data_array['entry_zone_id'] = '0';
      $sql_data_array['entry_state'] = $entry_state;
    }
  }

  zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

  $address_id = $db->Insert_ID();

  $sql = "update " . TABLE_CUSTOMERS . "
              set customers_default_address_id = '" . (int)$address_id . "'
              where customers_id = '" . (int)$customer_id . "'";

  $db->Execute($sql);

  $sql = "insert into " . TABLE_CUSTOMERS_INFO . "
                          (customers_info_id, customers_info_number_of_logons,
                           customers_info_date_account_created)
              values ('" . (int)$customer_id . "', '0', now())";

  $db->Execute($sql);

   

  // build the message content
  if($send_welcome == 1) {
    sendWelcomeEmail ($customers_gender, $customers_firstname, $customers_lastname, $customers_email_address, $customers_password);
  }
  return $customers_firstname . ' ' . $customers_lastname;
  
}
function sendWelcomeEmail($customers_gender, $customers_firstname, $customers_lastname, $customers_email_address, $customers_password) {
  global $db, $currencies;
  
  $name = $customers_firstname . ' ' . $customers_lastname;

  if (ACCOUNT_GENDER == 'true') {
    if ($customers_gender == 'm') {
      $email_text = sprintf(EMAIL_GREET_MR, $customers_lastname);
      $admin_email_text = sprintf(EMAIL_GREET_MR, $customers_lastname);
    } else {
      $email_text = sprintf(EMAIL_GREET_MS, $customers_lastname);
      $admin_email_text = sprintf(EMAIL_GREET_MS, $customers_lastname);
    }
  } else {
    $email_text = sprintf(EMAIL_GREET_NONE, $customers_firstname);
    $admin_email_text = sprintf(EMAIL_GREET_NONE, $customers_firstname);
  }
  $html_msg['EMAIL_GREETING'] = str_replace('\n','',$email_text);
  $html_msg['EMAIL_FIRST_NAME'] = $customers_firstname;
  $html_msg['EMAIL_LAST_NAME']  = $customers_lastname;

  $admin_html_msg['EMAIL_GREETING'] = str_replace('\n','',$email_text);
  $admin_html_msg['EMAIL_FIRST_NAME'] = $customers_firstname;
  $admin_html_msg['EMAIL_LAST_NAME']  = $customers_lastname;

  // initial welcome
  $email_text .=  EMAIL_WELCOME;
  $html_msg['EMAIL_WELCOME'] = str_replace('\n','',EMAIL_WELCOME);

  $admin_email_text .=  EMAIL_WELCOME;
  $admin_html_msg['EMAIL_WELCOME'] = str_replace('\n','',EMAIL_WELCOME);

  if (NEW_SIGNUP_DISCOUNT_COUPON != '' and NEW_SIGNUP_DISCOUNT_COUPON != '0') {
    $coupon_id = NEW_SIGNUP_DISCOUNT_COUPON;
    $coupon = $db->Execute("select * from " . TABLE_COUPONS . " where coupon_id = '" . $coupon_id . "'");
    $coupon_desc = $db->Execute("select coupon_description from " . TABLE_COUPONS_DESCRIPTION . " where coupon_id = '" . $coupon_id . "' and language_id = '" . $_SESSION['languages_id'] . "'");
    $db->Execute("insert into " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) values ('" . $coupon_id ."', '0', 'Admin', '" . $customers_email_address . "', now() )");

      // if on, add in Discount Coupon explanation
      //        $email_text .= EMAIL_COUPON_INCENTIVE_HEADER .
    $email_text .= "\n" . EMAIL_COUPON_INCENTIVE_HEADER .
      (!empty($coupon_desc->fields['coupon_description']) ? $coupon_desc->fields['coupon_description'] . "\n\n" : '') .
      strip_tags(sprintf(EMAIL_COUPON_REDEEM, ' ' . $coupon->fields['coupon_code'])) . EMAIL_SEPARATOR;
    $html_msg['COUPON_TEXT_VOUCHER_IS'] = EMAIL_COUPON_INCENTIVE_HEADER ;
    $html_msg['COUPON_DESCRIPTION']     = (!empty($coupon_desc->fields['coupon_description']) ? '<strong>' . $coupon_desc->fields['coupon_description'] . '</strong>' : '');
    $html_msg['COUPON_TEXT_TO_REDEEM']  = str_replace("\n", '', sprintf(EMAIL_COUPON_REDEEM, ''));
    $html_msg['COUPON_CODE']  = $coupon->fields['coupon_code'];

    $admin_email_text .= "\n" . EMAIL_COUPON_INCENTIVE_HEADER .
      (!empty($coupon_desc->fields['coupon_description']) ? $coupon_desc->fields['coupon_description'] . "\n\n" : '') .
      strip_tags(sprintf(EMAIL_COUPON_REDEEM, ' ' . $coupon->fields['coupon_code'])) . EMAIL_SEPARATOR;
    $admin_html_msg['COUPON_TEXT_VOUCHER_IS'] = EMAIL_COUPON_INCENTIVE_HEADER ;
    $admin_html_msg['COUPON_DESCRIPTION']     = (!empty($coupon_desc->fields['coupon_description']) ? '<strong>' . $coupon_desc->fields['coupon_description'] . '</strong>' : '');
    $admin_html_msg['COUPON_TEXT_TO_REDEEM']  = str_replace("\n", '', sprintf(EMAIL_COUPON_REDEEM, ''));
     $admin_html_msg['COUPON_CODE']  = $coupon->fields['coupon_code'];
  } //endif coupon

  if (NEW_SIGNUP_GIFT_VOUCHER_AMOUNT > 0) {
    $coupon_code = create_coupon_code();
    $insert_query = $db->Execute("insert into " . TABLE_COUPONS . " (coupon_code, coupon_type, coupon_amount, date_created) values ('" . $coupon_code . "', 'G', '" . NEW_SIGNUP_GIFT_VOUCHER_AMOUNT . "', now())");
    $insert_id = $db->Insert_ID();
    $db->Execute("insert into " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) values ('" . $insert_id ."', '0', 'Admin', '" . $customers_email_address . "', now() )");

    // if on, add in GV explanation
    $email_text .= "\n\n" . sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) .
    sprintf(EMAIL_GV_REDEEM, $coupon_code) .
      EMAIL_GV_LINK . zen_catalog_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . "\n\n" .
      EMAIL_GV_LINK_OTHER . EMAIL_SEPARATOR;
    $html_msg['GV_WORTH'] = str_replace('\n','',sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) );
    $html_msg['GV_REDEEM'] = str_replace('\n','',str_replace('\n\n','<br />',sprintf(EMAIL_GV_REDEEM, '<strong>' . $coupon_code . '</strong>')));
    $html_msg['GV_CODE_NUM'] = $coupon_code;
    $html_msg['GV_CODE_URL'] = str_replace('\n','',EMAIL_GV_LINK . '<a href="' . zen_catalog_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . '">' . TEXT_GV_NAME . ': ' . $coupon_code . '</a>');
    $html_msg['GV_LINK_OTHER'] = EMAIL_GV_LINK_OTHER;

    $admin_email_text .= "\n\n" . sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) .
      sprintf(EMAIL_GV_REDEEM, $coupon_code) .
      EMAIL_GV_LINK . zen_catalog_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . "\n\n" .
      EMAIL_GV_LINK_OTHER . EMAIL_SEPARATOR;
    $admin_html_msg['GV_WORTH'] = str_replace('\n','',sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) );
    $admin_html_msg['GV_REDEEM'] = str_replace('\n','',str_replace('\n\n','<br />',sprintf(EMAIL_GV_REDEEM, '<strong>' . $coupon_code . '</strong>')));
    $admin_html_msg['GV_CODE_NUM'] = $coupon_code;
    $admin_html_msg['GV_CODE_URL'] = str_replace('\n','',EMAIL_GV_LINK . '<a href="' . zen_catalog_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . '">' . TEXT_GV_NAME . ': ' . $coupon_code . '</a>');
    $admin_html_msg['GV_LINK_OTHER'] = EMAIL_GV_LINK_OTHER;
  } // endif voucher

  // add in regular email welcome text
  $email_text .= "\n\n" . EMAIL_TEXT_1 . (($customers_password !== false) ? sprintf(EMAIL_TEXT_2, $customers_password) : '') . EMAIL_TEXT_3 . EMAIL_CONTACT . EMAIL_GV_CLOSURE;
  $admin_email_text .= "\n\n" . sprintf(EMAIL_TEXT_1,'xxxxx') . EMAIL_CONTACT . EMAIL_GV_CLOSURE;

  $html_msg['EMAIL_MESSAGE_HTML']  = str_replace('\n','',EMAIL_TEXT_1 . (($customers_password !== false) ? sprintf(EMAIL_TEXT_2, $customers_password) : '') . EMAIL_TEXT_3);
  $html_msg['EMAIL_CONTACT_OWNER'] = str_replace('\n','',EMAIL_CONTACT);
  $html_msg['EMAIL_CLOSURE']       = nl2br(EMAIL_GV_CLOSURE);

  $admin_html_msg['EMAIL_MESSAGE_HTML']  = str_replace('\n','',sprintf(EMAIL_TEXT_1,$customers_password));
  $admin_html_msg['EMAIL_CONTACT_OWNER'] = str_replace('\n','',EMAIL_CONTACT);
  $admin_html_msg['EMAIL_CLOSURE']       = nl2br(EMAIL_GV_CLOSURE);

  // include create-account-specific disclaimer
  $email_text .= "\n\n" . sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, STORE_OWNER_EMAIL_ADDRESS). "\n\n";
  $html_msg['EMAIL_DISCLAIMER'] = sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, '<a href="mailto:' . STORE_OWNER_EMAIL_ADDRESS . '">'. STORE_OWNER_EMAIL_ADDRESS .' </a>');

  $admin_email_text .= "\n\n" . sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, STORE_OWNER_EMAIL_ADDRESS). "\n\n";
  $admin_html_msg['EMAIL_DISCLAIMER'] = sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, '<a href="mailto:' . STORE_OWNER_EMAIL_ADDRESS . '">'. STORE_OWNER_EMAIL_ADDRESS .' </a>');

  // send welcome email
  zen_mail($name, $customers_email_address, EMAIL_SUBJECT, $email_text, STORE_NAME, EMAIL_FROM, $html_msg, 'welcome');

  // send additional emails
  if (SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO_STATUS == '1' and SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO !='') {
    $extra_info=email_collect_extra_info($name, $customers_email_address, $customers_firstname . ' ' . $customers_lastname , $customers_email_address );
    $admin_html_msg['EXTRA_INFO'] = $extra_info['HTML'];
    zen_mail('', SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO, '[ACCOUNT CREATED BY ADMINISTRATOR]' . ' ' . EMAIL_SUBJECT,$admin_email_text . $extra_info['TEXT'], STORE_NAME, EMAIL_FROM, $admin_html_msg, 'welcome_extra');
  } //endif send extra emails

}

function validate_customer(&$inArray, $dateFormatName) {
  global $db, $messageStack, $theFormats;

  $errors = array();

  $customers_firstname = (isset ($inArray['customers_firstname'])) ? zen_db_prepare_input($inArray['customers_firstname']) : '';
  $customers_lastname = (isset ($inArray['customers_lastname'])) ? zen_db_prepare_input($inArray['customers_lastname']) : '';
  $customers_email_address = (isset ($inArray['customers_email_address'])) ? zen_db_prepare_input($inArray['customers_email_address']) : '';
  $customers_telephone = (isset ($inArray['customers_telephone'])) ? zen_db_prepare_input($inArray['customers_telephone']) : '';
  $customers_fax = (isset ($inArray['customers_fax'])) ? zen_db_prepare_input($inArray['customers_fax']) : '';
  $customers_group_pricing = (isset ($inArray['customers_group_pricing'])) ? (int)zen_db_prepare_input($inArray['customers_group_pricing']) : 0;
  $customers_gender = (isset ($inArray['customers_gender'])) ? zen_db_prepare_input($inArray['customers_gender']) : '';
  $customers_dob = (isset ($inArray['customers_dob'])) ? zen_db_prepare_input($inArray['customers_dob']) : '';
  $customers_authorization = (isset ($inArray['customers_authorization'])) ? (int)zen_db_prepare_input($inArray['customers_authorization']) : 0;

  if (CUSTOMERS_APPROVAL_AUTHORIZATION == 2 and $customers_authorization == 1) {
    $customers_authorization = 2;
    $messageStack->add_session(ERROR_CUSTOMER_APPROVAL_CORRECTION2, 'caution');
  }

  if (CUSTOMERS_APPROVAL_AUTHORIZATION == 1 and $customers_authorization == 2) {
    $customers_authorization = 1;
    $messageStack->add_session(ERROR_CUSTOMER_APPROVAL_CORRECTION1, 'caution');
  }

  $entry_street_address = (isset ($inArray['entry_street_address'])) ? zen_db_prepare_input($inArray['entry_street_address']) : '';
  $entry_suburb = (isset ($inArray['entry_suburb'])) ? zen_db_prepare_input($inArray['entry_suburb']) : '';
  $entry_postcode = (isset ($inArray['entry_postcode'])) ? zen_db_prepare_input($inArray['entry_postcode']) : '';
  $entry_city = (isset ($inArray['entry_city'])) ? zen_db_prepare_input($inArray['entry_city']) : '';
  $entry_country_id = (isset ($inArray['entry_country_id'])) ? (int)zen_db_prepare_input($inArray['entry_country_id']) : 0;

  $entry_company = (isset ($inArray['entry_company'])) ? zen_db_prepare_input($inArray['entry_company']) : '';
  $entry_state = (isset ($inArray['entry_state'])) ? zen_db_prepare_input($inArray['entry_state']) : '';
  $entry_zone_id = (isset($inArray['entry_zone_id'])) ? (int)zen_db_prepare_input($inArray['entry_zone_id']) : 0;

  if (ACCOUNT_GENDER == 'true') {
    if ($customers_gender != 'm' && $customers_gender != 'f') {
      $errors[] = ERROR_GENDER . "'$customers_gender'";  /*v2.0.3c*/
    }
  }
  
  if (strlen($customers_firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
    $errors[] = ERROR_FIRST_NAME . " ($customers_firstname)";  /*v2.0.3c*/
  }

  if (strlen($customers_lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
    $errors[] = ERROR_LAST_NAME . " ($customers_lastname)";  /*v2.0.3c*/
  }

  $dob_account = ACCOUNT_DOB;
  if (ACCOUNT_DOB != 'true') {
    $customers_dob = '0001-01-01 00:00:00';
  } else {
    $dobFormat = $theFormats[(int)(isset($_POST[$dateFormatName]) ? $_POST[$dateFormatName] : 0)];
  $dobError  = sprintf(ERROR_DOB_INVALID, $dobFormat);
    if (ENTRY_DOB_MIN_LENGTH > 0 && strlen($customers_dob) < ENTRY_DOB_MIN_LENGTH) {
      $errors[] = $dobError . " ($customers_dob)";  /*v2.0.3c*/
    } else {
      $month = (int)substr($customers_dob, strpos($dobFormat, 'MM'), 2);
      $day = (int)substr($customers_dob, strpos($dobFormat, 'DD'), 2);
      $year = (int)substr($customers_dob, strpos($dobFormat, 'YYYY'), 4);

      if (!checkdate($month, $day, $year)) {
        $errors[] = $dobError . " ($customers_dob)";  /*v2.0.3c*/
      } else {
        $inArray['customers_dob'] = date("Y-m-d H:i:s", mktime (0, 0, 0, $month, $day, $year));
      }
    }
  }

  if (strlen($customers_email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH) {
    $errors[] = ERROR_EMAIL_LENGTH . " ($customers_email_address)";  /*v2.0.3c*/
  
  } else if (!zen_validate_email($customers_email_address)) {
    $errors[] = ERROR_EMAIL_INVALID . " ($customers_email_address)";  /*v2.0.3c*/
  
  } else {
    $check_email_query = "SELECT count(*) as total
                            FROM " . TABLE_CUSTOMERS . "
                            WHERE customers_email_address = '" . zen_db_input($customers_email_address) . "'";
    $check_email = $db->Execute($check_email_query);

    if ($check_email->fields['total'] > 0) {
      $errors[] = sprintf (ERROR_EMAIL_ADDRESS_ERROR_EXISTS, $customers_email_address);
    }
  }

  if (ACCOUNT_COMPANY == 'true' && ENTRY_COMPANY_MIN_LENGTH > 0) {
    if (strlen($entry_company) < ENTRY_COMPANY_MIN_LENGTH) {
      $errors[] = sprintf(ERROR_COMPANY, ENTRY_COMPANY_MIN_LENGTH) . " ($entry_company)";  /*v2.0.3c*/
    }
  }
  
  if (strlen($entry_street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
    $errors[] = ERROR_STREET_ADDRESS . " ($entry_street_address)";  /*v2.0.3c*/
  }

  if (strlen($entry_city) < ENTRY_CITY_MIN_LENGTH) {
    $errors[] = ERROR_CITY . " ($entry_city)";  /*v2.0.3c*/
  }

  $error_country = false;
  if ($entry_country_id == '' || $entry_country_id == 0) {
    $error_country = true;
    $errors[] = ERROR_COUNTRY;
  }

  if (ACCOUNT_STATE == 'true' && !$error_country) {
    $check_value = $db->Execute("select count(*) as total
                                        from " . TABLE_ZONES . "
                                        where zone_country_id = '" . (int)$entry_country_id . "'");

    $entry_state_has_zones = ($check_value->fields['total'] > 0);

    if ($entry_state_has_zones == true) {
      $zone_query = $db->Execute("select zone_id
                                         from " . TABLE_ZONES . "
                                         where zone_country_id = '" . (int)$entry_country_id . "'
                                         and zone_id = '" . zen_db_input($entry_zone_id) . "'");

      if ($zone_query->RecordCount() < 1) {
        $errors[] = ERROR_SELECT_STATE . " ($entry_state)";  /*v2.0.3c*/
      }
    } else {
      if ($entry_state == false) {
        $errors[] = ERROR_STATE_REQUIRED . " ($entry_state)";  /*v2.0.3c*/
      }
    }
  }
 
  if (strlen($entry_postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
    $errors[] = ERROR_POSTCODE . " ($entry_postcode)";  /*v2.0.3c*/
  } elseif (!$error_country) {
    $errMsg = postcode_validate ($entry_postcode, $entry_country_id);
    if ($errMsg !== false) {
      $errors[] = $errMsg;
    }
  }
  
  //means that a telephone is not required but if it is given then it is subject to validation
  if (strlen($customers_telephone) < ENTRY_TELEPHONE_MIN_LENGTH) {
    $errors[] = ERROR_TELEPHONE . " ($customers_telephone)";  /*v2.0.3c*/
  } else {
    if ( ($errMsg = phone_validate($customers_telephone)) !== false) {
      $errors[] = $errMsg;
    }
  }
  
//-bof-a-v2.0.5
  if ($customers_group_pricing !== 0) {
    $cgp = $db->Execute('SELECT group_name FROM ' . TABLE_GROUP_PRICING . ' WHERE group_id=' . $customers_group_pricing);
    if ($cgp->EOF) {
      $errors[] = sprintf(ERROR_UNKNOWN_GROUP_PRICING, $customers_group_pricing);
    }
  }
//-eof-a-v2.0.5
  
  if (count($errors)) {
    $cInfo = new objectInfo($inArray);
    $processed = true;
  } else {
    //$errors = false;
  }
  
  $return = array('cInfo'=>$cInfo,'errors'=>$errors);
  return $return;
}

function get_countries_id($countries_iso_code_2) {
  global $db;
  
  $sql = "SELECT countries_id FROM " . TABLE_COUNTRIES . " 
          WHERE countries_iso_code_2 = '" . $countries_iso_code_2 . "'";
  
  $country_query = $db->Execute($sql);
  
  $countries_id = ($country_query->RecordCount() > 0) ? $country_query->fields['countries_id'] : false;
  
  return $countries_id;
  
}

function phone_validate($telephone) {
  // -----
  // Bypass the world phone prefix if it's the first character in the phone number.
  //
  $start_pos = 0;
  if (ENTRY_PHONE_NO_DELIM_WORLD !== false && strpos ($telephone, ENTRY_PHONE_NO_DELIM_WORLD) === 0) $start_pos = 1;
  
  // -----
  // Remove all the delimiter characters, the remaining telephone should contain only digits (0-9).
  //
  $telephone = str_replace (str_split (ENTRY_PHONE_NO_DELIMS), '', $telephone);

  for( $i = $start_pos, $errorMessage = false, $num_digits = 0, $telephone_len = strlen ($telephone); $i < $telephone_len && !$errorMessage; $i++ ) {
    if ($telephone[$i] < '0' || $telephone[$i] > '9') {
      $errorMessage = sprintf (ENTRY_PHONE_NO_CHAR_ERROR, $telephone[$i]);
    } else {
      $num_digits++;
    }
  }

  if( !$errorMessage ) {
    if( $num_digits < ENTRY_PHONE_NO_MIN_DIGITS ) {
      $errorMessage = ENTRY_PHONE_NO_MIN_ERROR;
    } else if( $num_digits > ENTRY_PHONE_NO_MAX_DIGITS ) {
      $errorMessage = ENTRY_PHONE_NO_MAX_ERROR;
    }
  }

  return $errorMessage;
  
}

/*----
** Validate a country-specific zip/postcode; the country code supplied is the country's numeric code.
** Returns false if no error, otherwise an error message string.
** If the postcode validates, the postcode might be updated with a re-formatted version of the value (e.g. uppercased).
*/
function postcode_validate(&$postcode, $country_id) {
  $formats = array ( 38 => '#(^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z] {0,1}\d[ABCEGHJ-NPRSTV-Z]\d$)#i',
                    222 => '#(((^[BEGLMNS][1-9]\d?)|(^W[2-9])|(^(A[BL]|B[ABDHLNRST]|C[ABFHMORTVW]|D[ADEGHLNTY]|E[HNX]|F[KY]|G[LUY]|H[ADGPRSUX]|I[GMPV]|JE|K[ATWY]|L[ADELNSU]|M[EKL]|N[EGNPRW]|O[LX]|P[AEHLOR]|R[GHM]|S[AEGKL-PRSTWY]|T[ADFNQRSW]|UB|W[ADFNRSV]|YO|ZE)\d\d?)|(^W1[A-HJKSTUW0-9])|(((^WC[1-2])|(^EC[1-4])|(^SW1))[ABEHMNPRVWXY]))(\s*)?([0-9][ABD-HJLNP-UW-Z]{2}))$|(^GIR\s?0AA$)#i',
                    223 => '#(^\d{5}$)|(^\d{5}-\d{4}$)#i');

  $errorMessage = false;

  if( array_key_exists( $country_id, $formats) ) {
    $temp = strtoupper( $postcode );
    if( preg_match( $formats[$country_id], $temp, $matches ) == 0 ) {
      $errorMessage = sprintf(ENTRY_POSTCODE_NOT_VALID, $postcode, zen_get_country_name($country_id));
    } else {
      $postcode = $temp;
    }
  }

  return $errorMessage;

}
function create_customer_drop_down() {
  global $db;
  $customers = array();
  $customers[] = array ( 'id' => '0', 'text' => TEXT_PLEASE_CHOOSE );
  
  $sql = "SELECT customers_id, customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . ";";
  $customersRecords = $db->Execute($sql);
  
  while (!$customersRecords->EOF) {
    $customers[] = array('id' => $customersRecords->fields['customers_id'], 'text' => $customersRecords->fields['customers_firstname'] . ' ' . $customersRecords->fields['customers_lastname'] . ' (' . $customersRecords->fields['customers_email_address'] . ')');
    $customersRecords->MoveNext();
  }
  
  return $customers;
}
?>