<?php

function innerHTML($node)
{
	$doc = new DOMDocument();
	foreach ($node->childNodes as $child)
	{
		$doc->appendChild($doc->importNode($child, true));		
	}
	return $doc->saveHTML();
}

?>
