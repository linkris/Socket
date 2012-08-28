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

class Accept extends Connection
{
		/**
		* Constructor, changes the internal socket resource, sets the address and
		* adds the connection to our main socket poller
		*
		* @param  resource $r_Socket  Socket that has been accepted
		* @param  string   $s_Address Address of the peer that connected to us
		* @param  bool     $b_Secure  Secure connection?
		* @return void
		*/
		public function __construct ($r_Socket, $s_Address, $b_Secure)
		{
				// Separate port from address
				$s_rAddress = substr ($s_Address, 0, strrpos ($s_Address, ':'));
				$i_rAddress = substr ($s_Address, strpos ($s_Address, ':'));
				
				// Initiate connection object
				parent :: __construct ($s_rAddress, $i_rAddress, null, $b_Secure);
				
				// Change properties.
				$this -> r_Socket = $r_Socket;
				$this -> i_Status = Status :: CONNECTED;
				
				// Add socket to poller
				Poller :: getInstance () -> addConnection ($r_Socket, $this);
		}
}