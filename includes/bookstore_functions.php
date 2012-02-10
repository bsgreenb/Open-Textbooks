<?php

//Bookstore functions: functions for querying bookstores.

function format_item($item) //returns an item result with the proper formatting
{
	$format_arr = array('Necessity', 'Title', 'Edition', 'Authors', 'Publisher');
	
	foreach ($format_arr as $name)
	{
		if (isset($item[$name]) && $item[$name])
		{
			$item[$name] = ucwords(strtolower(trim($item[$name], " \t\n\r\0\x0B\xA0")));
		}
	}
	if (isset($item['Year']) && $item['Year'])
	{
		$item['Year'] = date('Y', strtotime(trim($item['Year'])));
	}
	if (isset($item['ISBN']) && $item['ISBN'])
	{
		$item['ISBN'] = get_ISBN13(str_replace('&nbsp;', '', trim($item['ISBN'])));
	}
	if (isset($item['Bookstore_Price']) && $item['Bookstore_Price'])
	{
		$item['Bookstore_Price'] = priceFormat($item['Bookstore_Price']);
	}
	
	if (isset($item['New_Price']) && $item['New_Price'])
	{
		$item['New_Price'] = priceFormat($item['New_Price']);
	}
	if (isset($item['Used_Price']) && $item['Used_Price'])
	{
		$item['Used_Price'] = priceFormat($item['Used_Price']);
	}
	if (isset($item['New_Rental_Price']) && $item['New_Rental_Price'])
	{
		$item['New_Rental_Price'] = priceFormat($item['New_Rental_Price']);
	}
	if (isset($item['Used_Rental_Price']) && $item['Used_Rental_Price'])
	{
		$item['Used_Rental_Price'] = priceFormat($item['Used_Rental_Price']);
	}

	return $item;
}

function format_dropdown($dropdown) //takes a dropdown array include name and value.  also instructor sometimes in the case of class.
{
	//ucwords term_name and class_code
	$title_caps = array('Term_Name', 'Class_Code');

	foreach ($dropdown as $name => $val)
	{
		if (is_array($val)) //so we can get sections, or really anything, recursively.
		{
			$dropdown[$name] = format_dropdown($val);
		}
		else
		{
			$dropdown[$name] = trim($val); //trim everything
			
			if (in_array($name, $title_caps))
			{
				$dropown[$name] = ucwords($val); 
			}
		}
	}
	
	return $dropdown;
}

function get_classes_and_items_from_neebo($valuesArr)
{
	if (isset($valuesArr['Term_ID']) && !isset($valuesArr['Division_ID']))
	{
		return array(); //because Neebo can't have divisions.
	}

	//store fetch_url and neebo extension. assume we it as store_value
	$doc = new DOMDocument();
	$returnArray = array();	
	$base = $valuesArr['Fetch_URL'];
	
	$response = curl_request(array(CURLOPT_URL => $valuesArr['Storefront_URL'])); //initialize the session
	
	if (!isset($valuesArr['Term_ID']))
	{
		@$doc->loadHTML($response); //because their HTML is imperfect
		$finder = new DomXPath($doc);
		$select= $finder->query('//select[@id="term"]');
		
		if ($select->length != 0)
		{
			$select = $select->item(0);
			
			for ($i = 0; $i < $select->childNodes->length; $i++)
			{
				$option = $select->childNodes->item($i);
				$returnArray[] = array('Term_Value' => $option->getAttribute('value'), 'Term_Name' => $option->nodeValue);
			}
		}
		else
		{
			throw new Exception('Missing term select tag response with values '. print_r($valuesArr, true));
		}
	
	}
	else if (!isset($valuesArr['Department_ID'])) //get depts
	{
		$url = $base . 'Course/GetDepartments?termId=' . urlencode($valuesArr['Term_Value']);
		
		$response = curl_request(array(CURLOPT_URL => $url)); //overrides the response
		
		
		@$doc->loadHTML($response); //imperfect html
		$finder = new DomXPath($doc);
		$as = $finder->query('//ul[@class="dept-list filtered"]//a');
		
		if ($as->length != 0)
		{
			for ($i = 0; $i < $as->length; $i++) //loop through lis
			{
				$a = $as->item($i);
				
				$dept_td = $finder->query('..//td', $a)->item(0);
				
				$returnArray[] = array('Department_Code' => $dept_td->nodeValue, 'Department_Value' => $a->getAttribute('id'));
			}
		}
		else
		{
			throw new Exception('Missing department ul response with values '. print_r($valuesArr, true));
		}
	}
	else if (!isset($valuesArr['Course_ID'])) //get courses AND sections.  this is different on neebo than on other systems.
	{
		$url = $base . 'Course/GetCourses?departmentId=' .urlencode($valuesArr['Department_Value']);
		
		$response = curl_request(array(CURLOPT_URL => $url));
		$doc->loadHTML($response);
		$finder = new DomXPath($doc);
		$lis = $finder->query('//ul[@class="course-list filtered"]/li');
		
		if ($lis->length != 0)
		{
			for ($i = 0; $i < $lis->length; $i++)
			{
				$li = $lis->item($i);
				$course_code = $li->childNodes->item(1)->nodeValue;
				$course_value = null;
				$section_arr = array();
				
				$section_as = $finder->query('.//ul/li/a', $li);
				if ($section_as->length)
				{
					for ($j = 0; $j < $section_as->length; $j++)
					{
						$section_a = $section_as->item($j);
						$section_value = $section_a->getAttribute('id');
						$section_code = $section_a->nodeValue;
						
						$section = array('Class_Value' => $section_value, 'Class_Code' => $section_code);
						
						$section_arr[] = $section;
					}
				}
				
				$returnArray[] = array('Course_Code' => $course_code, 'Course_Value' => $course_value, 'Classes' => $section_arr);
			}
		}
		else
		{
			throw new Exception('Missing course ul response with values '. print_r($valuesArr, true));
		}	
	}	
	else if (!isset($valuesArr['Class_ID'])) //sections should always be returned as part of the courses stuff.
	{
		$returnArray = array(); 
	}
	else //get textbooks..
	{	
		$url = $base . 'CourseMaterials/AddSection?sectionId=' .urlencode($valuesArr['Class_Value']);
		
		$result_url = $base . 'Course/Results';
		
		curl_request(array(CURLOPT_URL => $url)); //step 1, add section
		
		$response = curl_request(array(CURLOPT_URL => $result_url)); //step 2, get the response
	
		$returnArray['Class_ID'] = $valuesArr['Class_ID'];
		$returnArray['items'] = array();
		
		@$doc->loadHTML($response); //imperfect HTML
		$finder = new DomXPath($doc);
		
		$tds = $finder->query('//td[@class="course-materials-description"]');
		
		$items = array();
		
		$i = 0;
		
		if ($tds->length != 0)
		{
			foreach ($tds as $td)
			{
				
				$items[$i]['Title'] = $finder->query('.//p[@class="title"]', $td)->item(0)->nodeValue;
				
				$info = innerHTML($finder->query('.//p[@class="info"]', $td)->item(0)); //have to use innerHTML to keep the tags for parsing
				
				$info = explode('<br>', $info);
				
				//GET Authors, Edition, ISBN
				
				foreach ($info as $subject)
				{
					$subject = explode(':', $subject);
					
					$key = str_replace('<strong>', '', $subject[0]);

					$value = str_replace('</strong>', '', $subject[1]);
						
					if ($key == 'Edition') //we need to get Edition and Publisher here..
					{
						
						$value = explode(',', $value);
						$items[$i]['Edition'] = $value[0];
						$items[$i]['Year'] = preg_replace('([^0-9]*)', '', $value[1]); 
					}
					else
					{
						if (trim($key) == 'Author')
						{
							$key = 'Authors';
						}
						
						$items[$i][$key] = $value;
					}
				}
				
				$items[$i]['Necessity'] = $finder->query('.//a[@rel="/Help/RequirementHelp"]//strong', $td)->item(0)->nodeValue;
				
				//Get the max price
				$prices = $finder->query('.//td[@class="course-product-price"]/label');
				$priceList = array();
				foreach ($prices as $price)
				{
					$priceList[] = preg_replace('([^0-9\.]*)', '', $price->nodeValue); //remove numbers so we can do max
				}

				$items[$i]['Bookstore_Price'] = max($priceList);
				
				$i++;
				
			}
			$returnArray['items'] = $items;
		}	
	}	
	
	return $returnArray;		
}

function get_classes_and_items_from_follett($valuesArr)
{	
	//We hardcode Program_Value and Campus_Value in the Campuses table because they are campus-specific and pretty much static.  Division_Value varies and is sometimes like a higher level department, so we give it it's own table..
	
	$returnArray = array();
	$url = $valuesArr['Fetch_URL'] . 'webapp/wcs/stores/servlet/';
	$referer = $valuesArr['Storefront_URL'];
	
	//We need to set these to empty or spaces appropraitely, because Follet expects them even when they aren't existent. 
	if (!$valuesArr['Campus_Value'])
	{
		//Do all Follett schools have Campus_Values?  Answer: No, some do not.  And in fact, when they don't have it it's not even sent as a parameter.  To simplify things we just set it as an empty string for those cases..
		$valuesArr['Campus_Value'] = '';
	}
	if (isset($valuesArr['Division_ID']) && !$valuesArr['Division_Value'])
	{
		//We set it to " " (a space) when it's not there, cus it needs to be sent.
		$valuesArr['Division_Value'] = ' ';
	}
	
	//Note: Follett schools *always* have a Program_Value.  When they don't have a real world one, the store adds one with the display name "ALL".	
	
	//Initial request to start the session with follett if we haven't already.. Follett won't let you do anything w/o one... 
	if (!isset($valuesArr['Class_ID'])) //note that we only need to do this for the dropdowns, not for booklook, which is on a seperate HEOA page which doesn't require a session.
	{
		$options = array(CURLOPT_URL => $valuesArr['Storefront_URL'], CURLOPT_HTTPPROXYTUNNEL => true, CURLOPT_PROXY => PROXY_1, CURLOPT_PROXYUSERPWD => PROXY_1_AUTH);
		
		$response = curl_request($options); //query the main page to pick up the cookies
		
		if (!$response)
		{
			throw new Exception('Unable to fetch Follett Storefront for session with values '. print_r($valuesArr, true));
		}
	}
		
	//Prepare for the request and its handling depending on whats up next..
	if (!isset($valuesArr['Term_ID']))
	{
		$url .= 'LocateCourseMaterialsServlet?demoKey=d&programId='. urlencode($valuesArr['Program_Value']) . '&requestType=TERMS&storeId=' . urlencode($valuesArr['Store_Value']);
		
		$response_name = 'TERMS';
		$display_name = 'Term_Name';
		$value_name = 'Term_Value';
	}
	else if (!isset($valuesArr['Division_ID']))
	{							
	
		//The divisions request is always sent, even when there aren't any.  	//http://www.bkstr.com/webapp/wcs/stores/servlet/LocateCourseMaterialsServlet?requestType=DIVISIONS&storeId=10415&demoKey=d&programId=727&termId=100019766&_=
		
		$url .= 'LocateCourseMaterialsServlet?requestType=DIVISIONS&storeId='. urlencode($valuesArr['Store_Value']) . '&campusId='. urlencode($valuesArr['Campus_Value']) .'&demoKey=d&programId='. urlencode($valuesArr['Program_Value']) .'&termId='. $valuesArr['Term_Value'];
		
		$response_name = 'DIVISIONS';
		$display_name = 'Division_Name';
		$value_name = 'Division_Value';
	}
	else if (!isset($valuesArr['Department_ID']))
	{
		$url .= 'LocateCourseMaterialsServlet?demoKey=d&divisionName='. urlencode($valuesArr['Division_Value']) .'&campusId='. urlencode($valuesArr['Campus_Value']) .'&programId='. urlencode($valuesArr['Program_Value']) .'&requestType=DEPARTMENTS&storeId='. urlencode($valuesArr['Store_Value']) .'&termId='. urlencode($valuesArr['Term_Value']);
		
		$response_name = 'DEPARTMENTS';
		$display_name = 'Department_Code';
		$value_name = 'Department_Value';
	}
	else if (!isset($valuesArr['Course_ID']))
	{	
		$url .= 'LocateCourseMaterialsServlet?demoKey=d&divisionName='. urlencode($valuesArr['Division_Value']).'&campusId='. urlencode($valuesArr['Campus_Value']) .'&programId='. urlencode($valuesArr['Program_Value']) .'&requestType=COURSES&storeId='. urlencode($valuesArr['Store_Value']) .'&termId='. urlencode($valuesArr['Term_Value']) .'&departmentName='. urlencode($valuesArr['Department_Code']). '&_=';
		
		$response_name = 'COURSES';
		$display_name = 'Course_Code';
		$value_name = 'Course_Value';
	}
	else if (!isset($valuesArr['Class_ID']))
	{
		$url .= 'LocateCourseMaterialsServlet?demoKey=d&divisionName='. urlencode($valuesArr['Division_Value']) .'&programId='. urlencode($valuesArr['Program_Value']) .'&requestType=SECTIONS&storeId='. urlencode($valuesArr['Store_Value']) .'&termId='. urlencode($valuesArr['Term_Value']) .'&departmentName='. urlencode($valuesArr['Department_Code']). '&courseName='. urlencode($valuesArr['Course_Code']) .'&_=';
		
		$response_name = 'SECTIONS';
		$display_name = 'Class_Code';
		$value_name = 'Class_Value';
	}
	else
	{	
		//class books query.. it's special.
		$url .= 'booklookServlet?bookstore_id-1='. urlencode($valuesArr['Follett_HEOA_Store_Value']) .'&term_id-1='. urlencode($valuesArr['Follett_HEOA_Term_Value']) .'&div-1='. urlencode($valuesArr['Division_Value']) . '&dept-1='. urlencode($valuesArr['Department_Value']) . '&course-1='. urlencode($valuesArr['Course_Value']) .'&section-1='. urlencode($valuesArr['Class_Value']);
	}
	
	//make the request and reutrn the response
	$response = curl_request(array(CURLOPT_URL => $url, CURLOPT_REFERER => $referer, CURLOPT_HTTPPROXYTUNNEL => true, CURLOPT_PROXY => PROXY_1, CURLOPT_PROXYUSERPWD => PROXY_1_AUTH));
	
	if ($response)
	{
		$doc = new DOMDocument();
		@$doc->loadHTML($response); //because their HTML is imperfect
	
		if (!isset($valuesArr['Class_ID'])) //dropdown response..
		{	
			//example $response: <script>parent.doneLoaded('{"meta":[{"request":"TERMS","skip":"false","campusActive":"true","progActive":"true","termActive":"true","size":"3"}],"data":[{"FALL 2011":"100019766","WINTER 2011-2012":"100021395","SPRING 2012":"100021394"}]}')</script>
			
			$script = $doc->getElementsByTagName('script');
			
			if ($script->length != 0)
			{
				$script = $script->item(0)->nodeValue;
						
				preg_match("/'[^']+'/", $script, $matches);
				
				$json = substr($matches[0], 1, -1);
				
				$json = json_decode($json, true);
				
				if (isset($json['meta'][0]['request']) && $json['meta'][0]['request'] == $response_name)
				{
					foreach ($json['data'][0] as $key => $value)
					{	
						$returnArray[] = array($display_name => $key, $value_name => $value);
					}
				}
				else
				{
					throw new Exception('Request for URL: '. $url . ' gave inappropriate response: '. $script .' with values '. print_r($valuesArr, true));
				}
			}
			else
			{
				throw new Exception('Missing script response with values '. print_r($valuesArr, true));
			}
		}
		
		else //class-book response from Follett's booklook system
		{	
			$finder = new DomXPath($doc);
			
			$error_tag = $finder->query('//*[@class="error"]'); //sometimes errors are in an <h2>, sometimes in a <p>, it depends on the error.
			
			//when there are no results (but the request is valid), there is also a class="error" tag, but its directly inside //div[@class="paddingLeft1em results"]/ .  So this will return $returnArray('class_id' => whatever, 'items' => array()) as its supposed to. 
			
			if ($error_tag->length != 0)
			{
				$error = $error_tag->item(0)->nodeValue;
					
				if (!stripos($error, 'to be determined') && !stripos($error, 'no course materials required') && !stripos($error, 'no information received')) //these are the two exceptions where there genuinely are 0 results.
				{		
					throw new Exception('Error: '. $error .' on Follett booklook with values '. print_r($valuesArr, true)); //we report the specific error that Follett's booklook gives us.
				}
			}
			$results = $finder->query('//div[@class="paddingLeft1em results"]/*');
			
			$items = array();
			$i = 0; //counter for $items
			foreach ($results as $resultNode)
			{
				if ($resultNode->nodeName == 'h2')
				{
					$necessity = $resultNode->nodeValue;
				}
				else if ($resultNode->nodeName == 'h3' && $resultNode->getAttribute('class') == 'paddingChoice')
				{
					$necessity = 'Choose One';
				}
				else if ($resultNode->nodeName == 'div' && $resultNode->getAttribute('class') == 'paddingLeft5em')
				{
					$resultLIs = $finder->query('.//ul/li', $resultNode);
					foreach ($resultLIs as $resultLI)
					{
						$span = $resultLI->getElementsByTagName('span');
						if ($span->length)
						{
							$resultLI->removeChild($span->item(0));
						}
						$result = explode(':', $resultLI->nodeValue);
						$items[$i]['Necessity'] = $necessity;
						switch (strtolower(trim($result[0])))
						{
							case 'title':
								$items[$i]['Title'] = $result[1];
								break;
							case 'author':
								$items[$i]['Authors'] = $result[1];
								break;
							case 'edition':
								$items[$i]['Edition'] = $result[1];
								break;
							case 'copyright year':
								$items[$i]['Year'] = $result[1];
								break;
							case 'publisher':
								$items[$i]['Publisher'] = $result[1];
								break;
							case 'isbn':
								$items[$i]['ISBN'] = $result[1];
								break;
							case 'new':
								$items[$i]['Bookstore_Price'] = $result[1];
								$items[$i]['New_Price'] = $result[1];
								break;
							case 'used':
								$items[$i]['Used_Price'] = $result[1];
								if (!isset($items[$i]['Bookstore_Price']))
								{
									$items[$i]['Bookstore_Price'] = $result[1];
								}
								break;
						}
					}
					$i++;
				}
			}
		
			$returnArray['Class_ID'] = $valuesArr['Class_ID'];
			$returnArray['items'] = $items; //trim them all
		}
		
		return $returnArray;
	}
	else
	{
		throw new Exception("No response with values ". print_r($valuesArr, true));
	}
}


function get_classes_and_items_from_epos($valuesArr)
{
	if (isset($valuesArr['Term_ID']) && !isset($valuesArr['Division_ID']))
	{
		//no divisions for ePOS
		return array();
	}
	
	$doc = new DOMDocument();
	$returnArray = array();
	
	$url = $valuesArr['Fetch_URL'] . '?';
	
	if ($valuesArr['Store_Value'])
	{
		$url .= 'store='. $valuesArr['Store_Value'] .'&';
	}
	
	$user_agent = urlencode(random_user_agent());
	
	if (!isset($valuesArr['Term_ID']))
	{
		$url .=  'form=shared3%2ftextbooks%2fno_jscript%2fmain.html&agent='. $user_agent;
		
		$response_name = 'term';
		$display_name = 'Term_Name';
		$value_name = 'Term_Value';
	}
	else if (!isset($valuesArr['Department_ID']))
	{
		$url .= 'wpd=1&step=2&listtype=begin&form=shared3%2Ftextbooks%2Fno_jscript%2Fmain.html&agent='. $user_agent .'&TERM='. urlencode($valuesArr['Term_Value']) .'&Go=Go';
		
		$response_name = 'department';
		$display_name = 'Department_Code';
		$value_name = 'Department_Value';
	}
	else if (!isset($valuesArr['Course_ID']))
	{
		$url .= 'wpd=1&step=3&listtype=begin&form=shared3%2Ftextbooks%2Fno_jscript%2Fmain.html&agent='. $user_agent .'&TERM='. urlencode($valuesArr['Term_Value']) .'&department='. urlencode($valuesArr['Department_Value']) .'&Go=Go';
		
		$response_name = 'course';
		$display_name = 'Course_Code';
		$value_name = 'Course_Value';
	}
	else if (!isset($valuesArr['Class_ID']))
	{
		$url .= 'wpd=1&step=4&listtype=begin&form=shared3%2Ftextbooks%2Fno_jscript%2Fmain.html&agent='. $user_agent .'&TERM='. urlencode($valuesArr['Term_Value']) .'&department='. urlencode($valuesArr['Department_Value']) .'&course='. urlencode($valuesArr['Course_Value']) .'&Go=Go';	
		
		$response_name = 'section';
		$display_name = 'Class_Code';
		$value_name = 'Class_Value';
	}
	else //they sent a class
	{	
		$url .='wpd=1&step=5&listtype=begin&form=shared3%2Ftextbooks%2Fno_jscript%2Fmain.html&agent='. $user_agent .'&TERM='. urlencode($valuesArr['Term_Value']) .'&department='. urlencode($valuesArr['Department_Value']) .'&course='. urlencode($valuesArr['Course_Value']) .'&section='. urlencode($valuesArr['Class_Value']) .'&Go=Go';	
	}
	
	$response = curl_request(array(CURLOPT_URL => $url));
	
	if ($response)
	{
		@$doc->loadHTML($response); //because their HTML is imperfect
		$finder = new DomXPath($doc);
		
		if (!isset($valuesArr['Class_Value'])) //dropdown response..
		{
			$select = $finder->query('//select[@id="'. $response_name .'"]');
			if ($select->length != 0)
			{
				$select = $select->item(0);
				
				for ($i = 1; $i < $select->childNodes->length; $i++) //we start at $i = 1 to skip the "- Select ... -"
				{
					$option = $select->childNodes->item($i);
					//how to set this one up?
					
					if (isset($valuesArr['Course_Value'])) //getting classes, so we parse instructors out
					{
						$split = explode('-', $option->nodeValue, 2); //split instructot
						{
							if (isset($split[1]))
							{
								$returnArray[] = array($value_name => $option->getAttribute('value'), $display_name => $split[0], 'Instructor' => $split[1]);
								
							}
							else
							{
								$returnArray[] = array($value_name => $option->getAttribute('value'), $display_name => $option->nodeValue);
							}
						}
						//split to get the instructor if possible.
					
					}
					else
					{
						$returnArray[] = array($value_name => $option->getAttribute('value'), $display_name => $option->nodeValue);
					}
				}
			}
			else
			{	
				throw new Exception('Missing select tag response with values '. print_r($valuesArr, true));
			}	
		}
		else //class-items response..
		{
			$returnArray['items'] = array();
			$cb_divs = $finder->query('//div[@id="info"]');
			
			foreach ($cb_divs as $cb_div)
			{
				$item = array();
				
				//Get the Title then remove it from the cb_div
				
				$title_span = $finder->query('.//span[@class="booktitle"]', $cb_div)->item(0);
				$item['Title'] = $title_span->getElementsByTagName('b')->item(0)->nodeValue;
				$cb_div->removeChild($title_span);
				
				//Get the Necessity then remove it from the cb_div
				$necc_span = $finder->query('.//b/span[@class="bookstatus"]', $cb_div)->item(0);
				$item['Necessity'] = $necc_span->nodeValue;
				$cb_div->removeChild($necc_span->parentNode);
				
				//remove the <p> that messes up with parsing if it's there
				$p = $cb_div->getElementsByTagName('p');
				if ($p->length)
				{
					foreach($p as $a_p)
					{
						$cb_div->removeChild($a_p);
					}
				}
				
				//get the inner html of the cb_div with that stuff removed
				$cb_div_html = innerHTML($cb_div);

				//Loop through that HTML and get the other fields\
				$fields = explode('<br>', $cb_div_html);
				foreach ($fields as $field)
				{
					if ($field)
					{
						$split_field = explode(':', $field , 2); //limit it to 2 elements so we only split on the first colon
				
						if (trim($split_field[0]))
						{
							switch (trim($split_field[0]))
							{
								case 'ISBN':
									$item['ISBN'] = $split_field[1];
									break;
								case 'Edition':
									$item['Edition'] = $split_field[1];
									break;
								case 'Copyright Year':
									$item['Year'] = $split_field[1];
									break;
								case 'Publisher':
									$item['Publisher'] = $split_field[1];
									break;
								default:
									$item['Authors'] = $split_field[0];
									break;
							}
						}
					}
				}
				
				$returnArray['items'][] = $item;
			}
			$returnArray['Class_ID'] = $valuesArr['Class_ID'];
		}
		
		return $returnArray; 
	}
	else
	{
		throw new Exception("No response with values ". print_r($valuesArr, true));
	}
}

function get_classes_and_items_from_mbs($valuesArr)
{
	if (isset($valuesArr['Term_ID']) && !isset($valuesArr['Division_ID']))
	{
		//because there are no divisions on MBS
		return array(); 
	}
	$doc = new DOMDocument();
	
	$useragent = 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16'; //IPhone useragent, because we fetch from the easy to scrape mobile version.
	
	$value_names = array('Term_Value', 'Department_Value', 'Course_Value', 'Class_Value');
	$display_names = array('Term_Name', 'Department_Code', 'Course_Code', 'Class_Code');
	
	$mbs_url = $valuesArr['Fetch_URL'] . 'textbooks.aspx';

	do
	{
		if (!isset($valuesArr['Term_ID']) || !isset($dd_state))
		{
			if (!isset($btnRegular) || !$btnRegular)
			{
				//initial terms request to establish a session
				$options = array(CURLOPT_URL => $mbs_url, 
				CURLOPT_USERAGENT => $useragent,
				CURLOPT_COOKIESESSION => true); 
			}
			else
 			{
				//make the btn regular request	
				$options = array(CURLOPT_URL => $mbs_url,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => '__VIEWSTATE='. urlencode($mbs_viewstate) . '&btnRegular=Browse+Course+Listing',
				CURLOPT_USERAGENT => $useragent);
			}
		}
		else if (!isset($valuesArr['Department_ID']) || $dd_state < 1)
		{
			$options = array(CURLOPT_URL => $mbs_url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => '__VIEWSTATE=' . urlencode($mbs_viewstate) .'&__EVENTTARGET='. $mbs_term_name .'&__EVENTARGUMENT=&'. $mbs_term_name .'='. urlencode($valuesArr['Term_Value']) . '&'. $mbs_dept_name .'=0&'. $mbs_course_name .'=0&'. $mbs_section_name .'=0',
			CURLOPT_USERAGENT => $useragent);
			$dd_state = 1;
		}
		else if (!isset($valuesArr['Course_ID']) || $dd_state < 2)
		{
			$options = array(CURLOPT_URL => $mbs_url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $mbs_term_name .'='. urlencode($valuesArr['Term_Value']) . '&'. $mbs_dept_name .'='. urlencode($valuesArr['Department_Value']) .'&'. $mbs_course_name .'=0&'. $mbs_section_name .'=0&__VIEWSTATE=' . urlencode($mbs_viewstate),
			CURLOPT_USERAGENT => $useragent);
			$dd_state = 2;
		}
		else if (!isset($valuesArr['Class_ID']) || $dd_state < 3)
		{
			$options = array(CURLOPT_URL => $mbs_url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $mbs_term_name .'='. urlencode($valuesArr['Term_Value']) . '&'. $mbs_dept_name .'='. urlencode($valuesArr['Department_Value']) .'&'. $mbs_course_name .'='. urlencode($valuesArr['Course_Value']) .'&'. $mbs_section_name .'=0&__VIEWSTATE=' . urlencode($mbs_viewstate),
			CURLOPT_USERAGENT => $useragent);
			$dd_state = 3;
		}
		else //class-item request
		{
			$options = array(CURLOPT_URL => $mbs_url, 
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $mbs_term_name .'='. urlencode($valuesArr['Term_Value']) . '&'. $mbs_dept_name .'='. urlencode($valuesArr['Department_Value']) .'&'. $mbs_course_name .'='. urlencode($valuesArr['Course_Value']) .'&'. $mbs_section_name .'='. urlencode($valuesArr['Class_Value']) .'&__VIEWSTATE=' . urlencode($mbs_viewstate),
			CURLOPT_USERAGENT => $useragent);
			$dd_state = 4;
		}
		
		//time to make the response
		$response = curl_request($options);
		
		if (!$response)
		{
			throw new Exception('No response with values '. print_r($valuesArr, true));
		}
		else
		{	
			@$doc->loadHTML($response); //because their HTML is malformed.
			
			if (!isset($dd_state) || $dd_state != 4) //not a class-item response
			{
				$form_tag = $doc->getElementsByTagName('form');
				if ($form_tag->length == 0)
				{
					throw new Exception('No form in response with values '. print_r($valuesArr, true));
				}
				else
				{
					$form = $form_tag->item(0);
					$input_tags = $doc->getElementsByTagName('input');
					if ($input_tags->length == 0)
					{
						throw new Exception('No input in response with values '. print_r($valuesArr, true));
					}
					else //form and input tags are there as they're supposed to be
					{
						$input = $input_tags->item(0);
						//update the state session stuff..
						$mbs_url = $valuesArr['Fetch_URL'] . $form->getAttribute('action');
						$mbs_viewstate = $input->getAttribute('value');
						
						if (!isset($valuesArr['Term_Value']) || !isset($dd_state)) //they're getting terms.
						{
							$finder = new DomXPath($doc);
						
							$btnRegular = $finder->query('//input[@name="btnRegular"]');
							
							if ($btnRegular->length != 0) //btnRegular step..
							{
								//note that we don't update $dd_state until they've made it past btnRegular stage.
								$btnRegular = true; //just store a boolean now, so it will go back..
								continue;
							}
							else
							{
								if (!isset($dd_state))
								{
									$mbs_start = 0;
									
									$select_tags = $doc->getElementsByTagName('select');
									
									if ($select_tags->length == 0)
									{ 
										throw new Exception('No select tags available on initial terms request with values ' . print_r($valuesArr, true));
									}
									else
									{
										if ($select_tags->length > 4) //special cases where term isn't the first dropdown, we want to start with term correctly.
										{
											$mbs_start = $select_tags->length - 4;
										}
									
										$mbs_term_name = urlencode($select_tags->item($mbs_start)->getAttribute('name'));
										$mbs_dept_name = urlencode($select_tags->item($mbs_start + 1)->getAttribute('name'));
										$mbs_course_name = urlencode($select_tags->item($mbs_start + 2)->getAttribute('name'));
										$mbs_section_name = urlencode($select_tags->item($mbs_start + 3)->getAttribute('name'));
									}
								}
								$dd_state = 0;
							}
						}
					
						if (!isset($valuesArr[$value_names[$dd_state]])) //this is the one they want returned
						{
							$select_tags = $doc->getElementsByTagName('select');
							if ($select_tags->length == 0)
							{
								throw new Exception('No select tag in response with values '. print_r($valuesArr, true));
							}
							else
							{	
								//build the returnArray based on the select.. then we're good to go.
								$returnArray = array();
								
								$select = $select_tags->item($mbs_start + $dd_state);
								
								
								for ($i = 1; $i < $select->childNodes->length; $i++) //we start at $i = 1 to skip the "select"
								{
									$option = $select->childNodes->item($i); 
									$returnArray[] = array($value_names[$dd_state] => $option->getAttribute('value'), $display_names[$dd_state] => $option->nodeValue);
								}
							}
						}
					}							
				}
			}
			else //class-item response to handle
			{
				$items = array();
			
				$finder = new DomXPath($doc);
					
				$table_tags = $doc->getElementsByTagName('table'); //each table is a class-item
				
				if ($table_tags->length != 0)
				{
					for ($i = 0; $i < $table_tags->length; $i++)
					{
						$table_tag = $table_tags->item($i);
						
						$font_tags = $table_tag->getElementsByTagName('font');
						
						if ($font_tags->item(0)->hasChildNodes())
						{
							$items[$i]['Necessity'] = $font_tags->item(0)->firstChild->nodeValue;
						}
						else
						{
							$items[$i]['Necessity'] = $font_tags->item(0)->nodeValue;
						}
						
						$second_td = $finder->query('.//td[2]', $table_tag)->item(0);
						
						$title = $finder->query('.//font', $second_td);
						
						$items[$i]['Title'] = $title->item(0)->nodeValue;
						
						$td_doc = new DOMDocument();
						
						$td_doc->appendChild($td_doc->importNode($second_td,true)); //we parse second_td to get the remaining stuff..
						
						$td_lines = $td_doc->saveHTML();
						
						/* Added New_Rental And Used_Rental here */
						
						if ($new_rental_start = strpos($td_lines, 'New Rental: </label>'))
						{
							$new_rental_start += strlen('New Rental: </label>');
							$new_rental_end = strpos($td_lines, '<br>', $new_rental_start);
						
							$items[$i]['New_Rental_Price'] = substr($td_lines, $new_rental_start, $new_rental_end - $new_rental_start);
						}
						
						if ($used_rental_start = strpos($td_lines, 'Used Rental: </label>'))
						{
							$used_rental_start += strlen('Used Rental: </label>');
							$used_rental_end = strpos($td_lines, '<br>', $used_rental_start);
						
							$items[$i]['Used_Rental_Price'] = substr($td_lines, $used_rental_start, $used_rental_end - $used_rental_start);
						}
						
						if ($new_price_start = strpos($td_lines, 'New:</label>'))
						{
							$new_price_start += strlen('New:</label>');
							$new_price_end = strpos($td_lines, '<br>', $new_price_start);
						
							$items[$i]['New_Price'] = substr($td_lines, $new_price_start, $new_price_end - $new_price_start);
							$items[$i]['Bookstore_Price'] = $items[$i]['New_Price'];
						}
						
						if ($used_price_start = strpos($td_lines, 'Used:</label>'))
						{
							
							$used_price_start += strlen('Used:</label>');
							$used_price_end = strpos($td_lines, '<br>', $used_price_start);
							$items[$i]['Used_Price'] = substr($td_lines, $used_price_start, $used_price_end - $used_price_start);
							if (!isset($items[$i]['Bookstore_Price']))
							{
								$items[$i]['Bookstore_Price'] = $items[$i]['Used_Price'];
							}
							
						}
						
						$td_lines = explode('<br>', $td_lines);
						
						foreach($td_lines as $td_line)
						{
							$td_line = explode('</b>', $td_line);
							switch (trim($td_line[0]))
							{
								case '<b>Author:':
									$items[$i]['Authors'] = $td_line[1];
									break;
								case '<b>ISBN:':
									$items[$i]['ISBN'] = $td_line[1];
									break;
								case '<b>Edition/Copyright:':
									$items[$i]['Edition'] = $td_line[1];
									break;
								case '<b>Publisher:':
									$items[$i]['Publisher'] = $td_line[1];
									break;
								case '<b>Published Date:':
									if (trim($td_line[1]) != "NA")
									{
										$items[$i]['Year'] = $td_line[1];
									}
									break;	
							}
						}
					}
				}
				
				$returnArray['Class_ID'] = $valuesArr['Class_ID'];
				
				//we have to remove the &nbsp; gunk
				foreach ($items as $key => $val)
				{
					$items[$key] = str_replace('&nbsp;', '', $val);
				}
				
				$returnArray['items'] = $items; //trim them all 
			}
		}
	} while (!isset($dd_state) || isset($valuesArr[$value_names[$dd_state]])); //the !isset($dd_state) is for the btnRegular situation.

	return $returnArray;
}

function get_classes_and_items_from_campushub($valuesArr)
{	
	if (isset($valuesArr['Term_ID']) && !isset($valuesArr['Division_ID']))
	{
		//No such thing as Divisions on CampusHub
		return array();
	}

	$url = dirname($valuesArr['Fetch_URL']) . '/textbooks_xml.asp';
	
	if (!isset($valuesArr['Term_ID']))
	{
		$options = array(CURLOPT_URL => $valuesArr['Fetch_URL']);
		$response_name = 'selTerm';
		$display_name = 'Term_Name';
		$value_name = 'Term_Value';
	}
	else
	{
		//CampusHub has a bit of a weird Campus/Term value system, because they use different combinations of these values for the Term dropdown but there's never a different campus dropdown.  Accordingly we bunch these together as 'Term_Value' in the DB.
		$campus_and_term = explode('|', $valuesArr['Term_Value']);
		$campus = $campus_and_term[0];
		$term = $campus_and_term[1];
		
		if (!isset($valuesArr['Department_ID']))
		{
			$options = array(CURLOPT_URL => $url .'?control=campus&campus='. $campus . '&term='. $term .'&t='. time(),
			CURLOPT_REFERER => $valuesArr['Fetch_URL']);
			$response_name = 'departments';
			$display_name = 'Department_Code';
			$value_name = 'Department_Value';
		}
		
		else if (!isset($valuesArr['Course_ID']))
		{
			$options = array(CURLOPT_URL => $url .'?control=department&dept='. $valuesArr['Department_Value'] . '&term='. $term .'&t='. time(), 
			CURLOPT_REFERER => $valuesArr['Fetch_URL']);
			$response_name = 'courses';
			$display_name = 'Course_Code';
			$value_name = 'Course_Value';
		}
		else if (!isset($valuesArr['Class_ID']))
		{
			
			$options = array(CURLOPT_URL => $url .'?control=course&course='. $valuesArr['Course_Value'] . '&term='. $term .'&t='. time(), 
			CURLOPT_REFERER => $valuesArr['Fetch_URL']);
			$response_name = 'sections';
			$display_name = 'Class_Code';
			$value_name = 'Class_Value';
		}
		else //they sent a class
		{
			$options = array(CURLOPT_URL => $url . '?control=section&section='. $valuesArr['Class_Value'] . '&t='. time(), CURLOPT_REFERER => $valuesArr['Fetch_URL']);
		}
	}
	
	$response = curl_request($options);
	
	if (!$response)
	{
		throw new Exception('No response with values '. print_r($valuesArr, true));
	}
	else
	{
		$returnArray = array();
	
		$doc = new DOMDocument();
		
		@$doc->loadHTML($response); //suppress the error cus the html is imperfect
		$finder = new DomXPath($doc);
		
		if (!isset($valuesArr['Class_Value'])) //dropdown request
		{
			if (!isset($valuesArr['Term_Value']))
			{
				$select = $finder->query('//*[@name="'. $response_name .'"]');
				$start = 1; //skip "--Select a Campus term--
			}
			else
			{
				$select = $doc->getElementsByTagName($response_name);
				$start = 0;
			}
			
			if ($select->length == 0)
			{
				throw new Exception('No select in response with values '. print_r($valuesArr, true));
			}
			else
			{
				$options = $select->item(0)->childNodes;
				for ($i = $start; $i < $options->length; $i++) //skip the first "Select an X" type option
				{
					$arr = array();
					$option = $options->item($i);
					//hmm, response handling actually varies somewhat. lets see which aspects do..
					if (!isset($valuesArr['Term_Value']))
					{
						$arr[$value_name] = $option->getAttribute('value');
						$arr[$display_name] = $option->nodeValue;
					}
					else if (!isset($valuesArr['Department_Value'])) //Depts request
					{
						$arr[$value_name] = $option->getAttribute('id');
						$arr[$display_name] = $option->getAttribute('abrev');
					}
					else if (!isset($valuesArr['Course_Value']))
					{
						$arr[$value_name] = $option->getAttribute('id');
						$arr[$display_name] = $option->getAttribute('name');
					}
					else //they're getting classes in response.
					{
						//we have to do some special stuff because they sometimes use only the instructor as the display name.
						if ($option->getAttribute('instructor'))
						{
							$arr['Instructor'] = $option->getAttribute('instructor');
						}
						if ($option->getAttribute('name'))
						{
							$arr[$display_name] = $option->getAttribute('name');
						}

						$arr[$value_name] = $option->getAttribute('id');
						
						
					}	
					$returnArray[] = $arr;
				}
			}
		}
		else //class-items request
		{
			$items = array();
			
			$tbody_tags = $doc->getElementsByTagName('tbody');
			if ($tbody_tags->length != 0)
			{
				$tbody_tag = $tbody_tags->item(0);
				$count = 0;
				
				$tr_tags = $tbody_tag->childNodes;
				if ($tr_tags->length)
				{
					foreach ($tr_tags as $book_tr)
					{
						$item = array(); //item we will add if there's a Title.
						
						$necessity_tag = $finder->query('.//*[@class="book-req"]', $book_tr);
						
						if ($necessity_tag->length)
						{	
							$item['Necessity'] = $necessity_tag->item(0)->nodeValue;
						}
						
						$spans = $book_tr->getElementsByTagName('span');
						
						for ($i = 0; $i < $spans->length; $i++)
						{
							$span = $spans->item($i);
							switch (trim($span->getAttribute('class')))
							{
								case 'book-title':
									$item['Title'] = $span->nodeValue;
									break;
								case 'book-meta book-author':
									$item['Authors'] = $span->nodeValue;
									break;
								case 'isbn':
									$item['ISBN'] = $span->nodeValue;
									break;
								case 'book-meta book-copyright':
									$item['Year'] = str_replace(array(' ', 'Â','Copyright'), '', $span->nodeValue);
									break;
								case 'book-meta book-publisher':
									$item['Publisher'] = str_replace(array(' ', 'Â','Publisher'), '', $span->nodeValue);
									break;
								case 'book-meta book-edition':
									$item['Edition'] = str_replace(array(' ', 'Â','Edition'), '', $span->nodeValue);
									break;
								case 'book-price-new':
									$item['Bookstore_Price'] = $span->nodeValue;
									break;
								case 'book-price-used':
									if (!isset($item['Bookstore_Price'])) //only new used when new isn't there.
									{
										$item['Bookstore_Price'] = $span->nodeValue;
									}
									break;
							}
						}
					
						//sometimes Bookstore Price is listed in a different way (radio labels)
						if (!isset($item['Bookstore_Price']))
						{
							$prices = $finder->query('.//td[@class="price"]/label', $book_tr); 
							$priceArr = array();
							
							foreach($prices as $price)
							{
								$priceArr[] = priceFormat($price->nodeValue); //format it so we can compare
							}
					
							if ($priceArr)
							{
								$item['Bookstore_Price'] = max($priceArr);
							}
						}
						
						if (isset($item['Title']))
						{						
							$items[$count] = $item;
							
							$count++;
						}
					}
				}
			}
			
			$returnArray['Class_ID'] = $valuesArr['Class_ID'];
			$returnArray['items'] = $items;
		}
	}
	return $returnArray;
}

function get_classes_and_items_from_bn($valuesArr)
{
	if (isset($valuesArr['Term_ID']) && !isset($valuesArr['Division_ID']))
	{
		return array(); //because BN doesn't have Division values.
	}
	
	$url = $valuesArr['Fetch_URL'] . 'webapp/wcs/stores/servlet/';
	
	$referer = $url . 'TBWizardView?catalogId=10001&storeId='. $valuesArr['Store_Value'] .'&langId=-1';
	
	if (!isset($valuesArr['Class_ID']))
	{
		//make initialization request if they don't have a session yet...
		curl_request(array(CURLOPT_URL => $valuesArr['Storefront_URL'], CURLOPT_COOKIESESSION => true, CURLOPT_PROXY => PROXY_2, CURLOPT_PROXYUSERPWD => PROXY_2_AUTH));
		
		//pt 2 of initialization is requesting the textbook lookup page
		$options = array(CURLOPT_URL => $referer, CURLOPT_PROXY => PROXY_2, CURLOPT_PROXYUSERPWD => PROXY_2_AUTH);
		
		$response = curl_request($options);
		
		if (!$response)
		{
			throw new Exception('Failed to initialize the BN session with values '. print_r($valuesArr, true));
		}
		
		//prepare appropriate dropdown query depending on what they're trying to get...
		if (!isset($valuesArr['Term_ID']))
		{
			//We're doing this Multiple_Campuses thing for now, until we improve the system..
			if ($valuesArr['Multiple_Campuses'] == 'Y') //they have a campus dropdown.
			{
				$url .= 'TextBookProcessDropdownsCmd?campusId='. $valuesArr['Campus_Value'] .'&termId=&deptId=&courseId=&sectionId=&storeId='. $valuesArr['Store_Value'] .'&catalogId=10001&langId=-1&dojo.transport=xmlhttp&dojo.preventCache='. time();
			}
			else
			{
				$url = $referer;
			}
		}
		else if (!isset($valuesArr['Department_ID']))
		{
			$url .= 'TextBookProcessDropdownsCmd?campusId='. $valuesArr['Campus_Value'] .'&termId='. $valuesArr['Term_Value'] .'&deptId=&courseId=&sectionId=&storeId='. $valuesArr['Store_Value'] .'&catalogId=10001&langId=-1&dojo.transport=xmlhttp&dojo.preventCache='. time();
		}
		else if (!isset($valuesArr['Course_ID']))
		{
			$url .= 'TextBookProcessDropdownsCmd?campusId='. $valuesArr['Campus_Value'] .'&termId='. $valuesArr['Term_Value'] .'&deptId='. $valuesArr['Department_Value'] .'&courseId=&sectionId=&storeId='. $valuesArr['Store_Value'] . '&catalogId=10001&langId=-1&dojo.transport=xmlhttp&dojo.preventCache='. time();
		}
		else if (!isset($valuesArr['Class_ID']))
		{
			$url .= 'TextBookProcessDropdownsCmd?campusId='. $valuesArr['Campus_Value'] .'&termId='. $valuesArr['Term_Value'] .'&deptId='. $valuesArr['Department_Value'] .'&courseId='. $valuesArr['Course_Value'] .'&sectionId=&storeId='. $valuesArr['Store_Value'] . '&catalogId=10001&langId=-1&dojo.transport=xmlhttp&dojo.preventCache='. time();
		}
		
		$options = array(CURLOPT_URL => $url, CURLOPT_REFERER => $referer, CURLOPT_PROXY => PROXY_2, CURLOPT_PROXYUSERPWD => PROXY_2_AUTH);
	}
	else //prepare the class-items query
	{
		//x and y values indicate to the script which pixels you clicked for their analytics purposes.  we play it safe by randomizing them within the possible range.
		
		$x = rand(0, 115);
		$y = rand(0, 20);
		
		$postdata = 'storeId='. $valuesArr['Store_Value'] .'&langId=-1&catalogId=10001&savedListAdded=true&clearAll=&viewName=TBWizardView&removeSectionId=&mcEnabled=N&section_1='. $valuesArr['Class_Value'] .'&numberOfCourseAlready=0&viewTextbooks.x='. $x .'&viewTextbooks.y='. $y .'&sectionList=newSectionNumber';//get the class-book data.
		
		//$options = array(CURLOPT_URL => $url .'TBListView', CURLOPT_REFERER => $referer, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postdata);
		$options = array(CURLOPT_URL => $url .'TBListView', CURLOPT_REFERER => $referer, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $postdata, CURLOPT_PROXY => PROXY_2, CURLOPT_PROXYUSERPWD => PROXY_2_AUTH);
	}
	
	$response = curl_request($options);
	
	
	if (!$response)
	{
		throw new Exception('Failed to get a response with values '. print_r($valuesArr, true));
	}
	else
	{
		$returnArray = array();
		//continue here with finder stuff for term...
		$doc = new DOMDocument();
	
		@$doc->loadHTML($response); //supress the error cus HTML is imperfect
		
		$finder = new DomXPath($doc);
		
		//time to process the response...
		if (!isset($valuesArr['Term_Value']))
		{
			$select_tags = $doc->getElementsByTagName('select');
			
			$term_options = $finder->query('//select[@name="s2"]/option');
			
			if ($term_options->length == 0)
			{
				throw new Exception('Failed to get term select with values '. print_r($valuesArr, true));
			}
			else
			{
				for ($j = 1; $j < $term_options->length; $j++) //skip the first "select"
				{
					$term_option = $term_options->item($j);
					$returnArray[] = array('Term_Value' => $term_option->getAttribute('value'), 'Term_Name' => $term_option->nodeValue);
				}
			}
		}
		else if (!isset($valuesArr['Department_Value']))
		{
			$option_tags = $doc->getElementsByTagName('option');
			for ($i = 1; $i < $option_tags->length;  $i++) //skip the first "select"
			{
				$option_tag = $option_tags->item($i);
				$returnArray[] = array('Department_Value' => $option_tag->getAttribute('value'), 'Department_Code' => $option_tag->nodeValue);
			}
		}
		else if (!isset($valuesArr['Course_Value']))
		{
			$option_tags = $doc->getElementsByTagName('option');
			
			for ($i = 1; $i < $option_tags->length;  $i++) //skip the first "select"
			{
				$option_tag = $option_tags->item($i);
				$returnArray[] = array('Course_Value' => $option_tag->getAttribute('value'), 'Course_Code' => $option_tag->nodeValue);
			}
		}
		else if (!isset($valuesArr['Class_Value']))
		{
			$option_tags = $doc->getElementsByTagName('option');
			
			for ($i = 1; $i < $option_tags->length;  $i++)
			{
				$option_tag = $option_tags->item($i);
				if (substr($option_tag->getAttribute('value'), -2) == "N_")
				{
					$value = substr($option_tag->getAttribute('value'), 0, -2); //clear up the N_ shit.
				}
				else
				{
					$value = $option_tag->getAttribute('value');
				}
				
				$returnArray[] = array('Class_Value' => $value, 'Class_Code' => $option_tag->nodeValue);
			}
		}
		//continue with other dropdown responses..
		else
		{
			$cb_divs = $finder->query('//div[@class="tbListHolding"]');
			
			$items = array();
			
			foreach ($cb_divs as $i => $cb_div)
			{
				//Begin by getting Title and Necessity..
				$title_search = $finder->query('.//div[@class="sectionProHeading"]//li/a', $cb_div);
				
				if ($title_search->length != 0)
				{
					$items[$i]['Title'] = $title_search->item(0)->nodeValue;
					$items[$i]['Necessity'] = $finder->query('.//div[@class="sectionProHeading"]//li[@class="required"]', $cb_div)->item(0)->nodeValue;
					
					//Next get more Items data..
					$item_lis = $finder->query('.//ul[@class="TBinfo"]/li', $cb_div); //these lis have the item data..
					
					foreach($item_lis as $li)
					{
						$span = $li->getElementsByTagName('span');
						if ($span->length)
						{
							$span = $span->item(0);
							$span_val = trim($span->nodeValue);
							$li->removeChild($span); //so its not included in nodeValue
							switch ($span_val)
							{
								case 'Author:':
									$items[$i]['Authors'] = $li->nodeValue;
									break;
								case 'Edition:':
									$items[$i]['Edition'] = $li->nodeValue;
									break;
								case 'Publisher:':
									$items[$i]['Publisher'] = $li->nodeValue;
									break;
								case 'ISBN:':
									$items[$i]['ISBN'] = $li->nodeValue;
									break;
							}
						}
					}
					
					//Next we get the Bookstore Price...
					$pricing_labels = $finder->query('.//td[@class="sectionSelect"]/ul/li/label', $cb_div);
					
					$pricingList = array();
					
					
					//need to fix this up to extract only the price..
					foreach($pricing_labels as $label)
					{
						$span = $label->getElementsByTagName('span');
						if ($span->length)
						{
							$span = $span->item(0);
							$label->removeChild($span); //so its not included in nodeValue
							$pricingList[] = priceFormat($label->nodeValue); //format so we can compare
						}
					}
					
					if ($pricingList)
					{
						$items[$i]['Bookstore_Price'] = max($pricingList);
					}
				}
			}
			
			$returnArray['Class_ID'] = $valuesArr['Class_ID'];
			$returnArray['items'] = $items; //trim them all 
			
		}
		
		return $returnArray;
	}
}	

function update_classes_from_bookstore($valuesArr) //$valuesArr is an array of values to send to the bookstore (usually its from a $row result).  Depending on what's there, we query the next thing:  Bookstore vars, Term_Value, Department_Value, Course_Value. 
{
	$wait_times = array(FALSE, 250000, 400000); //double retries
	$results = false;
	
	for ($n = 0; $n < count($wait_times) && !$results; $n++)
	{
		if ($wait_times[$n])
		{
			usleep($wait_times[$n]);
		}
		try //we need to catch exceptions because they might change their layouts
		{
			switch ($valuesArr['Bookstore_Type_Name'])
			{
				case 'Barnes and Nobles':
					$results = get_classes_and_items_from_bn($valuesArr);
					break;
				case 'CampusHub':
					$results = get_classes_and_items_from_campushub($valuesArr);
					break;
				case 'MBS':
					$results = get_classes_and_items_from_mbs($valuesArr);
					break;
				case 'Follett':
					$results= get_classes_and_items_from_follett($valuesArr);
					break;
				case 'ePOS':
					$results = get_classes_and_items_from_epos($valuesArr);
					break;
				case 'Neebo':
					$results = get_classes_and_items_from_neebo($valuesArr);
					break;
				//These functions will return false or empty array on error or 0 $results...	
			}
		}
		catch (Exception $e)
		{
			$results = false;
			trigger_error('Bookstore query problem: '. $e->getMessage() . ' on line '. $e->getLine());
		}	
	}
	
	if (!$conn = connect())
	{
		trigger_error('Connect failure', E_USER_WARNING);
	}
	
	if ($results !== false)
	{
		foreach ($results as $key => $result)
		{
			$results[$key] = format_dropdown($result);
		}
		
		if ($results && !isset($valuesArr['Term_ID'])) //it's getting terms
		{
			$query = 'INSERT INTO Terms_Cache (Campus_ID, Term_Name, Term_Value) VALUES ';
			foreach($results as $term)
			{
				$query .= '(' . $valuesArr['Campus_ID'] . ', "'. mysql_real_escape_string($term['Term_Name']) . '", "' . mysql_real_escape_string($term['Term_Value']) .'"),'; //Title Capitalize the Term_Name with ucwords()
			}	
			$query = substr(($query), 0, -1); //remove final comma
			$query .=  ' ON DUPLICATE KEY UPDATE Term_Name=VALUES(Term_Name),Cache_TimeStamp=NOW()';
		}
		else if (!isset($valuesArr['Division_ID'])) //no reuslts is interpreted as placeholder no division null insert.
		{
			$query = 'INSERT INTO Divisions_Cache (Term_ID, Division_Name, Division_Value) VALUES ';
			//we allow for empty results on this one
			if (!$results)
			{
				//insert NULL placeholder row
				$query .= '('. $valuesArr['Term_ID'] .', NULL, NULL)';
			}
			else
			{
				//insert actual programs
				foreach ($results as $program)
				{
					$query .= '('. $valuesArr['Term_ID'] . ', "'. mysql_real_escape_string($program['Division_Name']) . '", "'. mysql_real_escape_string($program['Division_Value']) .'"),';
				}
				$query = substr(($query), 0, -1); //remove final comma
			}
			
			$query .=  ' ON DUPLICATE KEY UPDATE Division_Name=VALUES(Division_Name),Cache_TimeStamp=NOW()';
		}
		else if ($results && !isset($valuesArr['Department_ID'])) //it's getting departments
		{
			$query = 'INSERT INTO Departments_Cache (Division_ID, Department_Code, Department_Value) VALUES ';
			foreach($results as $dept)
			{
				$query .= '(' . $valuesArr['Division_ID'] . ', "'. mysql_real_escape_string($dept['Department_Code']) . '", "' . mysql_real_escape_string($dept['Department_Value']) .'"),';
			}	
			$query = substr(($query), 0, -1); //remove final comma
			$query .=  ' ON DUPLICATE KEY UPDATE Department_Code=VALUES(Department_Code),Cache_TimeStamp=NOW()';
		}
		else if ($results && !isset($valuesArr['Course_ID'])) //it's getting courses
		{
			//Do things differently for neebo than the other schools.. Neebo gets courses and sections at one time, so we need to use a transaction to do that.
			if ($valuesArr['Bookstore_Type_Name'] != 'Neebo')
			{
			
				$query = 'INSERT INTO Courses_Cache (Department_ID, Course_Code, Course_Value) VALUES ';
				foreach($results as $course)
				{
					$query .= '(' . $valuesArr['Department_ID'] . ', "'. mysql_real_escape_string($course['Course_Code']) . '", "' . mysql_real_escape_string($course['Course_Value']) .'"),';
				}	
				$query = substr(($query), 0, -1); //remove final comma
				$query .=  ' ON DUPLICATE KEY UPDATE Course_Code=VALUES(Course_Code),Cache_TimeStamp=NOW()';
			}
			else
			{
				if ($results)
				{
					//we need to have a transaction for adding them so we don't get partial additions..
					
					mysql_query("START TRANSACTION");
					mysql_query("SET @time = NOW()"); //we need to store NOW() at the getgo so everything has the same cache time.
					
					$failed = false;
					foreach ($results as $course)
					{
						$neebo_query = 'INSERT INTO Courses_Cache (Department_ID, Course_Code, Course_Value) VALUES (' . $valuesArr['Department_ID'] . ', "'. mysql_real_escape_string($course['Course_Code']) . '", NULL) ON DUPLICATE KEY UPDATE Course_Code=VALUES(Course_Code),Cache_TimeStamp=NOW()'; //have to give it a different name than $query so we don't run the $query later
						//we always insert NULL for course_value cus it's never sent.

						if (!mysql_query($neebo_query))
						{
							trigger_error(mysql_error() .' on course part of neebo course+class cache update query: '. $neebo_query);
							$failed = true;
							mysql_query('ROLLBACK');
							break;
						}
						else
						{
							$course_id = mysql_insert_id(); //get the course_id jst inserted
							foreach ($course['Classes'] as $class)
							{
								$neebo_query = 'INSERT INTO	Classes_Cache (Course_ID, Class_Code, Class_Value) VALUES ('. $course_id . ', "'. mysql_real_escape_string($class['Class_Code']) . '", "'. mysql_real_escape_string($class['Class_Value']) . '") ON DUPLICATE KEY UPDATE Class_Code=VALUES(Class_Code),Cache_TimeStamp=NOW()';
								//DO:what about that there instructor? are we grabbing that?  want to get it seperatyely...
									//make sure to set default value for when that wont work
								
								if (!mysql_query($neebo_query))
								{
									trigger_error(mysql_error() .' on class part of neebo course+class cache update query: '. $neebo_query);
									$failed = true;
									mysql_query('ROLLBACK');
									break 2;
								}
							}
						}
						//make sure it breaks out of both loops (use break 2) to skip to the loo
					}
					
					if (!$failed) //it all went through
					{
						mysql_query('COMMIT');
					}
				}
			}
		}
		else if ($results) //it's getting classes aka sections.  by definition its not neebo which gets those when it gets courses. 
		{
			//we need to update this to store instructor.
			$query = 'INSERT INTO Classes_Cache (Course_ID, Class_Code, Class_Value, Instructor) VALUES ';
			foreach($results as $class)
			{
				if (isset($class['Instructor']))
				{
					$class['Instructor'] = '"'. $class['Instructor'] .'"';
				}
				else
				{
					$class['Instructor'] = 'NULL';
				}
			
				$query .= '(' . $valuesArr['Course_ID'] . ', "'. mysql_real_escape_string($class['Class_Code']) . '", "' . mysql_real_escape_string($class['Class_Value']) .'", '. $class['Instructor'] .'),'; //Title capitalize Class_Code with ucwords() in case prof was in it
			}	
			$query = substr(($query), 0, -1); //remove final comma
			$query .=  ' ON DUPLICATE KEY UPDATE Class_Code=VALUES(Class_Code),Cache_TimeStamp=NOW()';
		}
		
		
		if (isset($query) && !mysql_query($query)) //only applies to non-neebo.
		{
			
			trigger_error(mysql_error() .' on cache update query: '. $query);
		}
	}
	else
	{
		trigger_error('Failed to query bookstore with '. print_r($valuesArr, true), E_USER_WARNING);
	}
	if (isset($results))
	{
		return $results;
	}
	else
	{
		return array();
	}
	
}

function update_class_items_from_bookstore($classValuesArr) //$classValuesArr is an *array of arrays* of values to send to the bookstore (usually its from a $row result).  Expects Bookstore vars, Term_Value, Department_Value, Course_Value, and Class_Value.  This function updates the Class-Items and Items tables with the results.
{
	$resultsArray = array();
	$Items = array();
	
	$wait_times = array(FALSE, 250000, 400000); //double retries
	
	foreach($classValuesArr as $valuesArr)
	{
		$results = array();
		for ($n = 0; $n < count($wait_times) && !$results; $n++)
		{
			if ($wait_times[$n])
			{
				usleep($wait_times[$n]);
			}
			try
			{
				switch ($valuesArr['Bookstore_Type_Name'])
				{
					case 'Barnes and Nobles':
						$results = get_classes_and_items_from_bn($valuesArr);
						break;
					case 'CampusHub':
						$results = get_classes_and_items_from_campushub($valuesArr);
						break;
					case 'MBS':
						$results = get_classes_and_items_from_mbs($valuesArr);
						break;
					case 'Follett':
						$results = get_classes_and_items_from_follett($valuesArr);
						break;
					case 'ePOS':
						$results = get_classes_and_items_from_epos($valuesArr);
						break;
					case 'Neebo':
						$results = get_classes_and_items_from_neebo($valuesArr);
						break;
					//These functions will return false or empty array on error or 0 $results...
				}
			}
			catch (Exception $e)
			{
				$results = false;
				trigger_error('Bookstore query problem: '. $e->getMessage());
			}
			if ($results)
			{
				$Items = array();
				
				foreach ($results['items'] as $i => $item)
				{
					//Set data source and format the item.. Also add it to $Items for later update.
					//**make it so it ignores the ones with the bad titles..
					$exclude = array('As Of Today,No Book Order Has Been Submitted,Pleas,'); #Note that this is their typo, not mine
					$item = format_item($item);
					
					if (!in_array(trim($item['Title']), $exclude) && 
					(!isset($item['Necessity']) || !$item['Necessity'] || isNecessary($item['Necessity']) || (isset($item['ISBN']) && valid_book_ISBN13($item['ISBN'])))
					) //we lso require that its either (possibly) required or has an ISBN
					{
						$Items[] = $item;
					}
				}
				
				$results['items'] = $Items;
				
				$resultsArray[] = $results;
			}
		}
	}
	
	if ($resultsArray)
	{
		if (!$Items || update_items_db($Items)) //makes sure the update query works before proceeding
		{
			if (!$conn = connect())
			{
				trigger_error('DB connect failed');
			}
			else
			{
				$class_items_query = '';
					
				foreach($resultsArray as $result)
				{
					if (isset($result['items']) && $result['items'])
					{	
						/* we build a union select to get the Item_ID's for the books we just inserted into Items based on ISBN, or if ISBN isn't there, all the other fields.  The reason we select the info we already have is so we can easily match it with our data. */
						
						$selectArray = array(); //cus we break it into a union
						foreach($result['items'] as $item)
						{
							$New_Price = 'NULL';
							$Used_Price = 'NULL';
							$New_Rental_Price = 'NULL';
							$Used_Rental_Price = 'NULL';
							$Bookstore_Price = 'NULL';
							$Necessity = 'NULL';
							$Comments = 'NULL';
							
							if (isset($item['Bookstore_Price']))
							{
								$Bookstore_Price = "'". mysql_real_escape_string($item['Bookstore_Price']) . "'";
							}
							if (isset($item['New_Price']))
							{
								$New_Price = "'". mysql_real_escape_string($item['New_Price']) . "'";
							}
							if (isset($item['Used_Price']))
							{
								$Used_Price = "'". mysql_real_escape_string($item['Used_Price']) . "'";
							}
							if (isset($item['New_Rental_Price']))
							{
								$New_Rental_Price = "'". mysql_real_escape_string($item['New_Rental_Price']) . "'";
							}
							if (isset($item['Used_Rental_Price']))
							{
								$Used_Rental_Price = "'". mysql_real_escape_string($item['Used_Rental_Price']) . "'";
							}
							
							if (isset($item['Necessity']))
							{
								$Necessity = "'". mysql_real_escape_string($item['Necessity']) . "'"; //title capitalize Necessity
							}
							if (isset($item['Comments']))
							{
								$Comments = "'". mysql_real_escape_string($item['Comments']) . "'";
							}
							
							$select = 'SELECT Item_ID, '. 
							$Bookstore_Price .' AS Bookstore_Price, '.
							$New_Price .' AS New_Price, '.
							$Used_Price .' AS Used_Price, '.
							$New_Rental_Price .' AS New_Rental_Price, '.
							$Used_Rental_Price .' AS Used_Rental_Price, '.
							$Necessity .' AS Necessity, '. 
							$Comments .' AS Comments 
							
							FROM Items WHERE ';
							
							if (isset($item['ISBN']) && valid_ISBN13($item['ISBN']))
							{
								$select .= 'ISBN = '. $item['ISBN'];
							}
							else
							{
								$Edition = "''";
								$Authors = "''";
								$Year = 0000;
								$Publisher = "''";
								
								$Title = "'". mysql_real_escape_string($item['Title']) ."'";
								
								if (isset($item['Edition']))
								{
									$Edition =  "'". mysql_real_escape_string($item['Edition']) . "'";
								}
								if (isset($item['Authors']))
								{
									$Authors = "'". mysql_real_escape_string($item['Authors']) . "'"; //title capitalize authors
								}
								if (isset($item['Year']))
								{
									$Year = $item['Year'];
								}
								if (isset($item['Publisher']))
								{
									$Publisher = "'". mysql_real_escape_string($item['Publisher']) . "'"; //Title capitalize  Publisher
								}
								
								$select .= 'ISBN IS NULL AND Title = '. $Title .' AND Edition = '. $Edition .' AND Authors = '. $Authors . ' AND Year = '. $Year .' AND Publisher = '. $Publisher;
							}
							
							$selectArray[] = $select;
						}
						$select_items_query = implode($selectArray, ' UNION ALL ');					
						
						if (!$select_result = mysql_query($select_items_query))
						{
							trigger_error(mysql_error() . ' with select items query '. $select_items_query, E_USER_WARNING);
						}
						else if (mysql_num_rows($select_result) == 0)
						{
							trigger_error('0 rows on select items query '. $select_items_query, E_USER_WARNING);
						}
						else
						{	
							while ($row = mysql_fetch_assoc($select_result))
							{
								$Bookstore_Price = 'NULL';
								$New_Price = 'NULL';
								$Used_Price = 'NULL';
								$New_Rental_Price = 'NULL';
								$Used_Rental_Price = 'NULL';
								$Necessity = 'NULL';
								$Comments = 'NULL';
								
								if ($row['Bookstore_Price'])
								{
									$Bookstore_Price = "'". mysql_real_escape_string($row['Bookstore_Price']) . "'";
								}
								if ($row['New_Price'])
								{
									$New_Price = "'". mysql_real_escape_string($row['New_Price']) . "'";
								}
								if ($row['Used_Price'])
								{
									$Used_Price = "'". mysql_real_escape_string($row['Used_Price']) . "'";
								}
								if ($row['New_Rental_Price'])
								{
									$New_Rental_Price = "'". mysql_real_escape_string($row['New_Rental_Price']) . "'";
								}
								if ($row['Used_Rental_Price'])
								{
									$Used_Rental_Price = "'". mysql_real_escape_string($row['Used_Rental_Price']) . "'";
								}
				
								if ($row['Necessity'])
								{
									$Necessity = "'". mysql_real_escape_string($row['Necessity']) . "'";
								}
								if ($row['Comments'])
								{
									$Comments = "'". mysql_real_escape_string($row['Comments']) . "'";
								}
								
								$class_items_query .= '('. 
								$result['Class_ID'] .', '. 
								$row['Item_ID'] .', '. 
								$Bookstore_Price . ', '. 
								$New_Price . ', '. 
								$Used_Price . ', '. 
								$New_Rental_Price . ', '. 
								$Used_Rental_Price . ', '. 
								$Necessity .', '. 
								$Comments .
								'),';
							}
						}
					}
					else
					{
						$Comments = 'NULL'; 
						if (isset($item['Comments'])) //there still might be some comments even if no items.
						{
							$Comments = "'". mysql_real_escape_string($item['Comments']) . "'";
						}
						$class_items_query .= '('. $result['Class_ID'] .', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '. $Comments .'),';

					}
				}
				
				$class_items_query = 'INSERT INTO Class_Items_Cache (Class_ID, Item_ID, Bookstore_Price, New_Price, Used_Price, New_Rental_Price, Used_Rental_Price, Necessity, Comments) VALUES '. substr($class_items_query, 0, -1) . ' ON DUPLICATE KEY UPDATE Item_ID=VALUES(Item_ID),Bookstore_Price=VALUES(Bookstore_Price),New_Price=VALUES(New_Price),Used_Price=VALUES(Used_Price),New_Rental_Price=VALUES(New_Rental_Price),Used_Rental_Price=VALUES(Used_Rental_Price),Necessity=VALUES(Necessity),Comments=VALUES(Comments),Cache_TimeStamp=NOW()';
							
				if (!mysql_query($class_items_query))
				{
					trigger_error(mysql_error() . ' on class_items_query: '. $class_items_query, E_USER_WARNING);
				}
			}
		}
	}
	else
	{
		trigger_error('Failed to query bookstore with '. print_r($classValuesArr, true), E_USER_WARNING);
	}		
}

?>
