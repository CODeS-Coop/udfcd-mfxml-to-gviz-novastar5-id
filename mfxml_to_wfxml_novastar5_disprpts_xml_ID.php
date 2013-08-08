<?php
/* Copyright 2013 Urban Drainage and Flood Control District (UDFCD)
 * Developed for UDFCD by Leonard Rice Engineers, Inc. and Brannon Developments.
 * 
 * This file is one of four PHP scripts that operate together to convert a
 * UDFCD-specific,attribute-heavy XML into JSON formatted data for use in displaying
 * in Google Visualizations on the web using javascript.  This file in particular
 * converts the UDFCD-specific XML to well-formed XML and is called by
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
function mfxml_to_wfxml_novastar5_disprpts_xml_ID ($mfxml_string) {
	/*
	 * set up the new xml
	 */
	$wfxml_string = "<?xml version='1.0' standalone='yes'?><streamflows></streamflows>";
	$wfxml = new SimpleXMLElement($wfxml_string);
	/*
	 * read the incoming xml and populate the outgoing xml
	 */
	try {
		$mfxml = new SimpleXMLElement($mfxml_string);
		/*
		 * the incoming attribute heavy udfcd novastar5 xml structure
		 * 
		 * <streamflows>
		 *   <report date="2012-02-20" time="11:07:50-07:00">
		 *     <data id="1659">4.57</data>
		 *     <data id="1659">262</data>
		 *   </report>
		 *   <report date="2012-02-20" time="11:22:54-07:00">
		 *     <data id="1659">4.56</data>
		 *     <data id="1659">259</data>
		 *   </report>
		 *   ...
		 * </streamflows>
		 */
		/*
		 * the outgoing more generic udfcd novastar5 xml structure
		 *    with standard XML Schema data types as attributes
		 *    (http://www.w3schools.com/schema/schema_simple_attributes.asp)
		 *    for now, don't actually use XML Schema, but we REALLY should be...
		 *    
		 *    note how we are dropping the time zone info
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
		 */
		/*
		 * populate the new xml
		 */
		$counter = 0;
		foreach( $mfxml->report as $mfxml_report ) {
			$wfxml_streamflow = $wfxml->addChild('row');
			$wf_datetime = $mfxml_report->attributes()->date."T".substr($mfxml_report->attributes()->time,0,8); 
			$wf_data_id = array();
			$wf_data_val = array();
			// we assume there are only two "data" elements and the order is always stage then flow (now do you see why this is considered malformed?)
			foreach( $mfxml_report->data as $mfxml_data ) {
				$wf_data_id[] = $mfxml_data->attributes()->id;
				$wf_data_val[] = $mfxml_data;
			}
			$newchild = $wfxml_streamflow->addChild('site_id',$wf_data_id[0]);
			$newchild->addAttribute('type', 'xs:integer');
			$newchild = $wfxml_streamflow->addChild('obs_time',$wf_datetime);
			$newchild->addAttribute('type', 'xs:datetime');
			$newchild = $wfxml_streamflow->addChild('stage',$wf_data_val[0]);
			$newchild->addAttribute('type', 'xs:decimal');
			$newchild = $wfxml_streamflow->addChild('flow',$wf_data_val[1]);
			$newchild->addAttribute('type', 'xs:decimal');
		}
	} catch (Exception $e) {
	}
	return $wfxml->asXML();
}
?>