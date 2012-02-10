<?php

function connect()
{
	if (!($connection = mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD)) || !mysql_select_db(DB_NAME))
	{
		return false;
	}
	else
	{
		return $connection;
	}
}
?>
