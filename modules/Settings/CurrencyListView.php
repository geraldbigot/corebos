<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
require_once 'Smarty_setup.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/UserInfoUtil.php';
global $mod_strings, $adb, $theme, $app_strings, $default_charset;
$theme_path='themes/'.$theme.'/';
$image_path=$theme_path.'images/';
$smarty=new vtigerCRM_Smarty;
$result = $adb->pquery('select * from vtiger_currency_info where deleted=0', array());
$temprow = $adb->fetch_array($result);
$cnt=1;
$currency = array();
do {
	$currency_element = array();
	$currency_element['name'] = $temprow['currency_name'];
	$currency_element['code'] = $temprow['currency_code'];
	$currency_element['symbol'] = $temprow['currency_symbol'];
	$currency_element['position'] = $temprow['currency_position'];
	$currency_element['crate'] = $temprow['conversion_rate'];
	$currency_element['status'] = $temprow['currency_status'];
	if ($temprow["defaultid"] != '-11') {
		$currency_element['name']  = '<a href=index.php?module=Settings&action=CurrencyEditView&record='.$temprow['id'].'&detailview=detail_view>';
		$currency_element['name'] .= getTranslatedCurrencyString($temprow['currency_name']).'</a>';
		$currency_element['tool']  = '<a href=index.php?module=Settings&action=CurrencyEditView&record='.$temprow['id'].'><img src="';
		$currency_element['tool'] .= vtiger_imageurl('editfield.gif', $theme) .'" border="0" alt="'.$app_strings['LBL_EDIT_BUTTON_LABEL'].'" title="';
		$currency_element['tool'] .= $app_strings['LBL_EDIT_BUTTON_LABEL'].'"/></a>&nbsp;|&nbsp;<img style="cursor:pointer;" onClick="fnvshobj(this,\'currencydiv\');';
		$currency_element['tool'] .= 'deleteCurrency(\''.$temprow['id'].'\');" src="'. vtiger_imageurl('delete.gif', $theme).'" border="0" alt="';
		$currency_element['tool'] .= $app_strings['LBL_DELETE_BUTTON_LABEL'].'" title="'.$app_strings['LBL_DELETE_BUTTON_LABEL'].'"/>';
	} else {
		$currency_element['tool']= '';
	}
	$currency[] = $currency_element;
	$cnt++;
} while ($temprow = $adb->fetch_array($result));
$smarty->assign('THEME', $theme);
$smarty->assign('IMAGE_PATH', $image_path);
$smarty->assign('MOD', $mod_strings);
$smarty->assign('MODULE', $currentModule);
$smarty->assign('SINGLE_MOD', getTranslatedString('SINGLE_'.$currentModule));
$smarty->assign('CURRENCY_LIST', $currency);
$smarty->assign('CRON_TASK', Vtiger_Cron::getInstance('UpdateExchangeRate'));
if (!empty($_REQUEST['ajax'])) {
	$smarty->display('CurrencyListViewEntries.tpl');
} else {
	$smarty->display('CurrencyListView.tpl');
}
?>
