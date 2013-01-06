<?php
/**
* -----
*                               ,|
*                             //|                              ,|
*                           //,/                             -~ |
*                         // / |                         _-~   /  ,
*                       /'/ / /                       _-~   _/_-~ |
*                      ( ( / /'                   _ -~     _-~ ,/'
*                       \~\/'/|             __--~~__--\ _-~  _/,
*               ,,)))))));, \/~-_     __--~~  --~~  __/~  _-~ /
*            __))))))))))))));,>/\   /        __--~~  \-~~ _-~
*           -\(((((''''(((((((( >~\/     --~~   __--~' _-~ ~|
*  --==//////((''  .     `)))))), /     ___---~~  ~~\~~__--~
*          ))| @    ;-.     (((((/           __--~~~'~~/
*          ( `|    /  )      )))/      ~~~~~__\__---~~__--~~--_
*             |   |   |       (/      ---~~~/__-----~~  ,;::'  \         ,
*             o_);   ;        /      ----~~/           \,-~~~\  |       /|
*                   ;        (      ---~~/         `:::|      |;|      < >
*                  |   _      `----~~~~'      /      `:|       \;\_____//
*            ______/\/~    |                 /        /         ~------~
*          /~;;.____/;;'  /          ___----(   `;;;/
*         / //  _;______;'------~~~~~    |;;/\    /
*        //  | |                        /  |  \;;,\
*       (<_  | ;                      /',/-----'  _>
*        \_| ||_                     //~;~~~~~~~~~
*            `\_|                   (,~~
*                                    \~\
* ----
* Pegasus, irc bot of the Gods
* ----
*/
namespace Pegasus\Socket;
use OutOfBoundsException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pegasus\Socket\Poller\PollerInterface;
use Pegasus\Socket\Event\ConnectionSucceededEvent;
use Pegasus\Socket\Event\ConnectionFailedEvent;
use Pegasus\Socket\Event\DisconnectedEvent;
use Pegasus\Socket\Event\ReadExceptionEvent;
use Pegasus\Socket\Event\ReadMessageEvent;
use Pegasus\Socket\Event\WriteExceptionEvent;
use Pegasus\Socket\Event\WriteDrainMessageEvent;

class ClientSocket extends EventDispatcher implements ClientSocketInterface
{
		/**
		* Constants indicating connection status
		*/
		const STATE_EXCEPTION    = -1;
		const STATE_DISCONNECTED = 1;
		const STATE_CONNECTING   = 2;
		const STATE_CONNECTED    = 3;
		
		/**
		* Transports that are supported
		*/
		const TRANSPORT_TCP = 'tcp';
		const TRANSPORT_SSL = 'ssl';
		
		/**
		* Most important variables
		*/
		private   $pollerInstance;
		protected $resourcePointer;
		protected $resourcePointerId;
		protected $currentState;
		
		/**
		* Containing intial address as given by the user
		*/
		private $usedTransport;
		private $remoteAddress;
		private $remotePort;
		
		/**
		* Buffers containing the read and write buffer
		*/
		private $readQueue;
		private $writeQueue;
		
		
		/**
		* Initiates a new connection.
		*/
		public function __construct (PollerInterface $pollerInstance, $address, $port)
		{
				$this -> setTransport (self :: TRANSPORT_TCP);
				$this -> setAddress   ($address);
				$this -> setPort      ($port);
				
				$this -> currentState   = self :: STATE_DISCONNECTED;
				$this -> pollerInstance = $pollerInstance;				
				$this -> readQueue      = new Buffer ();
				$this -> writeQueue     = new Buffer ();
		}
		
		
		/**
		* Returns the poller we are using
		*/
		public function getPoller ()
		{
				return $this -> pollerInstance;
		}
		
		/**
		* Sets the address we should use
		*/
		public function setAddress ($address)
		{
				if ($this -> isConnected () || $this -> isConnecting ())
				{
						throw new SocketException ('Cannot change address while operating.');
				}
				
				$this -> remoteAddress = $address;
		}
		
		public function getAddress ()
		{
				return $this -> remoteAddress;
		}
		
		/**
		* Sets the port we should use
		*/
		public function setPort ($port)
		{
				if ($this -> isConnected () || $this -> isConnecting ())
				{
						throw new SocketException ('Cannot change port while operating.');
				} else if (!is_numeric ($port) || $port < 1 || $port > 65535)
				{
						throw new OutOfBoundsException ('Port is out of bounds.');
				}
				
				$this -> remotePort = (int) $port;
		}
		
		public function getPort ()
		{
				return $this -> remotePort;
		}
		
		/**
		* Sets the transport method to use for this connection.
		* See above for supported transports
		*/
		public function setTransport ($newTransport)
		{
				if ($this -> isConnected () || $this -> isConnecting ())
				{
						throw new SocketException ('Cannot change transport while operating.');
				} else if (!in_array ($newTransport, [self :: TRANSPORT_TCP, self :: TRANSPORT_SSL]))
				{
						throw new SocketException ('Transport is not supported.');
				}
				
				$this -> usedTransport = $newTransport;
		}
		
		public function getTransport ()
		{
				return $this -> usedTransport;
		}
		
		/**
		* Returns the resolved remote address and port we are connected to
		*/
		public function remoteAddress ()
		{
				return stream_socket_get_name ($this -> resourcePointer, true);
		}
		
		/**
		* Returs the local address we are using to talk to the remote machine
		*/
		public function localAddress ()
		{
				return stream_socket_get_name ($this -> resourcePointer, false);
		}
		
		
		/**
		* Returns true if the socket is connected, false otherwise
		*/
		public function isConnected ()
		{
				return ($this -> currentState == self :: STATE_CONNECTED);
		}
		
		/**
		* Returns true if the socket is connecting, false otherwise
		*/
		public function isConnecting ()
		{
				return ($this -> currentState == self :: STATE_CONNECTING);
		}

		/**
		* Returns true if the socket has an error condition, false otherwise
		*/
		public function hasException ()
		{
				return ($this -> currentState == self :: STATE_EXCEPTION);
		}
		
		/**
		* Returns true if the socket is open, false otherwise
		*/
		public function isOpen ()
		{
				return is_resource ($this -> resourcePointer);
		}
		
		/**
		* Starts a new connection attempt to connect to the remote machine,
		* or throws an exception if failed.
		*/
		public function connect (array $options = [])
		{
				if ($this -> isConnected () || $this -> isConnecting ())
				{
						return true;
				}
				
				/**
				* Create a new socket resource
				*/
				$context         = stream_context_create ($options);
				$flags           = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
				$scheme          = (string) $this -> getTransport () . '://' . $this -> getAddress () . ':' . $this -> getPort ();
				$resourcePointer = stream_socket_client ($scheme, $errno, $errstr, null, $flags, $context);
				
				/**
				* Check if we succeeded to make the resource
				*/
				if (!is_resource ($resourcePointer) || $errno || $errstr )
				{
						throw new SocketException (sprintf ('Failed to create socket resource, error: %s', $errstr));
				}
				
				/**
				* Callback to be used when the connection attempt succeeded.
				* We need to change the internal status, remove the resource from
				* the except streams, and throw an event.
				*/
				$connectCallback = function (PollerInterface $pollerInstance, $resourcePointer)
				{
						/**
						* Change socket to non-blocking
						*/
						stream_set_blocking ($resourcePointer, false);
						
						/**
						* Change state to connected
						*/
						$this -> currentState = self :: STATE_CONNECTED;
						
						/**
						* Remove from except streams
						*/
						$pollerInstance -> removeExceptStream ($this -> resourcePointerId);
						
						/**
						* Callback to be used to read for data,
						* we wrap this in a closure so I can keep the handleReadingQueue method
						* protected.
						*/
						$readCallback = function ()
						{
								return $this -> handleReadingQueue ();
						};
						
						/**
						* Insert into read streams to start checking for data (or disconect)
						*/
						$pollerInstance -> addReadStream ($resourcePointer, $readCallback);
						
						/**
						* Throw event
						*/
						$event = new ConnectionSucceededEvent ($this);
						$this -> dispatch (SocketEvents :: SOCK_CONNECTED, $event);
						
						/**
						* Now, the above round of dispatching might have invoked the write () method which caused 
						* a callback to be added to the write streams. In that case, returning false here will
						* remove that callback.
						*/
						if (!$this -> writeQueue -> isEmpty ())
						{
								$pollerInstance -> addWriteStream ($resourcePointer, function ()
								{
										$this -> handleWritingQueue ();
								});
						} else
						{
								$pollerInstance -> removeWriteStream ($this -> resourcePointerId); // remove this closure to prevent it from being called again
						}
				};
				
				/**
				* Callback to be used when the connection attempt failed.
				* Change the internal status and throw an event.
				*/
				$exceptCallback = function (PollerInterface $pollerInstance, $resourcePointer)
				{
						/**
						* Change state to exception
						*/
						$this -> currentState = self :: STATE_EXCEPTION;
						
						/**
						* Remove from write and except streams
						*/
						$pollerInstance -> removeWriteStream  ($this -> resourcePointerId);
						$pollerInstance -> removeExceptStream ($this -> resourcePointerId);
						
						/**
						* Throw event
						*/
						$event = new ConnectionFailedEvent ($this);
						$this -> dispatch (SocketEvents :: SOCK_EXCEPTION, $event);
				};
				
				/**
				* Insert callbacks to our socket poller.
				*/
				$this -> pollerInstance -> addWriteStream  ($resourcePointer, $connectCallback);
				$this -> pollerInstance -> addExceptStream ($resourcePointer, $exceptCallback);
				
				/**
				* Finalize..
				*/
				$this -> resourcePointer   = $resourcePointer;
				$this -> resourcePointerId = (int) $resourcePointer;
				$this -> currentState      = self :: STATE_CONNECTING;
				
				return true;
		}
		
		/**
		* Immediately disconnects from the remote address, leaving any data
		* in the write queue untouched. Calling this after a call to write ()
		* thus means that that data will not be written.
		*/
		public function disconnect ()
		{
				if (!$this -> isOpen ())
				{
						throw new SocketException ('Socket is not open.');
				}
				
				/**
				* Close the socket resource and change the status
				*/
				$this -> handleClosing ();
				$this -> state = self :: STATE_DISCONNECTED;
				
				return true;
		}
		
		/**
		* Writes some data to the write queue.
		* If there was not yet any data in the queue,
		* insert a callback to the poller to make sure
		* data will be written when we can.
		*/
		public function write ($dataToWrite)
		{
				if (!$this -> isConnected ())
				{
						throw new SocketException ('Cannot write to socket that is not connected.');
				} else if (strlen (trim ($dataToWrite)) == 0)
				{
						throw new SocketException ('Cannot write empty string.');
				}
				
				/**
				* Insert a callback to the poller if the write queue is currently empty.
				*/
				if ($this -> writeQueue -> isEmpty ())
				{
						$writeCallback = function ()
						{
								$this -> handleWritingQueue ();
						};
						
						$this -> pollerInstance -> addWriteStream ($this -> resourcePointer, $writeCallback);
				}
				
				/**
				* Append the data to our buffer
				*/
				$this -> writeQueue -> append ($dataToWrite);
				return true;
		}
		
		/**
		* Reads an x amount of bytes from the read queue,
		* or false if it is empty.
		*/
		public function read ($bytesToRetrieve = null)
		{
				if ($this -> readQueue -> isEmpty ())
				{
						return false;
				}
				
				$bytesToRetrieve = !empty ($bytesToRetrieve) ?: $this -> readQueue -> length ();
				return $this -> readQueue -> consume ($bytesToRetrieve);
		}
		
		/**
		* Returns one line from the readqueue.
		* You can use this in a while loop
		* while (false !== $line = $socket -> readLine ())
		*/
		public function readLine ()
		{
				if ($this -> readQueue -> isEmpty ())
				{
						return false;
				}
				
				$completeBuffer = $this -> readQueue -> toString ();
				
				/**
				* Check for a newline character in the queue
				*/
				if (false === $pos = strpos ($completeBuffer, "\n"))
				{
						return false;
				}
				
				/**
				* Get one line
				*/
				$oneLine = substr ($completeBuffer, 0, $pos);
				$this -> readQueue -> truncate ($pos + 2);
				
				return $oneLine;
		}
		
		/**
		* Reads as much lines as possible from the buffer
		* and then truncates them.
		*/
		public function readLines ()
		{
				if ($this -> readQueue -> isEmpty ())
				{
						return false;
				}
				
				$completeBuffer = $this -> readQueue -> toString ();
				
				/**
				* Check for a newline character in the queue
				*/
				if (false === $pos = strrpos ($completeBuffer, "\n"))
				{
						return false;
				}	

				$lines = substr ($completeBuffer, 0, $pos);
				$this -> readQueue -> truncate ($pos + 2);
				
				return explode ("\n", $lines);
		}
		
		/**
		* Handles reading from the socket and putting the data in the queue.
		*/
		protected function handleReadingQueue ()
		{
				/**
				* If we were in the read fd set, this can also indicate that the
				* connection was disconnected. Check for an end-of-file first.
				*/
				if (feof ($this -> resourcePointer))
				{
						$this -> handleClosing ();
						$this -> currentState = self :: STATE_DISCONNECTED;
						
						/**
						* Throw event
						*/
						$event = new DisconnectedEvent ($this);
						$this -> dispatch (SocketEvents :: SOCK_DISCONNECTED, $event);
						
						return;
				}
				
				/**
				* Attempt to read from the socket
				*/
				if (false === $fRead = fread ($this -> resourcePointer, 8196))
				{
						/**
						* Error while reading, close the connection.
						*/
						$this -> handleClosing ();
						$this -> currentState = self :: STATE_EXCEPTION;
						
						/**
						* Throw event
						*/
						$event = new ReadExceptionEvent ($this);
						$this -> dispatch (SocketEvents :: SOCK_EXCEPTION, $event);
						
						return;
				}

				$this -> readQueue -> append ($fRead);
				
				/**
				* Throw event
				*/
				$event = new ReadMessageEvent ($this);
				$this -> dispatch (SocketEvents :: SOCK_READ_QUEUE_FILLED, $event);
				
				return true;
		}
		
		/**
		* Handles writing queued data to the socket.
		*/
		protected function handleWritingQueue ()
		{
				if (false === $fWrite = fwrite ($this -> resourcePointer, $this -> writeQueue -> toString ()))
				{
						/**
						* Error while writing, close the connection.
						*/
						$this -> handleClosing ();
						$this -> currentState = self :: STATE_EXCEPTION;
						
						/**
						* Throw event
						*/
						$event = new WriteExceptionEvent ($this);
						$this -> dispatch (SocketEvents :: SOCK_EXCEPTION, $event);
						
						return;						
				}
				
				/**
				* Truncate from write buffer.
				*/
				$this -> writeQueue -> truncate ($fWrite);
				
				/**
				* Depending on whether we still have data to write, dispatch an event
				* and return either true or false.
				*/
				if ($this -> writeQueue -> isEmpty ())
				{
						$this -> pollerInstance -> removeWriteStream ($this -> resourcePointerId);
						$event = new WriteDrainMessageEvent ($this);
						$this -> dispatch (SocketEvents :: SOCK_WRITE_QUEUE_DRAIN, $event);
				}
		}
		
		/**
		* Handles closing the socket and then
		* removing the stream from the poller.
		*/
		protected function handleClosing ()
		{
				if (!is_resource ($this -> resourcePointer))
				{
						return;
				}
				
				/**
				* Shutdown and close the socket.
				*/
				stream_socket_shutdown ($this -> resourcePointer, STREAM_SHUT_RDWR);
				fclose                 ($this -> resourcePointer);
				
				/**
				* Depending on our state, check where to remove.
				*/
				if ($this -> isConnected ())
				{
						/**
						* When we are connected, we are for sure in the read fd list.
						*/
						$this -> pollerInstance -> removeReadStream ($this -> resourcePointerId);
						
						/**
						* If we had data waiting to be written, also remove us from the write fd.
						*/
						if (!$this -> writeQueue -> isEmpty ())
						{
								$this -> pollerInstance -> removeWriteStream ($this -> resourcePointerId);
						}
				} else if ($this -> isConnecting ())
				{
						$this -> pollerInstance -> removeExceptStream ($this -> resourcePointerId);
						$this -> pollerInstance -> removeWriteStream  ($this -> resourcePointerId);
				} 
		}
}