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
		private $o_Connection = null;
		private $a_Headers    = null;
		private $s_Body       = null;
		private $s_Site       = null;
		private $s_Page       = null;
		
		public  $onSuccess    = null;
		public  $onFailure    = null;
		
		public static function doGet ($s_Site, $s_Page = '')
		{
				$conn = new HttpRequest ($s_Site, $s_Page);
				
				// Fill headers..
				$conn -> a_Headers [] = "GET /" . $s_Page . " HTTP/1.1\r\n";
				$conn -> a_Headers [] = "Host: " . $s_Site . "\r\n";
				$conn -> a_Headers [] = "Connection: close\r\n";
				$conn -> a_Headers [] = "\r\n";
				
				// Return object
				return $conn;
		}
		
		public static function doPost ($s_Site, $s_Page, array $a_Fields = array ())
		{
				$conn     = new HttpRequest ($s_Site, $s_Page);
				$s_Fields = http_build_query ($a_Fields);
				
				// Fill headers
				$conn -> a_Headers [] = "POST /" . $s_Page . " HTTP/1.1";
				$conn -> a_Headers [] = "Host: " . $s_Site;
				$conn -> a_Headers [] = "Connection: close";
				$conn -> a_Headers [] = "Content-Type: application/x-www-form-urlencoded";
				$conn -> a_Headers [] = "Content-Length: " . strlen ($s_Fields);
				$conn -> s_Body       = $s_Fields;
				
				// Return object
				return $conn;
		}
		
		public function __construct ($s_Site, $s_Page)
		{
				$this -> s_Site = $s_Site;
				$this -> s_Page = $s_Page;
				
				// Create connection and set callbacks.
				$this -> o_Connection                = new Connection ($s_Site, 80);
				$this -> o_Connection -> onConnect   = array ($this, 'onConnect');
				$this -> o_Connection -> onTerminate = array ($this, 'onTerminate');
				$this -> o_Connection -> onError     = array ($this, 'onError');
		}
		
		public function __destruct ()
		{
				$this -> o_Connection = null;
		}
		
		public function execute ()
		{
				return $this -> o_Connection -> connect ();
		}
		
		public function onConnect ($o_Connection, $i_connTime)
		{
				// Build headers
				$s_Headers   = implode ("\r\n", $this -> a_Headers);
				$s_Headers .= "\r\n";
				
				if (!empty ($this -> s_Body))
				{
						$s_Headers .= $this -> s_Body;
				}
				
				// Send headers
				$o_Connection -> writeBuffer -> appendBuffer ($s_Headers);
				return true;
		}
		
		public function onError ($o_Connection, $i_Error)
		{
				if (isset ($this -> onFailure) && is_callable ($this -> onFailure))
				{
						call_user_func_array ($this -> onFailure, array ($this, $i_Error));
				}
		}
		
		public function onTerminate ($o_Connection, $i_whoClosed)
		{
				// Request finished, filter headers from response.
				$s_httpResponse = $o_Connection -> readBuffer -> getBuffer ();
				$a_httpResponse = explode ("\r\n\r\n", $s_httpResponse);			
				$a_httpResponse  = array ('headers' => $a_httpResponse [0],
				                          'body'    => $a_httpResponse [1]);
										 
				if (isset ($this -> onSuccess) && is_callable ($this -> onSuccess))
				{
						call_user_func_array ($this -> onSuccess, array ($this, $a_httpResponse));
				}
				
				return true;
		}
}