<?php
/**
 * @copyright Copyright 2003-2019 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 *
 * add_customers module modified by Garden 2012-07-20
 * www.inzencart.cz Czech forum for ZenCart
 * Modified for Zen Cart 1.5.0, v1.5.1, lat9 2013-05-16
 * Modified for Zen Cart 1.5.5, lat9 2015-12-24
 * Modified for Zen Cart 1.5.6 German / PHP 7.3, webchills 2019-06-26
 */
require('includes/application_top.php');

require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

$action = (isset($_POST['action'])) ? $_POST['action'] : false;
$error = false;
$processed = false;
$cInfo = array();
$theFormats = array('YYYY/MM/DD', 'MM/DD/YYYY', 'YYYY-MM-DD', 'MM-DD-YYYY', 'YYYY/DD/MM', 'DD/MM/YYYY', 'YYYY-DD-MM', 'DD-MM-YYYY');

require_once('add_customers_backend.php');

// ---- Determine what to do next ... single add vs. country change vs. multiple/file add
if (zen_not_null($action)) {
  switch ($action) {
    case 'add_single':
      $array = validate_customer($_POST, 'date_format_s');
      $errors = $array['errors'];
      $cInfo = $array['cInfo'];

      if (count($errors) < 1) {
        $customerName = insert_customer($_POST);
        $feedback = sprintf( MESSAGE_CUSTOMER_OK, $customerName);
      }
      break;
    
  case 'add_multiple':
    $array = check_file_upload();
    $errors = $array['errors'];
    $feedback = $array['feedback'];
    break;
    
  case 'resend_email':
    if (!isset($_POST['resend_id'])) {
    $errors[] = ERROR_NO_CUSTOMER_SELECTED;
  
    } else {    
      $sql = "SELECT customers_gender, customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = '" . (int)$_POST['resend_id'] . "'";
      $custInfo = $db->Execute($sql);
      if ($custInfo->RecordCount() == 0) {
        $errors[] = ERROR_NO_CUSTOMER_SELECTED;
        
      } else {
        $thePassword = false;
        if (isset($_POST['reset_pw']) && $_POST['reset_pw'] == 1) {
//-bof-20151224-lat9-Use updated random number generator, if available.
          $thePassword = (function_exists ('zen_create_PADSS_password')) ? zen_create_PADSS_password ((ENTRY_PASSWORD_MIN_LENGTH > 0) ? ENTRY_PASSWORD_MIN_LENGTH : 5) : zen_create_random_value (ENTRY_PASSWORD_MIN_LENGTH);
//-eof-20151224-lat9
          $sql = "UPDATE " . TABLE_CUSTOMERS . "
                  SET customers_password = '" . zen_encrypt_password($thePassword) . "'
                  where customers_id = '" . (int)$_POST['resend_id'] . "'";
          $db->Execute($sql);
        }
        sendWelcomeEmail ($custInfo->fields['customers_gender'], $custInfo->fields['customers_firstname'], $custInfo->fields['customers_lastname'], $custInfo->fields['customers_email_address'], $thePassword);
      
        $feedback[] = CUSTOMER_EMAIL_RESENT;
      }
    }
    break;
    
  default:
    break;
  }
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
  <head>
    <meta charset="<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/stylesheet_acfa.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <script src="includes/menu.js"></script>
    <script src="includes/general.js"></script>
<script type="text/javascript">

function init()
{
  cssjsmenu(\'navbar\');
  if (document.getElementById)
  {
    var kill = document.getElementById(\'hoverJS\');
    kill.disabled = true;
  }
}

</script>

</head>

<body onLoad="init()">
<?php
require(DIR_WS_INCLUDES . 'header.php');

$infoDivContents = '';
if ($errors && count($errors) > 0) {
  $infoDivContents = '<div class="errorDiv"><p class="errorBold">' . ERROR_CUSTOMER_ERROR_1 . (($action == 'insert_multiple' && zen_not_null($_FILES['bulk_upload']['name'])) ? (' (' . $_FILES['bulk_upload']['name'] . ')') : '') . ':' . '</p><ul>';

  foreach ($errors as $line_no=>$error) {
    if (is_array($error)) {
      $infoDivContents .= '<div class="error">' . sprintf(ERROR_ON_LINE, ($line_no+1)) . '</div><ul>';

      foreach ($error as $err) {
        $infoDivContents .= '<li class="error">' . $err. '</li>';
      }
      $infoDivContents .= '</ul>';
    } else {
      $infoDivContents .= '<li class="error">' . $error . '</li>';
    }
  }
  
  $infoDivContents .= '</ul></div>';
}

if ((isset ($feedback) && is_array($feedback) && count($feedback) > 0)) {
  $infoDivContents .= '<div class="okDiv"><p class="okBold">' . ((count($errors) && $_POST['insert_mode'] == 'file') ? MESSAGE_LINES_OK_NOT_INSERTED : MESSAGE_CUSTOMERS_OK) . '</p><ul>';
 
  foreach ($feedback as $line_no=>$feedback_msg) {
    $infoDivContents .= '<li class="ok">' . (($feedback_msg != '' && !is_array($feedback_msg)) ? $feedback_msg : sprintf(LINE_MSG, $line_no+1, $feedback_msg['customers_firstname'], $feedback_msg['customers_lastname'])) . '</li>';
  }
  
  $infoDivContents .= '</ul></div>';
}

$insert_mode = (isset($_POST['insert_mode'])) ? $_POST['insert_mode'] : 'file';
$newsletter_array = array( array('id' => '1', 'text' => ENTRY_NEWSLETTER_YES), array('id' => '0', 'text' => ENTRY_NEWSLETTER_NO) );

if (isset($_POST['date_format_m'])) {
  $selectedDateFormat_m = (int)$_POST['date_format_m'];
} else {
  $currentDateFormat_m = str_replace('%m', 'MM', str_replace('%d', 'DD', str_replace('%Y', 'YYYY', DATE_FORMAT_SHORT)));
  $selectedDateFormat_m = array_search($currentDateFormat_m, $theFormats);
}
if (isset($_POST['date_format_s'])) {
  $selectedDateFormat_s = (int)$_POST['date_format_s'];
} else {
  $currentDateFormat_s = str_replace('%m', 'MM', str_replace('%d', 'DD', str_replace('%Y', 'YYYY', DATE_FORMAT_SHORT)));
  $selectedDateFormat_s = array_search($currentDateFormat_s, $theFormats);
}

for ($i=0, $n=sizeof($theFormats), $dateFormats=array(); $i<$n; $i++) {
  $dateFormats[$i]['id'] = $i;
  $dateFormats[$i]['text'] = $theFormats[$i];
}

$resendID = (isset($_POST['resend_id'])) ? $_POST['resend_id'] : 0;
?>
  <h1><?php echo HEADING_TITLE; ?></h1>
  <table border="0" width="100%" cellspacing="2" cellpadding="2">
    <tr>
      <td width="100%" valign="top">
        <table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td id="multiple"><table border="0" cellspacing="2" cellpadding="2">
              <tr>
                <td id="csv_in">
                  <div class="headingLabel"><?php echo CUSTOMERS_BULK_UPLOAD; ?></div>
        <?php if ($action == 'add_multiple') echo $infoDivContents; ?>
              <?php echo zen_draw_form('customers', FILENAME_ADD_CUSTOMERS, '', 'post', 'enctype="multipart/form-data"') . zen_hide_session_id(). zen_draw_hidden_field( 'action', 'add_multiple'); ?>
                    <div class="formArea"><table border="0" cellspacing="2" cellpadding="2">
                      <tr>
                        <td><div class="back mainLabel"><?php echo CUSTOMERS_FILE_IMPORT; ?></div><input type="file" name="bulk_upload" /></td>
                      </tr>
                      <tr>
                        <td>
                          <div class="back mainLabel"><?php echo CUSTOMERS_INSERT_MODE; ?></div>
                          <div class="back">
                            <input type="radio" name="insert_mode" value="part" <?php echo ($insert_mode == 'part') ? 'checked="checked"' : ''; ?>/><?php echo CUSTOMERS_INSERT_MODE_VALID; ?><br />
                            <input type="radio" name="insert_mode" value="file" <?php echo ($insert_mode == 'file') ? 'checked="checked"' : ''; ?>/><?php echo CUSTOMERS_INSERT_MODE_FILE; ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                      <td><div class="back mainLabel"><?php echo DATE_FORMAT_CHOOSE_MULTI; ?></div><?php echo zen_draw_pull_down_menu('date_format_m', $dateFormats, $selectedDateFormat_m); ?></td>
                      </tr>
                      <tr>
                        <td>
                          <div style="width:350px; text-align: right;">
                           <input type="submit" name="add_customers_in_bulk" value="Upload" />
                          </div>
                        </td>
                      </tr>
                    </table></div>
                  </form>
                </td>
              </tr>
        
              <tr>
                <td>
                  <div class="headingLabel"><?php echo RESEND_WELCOME_EMAIL; ?></div>
        <?php if ($action == 'resend_email') echo $infoDivContents; ?>
              <?php echo zen_draw_form('resend', FILENAME_ADD_CUSTOMERS, '', 'post', 'enctype="multipart/form-data"') . zen_hide_session_id() . zen_draw_hidden_field ('action', 'resend_email'); ?>
                    <div class="formArea"><table border="0" cellspacing="2" cellpadding="2">
                      <tr>
                        <td><div class="back mainLabel"><?php echo TEXT_CHOOSE_CUSTOMER; ?></div><div class="main"><?php echo zen_draw_pull_down_menu('resend_id', create_customer_drop_down(), $resendID); ?></div></td>
                      </tr>
                      <tr class="clearBoth">
                        <td><div class="back mainLabel"><?php echo TEXT_RESET_PASSWORD; ?></div><div class="main"><input type="checkbox" id="reset_pw" value="1" name="reset_pw" <?php echo (isset($_POST['reset_pw'])) ? 'checked="checked"' : ''; ?>/></div></td>
                      </tr>
                      <tr>
                        <td>
                          <div style="width:350px; text-align: right;">
                            <input type="submit" name="resend" value="<?php echo BUTTON_RESEND; ?>" />
                          </div>
                        </td>
                      </tr>
                    </table></div>
                  </form>
                </td>
              </tr>
            </table></td>
      
            <td id="single"><div class="headingLabel"><?php echo CUSTOMERS_SINGLE_ENTRY; ?></div><?php if ($action == 'add_single') echo $infoDivContents; ?><?php echo zen_draw_form('customers_1', FILENAME_ADD_CUSTOMERS, '', 'post') . zen_hide_session_id() . zen_draw_hidden_field( 'action', 'add_single'); ?><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td class="formAreaTitle"><?php echo CATEGORY_PERSONAL; ?></td>
              </tr>
              <tr>
                <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
<?php
  $customers_authorization_array = array( array('id' => '0', 'text' => CUSTOMERS_AUTHORIZATION_0),
                                          array('id' => '1', 'text' => CUSTOMERS_AUTHORIZATION_1),
                                          array('id' => '2', 'text' => CUSTOMERS_AUTHORIZATION_2),
                                          array('id' => '3', 'text' => CUSTOMERS_AUTHORIZATION_3)
                                        );
?>
                  <tr>
                    <td class="main mainLabel"><?php echo CUSTOMERS_AUTHORIZATION; ?></td>
                    <td class="main"><?php echo zen_draw_pull_down_menu('customers_authorization', $customers_authorization_array, $cInfo->customers_authorization); ?></td>
                  </tr>        
<?php
  if (ACCOUNT_GENDER == 'true') {
?>
                  <tr>
                    <td class="main mainLabel" style="vertical-align:top;"><?php echo ENTRY_GENDER; ?></td>
                    <td class="main">
                      <input type="radio" name="customers_gender" value="m" <?php echo ($cInfo->customers_gender == 'm') ? 'checked' : ''; ?> /><?php echo MALE; ?>
                      <span class="spacer"></span>
                      <input type="radio" name="customers_gender" value="f" <?php echo ($cInfo->customers_gender == 'f') ? 'checked' : ''; ?> /><?php echo FEMALE; ?>
                    </td>
                  </tr>
<?php
  }
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_FIRST_NAME; ?></td>
                    <td class="main"><input size="30" name="customers_firstname" value="<?php echo $cInfo->customers_firstname; ?>" /></td>
                  </tr>

                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_LAST_NAME; ?></td>
                    <td class="main"><input size="30" name="customers_lastname" value="<?php echo $cInfo->customers_lastname; ?>" /></td>
                  </tr>
<?php
  if (ACCOUNT_DOB == 'true') {
?>
                  <tr>
                    <td class="back mainLabel"><?php echo DATE_FORMAT_CHOOSE_SINGLE; ?></td>
                    <td class="main"><?php echo zen_draw_pull_down_menu('date_format_s', $dateFormats, $selectedDateFormat_s); ?></td>
                  </tr>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_DATE_OF_BIRTH; ?></td>
                    <td class="main"><input size="30" name="customers_dob" value="<?php echo $cInfo->customers_dob; ?>" /></td>
                  </tr>
<?php
  }
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_EMAIL_ADDRESS; ?></td>
                    <td class="main"><input size="30" name="customers_email_address" value="<?php echo $cInfo->customers_email_address; ?>" /></td>
                  </tr> 

                </table></td>
              </tr>
<?php
  if (ACCOUNT_COMPANY == 'true') {
?>
              <tr><td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td></tr>
              <tr><td class="formAreaTitle"><?php echo CATEGORY_COMPANY; ?></td></tr>
              <tr>
                <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_COMPANY; ?></td>
                    <td class="main"><input size="30" name="entry_company" value="<?php echo $cInfo->entry_company; ?>" /></td>
                  </tr>
                </table></td>
              </tr>
<?php
  }
?>
              <tr><td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td></tr>
              <tr><td class="formAreaTitle"><?php echo CATEGORY_ADDRESS; ?></td></tr>
              <tr>
                <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_STREET_ADDRESS; ?></td>
                    <td class="main"><input size="30" name="entry_street_address" value="<?php echo $cInfo->entry_street_address; ?>" /></td>
                  </tr>           
<?php
  if (ACCOUNT_SUBURB == 'true') {
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_SUBURB; ?></td>
                    <td class="main"><input size="30" name="entry_suburb" value="<?php echo $cInfo->entry_suburb; ?>" /></td>
                  </tr>
<?php        
  }
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_CITY; ?></td>
                    <td class="main"><input size="30" name="entry_city" value="<?php echo $cInfo->entry_city; ?>" /></td>
                  </tr>                                   

                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_POST_CODE; ?></td>
                    <td class="main"><input size="30" name="entry_postcode" value="<?php echo $cInfo->entry_postcode; ?>" /></td>
                  </tr>
          
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_COUNTRY; ?></td>
                    <td class="main"><?php echo zen_draw_pull_down_menu('entry_country_id', zen_get_countries(), (zen_not_null($cInfo->entry_country_id) ? $cInfo->entry_country_id : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY), 'onchange="this.form.submit();"'); ?></td>
                  </tr>
<?php
  if (ACCOUNT_STATE == 'true') {
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_STATE; ?></td>
                    <td class="main">
<?php
    $theZones = zen_prepare_country_zones_pull_down ((zen_not_null($cInfo->entry_country_id) ? $cInfo->entry_country_id : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY));
  if (count($theZones) > 1) {
    echo zen_draw_pull_down_menu('entry_zone_id', $theZones, $cInfo->entry_zone_id);
    
  } else {
      $entry_state = zen_get_zone_name($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state);
      echo zen_draw_input_field('entry_state', zen_get_zone_name($cInfo->entry_country_id, $cInfo->entry_zone_id, $cInfo->entry_state));
  }
  }
?>
                    </td>
                  </tr>
                </table></td>
              </tr>
      
              <tr><td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td></tr>
              <tr><td class="formAreaTitle"><?php echo CATEGORY_CONTACT; ?></td></tr>
              <tr>
                <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_TELEPHONE_NUMBER; ?></td>
                    <td class="main"><input size="30" name="customers_telephone" value="<?php echo $cInfo->customers_telephone; ?>" /></td>
                  </tr>
<?php
  if (ACCOUNT_FAX_NUMBER == 'true') {
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_FAX_NUMBER; ?></td>
                    <td class="main"><input size="30" name="customers_fax" value="<?php echo $cInfo->customers_fax; ?>" /></td>
                  </tr>
<?php
  }
?>
                </table></td>
              </tr>
        
              <tr><td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td></tr>
              <tr><td class="formAreaTitle"><?php echo CATEGORY_OPTIONS; ?></td></tr>
              <tr>
                <td class="formArea"><table border="0" cellspacing="2" cellpadding="2">
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_EMAIL_PREFERENCE; ?></td>
                    <td class="main">
<?php
  $email_pref_text = ( (empty($cInfo) && ACCOUNT_EMAIL_PREFERENCE != '1') || $cInfo->customers_email_format == 'TEXT' ) ? true : false;
  $email_pref_html = !$email_pref_text;
  echo zen_draw_radio_field('customers_email_format', 'HTML', $email_pref_html) . '&nbsp;' . ENTRY_EMAIL_HTML_DISPLAY . '&nbsp;&nbsp;&nbsp;' . zen_draw_radio_field('customers_email_format', 'TEXT', $email_pref_text) . '&nbsp;' . ENTRY_EMAIL_TEXT_DISPLAY ;
?>
                    </td>
                  </tr>
          
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_NEWSLETTER; ?></td>
<?php
//-bof-v2.0.4-a
$newsletter =  ( (empty($cInfo) && ACCOUNT_NEWSLETTER_STATUS == '2') || (isset($cInfo) && $cInfo->customers_newsletter == '1') ) ? '1' : '0';
//-eof-v2.0.4-a
?>
                    <td class="main"><?php echo zen_draw_pull_down_menu('customers_newsletter', $newsletter_array, /*v2.0.4c*/ $newsletter); ?></td>
                  </tr>
          
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_PRICING_GROUP; ?></td>
                    <td class="main">
<?php
  $group_array_query = $db->execute("select group_id, group_name, group_percentage from " . TABLE_GROUP_PRICING);
  $group_array[] = array('id'=>0, 'text'=>TEXT_NONE);

  while (!$group_array_query->EOF) {
    $group_array[] = array('id'=>$group_array_query->fields['group_id'], 'text'=>$group_array_query->fields['group_name'].'&nbsp;'.$group_array_query->fields['group_percentage'].'%');
    $group_array_query->MoveNext();
  }

  echo zen_draw_pull_down_menu('customers_group_pricing', $group_array, $cInfo->customers_group_pricing);
?>
                    </td>
                  </tr>
<?php
  if (CUSTOMERS_REFERRAL_STATUS == 2) {
?>
                  <tr>
                    <td class="main mainLabel"><?php echo CUSTOMERS_REFERRAL; ?></td>
                    <td class="main"><?php echo zen_draw_input_field('customers_referral', $cInfo->customers_referral, zen_set_field_length(TABLE_CUSTOMERS, 'customers_referral', 15)); ?></td>
                  </tr>
<?php
  }
?>
                  <tr>
                    <td class="main mainLabel"><?php echo ENTRY_EMAIL; ?></td>
                    <td class="main"><input type="checkbox" id="send_welcome" value="1" name="send_welcome" <?php echo (isset($_POST['send_welcome'])) ? 'checked="checked"' : ''; ?>/></td>
                  </tr>
                </table></td>
              </tr>
        
              <tr><td><?php echo zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td></tr>
              <tr>
                <td align="right" class="main"><?php echo zen_image_submit('button_insert.gif', IMAGE_UPDATE, 'name="insert"'); ?><a href="<?php echo zen_href_link(FILENAME_CUSTOMERS, zen_get_all_get_params(array('action')), 'NONSSL'); ?>"><?php echo zen_image_button('button_cancel.gif', IMAGE_CANCEL); ?></a></td>
              </tr>
            </table></form></td></tr>

        </table>
      </td>
    </tr>
  </table>
<?php
require(DIR_WS_INCLUDES . 'footer.php');
?>
<br />
</body>
</html>
<?php
require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
