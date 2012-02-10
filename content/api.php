<?php

date_default_timezone_set('GMT');

$first_start = microtime(true);

$user_token = time() . rand(); //globalled in url_functions

$json = array();
$json['data'] = array();

if (!$conn = connect())
{
	$json['status'] = 'Error: DB Connect failure';
}

/* campus list */
else if (!isset($_GET['campus']))
{
	$query = 'SELECT Campuses.Campus_ID, Campus_Names.Campus_Name
	FROM
	Campuses
	INNER JOIN
	Campus_Names
	 ON Campuses.Campus_ID = Campus_Names.Campus_ID
	INNER JOIN
	Bookstores
	 ON Bookstores.Bookstore_ID = Campuses.Bookstore_ID
	WHERE Campus_Names.Is_Primary = "Y"
	ORDER BY Campus_Names.Campus_Name ASC';
	
	if (!$result = mysql_query($query))
	{
		$json['status'] = 'Error: SQL query for all campuses yielded error: '. mysql_error();
	}
	else if (mysql_num_rows($result) == 0)
	{
		$json['status'] = 'Error: SQL query for all campuses yielded no results';
	}
	else //all good, show them the campuses
	{
		$json['status'] = 'ok';
		while ($row = mysql_fetch_assoc($result))
		{
			$json['data'][] = array('id' => $row['Campus_ID'], 'name' => $row['Campus_Name']);
		}
	}
}

/* dropdowns.. */
else if (!isset($_GET['section']))
{
	$dd_array = array('campus', 'term', 'division', 'dept', 'course', 'section');
	$ids_array = array('Campus_ID', 'Term_ID', 'Division_ID', 'Department_ID', 'Course_ID', 'Class_ID'); //yes, i know I should've called this Section_ID..
	$names_array = array('Campus_Name', 'Term_Name', 'Division_Name', 'Department_Code', 'Course_Code', 'Class_Code');
	
	//determine which dropdown they sent based on what they sent..
	$sent_dd = 0;
	foreach ($dd_array as $dd)
	{
		if (isset($_GET[$dd]))
		{
			$sent_dd++;
		}
		else
		{
			break;
		}
	}
	$sent_dd--; //lazy hack
	
	$sent_name = $dd_array[$sent_dd];
	$sent_val = $_GET[$sent_name];
	
	if (!valid_ID($sent_val))
	{
		$json['status'] = 'Error: Invalid '. $sent_name . ' id = '. $sent_val;
	}
	else
	{	
		//begin by checking if they're in the DB already
		$start = microtime(true);
		$query = next_dropdowns_query(array($sent_name => $sent_val));
		$end = microtime(true);
		$json['first_query_time'] = round($end - $start, 3) * 1000;
		
		if (!$result = mysql_query($query))
		{
			$json['status'] = 'Error: SQL query based on '. $sent_name .'='. $sent_val .' yielded error: '. mysql_error();
		}
		else if (mysql_num_rows($result) == 0)
		{
			$json['status'] = 'Error: SQL query based on '. $sent_name .'='. $sent_val .' yielded no results';
		}
		else
		{
			$next_name = $names_array[$sent_dd + 1];
			$next_id = $ids_array[$sent_dd + 1];
		
			$row = mysql_fetch_assoc($result);
			
			if (!$row[$next_id])
			{
				$start = microtime(true);
				mysql_close($conn);
				
				update_classes_from_bookstore($row); //make bookstore requests to update db cache with what we want.. note that this attempts 3 times..
				if (!$conn = connect())
				{
					$json['status'] = mysql_error();
					die(json_encode($json));
				}
				$end = microtime(true);
				$json['scrape_and_cache_update_time'] = round($end - $start, 3) * 1000;
				
				//now we try to the query again..
				$start = microtime(true);
				if (!$result = mysql_query($query))
				{
					$json['status'] = 'Error: SQL query based on '. $sent_name .'='. $sent_val .' yielded error: '. mysql_error();
				}
				else if (mysql_num_rows($result) == 0)
				{
					$json['status'] = 'Error: SQL query based on '. $sent_name .'='. $sent_val .' yielded no results';
				}
				$end = microtime(true);
				$json['second_query_time'] = round($end - $start, 3) * 1000;
			}
			else
			{	
				mysql_data_seek($result, 0); //rewind
			}
			
			$row = mysql_fetch_assoc($result);
			if (!$row[$next_id])
			{
				$json['status'] = 'Warning: No entities returned from bookstore with '. $sent_name .'='. $sent_val . '. Could be scraping error, or there could genuinely be no corresponding entities.';
			}
			else //everything worked out; time to return ata
			{
				$json['status'] = 'ok';
				mysql_data_seek($result, 0); //rewind
				
				$data = array();
				
				while ($row = mysql_fetch_assoc($result))
				{
					$data[$row[$next_id]] = array('ty_id' => $row[$next_id], 'name' => $row[$next_name]);
					
					if ($row['Instructor'])
					{
						$data[$row[$next_id]]['instructor'] = $row['Instructor'];
					}
				}
				$json['data'] = array_values($data);
			}
		}
	}
}

/* class-books */
else
{
	$section_id = $_GET['section'];
	$campus_id = $_GET['campus'];
	
	if (!valid_ID($campus_id))
	{
		$json['status'] = 'Error: invalid campus id: '. $campus_id;
	}
	else if (!valid_ID($section_id))
	{
		$json['status'] = 'Error: invalid section id: '. $section_id;
	}
	else
	{
		$Books = array();
		
		$query = class_items_query(array($section_id), $campus_id);
		
		if (!$result = mysql_query($query))
		{
			$json['status'] = 'Error: Class-Items SQL query failed with '. mysql_error();
		}
		else if (mysql_num_rows($result) == 0)
		{
			$json['status'] = 'Error: Class-Items SQL query yielded no results';
		}
		else
		{	
			$row = mysql_fetch_assoc($result);
			if ($row['no_class_item']) //note that this is Class_Items_Cache.Class_ID, NOT Classes_Cache.Class_ID which must have been set from before (or else you'd get the 0 rows error above)
			{	
				mysql_close($conn);
				update_class_items_from_bookstore(array($row)); //this function updates the DB with the class-item data
				if (!$conn = connect())
				{
					$json['status'] = mysql_error();
					die(json_encode($json));
				}
				if (!$result = mysql_query($query))
				{
					$json['status'] = 'Error: Class-Items SQL query failed with '. mysql_error();
				}
				else if (mysql_num_rows($result) == 0)
				{
					$json['status'] = 'Error: Class-Items SQL query yielded no results';
				}
			}
			else
			{
				mysql_data_seek($result, 0); //rewind
			}
			
			if (mysql_num_rows($result)) //everything has worked..
			{
				$json['status'] = 'ok';
				$json['data']['items'] = array();
				
				$row = mysql_fetch_assoc($result);
				
				if ($row['Item_ID']) //it has at least one item
				{
					//So, loop the books into the array
					while ($row = mysql_fetch_assoc($result))
					{
						$Books = load_books_from_row($Books, $row);
					}
					
					//Load our ish into JSON and output...
					$data = array('items' => array());
			
					mysql_data_seek($result, 0);
					
					$previous_item = false; //keeps track of whether we've run into a new time
					
					while ($row = mysql_fetch_assoc($result))
					{
						if ($row['Item_ID'] != $previous_item)
						{
							$previous_item = $row['Item_ID'];
							
                            $item  = array('id' => $row['Item_ID'], 'necessity' => $row['Necessity'], 'comment' => $row['Comments'], 'isbn' => $row['ISBN'], 'title' => $row['Title'], 'edition' => $row['Edition'], 'authors' => $row['Authors'], 'year' => $row['Year'], 'publisher' => $row['Publisher']);
							
							$data['items'][] = $item;
						}
					}
					$json['data'] = $data;
				}
			}
		}
	}
}	

$json['total_time'] = round(microtime(true) - $first_start,3) * 1000;

echo json_encode($json);

?>
