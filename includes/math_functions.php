<?php
function gmp_shiftr($x,$n) 
{ 
	if ($x < 0)
	{
		return gmp_strval(gmp_com(gmp_div(gmp_com($x),gmp_pow(2,$n))));
	}
	else
	{
		return gmp_strval(gmp_div($x,gmp_pow(2,$n)));
	}
}

function toSigned($value) 
{  
	if ($value > 2147483647)
	{
		return $value - 4294967295 - 1;
	}
	else if ($value < -2147483647)
	{
		return $value + 4294967295 + 1;
	}
	else
	{
		return $value;
	}
}
?>