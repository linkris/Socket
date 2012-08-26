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

class Connection
{
		// Global constants
		const CERT_FILE_PATH     = './';
		const CONN_MAX_TIME      = 5;
		
		// Connection details
		protected $r_Socket      = null;
		protected $i_Status      = 0;
		private $s_rAddress      = null;
		private $s_rPort         = null;
		private $b_Secure        = false;
		private $s_bindTo        = null;
		private $i_timeConnected = 0;
		
		// Buffers for data
		private  $readBuffer     = null;
		private  $writeBuffer    = null;
		
		// Callbacks
		public  $onConnect       = null;
		public  $onTerminate     = null;
		public  $onRead          = null;
		public  $onWrite         = null;
		public  $onError         = null;
		
		// Create a new connection
		public function __construct ($s_Address, $i_Port, $s_bindAddress = null, $b_Secure = false)
		{
				// Resolve address
				if (!@gethostbyname ($s_Address) && !@gethostbyaddr ($s_Address) || $i_Port > 65535 || $i_Port < 2)
				{
						throw new InvalidArgumentException ('Address "' . $s_Address . ':' . $i_Port . '" could not be resolved or is invalid.');
				}
				
				$this -> s_rAddress = $s_Address;
				$this -> i_rPort    = $i_Port;
				$this -> s_bindTo   = $s_bindAddress;
				$this -> b_Secure   = $b_Secure;
				
				if ($this -> b_Secure)
				{
						if (!extension_loaded ('openssl'))
						{
								throw new LogicException ('Cannot create secure connection withouth the OpenSSL extension loaded.');
						}
						
						if (!file_exists (self :: CERT_FILE_PATH))
						{
								throw new LogicException ('Cannot create secure connection withouth a certificate file.');
						}
				}
				
				// Create buffers
				$this -> readBuffer  = new StringBuffer ();
				$this -> writeBuffer = new StringBuffer ();
		}
		
		// Destructor, closes the connection if it is still open
		public function __destruct ()
		{
				// Check if we are connected.
				if ($this -> isConnected ())
				{
						$this -> disconnect ();
				} else
				{
						$this -> _forceClose ();
				}
				
				// Empty buffer
				$this -> readBuffer  = null;
				$this -> writeBuffer = null;
		}
		
		public function __get ($s_Member)
		{
				switch ($s_Member)
				{
						case 'readBuffer':
								return $this -> readBuffer;
								
						case 'writeBuffer':
								return $this -> writeBuffer;
								
						default:
								throw new LogicException ('Acces to "' . $s_Member . '" is not allowed.');
				}
		}
		
		// Returns address
		public function __toString ()
		{
				return $this -> s_rAddress . ':' . $this -> i_rPort;
		}
		
		// Returns the time we connected
		public function getTimeConnected ()
		{
				return $this -> i_timeConnected;
		}
		
		// Returns the current status
		public function getStatus ()
		{
				return $this -> i_Status;
		}
				
		// Returns whether the socket is connected or not
		public function isConnected ()
		{
				return ($this -> i_Status == Status :: CONNECTED);
		}
		
		// Returns whether this socket is using a secure connection
		public function isSecure ()
		{
				return $this -> b_Secure;
		}
		
		// Checks if the socket has data pending to write
		public function hasWriteBuffer ()
		{
				return $this -> writeBuffer -> hasBuffer ();
		}
		
		// Checks if the socket has data pending in its read buffer
		public function hasReadBuffer ()
		{
				return $this -> readBuffer -> hasBuffer ();
		}
				
		// Queries the remote machine to get it's address
		public function getRemoteAddress ()
		{
				if (!$this -> isConnected ())
				{
						throw new LogicException ('Cannot receive remote address on socket that is not connected.');
				}
				
				return stream_socket_get_name ($this -> r_Socket, true);
		}
		
		// Queries the local machine to get it's address
		public function getLocalAddress ()
		{
				if (!$this -> isConnected ())
				{
						throw new LogicException ('Cannot receive local address on socket that is not connected.');
				}
				
				return stream_socket_get_name ($this -> r_Socket, false);
		}
		
		// Sets the encoding for the stream
		public function setEncoding ($s_Encoding)
		{
				if (!function_exists ('mb_list_encodings')  || !in_array ($s_Encoding, mb_list_encodings ()))
				{
						return false;
				}
				
				return stream_encoding ($this -> r_Socket, $s_Encoding);
		}
		
		// Sets the internal buffer's line endings to something else
		public function setLineEnding ($s_lineEnd)
		{
				$this -> readBuffer  -> setLineEnding ($s_lineEnd);
				$this -> writeBuffer -> setLineEnding ($s_lineEnd);
				
				return true;
		}
				
		// Connects to the remote machine
		public function connect ()
		{
				if ($this -> isConnected ())
				{
						return true;
				}
				
				if ($this -> i_Status == Status :: CONNECTING)
				{
						if (!feof ($this -> r_Socket))
						{
								$this -> i_Status        = Status :: CONNECTED;
								$this -> i_timeConnected = time ();
								$this -> _doCallback ('onConnect', array ($this, $this -> i_timeConnected));
								
								return true;
						} else /** call from poller that connection failed **/
						{
								$this -> i_Status = Status :: ERROR;
								$this -> _doCallback ('onError', array ($this, Error :: CONNFAILED));
								
								return false;
						}
				}
				
				// Prepare context
				$a_Opts = array ();
				$s_Scheme = 'tcp://';
				
				// Set options for ssl connection
				if ($this -> b_Secure)
				{
						$s_Scheme       = 'ssl://';
						$a_Opts ['ssl'] = array ('verify_peer'       => false,
												 'allow_self_signed' => true,
												 'cert_file'         => self :: CERT_FILE_PATH,
												 'passphrase'        => ''
												);
				}
				
				// Set bind address
				if (!empty ($this -> s_bindTo))
				{
						$a_Opts ['socket'] = array ('bindto' => $this -> s_bindTo);
				}

				// Now, create the actual resource.
				$i_Flags          = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
				$r_Context        = stream_context_create ($a_Opts);
				$this -> r_Socket = stream_socket_client  ($s_Scheme . $this -> s_rAddress . ':' . $this -> i_rPort, $i_Errno, $s_Errstr, null, $i_Flags, $r_Context);
				
				// Check if we succeeded
				if (!is_resource ($this -> r_Socket))
				{
						$this -> i_Status = Status :: ERROR;
						$this -> _doCallback ('onError', array ($this, Error :: INITFAILED));
						return false;
				}
				
				// Change blocking status.
				stream_set_blocking ($this -> r_Socket, false);
				$this -> i_Status = Status :: CONNECTING;
				
				// Insert to our poller object
				Poller :: getInstance () -> addConnection ($this -> r_Socket, $this);
				
				return false;
		}
		
		// Terminates the connection to the remote machine
		public function disconnect ()
		{
				if (!$this -> isConnected ())
				{
						return true;
				}
				
				// Check if there is data pending..
				if ($this -> writeBuffer -> hasBuffer ())
				{
						// Bypass write () because we do not want to send any callbacks anymore
						// and do not care for errors
						if (($i_Written = @fwrite ($this -> r_Socket, $this -> writeBuffer -> getBuffer ())) !== false)
						{
								$this -> writeBuffer -> removeLength ($i_Written);
						}		
				}
				
				// Close it.
				$this -> _forceClose ();
				$this -> i_Status = Status :: DISCONNECTED_LOCAL;
				$this -> _doCallback ('onTerminate', array ($this, Status :: DISCONNECTED_LOCAL));
				
				return true;
		}
		
		// Attempts to write data from the write buffer to the remote machine
		// Returns amount of bytes written or false
		public function write ()
		{
				if (!$this -> isConnected ())
				{
						throw new LogicException ('Cannot perform write on socket that is not connected.');
				}
				
				if (($i_Written = fwrite ($this -> r_Socket, $this -> writeBuffer -> getBuffer ())) === false)
				{
						$this -> i_Status = Status :: ERROR;
						$this -> _forceClose ();
						$this -> _doCallback ('onError', array ($this, Error :: WRITEERR));
						
						return false;						
				}
				
				$this -> writeBuffer -> removeLength ($i_Written);
				$this -> _doCallback ('onWrite', array ($this, $this -> writeBuffer, $i_Written));
				
				return true;
		}
		
		// Read as much data from the stream as possible
		// Returns amount of bytes written or false
		public function read ()
		{
				if (!$this -> isConnected ())
				{
						throw new LogicException ('Cannot perform read on socket that is not connected.');
				}
				
				// Check for end-of-file
				if (feof ($this -> r_Socket))
				{
						$this -> _forceClose ();
						$this -> i_Status = Status :: DISCONNECTED_REMOTE;
						$this -> _doCallback ('onTerminate', array ($this, Status :: DISCONNECTED_REMOTE));
						
						return false;
				}
				
				// Perform read action..
				if (($s_readString = fread ($this -> r_Socket, 8192)) == false)
				{
						$this -> i_Status = Status :: ERROR;
						$this -> _forceClose ();
						$this -> _doCallback ('onError', array ($this, Error :: READERR));
						
						return false;
				}
				
				// Append to read buffer
				$this -> readBuffer -> appendBuffer ($s_readString);
				$this -> _doCallback ('onRead', array ($this, $this -> readBuffer, strlen ($s_readString)));
				
				return true;
		}
		
		// Private function to close the resource
		private function _forceClose ()
		{
				if (!is_resource ($this -> r_Socket))
				{
						return;
				}
								
				// Close the socket resource
				stream_socket_shutdown ($this -> r_Socket, STREAM_SHUT_RDWR);
				fclose                 ($this -> r_Socket);				
				$this -> r_Socket        = null;
				$this -> i_timeConnected = 0;
				
				// Remove from poller
				Poller :: getInstance () -> removeConnection ($this); 
		}
		
		// Attempts to callback to someone
		private function _doCallback ($s_Event, array $a_Args = array ())
		{
				if (isset ($this -> $s_Event) && is_callable ($this -> $s_Event))
				{
						call_user_func_array ($this -> $s_Event, $a_Args);
				}
				
				return;
		}
}