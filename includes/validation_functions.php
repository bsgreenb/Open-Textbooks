<?php

function isNecessary($necc) //if necessity seems to be required..
{
	$reqs = array('required', 'choose', 'mandatory');
	foreach ($reqs as $req)
	{
		if (stripos($req,$necc) !== false)
		{
			return true;
		}
	}
	return false;
}

function priceFormat($str) //formats prices for our database
{
	$str = trim($str, " \t\n\r\0\x0B\xA0"); //clear out the gunk that some schools give.
	return number_format((float) str_replace(array('$', ',', ' '), '', $str), 2, '.', '');
}

function super_unique($array) //array unique for multi 
{
  $result = array_map("unserialize", array_unique(array_map("serialize", $array)));

  foreach ($result as $key => $value)
  {
    if ( is_array($value) )
    {
      $result[$key] = super_unique($value);
    }
  }

  return $result;
}

function valid_ID($ID)
{
	return(ctype_digit(strval($ID)));
}

function valid_email($email) //this is for contact.php
{
	return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function ISBN13_to_10($isbn13)
{
	$isbn13 = cleanISBN($isbn13);
	$isbn2 = substr(trim($isbn13), 3, 9);
	$sum10 = isbn_genchksum10($isbn2);
	if ($sum10 == 10) 
	{
		$sum10='X';
	}
	return $isbn2 . $sum10;
}

function get_ISBN13($isbn) //takes an isbn of length 9, 10, or 12 and returns the 13
{
	$isbn = cleanISBN($isbn);
	if (strlen($isbn) == 9)
	{
		$isbn .= isbn_genchksum10($isbn); 
	}
	if (strlen($isbn) == 10)
	{
		return ISBN10_to_13($isbn);
	}
	else if (strlen($isbn) == 12)
	{
		return $isbn . isbn_genchksum13($isbn);
	}
	else
	{
		return $isbn;
	}
}

function ISBN10_to_13($isbn10) //adapted from http://www.blyberg.net/2006/04/05/php-port-of-isbn-1013-tool/
{
	//NOTE:THIS FUNCTION REQUIRES A STRING, not a number type.
	$isbn2 = substr("978" . $isbn10, 0, -1);
	$sum13 = isbn_genchksum13($isbn2);
	return ($isbn2 . $sum13);
}

function isbn_genchksum10($isbn) {
		$t = 2;$b = 0; $a = 0;
		$isbn = trim($isbn);
		for($i = 0; $i <= 9; $i++){
			$b = $b + $a;
			$c = substr($isbn, -1, 1);
			$isbn = substr($isbn, 0, -1);
			$a = ($c * $t);
			$t++;
		}
		$s = ($b / 11);
		$s = intval($s);
		$s++;
		$g = ($s * 11);
		$sum = ($g - $b); 
		return $sum;
	}

function isbn_genchksum13($isbn) //taken from http://www.blyberg.net/2006/04/05/php-port-of-isbn-1013-tool/
{
	$tb = 0;
	for ($i = 0; $i <= 12; $i++) 
	{
		$tc = substr($isbn, -1, 1); //last character
		$isbn = substr($isbn, 0, -1);
		$ta = ($tc*3);
		$tci = substr($isbn, -1, 1);
		$isbn = substr($isbn, 0, -1);
		$tb = $tb + $ta + $tci;
	}
	$tg = ($tb / 10);
	$tint = intval($tg);
	if ($tint == $tg) 
	{ 
		return 0; 
	}
	$ts = substr($tg, -1, 1);
	$tsum = (10 - $ts);
	return $tsum;
}

function valid_book_ISBN13($isbn) //ensures that it's a book ISBN (begins with 978 or 979)
{
	$first_3 = substr($isbn, 0, 3);
	return (($first_3 == '978' || $first_3 == '979') && valid_ISBN13($isbn));
}
		
function valid_ISBN13($isbn)
//adapted from http://www.alixaxel.com/wordpress/wp-content/2007/07/ISBN.php?isbn=1234567890123
{
	if (is_numeric($isbn) && strlen($isbn) == 13)
	{
		$sum = 0;
		$even = false;
		for ($i = 12; $i >= 0; $i--)
		{
			if ($even === true)
			{
				$sum += $isbn[$i] * 3;
				$even = false;
			}
			else
			{
				$sum += $isbn[$i];
				$even = true;
			}
		}
		return ($sum % 10 == 0);
	}
	else
	{
		return false;
	}
}

function cleanISBN($isbn)
{
	return str_replace(array(' ', '-', '.'), '', $isbn);
}

function valid_ISBN($isbn) //CHANGE TO ISBN
//adapted from http://www.alixaxel.com/wordpress/wp-content/2007/07/ISBN.php?isbn=1234567890123
//$string = str_replace(array(' ', '-', '.'), '', $string); has to come before this.
{
    settype($isbn,'string');
	$sum = 0;
	if (is_string($isbn))
	{
		if (strlen($isbn) == 13)
		{
			$even = false;
			for ($i = 12; $i >= 0; $i--)
			{
				if ($even === true)
				{
					$sum += $isbn[$i] * 3;
					$even = false;
				}
				else
				{
					$sum += $isbn[$i];
					$even = true;
				}
			}
			return ($sum % 10 == 0);
		}
		else if (strlen($isbn) == 10)
		{
			for ($i = 0; $i < 9; $i++)
			{
				$sum += $isbn[$i] * ($i + 1);
			}
			$check_digit = substr($isbn, -1);
			if (strtoupper($check_digit) == 'X')
			{
				$check_digit = 10;
			}
			return ($sum % 11 == $check_digit);
		}
		else
		{
			return false;
		}
	}
	else
	{
		return false;
	}
}

?>
