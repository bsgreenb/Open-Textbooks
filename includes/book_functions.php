<?php

//book_functions.. These are functions that are use in the process of querying/cacheing book and class stuff.  bookstore_functions (functions for querying bookstores) are seperated to keep things clean.

function next_dropdowns_query($where_arr) //queries for the next dropdown based on the array it receives
{
	//TODO: this function could be further abstracted
	
	$key = key($where_arr);
	$val = current($where_arr);
	
	$dd_array = array('campus', 'term', 'division', 'dept', 'course'); //dropdowns ordinal array
	
	$firstKeyPosition = array_search($key, $dd_array);
	
	//we always select this campus stuf..
	$select = 'SELECT 
	Campuses.Campus_ID, Campuses.Location, 
	Campus_Names.Campus_Name, Campuses.Program_Value, Campuses.Campus_Value,
	Bookstores.Bookstore_ID, Bookstores.Storefront_URL, Bookstores.Fetch_URL, Bookstores.Store_Value, Bookstores.Follett_HEOA_Store_Value, Bookstores.Multiple_Campuses,
	Bookstore_Types.Bookstore_Type_Name, 
	Terms_Cache.Term_ID, Terms_Cache.Term_Name, Terms_Cache.Term_Value, Terms_Cache.Follett_HEOA_Term_Value'; 
	$from_and_where = 
	' FROM 
	Campuses
	INNER JOIN (Campus_Names) 
	 ON (Campuses.Campus_ID = Campus_Names.Campus_ID AND Campus_Names.Is_Primary = "Y")
	INNER JOIN (Bookstores, Bookstore_Types) 
	 ON (Bookstores.Bookstore_ID = Campuses.Bookstore_ID AND Bookstores.Bookstore_Type_ID = Bookstore_Types.Bookstore_Type_ID)
	';
	
	if ($firstKeyPosition) 
	{
		$select_dd_array = array_slice($dd_array, 0, $firstKeyPosition + 1); //array of this and previous dropdowns
		
		//we load in the previous dd's..
		if (in_array('campus', $select_dd_array))
		{
			$select .= ', Terms_Cache.Term_ID, Terms_Cache.Term_Value, Terms_Cache.Follett_HEOA_Term_Value, Terms_Cache.Term_Name';
		}
		if (in_array('term', $select_dd_array))
		{
			$select .= ', Divisions_Cache.Division_ID, Divisions_Cache.Division_Value, Divisions_Cache.Division_Name';
			$from_and_where .= ' INNER JOIN (Terms_Cache) ON (Campuses.Campus_ID = Terms_Cache.Campus_ID)';
		}
		if (in_array('division', $select_dd_array))
		{
			$select .= ', Departments_Cache.Department_ID, Departments_Cache.Department_Value, Departments_Cache.Department_Code';
			$from_and_where .= ' INNER JOIN (Divisions_Cache) ON (Terms_Cache.Term_ID = Divisions_Cache.Term_ID)';
		}
		if (in_array('dept', $select_dd_array))
		{
			$select .= ', Courses_Cache.Course_ID, Courses_Cache.Course_Value, Courses_Cache.Course_Code';
			$from_and_where .= 'INNER JOIN (Departments_Cache) ON (Departments_Cache.Division_ID = Divisions_Cache.Division_ID)';
		}
		if (in_array('course', $select_dd_array))
		{
			$select .= ', Classes_Cache.Class_ID, Classes_Cache.Class_Code, Classes_Cache.Instructor, Classes_Cache.Class_Value';
			$from_and_where .= ' INNER JOIN (Courses_Cache) ON (Courses_Cache.Department_ID = Departments_Cache.Department_ID)';
		}
	}

	$order_by = '';
	
	switch ($key)
	{
		case 'campus':
			$from_and_where .= ' LEFT JOIN (Terms_Cache) ON (Campuses.Campus_ID = Terms_Cache.Campus_ID AND Terms_Cache.Cache_TimeStamp BETWEEN NOW() - INTERVAL 1 WEEK AND NOW())
			WHERE Campuses.Enabled = "Y" AND Campuses.Campus_ID = '. $val;
			break;
		case 'term':
			$from_and_where .= ' LEFT JOIN (Divisions_Cache) ON (Divisions_Cache.Term_ID = Terms_Cache.Term_ID AND Divisions_Cache.Cache_TimeStamp BETWEEN NOW() - INTERVAL 1 WEEK AND NOW())
			WHERE Terms_Cache.Term_ID = '. $val;
			$order_by = ' ORDER BY Divisions_Cache.Division_Name ASC';
			break;
		case 'division':
			$from_and_where .= ' LEFT JOIN (Departments_Cache) ON (Departments_Cache.Division_ID = Divisions_Cache.Division_ID AND Departments_Cache.Cache_TimeStamp BETWEEN NOW() - INTERVAL 1 WEEK AND NOW())
			WHERE Divisions_Cache.Division_ID = '. $val;
			$order_by = ' ORDER BY Departments_Cache.Department_Code ASC';
			break;
		case 'dept':
			$from_and_where .= ' LEFT JOIN (Courses_Cache) ON (Courses_Cache.Department_ID = Departments_Cache.Department_ID AND Courses_Cache.Cache_TimeStamp BETWEEN NOW() - INTERVAL 1 WEEK AND NOW())
			WHERE Departments_Cache.Department_ID = '. $val;
			$order_by = ' ORDER BY ABS(Courses_Cache.Course_Code) ASC'; //sort numerically
			break;
		case 'course':
			$from_and_where .= ' LEFT JOIN (Classes_Cache) ON (Classes_Cache.Course_ID = Courses_Cache.Course_ID AND Classes_Cache.Cache_TimeStamp BETWEEN NOW() - INTERVAL 1 WEEK AND NOW())
			WHERE Courses_Cache.Course_ID = '. $val;
			$order_by = ' ORDER BY ABS(Classes_Cache.Class_Code) ASC';
			break;
	}
	
	return $select . $from_and_where . $order_by;
}

function load_books_from_row($Books, $row) //Updates Books with a row from the database.
{
	if ($row['ISBN'])
	{
		if (!isset($Books[$row['ISBN']]))
		{
			$Books[$row['ISBN']] = array();
		}
		if (isset($row['Item_ID']) && $row['Item_ID'])
		{
			$Books[$row['ISBN']]['Items_Data'] = load_items_from_array($row);
		}
	}
	return $Books;
}

function load_items_from_array($arr)
{	
	$items_arr = array();
	
	$fields_arr = array('Title', 'Item_ID', 'ISBN', 'Authors', 'Edition', 'Year', 'Publisher', 'Necessity', 'Comments', 'Bookstore_Price', 'New_Price', 'Used_Price', 'New_Rental_Price', 'Used_Rental_Price');

	foreach ($fields_arr as $field_name)
	{
		if (isset($arr[$field_name]) && $arr[$field_name])
		{
			$items_array[$field_name] = $arr[$field_name];
		}
	}
	return $items_array;
}

function class_items_query($Class_IDs) //takes array of Class_IDs
{
	return 'SELECT 
	Campuses.Campus_Value, Campuses.Program_Value,
	Bookstores.Storefront_URL, Bookstores.Fetch_URL, Bookstores.Store_Value, Bookstores.Follett_HEOA_Store_Value, Bookstores.Multiple_Campuses,
	Bookstore_Types.Bookstore_Type_Name, 
	Terms_Cache.Term_ID, Terms_Cache.Term_Value, Terms_Cache.Follett_HEOA_Term_Value, Terms_Cache.Term_Name,
	Divisions_Cache.Division_ID, Divisions_Cache.Division_Value, Divisions_Cache.Division_Name,
	Departments_Cache.Department_ID, Departments_Cache.Department_Value, Departments_Cache.Department_Code,
	Courses_Cache.Course_ID, Courses_Cache.Course_Value, Courses_Cache.Course_Code,
	Classes_Cache.Class_Value, Classes_Cache.Class_Code, Classes_Cache.Instructor, Classes_Cache.Class_ID,
	Class_Items_Cache.Class_Items_Cache_ID, Class_Items_Cache.Bookstore_Price, Class_Items_Cache.New_Price, Class_Items_Cache.Used_Price, Class_Items_Cache.New_Rental_Price, Class_Items_Cache.Used_Rental_Price, Class_Items_Cache.Necessity, Class_Items_Cache.Comments,  Class_Items_Cache.Class_ID IS NULL AS no_class_item, Class_Items_Cache.Item_ID IS NULL as noitems,
	Items.Item_ID, Items.ISBN, Items.Title, Items.Edition, Items.Authors, Items.Year, Items.Publisher 
	FROM Campuses
	INNER JOIN Bookstores 
	 ON (Bookstores.Bookstore_ID = Campuses.Bookstore_ID) 
	  
	INNER JOIN Bookstore_Types
	 ON (Bookstores.Bookstore_Type_ID = Bookstore_Types.Bookstore_Type_ID)

	INNER JOIN Terms_Cache 
	 ON (Terms_Cache.Campus_ID = Campuses.Campus_ID)

	INNER JOIN Divisions_Cache
	 ON (Divisions_Cache.Term_ID = Terms_Cache.Term_ID)
	 
	INNER JOIN Departments_Cache
	 ON (Departments_Cache.Division_ID = Divisions_Cache.Division_ID)

	INNER JOIN Courses_Cache 
	 ON (Courses_Cache.Department_ID = Departments_Cache.Department_ID)

	INNER JOIN Classes_Cache
	 ON (Classes_Cache.Course_ID = Courses_Cache.Course_ID)

		LEFT JOIN 
		 Class_Items_Cache
		 ON (Class_Items_Cache.Class_ID = Classes_Cache.Class_ID AND 
		 Class_Items_Cache.Cache_TimeStamp BETWEEN NOW() - INTERVAL 1 WEEK AND NOW())
		  
		LEFT JOIN Items
		 ON (Class_Items_Cache.Item_ID = Items.Item_ID)

	WHERE Classes_Cache.Class_ID IN ('. implode(',', $Class_IDs) . ')

	GROUP BY Classes_Cache.Class_ID, Items.Item_ID
	ORDER BY noitems ASC, Classes_Cache.Class_ID, Class_Items_Cache.Class_Items_Cache_ID ASC, Items.Item_ID DESC';
}	

function update_items_db($Items)
{
	if (!$conn = connect())
	{
		trigger_error('Problem with DB connect', E_USER_WARNING);
		//it should stop here
		return false;
	}
	else if ($Items)
	{
		$items_query = '';
		foreach ($Items as $item)
		{			
			if ($item['Title']) //Only stuff with titles goes here
			{
				$ISBN = 'NULL';
				$Edition = "''";
				$Authors = "''";
				$Year = '0000';
				$Publisher = "''";
				
				$Title = "'". mysql_real_escape_string($item['Title']) . "'";
				
				if (isset($item['ISBN']) && valid_ISBN13($item['ISBN']))
				{
					$ISBN = $item['ISBN'];
				}
				
				if (isset($item['Edition']))
				{
					$Edition =  "'". mysql_real_escape_string($item['Edition']) . "'";
				}
				if (isset($item['Authors']))
				{
					$Authors = "'". mysql_real_escape_string($item['Authors']) . "'";
				}
				if (isset($item['Year']))
				{
					$Year = "'". mysql_real_escape_string($item['Year']) . "'";
				}
				if (isset($item['Publisher']))
				{
					$Publisher = "'". mysql_real_escape_string($item['Publisher']) . "'";
				}
				
				$items_query .= '(' . $ISBN . ',' . $Title . ', ' . $Edition . ', ' . $Authors . ', ' . $Year . ', ' . $Publisher .'),';
			}
		}
		if ($items_query)
		{
			$items_query = 'INSERT INTO Items (ISBN, Title, Edition, Authors, Year, Publisher) VALUES '. substr(($items_query), 0, -1) .' ON DUPLICATE KEY UPDATE Title=VALUES(Title), Edition=VALUES(Edition), Authors=VALUES(Authors), Year=VALUES(Year), Publisher=VALUES(Publisher)'; 
			
			if (!mysql_query($items_query))
			{
				trigger_error(mysql_error() .' with items query: '. $items_query);
			}
			else
			{
				return true;
			}
		}
		else
		{
			return true;
		}
	}
}

?>
