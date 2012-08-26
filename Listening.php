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

class Listening
{		
		// Global constants
		const CERT_FILE_PATH   ='./';
		
		// Stream details
		private $r_Socket      = null;
		private $i_Status      = 0;
		private $s_lAddress    = null;
		private $i_lPort       = 0;
		private $i_listenStart = 0;
		private $i_Accepted    = 0;
		private $b_Secure      = false;
		
		// Callbacks
		public  $onAccept      = null;
		public  $onTerminate   = null;
		public  $onError       = null;
		
		public function __construct ($i_Port = 0, $s_Address = null, $b_Secure = false)
		{
				if ($b_Secure)
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
				
				$this -> i_lPort    = $i_Port;
				$this -> s_lAddress = null;
				$this -> b_Secure   = $b_Secure;
		}
		
		public function isListening ()
		{
				return ($this -> i_Status == Status :: LISTENING);
		}
		
		public function getStatus ()
		{
				return $this -> i_Status;
		}
		
		public function getAcceptedCount ()
		{
				return $this -> i_Accepted;
		}
		
		public function getListenStarted ()
		{
				return $this -> i_listenStart;
		}
		
		public function getListenPort ()
		{
				return $this -> i_lPort;
		}
		
		public function getLocalAddress ()
		{
				if (!$this -> isListening ())
				{
						throw new LogicException ('Cannot retrieve local address on stream that is not listening.');
				}
				
				return stream_socket_get_name ($this -> r_Socket, false);
		}
		
		// Start listening on the given port and address
		public function listen ()
		{
				if ($this -> isListening ())
				{
						return true;
				}
				
				$s_Scheme = 'tcp://';
				$a_Opts   = array ();
				
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
				
				$i_Flags          = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
				$r_Context        = stream_context_create ($a_Opts);
				$this -> r_Socket = stream_socket_server  ($s_Scheme . $this -> s_lAddress . ':' . $this -> i_lPort, $i_Errno, $s_Errstr, $i_Flags, $r_Context);
				
				// Check if we succeeded
				if (!is_resource ($this -> r_Socket))
				{
						$this -> i_Status = Status :: ERROR;
						$this -> _doCallback ('onError', array ($this, Error :: INITFAILED));
						return false;
				}
				
				// Change blocking status
				stream_set_blocking ($this -> r_Socket, false);
				$this -> i_Status      = Status :: LISTENING;
				$this -> i_listenStart = time ();
				
				// Fetch the port on which we are listening now..
				if ($this -> i_lPort == 0)
				{
						$this -> i_lPort = substr ($this -> getLocalAddress (), strrpos ($this -> getLocalAddress (), ':'));
				}
				
				// Add to poller
				Poller :: getInstance () -> addConnection ($this -> r_Socket, $this);
				
				return true;
		}
		
		// Stop listening
		public function terminate ()
		{
				if (!$this -> isListening ())
				{
						return true;
				}
				
				// Close stream
				$this -> _forceClose ();
				$this -> i_Status = Status :: DISCONNECTED_LOCAL;
				$this -> i_listenStart = 0;
				$this -> _doCallback ('onTerminate', array ($this));
				
				return true;
		}
		
		// Accept an incoming connection and pass it on to the callback function
		public function accept ()
		{
				if (!$this -> isListening ())
				{
						throw new RuntimeException ('Cannot accept on a stream that is not listening.');
				}
				
				// Fetch the resource
				if (($r_Socket = stream_socket_accept ($this -> r_Socket, 0, $s_Address)) == false)
				{
						$this -> _doCallback ('onError', array ($this, Error :: ACCEPTERR));
						return false;
				}
				
				$o_Socket = new Accept ($r_Socket, $s_Address, $this -> b_Secure);
				$this -> i_Accepted++;
				
				// Callback,
				// if it has none, disconnect the accepted connection and remove it.
				if (isset ($this -> $s_Event) && is_callable ($this -> $s_Event))
				{
						Poller :: getInstance () -> addConnection ($r_Socket, $o_Socket);
						$this -> _doCallback ('onAccept', array ($this, $o_Socket));
				} else
				{
						$o_Socket -> disconnect ();
						unset ($o_Socket);
				}
				
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
				$this -> i_listenStart   = 0;
				
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