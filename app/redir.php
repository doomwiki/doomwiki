<?php
if(!isset($_GET['file']))
{
	header("Location: /", true, 301);
}
else 
{
	$t = $_GET['file'];
	$t = str_replace('.html', '', $t);
	if(strpos($t, '%25') >= 0)
	{
		$t = urldecode($t);
	}
	header("Location: /w/index.php?title=" . $t, true, 301);
}