<?php
/**
* Streams library
* Copyright (c) 2012, Remco Pander
*
* This library consists of a set of classes that can be used to create high-performing
* applications using full-duplex connections. It uses the built-in stream functions
* provided with php.
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @package    Streams
* @author     Remco Pander <rpander93@gmail.com>
* @version    v1 initial release
* @copyright  Copyright (c) 2012, Remco Pander
*/

namespace Streams;
use \LogicException;
use \InvalidArgumentException;

class HttpRequest
{
		/**
		* Holds the connection object used to connect to the webpage
		*
		* @var object
		*/
		private $o_Stream  = null;
		
		/**
		* Contains data about who we are about to connect to
		*
		* @var array
		*/
		private $a_Address = array ();
		
		/**
		* An array of headers to be sent when connected
		*
		* @var array
		*/
		private $a_Headers = array ();
		
		/**
		* Optional body for the request (POST variables)
		*
		* @var array
		*/
		private $s_Body    = null;
		
		/**
		* Callbacks triggered when the page was retrieved,
		* or when something bad happened
		*
		* @var callable
		*/
		public $onSucess   = null;
		public $onFailure  = null;
		
		/**
		* Static routine that creates a new request and sets the headers appropriately
		*
		* @param  string $s_Domain Domain where the page lives
		* @param  string $s_Page   Page we want, can be left blank
		* @return object
		*/
		public static function doGet ($s_Domain, $s_Page = '/')
		{
				$o_Http = new HttpRequest ($s_Domain, $s_Page);
				
				/**
				* Build list of headers
				*/
				$o_Http -> a_Headers [] = 'GET ' . $s_Page . ' HTTP/1.1';
				$o_Http -> a_Headers [] = 'Host: ' . $s_Domain;
				$o_Http -> a_Headers [] = 'Connection: close';
		
				return $o_Http;
		}
		
		/**
		* Static routine that creates a new post request and sets the headers appropriately
		*
		* @param  string $s_Domain Domain where the page lives
		* @param  string $s_Page   Page that we want to POST to
		* @param  array $a_Variables Variables to be posted
		* @return object
		*/
		public static function doPost ($s_Domain, $s_Page, array $a_Variables)
		{
				$o_Http = new HttpRequest ($s_Domain, $s_Page);
				$s_Body = http_build_query ($a_Variables);
				
				/**
				* Build headers
				*/
				$o_Http -> a_Headers [] = 'POST ' . $s_Page . ' HTTP/1.1';
				$o_Http -> a_Headers [] = 'Host: ' . $s_Domain;
				$o_Http -> a_Headers [] = 'Connection: close';		
				$o_Http -> a_Headers [] = 'Content-Type: application/x-www-form-urlencoded';
				$o_Http -> a_Headers [] = 'Content-Length: ' . strlen ($s_Body);
				$o_Http -> s_Body       = $s_Body;
				
				return $o_Http;
		}
		
		/**
		* Creates a new connection object and sets the address
		*
		* @param  string $s_Domain Domain where the page lives
		* @param  string $s_Page   Page we want, can be left blank
		* @return void
		*/
		public function __construct ($s_Domain, $s_Page = '/')
		{
				/**
				* Create connection object and set callbacks
				*/
				$this -> o_Stream                = new Connection ($s_Domain, 80);
				$this -> o_Stream -> onConnect   = array ($this, 'sendHeaders');
				$this -> o_Stream -> onTerminate = array ($this, 'parseResult');
				$this -> o_Stream -> onError     = &$this -> onFailure;
				
				/**
				* Set internal variables
				*/
				$this -> a_Address = array ('domain' => $s_Domain,
				                            'page'   => $s_Page
										   );
		}
		
		/**
		* Clears the connection object
		*
		* @return void
		*/
		public function __destruct ()
		{
				$this -> o_Stream = null;
		}
		
		/**
		* Adds a custom header to the list of headers that has to be sent
		*
		* @param string $s_Header Header to add
		* @return void
		*/
		public function addHeader ($s_Header)
		{
				$this -> s_Headers [] = $s_Header;
				return;
		}
		
		/**
		* Connects the transport endpoint
		*
		* @return bool
		*/
		public function execute ()
		{
				if ($this -> o_Stream -> getStatus () > Status :: INIT)
				{
						return true;
				}
				
				return $this -> o_Stream -> connect ();
		}
		
		/**
		* Called when connection to the domain has been made.
		* Now we need to send the headers to the remote endpoint.
		*
		* @param  object $o_Connection Connection object
		* @param  int    $i_connTime   Time connection was made
		* @return void
		*/
		public function sendHeaders (Connection $o_Connection, $i_connTime)
		{
				/**
				* Build the headers that we want to send
				*/
				$s_String = implode ("\r\n", $this -> a_Headers) . "\r\n";
				
				/**
				* If this is a POST request, also add the post data
				*/
				if (!empty ($this -> s_Body))
				{
						$s_String .= $this -> s_Body;
				}
				
				/**
				* Add the headers to the write buffer
				*/
				$o_Connection -> writeBuffer -> appendBuffer ($s_String);
				return;
		}
		
		/**
		* Called when the connection was terminated.
		* This means that all data has been received and
		* the webserver closed the connection.
		*
		* @param  object $o_Connection Connection ojbect
		* @param  int    $i_whoClosed  Who closed the connection?
		* @return void
		*/
		public function parseResult (Connection $o_Connection, $i_whoClosed)
		{
				/**
				* Fetch the result and separate headers from body
				*/
				$a_httpResponse = explode ("\r\n\r\n", $o_Connection -> readBuffer -> getBuffer ());
				$a_httpResponse = array ('headers' => $a_httpResponse [0],
				                         'body'    => $a_httpResponse [1]
										);
										
				/**
				* Do a callback
				*/
				if (isset ($this -> onSuccess) && is_callable ($this -> onSuccess))
				{
						call_user_func_array ($this -> onSuccess, array ($this, $a_httpResponse));
				}
				
				return;
		}
}