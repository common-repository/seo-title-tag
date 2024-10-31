
<?php
$path = dirname(__FILE__)."/uploads/";

	$valid_formats = array("csv");
	if(isset($_POST) and $_SERVER['REQUEST_METHOD'] == "POST")
		{
			$name = $_FILES['photoimg']['name'];
			$size = $_FILES['photoimg']['size'];
			
			if(strlen($name))
				{
					list($txt, $ext) = explode(".", $name);
					if(in_array($ext,$valid_formats))
					{
					if($size<(10240000000000*102400000000))
						{
							$actual_image_name = time().substr(str_replace(" ", "_", $txt), 5).".".$ext;
							$tmp = $_FILES['photoimg']['tmp_name'];
							if(move_uploaded_file($tmp, $path.$actual_image_name))
								{
								//mysql_query("UPDATE users SET profile_image='$actual_image_name' WHERE uid='$session_id'");
									echo '<b>Uploaded File:</b>&nbsp;&nbsp;'.$actual_image_name ;
									if($actual_image_name){
											$file_name = $actual_image_name;
											$table_name = 'wp_postmeta';
											$column_string = 'post_id,meta_key,meta_value';
											$start_row = '2';

											function csv_to_db_get_abs_path_from_src_file($src_file)
{

	

	if(preg_match("/http/",$src_file))
	{
		$path = parse_url($src_file, PHP_URL_PATH);
		$abs_path = $_SERVER['DOCUMENT_ROOT'].$path;
		$abs_path = realpath($abs_path);
		if(empty($abs_path)){
			$wpurl = get_bloginfo('wpurl');
			$abs_path = str_replace($wpurl,ABSPATH,$src_file);
			$abs_path = realpath($abs_path);			
		}
	}
	else
	{
		echo $relative_path = $src_file;
		$abs_path = realpath($relative_path);
	}
	return $abs_path;
}




											
function readAndDump($src_file,$table_name,$column_string="",$start_row=2)
{
	

	global $wpdb;
	$errorMsg = "";

	if(empty($src_file))
	{
		echo "empltyfilej el j";
            $errorMsg .= "<br />Input file is not specified";
            return $errorMsg;
    }
echo $path;

	echo "File path= ". $file_path = 'http://localhost/trello/wp-content/plugins/seo-title-tag/uploads/1347020450ng.csv';
	
	$file_handle = fopen($file_path, "r");
	if ($file_handle === FALSE) {
		// File could not be opened...
		$errorMsg .= 'Source file could not be opened!<br />';
		$errorMsg .= "Error on fopen('$file_path')";	// Catch any fopen() problems.
		return $errorMsg;
	}
		
	$row = 1;
	while (!feof($file_handle) ) 
	{
		$line_of_text = fgetcsv($file_handle, 1024);
		if ($row < $start_row)
		{
			// Skip until we hit the row that we want to read from.
			$row++;
			continue;
		}
		die($row);

		$columns = count($line_of_text);
		echo "<br />Column Count: ".$columns."<br />";
		
		if ($columns >1)
		{
			
	        	$query_vals = "'".$wpdb->escape($line_of_text[0])."'";
	        	for($c=1;$c<$columns;$c++)
	        	{
	        		$line_of_text[$c] = utf8_encode($line_of_text[$c]);
					$line_of_text[$c] = addslashes($line_of_text[$c]);
	                $query_vals .= ",'".$wpdb->escape($line_of_text[$c])."'";
	        	}
	        	echo "<br />Query Val: ".$query_vals."<br />";
	        	
	        	
          echo  $query = "INSERT INTO $table_name ($column_string) VALUES ($query_vals)";
	           die('fds');     
                        //echo "<br />Query String: ". $query;
                        $results = $wpdb->query($query);
                        if(empty($results))
                        {
                            $errorMsg .= "<br />Insert into the Database failed for the following Query:<br />";
                            $errorMsg .= $query;
                        }
	                //echo "<br />Query result".$results;
	    }
		$row++;
	}
	fclose($file_handle);
	
	return $errorMsg;
}




											
											echo $errorMsg = readAndDump($file_name,$table_name,$column_string,$start_row);
        
												echo '<div id="message" class="updated fade"><p><strong>';
												if(empty($errorMsg))
												{
													echo 'File content has been successfully imported into the database!';
												}
												else
												{
													echo "Error occured while trying to import!<br />";
													echo $errorMsg;
												}
												echo '</strong></p></div>';

										

										}

									
									//echo "<img src='uploads/".$actual_image_name."'  class='preview'>";
								}
							else
								echo "failed";
						}
						else
						echo "Image file size max 1 MB";					
						}
						else
						echo "Invalid file format..";	
				}
				
			else
				echo "Please Select CSV File..!";
				
			exit;
		}
?>