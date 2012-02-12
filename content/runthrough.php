<?php

#Goes through and tests whether all Campuses are returning Terms

require_once('../includes/autoloads.php');

$problem_ids = array();

//Get the campuses...
$conn = connect();
$all_campuses_query = 'SELECT Campus_ID FROM Campuses';
$result = mysql_query($all_campuses_query, $conn);

while ($row = mysql_fetch_assoc($result))
{
    //Query the campus
    $query = next_dropdowns_query(array('campus' => $row['Campus_ID']));
    
    $campus_result = mysql_query($query, $conn);

    if (mysql_num_rows($campus_result) == 0)
    {
        $problem_ids[] = $row['Campus_ID'];
    }
    else
    {
        $campus_row = mysql_fetch_assoc($campus_result);
        if (!$campus_row['Term_ID'])
        {
            update_classes_from_bookstore($campus_row);
            $campus_result = mysql_query($query, $conn);
            if (mysql_num_rows($campus_result) == 0)
            {
                $problem_ids[] = $row['Campus_ID'];
            }
            else
            {
                $campus_row = mysql_fetch_assoc($campus_result);
                if (!$campus_row['Term_ID'])
                {
                    $problem_ids[] = $row['Campus_ID'];
                }
            }
        }
    }
}

echo implode(',', $problem_ids);

?>

