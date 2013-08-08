<?php
/* Copyright 2013 Urban Drainage and Flood Control District (UDFCD)
 * Developed for UDFCD by Leonard Rice Engineers, Inc. and Brannon Developments.
 * 
 * This file is one of four PHP scripts that operate together to convert a
 * UDFCD-specific,attribute-heavy XML into JSON formatted data for use in displaying
 * in Google Visualizations on the web using javascript.  This file in particular
 * converts the UDFCD-specific XML to a generic JSON array and is called by the
 * top-level script, "mfxml_to_gviz_novastar5_disprpts_xml_id.php".
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/*
 * functions we need
 */
include 'mfxml_to_wfxml_novastar5_disprpts_xml_ID.php';
include 'wfxml_to_jsonarray_novastar5_disprpts_xml_id.php';
function mfxml_to_jsonarray_novastar5_disprpts_xml_id ($id,$output_type='all',$output_format='json',$output_gv_type='table',$notempty=FALSE) {
	/*
	 * get the mf xml from novastar O:-)
	 */
	$url = "http://72.54.233.74/cgi-bin/disprpts_xml?ID=$id";
	$mfxml_string = file_get_contents($url);
	/*
	 * convert it to a json array
	 */
	$jsonarray = wfxml_to_jsonarray_novastar5_disprpts_xml_id(mfxml_to_wfxml_novastar5_disprpts_xml_ID ($mfxml_string),$output_type,$output_format,$output_gv_type,$notempty);
	switch ($output_type) {
		case 'stagethreshold':
			/*
			 * add the threshold values
			 *first get the threshold values for this station from the database
			 */
			$dbhandle = pg_connect("host=lredb2 port=5431 user=udfcd_ahps password=udfcdahps dbname=UDFCD_AHPS") or die("Could not connect");
			$tablename_alertstationmetadata = "ahps.alert_stations_master_list";
			$fieldnames_alertstationmetadata = "stage_major,stage_moderate,stage_flood,stage_bankfull,stage_action";
			$stationidfieldname_alertstationmetadata = "alert_id";
			$stationnamefieldname_alertstationmetadata = "station_name";
			$query_alertstationmetadata = "SELECT $stationidfieldname_alertstationmetadata,$stationnamefieldname_alertstationmetadata,$fieldnames_alertstationmetadata FROM $tablename_alertstationmetadata WHERE $stationidfieldname_alertstationmetadata = $id";
			$pgresults_alertstationmetadata = pg_query($dbhandle, $query_alertstationmetadata);
			$fieldnames_array = explode(",","stage_major,stage_moderate,stage_flood,stage_bankfull,stage_action");
			
			// should be only one row, but just in case, this does them all, so only the last one sticks
			while ($data_row = pg_fetch_object($pgresults_alertstationmetadata)) {
				$data_row_last = $data_row;
			}
			foreach ($fieldnames_array as $fieldname) {
				$columns_count = count($jsonarray['cols']);
				$rows_count = count($jsonarray['rows']);
				$jsonarray["cols"][$columns_count]["id"] = $fieldname;
				$jsonarray["cols"][$columns_count]["label"] = $fieldname;
				$jsonarray["cols"][$columns_count]["type"] = "number";
				$stage_value = $data_row_last->$fieldname;
				for ($rowindex=0;$rowindex<$rows_count;++$rowindex) {
					$jsonarray["rows"][$rowindex]["c"][$columns_count]["v"] = (real) $stage_value;
				}
			}
			break;
		case 'flow':
		case 'stage':
		case 'all':
		default:
			//do nothing
	}
	return $jsonarray;
}
?>
