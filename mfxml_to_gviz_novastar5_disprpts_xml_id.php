<?php
/* Copyright 2013 Urban Drainage and Flood Control District (UDFCD)
 * Developed for UDFCD by Leonard Rice Engineers, Inc. and Brannon Developments.
 * 
 * This file is one of four PHP scripts that operate together to convert a
 * UDFCD-specific,attribute-heavy XML into JSON formatted data for use in displaying
 * in Google Visualizations on the web using javascript.  This file in particular
 * is is the top-level script intended to be called by the GViz javascript in
 * web page.
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
include 'mfxml_to_jsonarray_novastar5_disprpts_xml_id.php';
/*
 * put some default values up here so ya don't have to dig for them!!
 *   unfortunately not all of them are up here right now
 *   for now you'll have to dig into subroutines for the novastar and ahps specific XML and pg stuff
 */
$station_id = ""; //"" is the official default

$debug_arg = "false"; //false is the official default
$silent_debug_arg = "false"; //false is the official default
$output_format_arg = "json"; //"json" is the official default so it always works with gviz
$output_gv_type = "table"; //"table" is the official default - no restrictions on column order or column types on tables
$output_type = "all"; //"all" is the official default
$notempty_arg = "false"; //false is the official default

$category_axis_label = ""; //"" is the official default
$series_axis_label = ""; //"" is the official default
$conv_factor = 1.0; //1.0 is the official default
$precision = 99; //99 is the official default

// get the debug arg first
$debug_arg = getargs ("debug",$debug_arg);
if (strlen(trim($debug_arg))) {
	$debug = strtobool($debug_arg);
} else {
	$debug = false;
}
if ($debug) echo "debug arg: $debug_arg<br>";
// get the silent debug arg
$silent_debug_arg = getargs ("silent_debug",$silent_debug_arg);
if (strlen(trim($silent_debug_arg))) {
	$silent_debug = strtobool($silent_debug_arg);
} else {
	$silent_debug = false;
}
if ($silent_debug) {
	$path = '/tmp/';
	$file_name_base = "pg_to_gviz_basic_debug_".date('YmdHis');
	$file_name = $file_name_base.".txt";
	$txtfile = $path.$file_name;
	$silent_debug_handle = fopen($txtfile, 'w');
}
// csv - used by google, comma delimited text file
// json - used by google, json data source for gv objects
//		unfortunately, we'll need a third dimension, since the field types change based on the gv object type, $output_gv_type
//		table - the default for now
//		column_graph
//		annotated_time_line
//		etc. as needed
// html_table_raw - 
// html_table_2d -
$output_format_arg = getargs ("output_format",$output_format_arg);
if ($debug) echo "initial output_format: $output_format_arg<br>";
if ($silent_debug) fwrite($silent_debug_handle,"initial output_format: $output_format_arg<br>");
// but let google args over-ride it...
// ----------------------
// this script operates both as a simple (no querying) gv data source returning either a json data stream or a csv file
//   and also a stand-alone callable data service returning data in various forms: json, csv file, and html tables
// as the former it can handle a few tqx args, including specifically the 'out' arg as follows:
//   if it is 'csv', it returns a csv file containing comma delimited data
//   if it is 'json', then it returns the appropriate json to feed google visualizations
// it can not (yet) handle the tq arg (query strings)
// ----------------------
// get the gv json tq args, if any
// ----------------------
$tq = getargs ("tq","");  // perhaps we should expand this to send an error mesage that queries are not handled...
if ($debug) {
	echo "Google Visualization JSON data source arguments:<br>";
	echo "&#160;&#160;tq: $tq<br>";
}
if ($silent_debug) {
	fwrite($silent_debug_handle,"Google Visualization JSON data source arguments:<br>");
	fwrite($silent_debug_handle,"&#160;&#160;tq: $tq<br>");
}
// ----------------------
// get the gv json tqx args, if any
// ----------------------
$tqxlist = array();
$tqxlist = explode(";",getargs ("tqx",""));
$tqx = array();
$tqx_args = 0;
foreach ($tqxlist as $tqxstr) {
	++$tqx_args;
	$tmp = array();
	$tmp = explode(':',$tqxstr);
	$tqx[$tmp[0]] = $tmp[1];
}
// figure out the output format to use - use google's passed arg, if none, then use the output_format arg
if (strlen($tqx['out'])) {
	$output_format = strtolower($tqx['out']); // i.e. ignore the other arg if this one exists
} else {
	$output_format = strtolower($output_format_arg); // assumes this is set above
}
if ($debug)echo "final output_format: $output_format<br>";
if ($silent_debug)fwrite($silent_debug_handle,"final output_format: $output_format<br>");
switch ($output_format) {
	case 'json':
		$output_gv_type = getargs ("output_gv_type",$output_gv_type);
		if ($debug) echo "output_gv_type: $output_gv_type<br>";
		// assume this is for google viz and therefore make sure the args for the header are set...
		if (!isset($tqx['responseHandler']) || !strlen($tqx['responseHandler'])) $tqx['responseHandler']='google.visualization.Query.setResponse';
		if (!isset($tqx['version']) || !strlen($tqx['version'])) $tqx['version']='0.6';
		if (!isset($tqx['reqId'])) $tqx['reqId']=0;
		break;
	case 'csv':
	case 'html_table_2d':
	case 'html_table_raw':
	default:
		// nothing to see here. move along, please.
}
/*
 * $output_type
 *   first my apologies - the output_type in this case is conceptually something a little different than usual
 *   this defines which input data to use from the XML data source
 *   this is specific to the novastar5 disprepts_xml report with the ID= input parameter
 * all - all the fields in the original XML fields
 * flow - only the datetime and flow fields
 * stage - only the datetime and stage fields
 * stagethresholds - the datetime, stage, and threshold fields
 */ 
$output_type = getargs ("output_type",$output_type);
if ($debug) echo "output_type: $output_type<br>";
// common args for all types
$station_id = getargs ("ID",$station_id);
if ($debug) {
	echo "station_id: $station_id<br>";
}
if (!strlen(trim($station_id))) {
	echo "Error: Missing ID arg.  station ID is required.<br>";
	exit;
}
// get the needed args for each type
switch ($output_type) {
	case 'all':
	case 'flow':
	case 'stage':
	case 'stagethreshold':
	default:
}
// get the notempty arg
$notempty_arg = getargs ("notempty",$notempty_arg);
if (strlen(trim($notempty_arg))) {
	$notempty = strtobool($notempty_arg);
} else {
	$notempty = false;
}
if ($debug) echo "notempty arg: $notempty_arg<br>";
// other args
$category_axis_label = getargs ("category_axis_label",$category_axis_label);
$series_axis_label = getargs ("series_axis_label",$series_axis_label);
$conv_factor = getargs ("conversion_factor",$conv_factor);
$precision = getargs ("output_precision",$precision);
if ($debug) {
	echo "output_type: $output_type<br>";
	echo "category_axis_label: $category_axis_label<br>";
	echo "series_axis_label: $series_axis_label<br>";
	echo "conversion_factor: $conv_factor<br>";
	echo "output_precision: $precision<br>";
}
/*
 * get the data from the XML and load it into arrays ready for processing into the various output formats
 * 
 * !!!!!!!!!!!! here is where the magic happens !!!!!!!!!!!!
 */
switch ($output_type) {
	// if we got this far, the required args have something in them, but might still have the optional args to deal with...
	case 'all':
	case 'flow':
	case 'stage':
	case 'stagethreshold':
	default:
}
$datatable = array();
$datatable = mfxml_to_jsonarray_novastar5_disprpts_xml_id($station_id,$output_type,$output_format,$output_gv_type,$notempty);
$table_column_count = count($datatable['cols'])-1;
$table_row_count = count($datatable['rows']);
switch ($output_format) {
	case 'csv': // output them to a csv file - this is required as part of gviz data source
		if ($debug) echo "outputting to a csv file:<br>";
		$path = '/tmp/';
		$file_name_base = "mfxml_to_gviz_novastar5_disprpts_xml_id_data_".date('YmdHis');
		$file_name = $file_name_base.".csv";
		$csvfile = $path.$file_name;
		if ($debug) echo "file name: $csvfile<br>";
		$handle = fopen($csvfile, 'w');
		if(!$handle) {
			echo "<br>";
			echo "System error creating CSV file: $csvfile <br>Contact Support.";
			echo "<br>";
		} else {
			$row = array();
			for ($c=0;$c<=$table_column_count;$c++) {
				$row[] = $datatable["cols"][$c]["label"];
			}
			fputcsv($handle,$row);
			for ($r=0;$r<$table_row_count;$r++) {
				$row = array();
				for ($c=0;$c<=$table_column_count;$c++) {
					$row[] = $datatable["rows"][$r]["c"][$c]["v"];
				}
				fputcsv($handle,$row);
			}
		}
		fclose($handle);
		if ($debug) echo "finished creating csv file<br>";
		if (file_exists($csvfile)) {
			if ($debug) echo "now downloading csv file<br>";
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($csvfile));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($csvfile));
			ob_clean();
			flush();
			readfile($csvfile);
		} else {
			echo "<br>";
			echo "System error reading CSV file: $csvfile <br>Contact Support.";
			echo "<br>";
		}
		break;
	case 'json': //return a json data stream tailored for a gviz object
		//   first the json data table
		$json_data_table = json_encode($datatable);
		//   some stuff that seemed necessary to make the json readaable by the gviz libraries
		$json_data_table = preg_replace('/\"new/','new',$json_data_table);
		$json_data_table = preg_replace('/\)\"/',')',$json_data_table);
		$json_data_table = preg_replace('/\"v\":/','v:',$json_data_table);
		$json_data_table = preg_replace('/\"c\":/','c:',$json_data_table);
		$json_data_table = preg_replace('/\"cols\":/','cols:',$json_data_table);
		$json_data_table = preg_replace('/\"rows\":/','rows:',$json_data_table);
		$json_data_table = preg_replace('/{\"id\":/','{id:',$json_data_table);
		$json_data_table = preg_replace('/,\"label\":/',',label:',$json_data_table);
		$json_data_table = preg_replace('/,\"type\":/',',type:',$json_data_table);
		// and echo the results
		echo $tqx['responseHandler']."({version:'".$tqx['version']."',reqId:'".$tqx['reqId']."',status:'ok',table:$json_data_table});";
		//echo $tqx['responseHandler']."({\"version\":\"".$tqx['version']."\",\"reqId\":\"".$tqx['reqId']."\",\"status\":\"ok\",\"table\":$json_data_table});";
		//write this to a temp file for debugging
		if ($silent_debug) {
			fwrite($silent_debug_handle,$tqx['responseHandler']."({version:'".$tqx['version']."',reqId:'".$tqx['reqId']."',status:'ok',table:$json_data_table});");
		}
		break;
	case 'html_table_2d': // output a simple html table, rows and columns, i.e. 2 dimensional
		echo "<table cellspacing=\"0\" border=\"2\">\n";
		echo "<tr>";
		for ($c=0;$c<=$table_column_count;$c++) {
			echo "<td>";
			echo $datatable["cols"][$c]["label"];
			echo "</td>";
		}
		echo "</tr>\n";
		for ($r=0;$r<$table_row_count;$r++) {
			echo "<tr>";
			for ($c=0;$c<=$table_column_count;$c++) {
				echo "<td>";
				echo $datatable["rows"][$r]["c"][$c]["v"];
				echo "</td>";
			}
			echo "</tr>\n";
		}
		echo "</table>\n";
		break;
	case 'html_table_raw': // dump the data array into a html table supporting more than 2 dimensions
	default:
		html_show_array($datatable);
}
if ($silent_debug) {
	fclose($silent_debug_handle);
}
/*
 * other functions I need
 */
function output_array($array){
    foreach($array as $key => $val){
        echo "    $key = ".$val."<br>";
    }
}
function getargs ($key,$def) {
        if(isset($_GET[$key])){
                if(empty($_GET[$key])) {
                        $output = $def;
                } else {
                        $output = $_GET[$key];
                }
        } else {
                $output = $def; 
        }
        return $output;
}
function do_offset($level){
    $offset = "";             // offset for subarry 
    for ($i=1; $i<$level;$i++){
    $offset = $offset . "<td></td>";
    }
    return $offset;
}

function show_array($array, $level, $sub){
    if (is_array($array) == 1){          // check if input is an array
       foreach($array as $key_val => $value) {
           $offset = "";
           if (is_array($value) == 1){   // array is multidimensional
           echo "<tr>";
           $offset = do_offset($level);
           echo $offset . "<td>" . $key_val . "</td>";
           show_array($value, $level+1, 1);
           }
           else{                        // (sub)array is not multidim
           if ($sub != 1){          // first entry for subarray
               echo "<tr nosub>";
               $offset = do_offset($level);
           }
           $sub = 0;
           echo $offset . "<td main ".$sub." width=\"120\">" . $key_val . 
               "</td><td width=\"120\">" . $value . "</td>"; 
           echo "</tr>\n";
           }
       } //foreach $array
    }  
    else{ // argument $array is not an array
        return;
    }
}

function html_show_array($array){
  echo "<table cellspacing=\"0\" border=\"2\">\n";
  show_array($array, 1, 0);
  echo "</table>\n";
}

function strtobool($str) {
	switch (strtolower($str)) {
		case "false":
		case "no":
		case "off":
		case "0":
			return false;
			break;
		default:
			return true;
	}
}
?>
