<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'include/Webservices/CustomerPortalWS.php';
include_once 'include/Webservices/getRecordImages.php';

function vtws_retrieve($id, $user) {
	global $log, $adb;
	if (empty($id)) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDID, 'Id specified is incorrect');
	}
	$id = vtws_getWSID($id);
	$webserviceObject = VtigerWebserviceObject::fromId($adb, $id);
	$handlerPath = $webserviceObject->getHandlerPath();
	$handlerClass = $webserviceObject->getHandlerClass();

	require_once $handlerPath;

	$handler = new $handlerClass($webserviceObject, $user, $adb, $log);
	$meta = $handler->getMeta();
	$entityName = $meta->getObjectEntityName($id);
	$types = vtws_listtypes(null, $user);
	if (!in_array($entityName, $types['types'])) {
		throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED, 'Permission to perform the operation is denied');
	}
	if ($meta->hasReadAccess()!==true) {
		throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED, 'Permission to write is denied');
	}

	if ($entityName !== $webserviceObject->getEntityName()) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDID, 'Id specified is incorrect');
	}

	if (!$meta->hasPermission(EntityMeta::$RETRIEVE, $id)) {
		throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED, 'Permission to read given object is denied');
	}

	$idComponents = vtws_getIdComponents($id);
	if (!$meta->exists($idComponents[1])) {
		throw new WebServiceException(WebServiceErrorCode::$RECORDNOTFOUND, 'Record you are trying to access is not found');
	}

	$entity = $handler->retrieve($id);
	// Dereference WSIDs
	$r = $meta->getReferenceFieldDetails();
	$listofrelfields = array();
	if (!empty($entity['assigned_user_id'])) {
		$r['assigned_user_id'] = array('Users');
		$listofrelfields[] = $entity['assigned_user_id'];
	}
	foreach ($r as $relfield => $mods) {
		if (!empty($entity[$relfield])) {
			$listofrelfields[] = $entity[$relfield];
		}
	}
	if (count($listofrelfields)>0) {
		if ($entityName=='Emails' && $entity['parent_id']!='') {
			unset($listofrelfields['parent_id'], $r['parent_id']);
		}
		$deref = unserialize(vtws_getReferenceValue(serialize($listofrelfields), $user));
		foreach ($r as $relfield => $mods) {
			if (!empty($entity[$relfield])) {
				$entity[$relfield.'ename'] = $deref[$entity[$relfield]];
			}
		}
		if ($entityName=='Emails' && $entity['parent_id']!='') {
			$entity['parent_idename'] = unserialize(vtws_getReferenceValue(serialize(array($entity['parent_id'])), $user));
		}
	}
	// Add attachment information
	$imgs = $meta->getImageFields();
	if (count($imgs)>0) {
		$imginfo = cbws_getrecordimageinfo($id, $user);
		if ($imginfo['results']>0) {
			foreach ($imgs as $img) {
				if (!empty($entity[$img]) && !empty($imginfo['images'][$img])) {
					$entity[$img.'imageinfo'] = $imginfo['images'][$img];
				}
			}
		}
	}
	//return product lines
	if ($entityName == 'Quotes' || $entityName == 'PurchaseOrder' || $entityName == 'SalesOrder' || $entityName == 'Invoice') {
		$pdowsid = vtws_getEntityId('Products').'x';
		$srvwsid = vtws_getEntityId('Services').'x';
		list($wsid, $recordid) = explode('x', $id);
		$result = $adb->pquery('select * from vtiger_inventoryproductrel where id=?', array($recordid));
		while ($row=$adb->getNextRow($result, false)) {
			if ($row['discount_amount'] == null && $row['discount_percent'] == null) {
				$discount = 0;
				$discount_type = 0;
			} else {
				$discount = 1;
			}
			if ($row['discount_amount'] == null) {
				$discount_amount = 0;
			} else {
				$discount_amount = $row['discount_amount'];
				$discount_type = 'amount';
			}
			if ($row['discount_percent'] == null) {
				$discount_percent = 0;
			} else {
				$discount_percent = $row['discount_percent'];
				$discount_type = 'percentage';
			}
			$ltype = getSalesEntityType($row['productid']);
			$onlyPrd = array(
				'productid'=>$row['productid'],
				'wsproductid' => ($ltype=='Products' ? $pdowsid : $srvwsid).$row['productid'],
				'linetype' => $ltype,
				'comment'=>$row['comment'],
				'qty'=>$row['quantity'],
				'listprice'=>$row['listprice'],
				'discount'=>$discount, // 0 no discount, 1 discount
				'discount_type'=>$discount_type, // amount/percentage
				'discount_percentage'=>$discount_percent,
				'discount_amount'=>$discount_amount,
			);
			$entity['pdoInformation'][] = $onlyPrd;
		}
	}
	VTWS_PreserveGlobal::flush();
	return $entity;
}

function vtws_retrieve_deleted($id, $user) {
	global $log,$adb;

	// First we look if it has been totally eliminated
	$parts = explode('x', $id);
	$result = $adb->pquery('SELECT count(*) as cnt FROM vtiger_crmentity WHERE crmid=?', array($parts[1]));
	if ($adb->query_result($result, 0, 'cnt') == 1) { // If not we can "almost" continue normally
		$webserviceObject = VtigerWebserviceObject::fromId($adb, $id);
		$handlerPath = $webserviceObject->getHandlerPath();
		$handlerClass = $webserviceObject->getHandlerClass();
		require_once $handlerPath;
		$handler = new $handlerClass($webserviceObject, $user, $adb, $log);
		$meta = $handler->getMeta();
		$meta->getObjectEntityNameDeleted($id);
		$entity = $handler->retrieve($id, true);
		VTWS_PreserveGlobal::flush();
	} else { // if it has been eliminated we have to mock up object and return with nothing
		// here we should return a mock object with empty values.
		$entity = null; // I am being lazy
	}
	return $entity;
}
?>
