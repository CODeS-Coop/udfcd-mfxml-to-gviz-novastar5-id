<?php
/* Copyright 2013 Urban Drainage and Flood Control District (UDFCD)
 * Developed for UDFCD by Leonard Rice Engineers, Inc. and Brannon Developments.
 * 
 * This file is one of four PHP scripts that operate together to convert a
 * UDFCD-specific,attribute-heavy XML into JSON formatted data for use in displaying
 * in Google Visualizations on the web using javascript.  This file in particular
 * converts "well-formed" XML to a generic JSON array and is called by
 * "mfxml_to_jsonarray_novastar5_disprpts_xml_id.php".
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
function wfxml_to_jsonarray_novastar5_disprpts_xml_id ($wfxml_string,$output_type='all',$output_format='json',$output_gv_type='table',$notempty=FALSE) {
	/*
	 * read the incoming xml and populate the outgoing array
	 */
	try {
		$wfxml = new SimpleXMLElement($wfxml_string);
		/*
		 * the assumed incoming udfcd novastar5 xml structure, but this routine could work for most anything!
		 * 
		 *    note it now has standard XML Schema data types as attributes
		 *    (http://www.w3schools.com/schema/schema_simple_attributes.asp)
		 *    for now, not actually using XML Schema, but we REALLY should be...
		 *    
		 *    also note how we have dropped the time zone info
		 *    and using local time as if it is a UTC time...
		 *    ...this could bite us later!!!
		 * 
		 *   <streamflows>
		 *     <row>
		 *       <site_id type="xs:integer">1659</site_id>
		 *       <obs_time type="xs:datetime">2012-02-20T11:07:50</obs_time>
		 *       <stage type="xs:decimal">4.57</stage>
		 *       <flow type="xs:decimal">262</flow>
		 *     </row>
		 *     <row>
		 *       <site_id type="xs:integer">1659</site_id>
		 *       <obs_time type="xs:datetime">2012-02-20T11:22:54</obs_time>
		 *       <stage type="xs:decimal">4.56</stage>
		 *       <flow type="xs:decimal">259</flow>
		 *     </row>
		 *   ...
		 *   </streamflows>
		 *   
		 */
		switch ($output_type) {
			case 'flow':
				$element_names = array('obs_time','flow');
				break;
			case 'stage':
			case 'stagethreshold':
				$element_names = array('obs_time','stage');
				break;
			case 'all':
			default:
				$element_names = array();
				$record_counter = 0;
				foreach( $wfxml as $row ) {
					if (! $record_counter) {
						$field_counter = 0;
						foreach ($row->children() as $child) {
							$element_names[] = (string) $child->getName();
							++$field_counter;
						}
						break 2;
					}
				}
		}
		/*
		 * create the gviz json array 
		 * 
		 * note this array structure is designed to mimic the structure in a Google Data Visualization json data source
		 * this makes it easier for creating the gviz json stream later...
		 *
		 */
		$jsonarray = array();
		$record_counter = 0;
		foreach( $wfxml as $row ) {
			if (! $record_counter) {
				 // it's the first record, so do some housework here...
				 // get the element names and and element types and save then to the gviz json array meta data
				 // also, determine which fields to carry through based on the $output_type arg, build a little boolean table
				$field_counter = 0;
				$json_field_counter = 0;
				$json_include = array();
				foreach ($row->children() as $child) {
					$element_name = (string) $child->getName();
					if (in_array($element_name,$element_names)) {
						$json_include[$field_counter] = TRUE;
						$jsonarray["cols"][$json_field_counter]["id"] = $element_name;
						$jsonarray["cols"][$json_field_counter]["label"] = $element_name;
						$element_type = (string) $child->attributes()->type;
						$jsonarray["cols"][$json_field_counter]["type"] = xmltotypemap($element_type,$output_format,$output_gv_type);
						++$json_field_counter;
					} else {
						$json_include[$field_counter] = FALSE;
					}
					++$field_counter;
				}
			}
			// get the element values and save them to the gviz json array
			$field_counter = 0;
			$json_field_counter = 0;
			foreach ($row->children() as $child) {
				$element_name = (string) $child->getName();
				if ($json_include[$field_counter]) {
					$element_type = (string) $child->attributes()->type;
					$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = xmltovaluemap($child,$element_type,$output_format,$output_gv_type);
					++$json_field_counter;
				}
				++$field_counter;
			}
			++$record_counter;
		} // foreach( $wfxml as $row )
		/*
		 * check if we need to fill an empty array structure
		 */ 
		if ($notempty) {
			if (! $record_counter) {
				/*
				 * no records were in the XML string
				 * do the json set up again, but this time with a fake record:
				 *   current time stamp and a null value
				 * note that this actually only works with flow/stage/stagethreshold types
				 *   because those are the only types where we really know what the fields should be
				 * however make up some fields for the other types so it still passes something back in the array
				 */
				switch ($output_type) {
					case 'flow':
						$element_names = array('obs_time','flow');
						$element_types = array('xs:datetime','xs:decimal');
						$element_values = array(date(DATE_ATOM),'-1.0');
						break;
					case 'stage':
					case 'stagethreshold':
						$element_names = array('obs_time','stage');
						$element_types = array('xs:datetime','xs:decimal');
						$element_values = array(date(DATE_ATOM),'-1.0');
						break;
					case 'all':
					default:
						$element_names = array('obs_time','value');
						$element_types = array('xs:datetime','xs:decimal');
						$element_values = array(date(DATE_ATOM),'-1.0');
						break;
				}
				/* 
				 * create the json array structure
				 * simpler than before since all the defined fields are used
				 * and there will only be one row
				 */
				$json_field_counter = 0;
				$record_counter = 0;
				foreach ($element_names as $element_name) {
					$jsonarray["cols"][$json_field_counter]["id"] = $element_name;
					$jsonarray["cols"][$json_field_counter]["label"] = $element_name;
					$element_type = (string) $element_types[$json_field_counter];
					$jsonarray["cols"][$json_field_counter]["type"] = xmltotypemap($element_type,$output_format,$output_gv_type);
					$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = xmltovaluemap($element_values[$json_field_counter],$element_type,$output_format,$output_gv_type);
					++$json_field_counter;
				}
			} // if (! $record_counter) {
		} // if ($notempty) {
	} catch (Exception $e) {
	}
	return $jsonarray;
}
/*
 * function xmltofieldtypemap($element_type,$output_format,$output_gv_type) 
 * 
 * this function converts a specified XML element type into the appropriate 
 * output structure field type.  Needs the output format and output gv type
 * 
 */
function xmltotypemap($element_type,$output_format,$output_gv_type) {
	switch ($element_type) {
		case 'xs:date':
			switch (strtolower($output_format)) {
				case 'json':
					switch (strtolower($output_gv_type)) {
						case 'combochart':
							$fieldtype = "date";
							break;
						case 'table':
						case 'linechart':
						default:
							$fieldtype = "string";
					}
					break;
				case 'csv':
				case 'html_table_2d':
				case 'html_table_raw':
				default:
					$fieldtype = "string";
			}
			break;
		case 'xs:time':
			switch (strtolower($output_format)) {
				case 'json':
					switch (strtolower($output_gv_type)) {
						case 'combochart':
							$fieldtype = "timeofday";
							break;
						case 'table':
						case 'linechart':
						default:
							$fieldtype = "string";
					}
					break;
				case 'csv':
				case 'html_table_2d':
				case 'html_table_raw':
				default:
					$fieldtype = "string";
			}
			break;
		case 'xs:datetime':
			switch (strtolower($output_format)) {
				case 'json':
					switch (strtolower($output_gv_type)) {
						case 'combochart':
							$fieldtype = "datetime";
							break;
						case 'table':
						case 'linechart':
						default:
							$fieldtype = "string";
					}
					break;
				case 'csv':
				case 'html_table_2d':
				case 'html_table_raw':
				default:
					$fieldtype = "string";
			}
			break;
		case 'xs:string':
			$fieldtype = "text";
			break;
		case 'xs:integer':
		case 'xs:decimal':
			$fieldtype = "number";
			break;
		default:
			$fieldtype = "text";
	}
	return $fieldtype;
}
/*
 * function xmltovaluemap($element,$element_type,$output_format,$output_gv_type) 
 * 
 * this function converts a specified XML element type into the appropriate 
 * output structure field type.  Needs the output format and output gv type
 * 
 */
function xmltovaluemap($element,$element_type,$output_format,$output_gv_type) {
	switch ($element_type) {
		case 'xs:integer':
			$value = (integer) $element;
			break;
		case 'xs:decimal':
			$value = (real) $element;
			break;
		case 'xs:datetime':
			switch (strtolower($output_format)) {
				case 'json':
					switch (strtolower($output_gv_type)) {
						case 'combochart':
							$thetimestamp = strtotime((string) $element);
							$thetimestamparray = getdate($thetimestamp);
							$year = $thetimestamparray['year'];
							$month = $thetimestamparray['mon'];
							$day = $thetimestamparray['mday'];
							$hour = $thetimestamparray['hours'];
							$minute = $thetimestamparray['minutes'];
							$second = $thetimestamparray['seconds'];
							// here is a pretty little gem of a line that makes everything work with GViz!!
							$value = "new Date($year,$month-1,$day,$hour,$minute,$second)";
							break;
						case 'table':
						case 'linechart':
						default:
							$value = str_replace("T"," ",(string) $element);
					}
					break;
				case 'csv':
				case 'html_table_2d':
				case 'html_table_raw':
				default:
					$value = str_replace("T"," ",(string) $element);
			}
			break;
		case 'xs:date':
			switch (strtolower($output_format)) {
				case 'json':
					switch (strtolower($output_gv_type)) {
						case 'combochart':
							$thetimestamp = strtotime((string) $element);
							$thetimestamparray = getdate($thetimestamp);
							// "new Date($yy,$mm,1,0,0,0)";
							$year = $thetimestamparray['year'];
							$month = $thetimestamparray['mon'];
							$day = $thetimestamparray['mday'];
							$hour = $thetimestamparray['hours'];
							$minute = $thetimestamparray['minutes'];
							$second = $thetimestamparray['seconds'];
							// here is a pretty little gem of a line that makes everything work with GViz!!
							$value = "new Date($year,$month-1,$day,$hour,$minute,$second)";
							break;
						case 'table':
						case 'linechart':
						default:
							$value = str_replace("T"," ",(string) $element);
					}
					break;
				case 'csv':
				case 'html_table_2d':
				case 'html_table_raw':
				default:
					$value = str_replace("T"," ",(string) $element);
			}
			break;
		case 'xs:time':
			switch (strtolower($output_format)) {
				case 'json':
					switch (strtolower($output_gv_type)) {
						case 'combochart':
							$thetimestamp = strtotime((string) $element);
							$thetimestamparray = getdate($thetimestamp);
							// "new Date($yy,$mm,1,0,0,0)";
							$year = $thetimestamparray['year'];
							$month = $thetimestamparray['mon'];
							$day = $thetimestamparray['mday'];
							$hour = $thetimestamparray['hours'];
							$minute = $thetimestamparray['minutes'];
							$second = $thetimestamparray['seconds'];
							// here is a pretty little gem of a line that makes everything work with GViz!!
							$value = "new Date($year,$month-1,$day,$hour,$minute,$second)";
							break;
						case 'table':
						case 'linechart':
						default:
							$value = str_replace("T"," ",(string) $element);
					}
					break;
				case 'csv':
				case 'html_table_2d':
				case 'html_table_raw':
				default:
					$value = str_replace("T"," ",(string) $element);
			}
			break;
		case 'xs:string':
		default:
			$value = (string) $element;
	}
	return $value;
}
?>
