<?php
namespace Streams;
require_once './Status.php';
require_once './Error.php';
require_once './StringBuffer.php';
require_once './Connection.php';
require_once './Poller.php';

$o_Conn   = new Connection ('www.google.nl', 80);
$o_Poller = new Poller ();

$o_Conn -> addCallback ('onConnect', function ($o_Conn, $i_Time) {
											echo 'Connected at ' . date ('F j, Y, g:i a', $i_Time) . PHP_EOL;
									 }
					   )
		-> addCallback ('onRead', function ($o_Conn, $readBuffer, $i_Size) {
											echo 'Read ' . $i_Size . ' bytes from the stream.' . PHP_EOL;
								  }
					   );
$o_Conn -> connect ();