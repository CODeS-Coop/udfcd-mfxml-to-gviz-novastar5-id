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
function wfxml_to_jsonarray_novastar5_disprpts_xml_id ($wfxml_string,$output_type='all',$output_format='json',$output_gv_type='table') {
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
						switch ($element_type) {
							case 'xs:date':
								switch (strtolower($output_format)) {
									case 'json':
										switch (strtolower($output_gv_type)) {
											case 'combochart':
												$jsonarray["cols"][$json_field_counter]["type"] = "date";
												break;
											case 'table':
											case 'linechart':
											default:
												$jsonarray["cols"][$json_field_counter]["type"] = "string";
										}
										break;
									case 'csv':
									case 'html_table_2d':
									case 'html_table_raw':
									default:
										$jsonarray["cols"][$json_field_counter]["type"] = "string";
								}
								break;
							case 'xs:time':
								switch (strtolower($output_format)) {
									case 'json':
										switch (strtolower($output_gv_type)) {
											case 'combochart':
												$jsonarray["cols"][$json_field_counter]["type"] = "timeofday";
												break;
											case 'table':
											case 'linechart':
											default:
												$jsonarray["cols"][$json_field_counter]["type"] = "string";
										}
										break;
									case 'csv':
									case 'html_table_2d':
									case 'html_table_raw':
									default:
										$jsonarray["cols"][$json_field_counter]["type"] = "string";
								}
								break;
							case 'xs:datetime':
								switch (strtolower($output_format)) {
									case 'json':
										switch (strtolower($output_gv_type)) {
											case 'combochart':
												$jsonarray["cols"][$json_field_counter]["type"] = "datetime";
												break;
											case 'table':
											case 'linechart':
											default:
												$jsonarray["cols"][$json_field_counter]["type"] = "string";
										}
										break;
									case 'csv':
									case 'html_table_2d':
									case 'html_table_raw':
									default:
										$jsonarray["cols"][$json_field_counter]["type"] = "string";
								}
								break;
							case 'xs:string':
								$jsonarray["cols"][$json_field_counter]["type"] = "text";
								break;
							case 'xs:integer':
							case 'xs:decimal':
								$jsonarray["cols"][$json_field_counter]["type"] = "number";
								break;
							default:
								$jsonarray["cols"][$json_field_counter]["type"] = "text";
						}
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
					switch ($element_type) {
						case 'xs:integer':
							$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = (integer) $child;
							break;
						case 'xs:decimal':
							$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = (real) $child;
							break;
						case 'xs:datetime':
							switch (strtolower($output_format)) {
								case 'json':
									switch (strtolower($output_gv_type)) {
										case 'combochart':
											$thetimestamp = strtotime((string) $child);
											$thetimestamparray = getdate($thetimestamp);
											$year = $thetimestamparray['year'];
											$month = $thetimestamparray['mon'];
											$day = $thetimestamparray['mday'];
											$hour = $thetimestamparray['hours'];
											$minute = $thetimestamparray['minutes'];
											$second = $thetimestamparray['seconds'];
											$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = "new Date($year,$month-1,$day,$hour,$minute,$second)";
											break;
										case 'table':
										case 'linechart':
										default:
											$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = str_replace("T"," ",(string) $child);
									}
									break;
								case 'csv':
								case 'html_table_2d':
								case 'html_table_raw':
								default:
									$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = str_replace("T"," ",(string) $child);
							}
							break;
						case 'xs:date':
							switch (strtolower($output_format)) {
								case 'json':
									switch (strtolower($output_gv_type)) {
										case 'combochart':
											$thetimestamp = strtotime((string) $child);
											$thetimestamparray = getdate($thetimestamp);
											// "new Date($yy,$mm,1,0,0,0)";
											$year = $thetimestamparray['year'];
											$month = $thetimestamparray['mon'];
											$day = $thetimestamparray['mday'];
											$hour = $thetimestamparray['hours'];
											$minute = $thetimestamparray['minutes'];
											$second = $thetimestamparray['seconds'];
											$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = "new Date($year,$month-1,$day,$hour,$minute,$second)";
											break;
										case 'table':
										case 'linechart':
										default:
											$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = str_replace("T"," ",(string) $child);
									}
									break;
								case 'csv':
								case 'html_table_2d':
								case 'html_table_raw':
								default:
									$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = str_replace("T"," ",(string) $child);
							}
							break;
						case 'xs:time':
							switch (strtolower($output_format)) {
								case 'json':
									switch (strtolower($output_gv_type)) {
										case 'combochart':
											$thetimestamp = strtotime((string) $child);
											$thetimestamparray = getdate($thetimestamp);
											// "new Date($yy,$mm,1,0,0,0)";
											$year = $thetimestamparray['year'];
											$month = $thetimestamparray['mon'];
											$day = $thetimestamparray['mday'];
											$hour = $thetimestamparray['hours'];
											$minute = $thetimestamparray['minutes'];
											$second = $thetimestamparray['seconds'];
											$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = "new Date($year,$month-1,$day,$hour,$minute,$second)";
											break;
										case 'table':
										case 'linechart':
										default:
											$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = str_replace("T"," ",(string) $child);
									}
									break;
								case 'csv':
								case 'html_table_2d':
								case 'html_table_raw':
								default:
									$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = str_replace("T"," ",(string) $child);
							}
							break;
						case 'xs:string':
						default:
							$jsonarray["rows"][$record_counter]["c"][$json_field_counter]["v"] = (string) $child;
					}
					++$json_field_counter;
				}
				++$field_counter;
			}
			++$record_counter;
		}
	} catch (Exception $e) {
	}
	return $jsonarray;
}
?>
