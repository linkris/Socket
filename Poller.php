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
use \InvalidArgumentException;
use \RuntimeException;

final class Poller
{
		// Instance of the poller
		private static $_instance = null;
		
		// List of all connections to monitor
		private $a_connList  = array ();
		private $a_connSocks = array ();
		
		// Returns instance of this class
		public static function getInstance ()
		{
				if (empty (self ::  $_instance))
				{
						self ::  $_instance = new Poller ();
				}
				
				return self ::  $_instance;
		}
		
		// Allow no creation of this class
		private function __construct ()
		{
		}		
		
		public function hasConnection (Connection $o_Conn)
		{
				return in_array ($o_Conn, $this -> a_connList);
		}
		
		public function getCount ()
		{
				return count ($this -> a_connList);
		}
		
		public function addConnection ($r_Socket, $o_Conn)
		{
				if (!$o_Conn instanceof Connection && !$o_Conn instanceof Listening)
				{
						throw new InvalidArgumentException ('Poller :: addConnection () expects parameter 2 to be an instance of Connection or Listening.');
				} else if (!is_resource ($r_Socket))
				{
						throw new InvalidArgumentException ('Poller :: addConnection () expects parameter 1 to be a valid resource.');
				} else if (in_array ($o_Conn, $this -> a_connList))
				{
						return true;
				}
				
				$i_Intval                        = intval ($r_Socket);
				$this -> a_connList [$i_Intval]  = $o_Conn;
				$this -> a_connSocks [$i_Intval] = $r_Socket;
				
				return true;
		}
		
		public function removeConnection ($o_Conn)
		{
				if (!$o_Conn instanceof Connection && !$o_Conn instanceof Listening)
				{
						throw new InvalidArgumentException ('Poller :: addConnection () expects parameter 2 to be an instance of Connection or Listening.');
				} 
				
				if (!in_array ($o_Conn, $this -> a_connList))
				{
						return true;
				}
				
				$i_Intval = array_search ($o_Conn, $this -> a_connList, true);
				unset ($this -> a_connList [$i_Intval]);
				unset ($this -> a_connSocks [$i_Intval]);
				
				return true;
		}
		
		public function pollConnections ($f_blockTime = 0)
		{
				if ($this -> getCount () < 1)
				{
						return false;
				}
				
				// Calculate time-out
				if (!is_float ($f_blockTime) && !is_int ($f_blockTime))
				{
						$i_Seconds  = null;
						$i_mSeconds = null;
				} else
				{
						$i_Seconds  = floor ($f_blockTime);
						$i_mSeconds = (($f_blockTime - $i_Seconds) * 1e6);
				}
				
				// Create fd sets
				$a_readSock = $a_writeSocks = $a_exceptSocks = array ();
				
				foreach ($this -> a_connList as $i_Intval => $o_Connection)
				{
						$r_Socket = $this -> a_connSocks [$i_Intval];
						
						switch ($o_Connection -> getStatus ())
						{
								case Status :: CONNECTING:
										$a_writeSocks [] = $r_Socket;
										break;
								
								case Status :: CONNECTED:
										$a_readSocks   [] = $r_Socket;
										$a_exceptSocks [] = $r_Socket;
										
										if ($o_Connection -> hasWriteBuffer ())
										{
												$a_writeSocks [] = $r_Socket;
										}
										
										break;
										
								case Status :: LISTENING:
										$a_readSocks [] = $r_Socket;
										break;
						}
				}
				
				// Here comes the magic part
				if (($i_Changed = stream_select ($a_readSocks, $a_writeSocks, $a_exceptSocks, $i_Seconds, $i_mSeconds)) === false)
				{
						throw new RuntimeException ('Fatal error occurred while performing stream_select ().');
				} else if ($i_Changed < 1)
				{
						return false;
				}
				
				/**
				* Readable sockets are sockets that:
				* 1. listening for connections with an incoming connection
				* 2. data is avaliable to be read
				* 3. end-of-file has been reached
				*/
				if (count ($a_readSocks) > 0)
				{
						foreach ($a_readSocks as $r_Socket)
						{
								$o_Connection = $this -> a_connList [intval ($r_Socket)];
								
								switch ($o_Connection -> getStatus ())
								{
										case Status :: LISTENING:
												$o_Connection -> accept ();
												break;
												
										case Status :: CONNECTED:
												$o_Connection -> read ();
												break;
								}
						}
				}
				
				/**
				* Writable sockets are sockets that:
				* 1. sockets that were connecting and now are connected
				* 2. data can be written
				*/
				if (count ($a_writeSocks) > 0)
				{
						foreach ($a_writeSocks as $r_Socket)
						{
								$o_Connection = $this -> a_connList [intval ($r_Socket)];
								
								switch ($o_Connection -> getStatus ())
								{
										case Status :: CONNECTING:
												$o_Connection -> connect ();
												break;
												
										case Status :: CONNECTED:
												$o_Connection -> write ();
												break;
								}
						}
				}
				
				/**
				* Except sockets are sockets that:
				* 1. sockets that were connecting and it failed
				* 2. Out-Of-Band data can be read
				*/
				if (count ($a_exceptSocks) > 0)
				{
						foreach ($a_exceptSocks as $r_Socket)
						{
								$o_Connection = $this -> a_connList [intval ($r_Socket)];
								
								switch ($o_Connection -> getStatus ())
								{
										case Status :: CONNECTING:
												$o_Connection -> connect ();
												break;
												
										case Status :: CONNECTED:
												// @todo
												break;
								}
						}
				}		

				return true;
		}
}