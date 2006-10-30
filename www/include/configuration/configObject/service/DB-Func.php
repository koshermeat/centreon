<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
Developped by : Julien Mathis - Romain Le Merlus

Adapted to Pear library by Merethis company, under direction of Cedrick Facon, Romain Le Merlus, Julien Mathis

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/
	if (!isset ($oreon))
		exit ();

	function testServiceTemplateExistence ($name = NULL)	{
		global $pearDB;
		global $form;
		$id = NULL;
		if (isset($form))
			$id = $form->getSubmitValue('service_id');
		$res =& $pearDB->query("SELECT service_description, service_id FROM service WHERE service_register = '0' AND service_description = '".htmlentities($name, ENT_QUOTES)."'");
		if (PEAR::isError($res)) {
			print "Mysql Error : ".$res->getMessage();
		}
		$service =& $res->fetchRow();
		#Modif case
		if ($res->numRows() >= 1 && $service["service_id"] == $id)
			return true;
		#Duplicate entry
		else if ($res->numRows() >= 1 && $service["service_id"] != $id)
			return false;
		else
			return true;
	}
			
	function testServiceExistence ($name = NULL, $hPars = array(), $hgPars = array())	{

		echo "<br>-";

	print_r($hPars);
		echo "-<br>";

		global $pearDB;
		global $form;
		$id = NULL;
		if (isset($form) && !count($hPars) && !count($hgPars))	{
			$arr = $form->getSubmitValues();
			if (isset($arr["service_id"]))
				$id = $arr["service_id"];
			if (isset($arr["service_hPars"]))
				$hPars = $arr["service_hPars"];
			else
				$hPars = array();
			if (isset($arr["service_hgPars"]))
				$hgPars = $arr["service_hgPars"];
			else
				$hgPars = array();
		}
		foreach ($hPars as $host)	{
			echo $host . "<br>";
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
			$res =& $pearDB->query("SELECT service_id FROM service, host_service_relation hsr WHERE hsr.host_host_id = '".$host."' AND hsr.service_service_id = service_id AND service.service_description = '".htmlentities($name, ENT_QUOTES)."'");
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
			$service =& $res->fetchRow();
			#Duplicate entry
			if ($res->numRows() >= 1 && $service["service_id"] != $id)
				return false;
			$res->free();
		}
		foreach ($hgPars as $hostgroup)	{
			$res =& $pearDB->query("SELECT service_id FROM service, host_service_relation hsr WHERE hsr.hostgroup_hg_id = '".$hostgroup."' AND hsr.service_service_id = service_id AND service.service_description = '".htmlentities($name, ENT_QUOTES)."'");
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
			$service =& $res->fetchRow();
			#Duplicate entry
			if ($res->numRows() >= 1 && $service["service_id"] != $id)
				return false;
			$res->free();
		}			
		return true;
	}
	
	function enableServiceInDB ($service_id = null)	{
		if (!$service_id) return;
		global $pearDB;
		$pearDB->query("UPDATE service SET service_activate = '1' WHERE service_id = '".$service_id."'");
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
	}
	
	function disableServiceInDB ($service_id = null)	{
		if (!$service_id) return;
		global $pearDB;
		$pearDB->query("UPDATE service SET service_activate = '0' WHERE service_id = '".$service_id."'");
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
	}
	
	function deleteServiceInDB ($services = array())	{
		global $pearDB;
		global $oreon;
		foreach($services as $key=>$value)	{
			$res =& $pearDB->query("SELECT service_id FROM service WHERE service_template_model_stm_id = '".$key."'");
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
			while ($res->fetchInto($row))
			{
				$pearDB->query("UPDATE service SET service_template_model_stm_id = NULL WHERE service_id = '".$row["service_id"]."'");
				if (PEAR::isError($pearDB)) {
					print "Mysql Error : ".$pearDB->getMessage();
				}
			}
			$pearDB->query("DELETE FROM service WHERE service_id = '".$key."'");
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
			$files = glob($oreon->optGen["oreon_rrdbase_path"]."*_".$key.".rrd");
			foreach ($files as $filename)
				unlink ($filename);
		}
	}
	
	function multipleServiceInDB ($services = array(), $nbrDup = array(), $host = NULL)	{
		# Foreach Service
		foreach($services as $key=>$value)	{
			global $pearDB;
			# Get all information about it
			$res =& $pearDB->query("SELECT * FROM service WHERE service_id = '".$key."' LIMIT 1");
			if (PEAR::isError($res))
				print "Mysql Error : ".$res->getMessage();
			$row = $res->fetchRow();
			print_r($row);
			
			$row["service_id"] = '';
			# Loop on the number of Service we want to duplicate
			for ($i = 1; $i <= $nbrDup[$key]; $i++)	{
				$val = null;
				# Create a sentence which contains all the value
				foreach ($row as $key2=>$value2)	{
					$key2 == "service_description" ? ($service_description = $value2 = $value2."_".$i) : $service_description = null;
					$val ? $val .= ($value2!=NULL?(", '".$value2."'"):", NULL") : $val .= ($value2!=NULL?("'".$value2."'"):"NULL");
				}

				$h = array('0' => $key);

				if (($row["service_register"] && testServiceExistence($service_description, $h)) || (!$row["service_register"] && testServiceTemplateExistence($service_description)))	{
					$val ? $rq = "INSERT INTO service VALUES (".$val.")" : $rq = null;
					$res2 =& $pearDB->query($rq);
					if (PEAR::isError($res2))
						print "Mysql Error : ".$res2->getMessage();
					$res =& $pearDB->query("SELECT MAX(service_id) FROM service");
					if (PEAR::isError($res))
						print "Mysql Error : ".$pearDB->getMessage();
					$maxId =& $res->fetchRow();
					if (isset($maxId["MAX(service_id)"]))	{
						# Host duplication case -> Duplicate the Service for the Host we create
						if ($host)
							$pearDB->query("INSERT INTO host_service_relation VALUES ('', NULL, '".$host."', NULL, '".$maxId["MAX(service_id)"]."')");
						else	{
						# Service duplication case -> Duplicate the Service for each relation the base Service have
							$res =& $pearDB->query("SELECT DISTINCT host_host_id, hostgroup_hg_id FROM host_service_relation WHERE service_service_id = '".$key."'");
							if (PEAR::isError($res))
								print "Mysql Error : ".$res->getMessage();
							while($res->fetchInto($service))	{
								if ($service["host_host_id"])				
									$res1 =& $pearDB->query("INSERT INTO host_service_relation VALUES ('', NULL, '".$service["host_host_id"]."', NULL, '".$maxId["MAX(service_id)"]."')");
								else if ($service["hostgroup_hg_id"])	
									$res1 =& $pearDB->query("INSERT INTO host_service_relation VALUES ('', '".$service["hostgroup_hg_id"]."', NULL, NULL, '".$maxId["MAX(service_id)"]."')");
								if (PEAR::isError($res1))
									print "Mysql Error : ".$res1->getMessage();
							}
						}
						$res =& $pearDB->query("SELECT DISTINCT contactgroup_cg_id FROM contactgroup_service_relation WHERE service_service_id = '".$key."'");
						if (PEAR::isError($res))
							print "Mysql Error : ".$res->getMessage();
						while($res->fetchInto($Cg)){
							$res1 =& $pearDB->query("INSERT INTO contactgroup_service_relation VALUES ('', '".$Cg["contactgroup_cg_id"]."', '".$maxId["MAX(service_id)"]."')");
							if (PEAR::isError($res1))
								print "Mysql Error : ".$res1->getMessage();
						}
						$res =& $pearDB->query("SELECT DISTINCT servicegroup_sg_id FROM servicegroup_relation WHERE service_service_id = '".$key."'");
						if (PEAR::isError($res))
							print "Mysql Error : ".$res->getMessage();
						while($res->fetchInto($Sg))
						{
							$pearDB->query("INSERT INTO servicegroup_relation VALUES ('', '".$maxId["MAX(service_id)"]."', '".$Sg["servicegroup_sg_id"]."')");
							if (PEAR::isError($pearDB)) {
								print "Mysql Error : ".$pearDB->getMessage();
							}
						}
						$res =& $pearDB->query("SELECT * FROM extended_service_information WHERE service_service_id = '".$key."'");
						if (PEAR::isError($pearDB)) {
							print "Mysql Error : ".$pearDB->getMessage();
						}
						while($res->fetchInto($esi))	{
							$val = null;
							$esi["service_service_id"] = $maxId["MAX(service_id)"];
							$esi["esi_id"] = NULL;
							foreach ($esi as $key2=>$value2)
								$val ? $val .= ($value2!=NULL?(", '".$value2."'"):", NULL") : $val .= ($value2!=NULL?("'".$value2."'"):"NULL");
							$val ? $rq = "INSERT INTO extended_service_information VALUES (".$val.")" : $rq = null;
							$pearDB->query($rq);
							if (PEAR::isError($pearDB)) {
								print "Mysql Error : ".$pearDB->getMessage();
							}
						}
				}
				}
			}
		}
	}
	
	function updateServiceInDB ($service_id = NULL)	{
		if (!$service_id) return;
		updateService($service_id);
		updateServiceContactGroup($service_id);
		updateServiceHost($service_id);
		updateServiceServiceGroup($service_id);
		updateServiceExtInfos($service_id);
		updateServiceTrap($service_id);
	}	
	
	function insertServiceInDB ($ret = array())	{
		$service_id = insertService($ret);
		updateServiceContactGroup($service_id, $ret);
		updateServiceHost($service_id, $ret);
		updateServiceServiceGroup($service_id, $ret);
		insertServiceExtInfos($service_id, $ret);
		updateServiceTrap($service_id, $ret);
		return ($service_id);
	}
	
	function insertService($ret = array())	{
		global $form;
		global $pearDB;
		if (!count($ret))
			$ret = $form->getSubmitValues();
		if (isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != NULL)		{
			$ret["command_command_id_arg"] = str_replace("\n", "#BR#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace("\t", "#T#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace("\r", "#R#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace('/', "#S#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace('\\', "#BS#", $ret["command_command_id_arg"]);
		}
		if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != NULL)		{
			$ret["command_command_id_arg2"] = str_replace("\n", "#BR#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace("\t", "#T#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace("\r", "#R#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace('/', "#S#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace('\\', "#BS#", $ret["command_command_id_arg2"]);
		}
		$rq = "INSERT INTO service " .
				"(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, timeperiod_tp_id2, purge_policy_id, " .
				"service_description, service_is_volatile, service_max_check_attempts, service_normal_check_interval, service_retry_check_interval, service_active_checks_enabled, " .
				"service_passive_checks_enabled, service_parallelize_check, service_obsess_over_service, service_check_freshness, service_freshness_threshold, " .
				"service_event_handler_enabled, service_low_flap_threshold, service_high_flap_threshold, service_flap_detection_enabled, " .
				"service_process_perf_data, service_retain_status_information, service_retain_nonstatus_information, service_notification_interval, " .
				"service_notification_options, service_notifications_enabled, service_stalking_options, service_comment, command_command_id_arg, command_command_id_arg2, service_register, service_activate) " .
				"VALUES ( ";
				isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != NULL ? $rq .= "'".$ret["service_template_model_stm_id"]."', ": $rq .= "NULL, ";
				isset($ret["command_command_id"]) && $ret["command_command_id"] != NULL ? $rq .= "'".$ret["command_command_id"]."', ": $rq .= "NULL, ";
				isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id"]."', ": $rq .= "NULL, ";
				isset($ret["command_command_id2"]) && $ret["command_command_id2"] != NULL ? $rq .= "'".$ret["command_command_id2"]."', ": $rq .= "NULL, ";
				isset($ret["timeperiod_tp_id2"]) && $ret["timeperiod_tp_id2"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id2"]."', ": $rq .= "NULL, ";
				isset($ret["purge_policy_id"]) && $ret["purge_policy_id"] != NULL ? $rq .= "'".$ret["purge_policy_id"]."', ": $rq .= "NULL, ";
				isset($ret["service_description"]) && $ret["service_description"] != NULL ? $rq .= "'".htmlentities($ret["service_description"], ENT_QUOTES)."', ": $rq .= "NULL, ";
				isset($ret["service_is_volatile"]) && $ret["service_is_volatile"]["service_is_volatile"] != 2 ? $rq .= "'".$ret["service_is_volatile"]["service_is_volatile"]."', ": $rq .= "'2', ";
				isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != NULL ? $rq .= "'".$ret["service_max_check_attempts"]."', " : $rq .= "NULL, ";
				isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != NULL ? $rq .= "'".$ret["service_normal_check_interval"]."', ": $rq .= "NULL, ";
				isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != NULL ? $rq .= "'".$ret["service_retry_check_interval"]."', ": $rq .= "NULL, ";
				isset($ret["service_active_checks_enabled"]["service_active_checks_enabled"]) && $ret["service_active_checks_enabled"]["service_active_checks_enabled"] != 2 ? $rq .= "'".$ret["service_active_checks_enabled"]["service_active_checks_enabled"]."', ": $rq .= "'2', ";
				isset($ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]) && $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] != 2 ? $rq .= "'".$ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]."', ": $rq .= "'2', ";
				isset($ret["service_parallelize_check"]["service_parallelize_check"]) && $ret["service_parallelize_check"]["service_parallelize_check"] != 2 ? $rq .= "'".$ret["service_parallelize_check"]["service_parallelize_check"]."', ": $rq .= "'2', ";
				isset($ret["service_obsess_over_service"]["service_obsess_over_service"]) && $ret["service_obsess_over_service"]["service_obsess_over_service"] != 2 ? $rq .= "'".$ret["service_obsess_over_service"]["service_obsess_over_service"]."', ": $rq .= "'2', ";
				isset($ret["service_check_freshness"]["service_check_freshness"]) && $ret["service_check_freshness"]["service_check_freshness"] != 2 ? $rq .= "'".$ret["service_check_freshness"]["service_check_freshness"]."', ": $rq .= "'2', ";
				isset($ret["service_freshness_threshold"]) && $ret["service_freshness_threshold"] != NULL ? $rq .= "'".$ret["service_freshness_threshold"]."', ": $rq .= "NULL, ";
				isset($ret["service_event_handler_enabled"]["service_event_handler_enabled"]) && $ret["service_event_handler_enabled"]["service_event_handler_enabled"] != 2 ? $rq .= "'".$ret["service_event_handler_enabled"]["service_event_handler_enabled"]."', ": $rq .= "'2', ";
				isset($ret["service_low_flap_threshold"]) && $ret["service_low_flap_threshold"] != NULL ? $rq .= "'".$ret["service_low_flap_threshold"]."', " : $rq .= "NULL, ";
				isset($ret["service_high_flap_threshold"]) && $ret["service_high_flap_threshold"] != NULL ? $rq .= "'".$ret["service_high_flap_threshold"]."', " : $rq .= "NULL, ";
				isset($ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]) && $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] != 2 ? $rq .= "'".$ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]."', " : $rq .= "'2', ";
				isset($ret["service_process_perf_data"]["service_process_perf_data"]) && $ret["service_process_perf_data"]["service_process_perf_data"] != 2 ? $rq .= "'".$ret["service_process_perf_data"]["service_process_perf_data"]."', " : $rq .= "'2', ";
				isset($ret["service_retain_status_information"]["service_retain_status_information"]) && $ret["service_retain_status_information"]["service_retain_status_information"] != 2 ? $rq .= "'".$ret["service_retain_status_information"]["service_retain_status_information"]."', " : $rq .= "'2', ";
				isset($ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]) && $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] != 2 ? $rq .= "'".$ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]."', " : $rq .= "'2', ";
				isset($ret["service_notification_interval"]) && $ret["service_notification_interval"] != NULL ? $rq .= "'".$ret["service_notification_interval"]."', " : $rq .= "NULL, ";
				isset($ret["service_notifOpts"]) && $ret["service_notifOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["service_notifOpts"]))."', " : $rq .= "NULL, ";
				isset($ret["service_notifications_enabled"]["service_notifications_enabled"]) && $ret["service_notifications_enabled"]["service_notifications_enabled"] != 2 ? $rq .= "'".$ret["service_notifications_enabled"]["service_notifications_enabled"]."', " : $rq .= "'2', ";
				isset($ret["service_stalOpts"]) && $ret["service_stalOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["service_stalOpts"]))."', " : $rq .= "NULL, ";
				isset($ret["service_comment"]) && $ret["service_comment"] != NULL ? $rq .= "'".htmlentities($ret["service_comment"])."', " : $rq .= "NULL, ";
				isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != NULL ? $rq .= "'".htmlentities($ret["command_command_id_arg"], ENT_QUOTES)."', " : $rq .= "NULL, ";
				isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != NULL ? $rq .= "'".htmlentities($ret["command_command_id_arg2"], ENT_QUOTES)."', " : $rq .= "NULL, ";
				isset($ret["service_register"]["service_register"]) && $ret["service_register"]["service_register"] != NULL ? $rq .= "'".$ret["service_register"]["service_register"]."', " : $rq .= "NULL, ";
				isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != NULL ? $rq .= "'".$ret["service_activate"]["service_activate"]."'" : $rq .= "NULL";
				$rq .= ")";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		$res =& $pearDB->query("SELECT MAX(service_id) FROM service");
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		$service_id = $res->fetchRow();
		return ($service_id["MAX(service_id)"]);
	}
	
	function insertServiceExtInfos($service_id = null, $ret)	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		if (!count($ret))
			$ret = $form->getSubmitValues();
		$rq = 	"INSERT INTO `extended_service_information` " .
				"( `esi_id` , `service_service_id`, `esi_notes` , `esi_notes_url` , " .
				"`esi_action_url` , `esi_icon_image` , `esi_icon_image_alt`, `graph_id` )" .
				"VALUES ( ";
		$rq .= "NULL, ".$service_id.", ";
		isset($ret["esi_notes"]) && $ret["esi_notes"] != NULL ? $rq .= "'".htmlentities($ret["esi_notes"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["esi_notes_url"]) && $ret["esi_notes_url"] != NULL ? $rq .= "'".htmlentities($ret["esi_notes_url"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["esi_action_url"]) && $ret["esi_action_url"] != NULL ? $rq .= "'".htmlentities($ret["esi_action_url"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["esi_icon_image"]) && $ret["esi_icon_image"] != NULL ? $rq .= "'".htmlentities($ret["esi_icon_image"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["esi_icon_image_alt"]) && $ret["esi_icon_image_alt"] != NULL ? $rq .= "'".htmlentities($ret["esi_icon_image_alt"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		isset($ret["graph_id"]) && $ret["graph_id"] != NULL ? $rq .= "'".$ret["graph_id"]."'": $rq .= "NULL";
		$rq .= ")";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
	}
	
	function updateService($service_id = null)	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		$ret = array();
		$ret = $form->getSubmitValues();
		if (isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != NULL)		{
			$ret["command_command_id_arg"] = str_replace("\n", "#BR#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace("\t", "#T#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace("\r", "#R#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace('/', "#S#", $ret["command_command_id_arg"]);
			$ret["command_command_id_arg"] = str_replace('\\', "#BS#", $ret["command_command_id_arg"]);
		}		
		if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != NULL)		{
			$ret["command_command_id_arg2"] = str_replace("\n", "#BR#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace("\t", "#T#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace("\r", "#R#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace('/', "#S#", $ret["command_command_id_arg2"]);
			$ret["command_command_id_arg2"] = str_replace('\\', "#BS#", $ret["command_command_id_arg2"]);
		}
		$rq = "UPDATE service SET " ;
		$rq .= "service_template_model_stm_id = ";
		isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != NULL ? $rq .= "'".$ret["service_template_model_stm_id"]."', ": $rq .= "NULL, ";
		$rq .= "command_command_id = ";		
		isset($ret["command_command_id"]) && $ret["command_command_id"] != NULL ? $rq .= "'".$ret["command_command_id"]."', ": $rq .= "NULL, ";
		$rq .= "timeperiod_tp_id = ";
		isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id"]."', ": $rq .= "NULL, ";
		$rq .= "command_command_id2 = ";
		isset($ret["command_command_id2"]) && $ret["command_command_id2"] != NULL ? $rq .= "'".$ret["command_command_id2"]."', ": $rq .= "NULL, ";
		$rq .= "timeperiod_tp_id2 = ";
		isset($ret["timeperiod_tp_id2"]) && $ret["timeperiod_tp_id2"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id2"]."', ": $rq .= "NULL, ";
		$rq .= "purge_policy_id = ";
		isset($ret["purge_policy_id"]) && $ret["purge_policy_id"] != NULL ? $rq .= "'".$ret["purge_policy_id"]."', ": $rq .= "NULL, ";
		$rq .= "service_description = ";
		isset($ret["service_description"]) && $ret["service_description"] != NULL ? $rq .= "'".htmlentities($ret["service_description"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .= "service_is_volatile = ";
		isset($ret["service_is_volatile"]["service_is_volatile"]) && $ret["service_is_volatile"]["service_is_volatile"] != 2 ? $rq .= "'".$ret["service_is_volatile"]["service_is_volatile"]."', ": $rq .= "'2', ";
		$rq .= "service_max_check_attempts = ";
		isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != NULL ? $rq .= "'".$ret["service_max_check_attempts"]."', " : $rq .= "NULL, ";
		$rq .= "service_normal_check_interval = ";
		isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != NULL ? $rq .= "'".$ret["service_normal_check_interval"]."', ": $rq .= "NULL, ";
		$rq .= "service_retry_check_interval = ";
		isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != NULL ? $rq .= "'".$ret["service_retry_check_interval"]."', ": $rq .= "NULL, ";
		$rq .= "service_active_checks_enabled = ";
		isset($ret["service_active_checks_enabled"]["service_active_checks_enabled"]) && $ret["service_active_checks_enabled"]["service_active_checks_enabled"] != 2 ? $rq .= "'".$ret["service_active_checks_enabled"]["service_active_checks_enabled"]."', ": $rq .= "'2', ";
		$rq .= "service_passive_checks_enabled = ";
		isset($ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]) && $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] != 2 ? $rq .= "'".$ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]."', ": $rq .= "'2', ";
		$rq .= "service_parallelize_check = ";
		isset($ret["service_parallelize_check"]["service_parallelize_check"]) && $ret["service_parallelize_check"]["service_parallelize_check"] != 2 ? $rq .= "'".$ret["service_parallelize_check"]["service_parallelize_check"]."', ": $rq .= "'2', ";
		$rq .= "service_obsess_over_service = ";
		isset($ret["service_obsess_over_service"]["service_obsess_over_service"]) && $ret["service_obsess_over_service"]["service_obsess_over_service"] != 2 ? $rq .= "'".$ret["service_obsess_over_service"]["service_obsess_over_service"]."', ": $rq .= "'2', ";
		$rq .= "service_check_freshness = ";
		isset($ret["service_check_freshness"]["service_check_freshness"]) && $ret["service_check_freshness"]["service_check_freshness"] != 2 ? $rq .= "'".$ret["service_check_freshness"]["service_check_freshness"]."', ": $rq .= "'2', ";
		$rq .= "service_freshness_threshold = ";
		isset($ret["service_freshness_threshold"]) && $ret["service_freshness_threshold"] != NULL ? $rq .= "'".$ret["service_freshness_threshold"]."', ": $rq .= "NULL, ";
		$rq .= "service_event_handler_enabled = ";
		isset($ret["service_event_handler_enabled"]["service_event_handler_enabled"]) && $ret["service_event_handler_enabled"]["service_event_handler_enabled"] != 2 ? $rq .= "'".$ret["service_event_handler_enabled"]["service_event_handler_enabled"]."', ": $rq .= "'2', ";
		$rq .= "service_low_flap_threshold = ";
		isset($ret["service_low_flap_threshold"]) && $ret["service_low_flap_threshold"] != NULL ? $rq .= "'".$ret["service_low_flap_threshold"]."', " : $rq .= "NULL, ";
		$rq .= "service_high_flap_threshold = ";
		isset($ret["service_high_flap_threshold"]) && $ret["service_high_flap_threshold"] != NULL ? $rq .= "'".$ret["service_high_flap_threshold"]."', " : $rq .= "NULL, ";
		$rq .= "service_flap_detection_enabled = ";
		isset($ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]) && $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] != 2 ? $rq .= "'".$ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]."', " : $rq .= "'2', ";
		$rq .= "service_process_perf_data = ";
		isset($ret["service_process_perf_data"]["service_process_perf_data"]) && $ret["service_process_perf_data"]["service_process_perf_data"] != 2 ? $rq .= "'".$ret["service_process_perf_data"]["service_process_perf_data"]."', " : $rq .= "'2', ";
		$rq .= "service_retain_status_information = ";
		isset($ret["service_retain_status_information"]["service_retain_status_information"]) && $ret["service_retain_status_information"]["service_retain_status_information"] != 2 ? $rq .= "'".$ret["service_retain_status_information"]["service_retain_status_information"]."', " : $rq .= "'2', ";
		$rq .= "service_retain_nonstatus_information = ";
		isset($ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]) && $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] != 2 ? $rq .= "'".$ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]."', " : $rq .= "'2', ";
		$rq .= "service_notification_interval = ";
		isset($ret["service_notification_interval"]) && $ret["service_notification_interval"] != NULL ? $rq .= "'".$ret["service_notification_interval"]."', " : $rq .= "NULL, ";
		$rq .= "service_notification_options = ";
		isset($ret["service_notifOpts"]) && $ret["service_notifOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["service_notifOpts"]))."', " : $rq .= "NULL, ";
		$rq .= "service_notifications_enabled = ";
		isset($ret["service_notifications_enabled"]["service_notifications_enabled"]) && $ret["service_notifications_enabled"]["service_notifications_enabled"] != 2 ? $rq .= "'".$ret["service_notifications_enabled"]["service_notifications_enabled"]."', " : $rq .= "'2', ";
		$rq .= "service_stalking_options = ";
		isset($ret["service_stalOpts"]) && $ret["service_stalOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["service_stalOpts"]))."', " : $rq .= "NULL, ";
		$rq .= "service_comment = ";
		isset($ret["service_comment"]) && $ret["service_comment"] != NULL ? $rq .= "'".htmlentities($ret["service_comment"], ENT_QUOTES)."', " : $rq .= "NULL, ";
		$rq .= "command_command_id_arg = ";
		isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != NULL ? $rq .= "'".htmlentities($ret["command_command_id_arg"], ENT_QUOTES)."', " : $rq .= "NULL, ";
		$rq .= "command_command_id_arg2 = ";
		isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != NULL ? $rq .= "'".htmlentities($ret["command_command_id_arg2"], ENT_QUOTES)."', " : $rq .= "NULL, ";
		$rq .= "service_register = ";
		isset($ret["service_register"]["service_register"]) && $ret["service_register"]["service_register"] != NULL ? $rq .= "'".$ret["service_register"]["service_register"]."', " : $rq .= "NULL, ";
		$rq .= "service_activate = ";
		isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != NULL ? $rq .= "'".$ret["service_activate"]["service_activate"]."'" : $rq .= "NULL ";
		$rq .= "WHERE service_id = '".$service_id."'";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
	}
		
	function updateServiceContactGroup($service_id = null, $ret = array())	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM contactgroup_service_relation ";
		$rq .= "WHERE service_service_id = '".$service_id."'";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		if (isset($ret["service_cgs"]))
			$ret = $ret["service_cgs"];
		else
			$ret = $form->getSubmitValue("service_cgs");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO contactgroup_service_relation ";
			$rq .= "(contactgroup_cg_id, service_service_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$ret[$i]."', '".$service_id."')";
			$pearDB->query($rq);
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
		}
	}
	
	function updateServiceServiceGroup($service_id = null, $ret = array())	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM servicegroup_relation ";
		$rq .= "WHERE service_service_id = '".$service_id."'";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		if (isset($ret["service_sgs"]))
			$ret = $ret["service_sgs"];
		else
			$ret = $form->getSubmitValue("service_sgs");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO servicegroup_relation ";
			$rq .= "(service_service_id, servicegroup_sg_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$service_id."', '".$ret[$i]."')";
			$pearDB->query($rq);
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
		}
	}	
	
	function updateServiceTrap($service_id = null, $ret = array())	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM traps_service_relation ";
		$rq .= "WHERE service_id = '".$service_id."'";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		if (isset($ret["service_traps"]))
			$ret = $ret["service_traps"];
		else
			$ret = $form->getSubmitValue("service_traps");
		for($i = 0; $i < count($ret); $i++)	{
			$rq = "INSERT INTO traps_service_relation ";
			$rq .= "(traps_id, service_id) ";
			$rq .= "VALUES ";
			$rq .= "('".$ret[$i]."', '".$service_id."')";
			$pearDB->query($rq);
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
		}
	}
	
	function updateServiceHost($service_id = null, $ret = array())	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		$rq = "DELETE FROM host_service_relation ";
		$rq .= "WHERE service_service_id = '".$service_id."'";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
		$ret1 = array();
		$ret2 = array();
		if (isset($ret["service_hPars"]))
			$ret1 = $ret["service_hPars"];
		else
			$ret1 = $form->getSubmitValue("service_hPars");
		if (isset($ret["service_hgPars"]))
			$ret2 = $ret["service_hgPars"];
		else
			$ret2 = $form->getSubmitValue("service_hgPars");
		 if (count($ret2))
			for($i = 0; $i < count($ret2); $i++)	{
				$rq = "INSERT INTO host_service_relation ";
				$rq .= "(hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) ";
				$rq .= "VALUES ";
				$rq .= "('".$ret2[$i]."', NULL, NULL, '".$service_id."')";
				$pearDB->query($rq);
				if (PEAR::isError($pearDB)) {
					print "Mysql Error : ".$pearDB->getMessage();
				}
			}
		else if (count($ret1))
			for($i = 0; $i < count($ret1); $i++)	{
				$rq = "INSERT INTO host_service_relation ";
				$rq .= "(hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id) ";
				$rq .= "VALUES ";
				$rq .= "(NULL, '".$ret1[$i]."', NULL, '".$service_id."')";
				$pearDB->query($rq);
				if (PEAR::isError($pearDB)) {
					print "Mysql Error : ".$pearDB->getMessage();
				}
			}
	}
	
	function updateServiceExtInfos($service_id = null, $ret = array())	{
		if (!$service_id) return;
		global $form;
		global $pearDB;
		if (!count($ret))
			$ret = $form->getSubmitValues();
		$rq = "UPDATE extended_service_information ";		
		$rq .= "SET esi_notes = ";
		isset($ret["esi_notes"]) && $ret["esi_notes"] != NULL ? $rq .= "'".htmlentities($ret["esi_notes"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .= "esi_notes_url = ";
		isset($ret["esi_notes_url"]) && $ret["esi_notes_url"] != NULL ? $rq .= "'".htmlentities($ret["esi_notes_url"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .= "esi_action_url = ";
		isset($ret["esi_action_url"]) && $ret["esi_action_url"] != NULL ? $rq .= "'".htmlentities($ret["esi_action_url"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .= "esi_icon_image = ";
		isset($ret["esi_icon_image"]) && $ret["esi_icon_image"] != NULL ? $rq .= "'".htmlentities($ret["esi_icon_image"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .= "esi_icon_image_alt = ";
		isset($ret["esi_icon_image_alt"]) && $ret["esi_icon_image_alt"] != NULL ? $rq .= "'".htmlentities($ret["esi_icon_image_alt"], ENT_QUOTES)."', ": $rq .= "NULL, ";
		$rq .= "graph_id = ";
		isset($ret["graph_id"]) && $ret["graph_id"] != NULL ? $rq .= "'".htmlentities($ret["graph_id"], ENT_QUOTES)."' ": $rq .= "NULL ";
		$rq .= "WHERE service_service_id = '".$service_id."'";
		$pearDB->query($rq);
		if (PEAR::isError($pearDB)) {
			print "Mysql Error : ".$pearDB->getMessage();
		}
	}
	
	function updateServiceTemplateUsed($useTpls = array())	{
		if(!count($useTpls)) return;
		global $pearDB;
		require_once "./include/common/common-Func.php";
		foreach ($useTpls as $key=>$value)
		{
			$pearDB->query("UPDATE service SET service_template_model_stm_id = '".getMyServiceTPLID($value)."' WHERE service_id = '".$key."'");
			if (PEAR::isError($pearDB)) {
				print "Mysql Error : ".$pearDB->getMessage();
			}
		}
	}
?>