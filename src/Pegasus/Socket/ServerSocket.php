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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pegasus\Socket\Poller\PollerInterface;
use Pegasus\Socket\Event\AcceptingFailedEvent;
use Pegasus\Socket\Event\AcceptingSucceededEvent;

class ServerSocket extends EventDispatcher implements ServerSocketInterface
{
		const STATE_CLOSED    = 1;
		const STATE_LISTENING = 2;
		
		const TRANSPORT_TCP = 'tcp';
		const TRANSPORT_SSL = 'ssl';
			
		/**
		* Most important variables
		*/
		private $pollerInstance;
		private $resourcePointer;
		private $resourcePointerId;
		private $currentState;
		
		private $listenAddress;
		private $listenPort;
		
		
		/**
		* Instantiates new server socket
		*/
		public function __construct (PollerInterface $pollerInstance, $listenAddress = null, $listenPort = null)
		{
				$this -> pollerInstance = $pollerInstance;
				$this -> setAddress   ($listenAddress);
				$this -> setPort      ($listenPort);
				$this -> setTransport (self :: TRANSPORT_TCP);
		}

		/**
		* Returns the poller we are using
		*/
		public function getPoller ()
		{
				return $this -> pollerInstance;
		}
		
		/**
		* Sets the address to listen on, defaults to
		* localhost.
		*/
		public function setAddress ($listenAddress)
		{
				if ($this -> isListening ())
				{
						throw new SocketException ('Cannot change local address while listening.');
				}
				
				$this -> listenAddress = !empty ($listenAddress) ?: '127.0.0.1';
		}
		
		public function getAddress ()
		{
				return $this -> listenAddress;
		}
		
		/**
		* Sets the port to listen on, defaults to
		* 0 which means that the system will choose a random port.
		*/
		public function setPort ($listenPort)
		{
				if ($this -> isListening ())
				{
						throw new SocketException ('Cannot change local port while listening.');
				}
				
				$this -> listenPort = !empty ($listenPort) ? $listenPort : 0;
		}
		
		public function getPort ()
		{
				return $this -> listenPort;
		}
		
		/**
		* Sets the transport method to use for this server.
		* See above for supported transports
		*/
		public function setTransport ($newTransport)
		{
				if ($this -> isListening ())
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
		* Returns true if we are currently listening, false otherwise
		*/
		public function isListening ()
		{
				return $this -> currentState == self :: STATE_LISTENING;
		}
		
		/**
		* Returns true if the socket is open, false otherwise
		*/
		public function isOpen ()
		{
				return is_resource ($this -> resourcePointer);
		}
		
		/**
		* Creates a new listening socket ready to accept connections
		*/
		public function listen (array $options = [])
		{
				if ($this -> isListening ())
				{
						return true;
				}
				
				$flags            = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
				$context          = stream_context_create ($options);
				$scheme           = (string) $this -> getTransport () . '://' . $this -> getAddress () . ':' . $this -> getPort ();
				$resourcePointer  = stream_socket_server ($scheme, $errno, $errstr, $flags, $context);
				
				/**
				* Determine on which port we bound.
				*/
				if ($this -> getPort () == 0)
				{
						$localAddress = stream_socket_get_name ($resourcePointer, false);
						$port         = substr ($localAddress, strrpos ($localAddress, ':') + 1);
						
						$this -> setPort ($port);
				}
				
				/**
				* Check if we succeeded to make the resource
				*/
				if (!is_resource ($resourcePointer) || $errno || $errstr )
				{
						throw new SocketException (sprintf ('Failed to create socket resource, error: %s', $errstr));
				}
				
				/**
				* Callback for when a new connection has been received.
				*/
				$acceptCallback = function (PollerInterface $pollerInterface, $resourcePointer)
				{
						if (false === $newConnection = stream_socket_accept ($resourcePointer))
						{
								$event = new AcceptingFailedEvent ($this);
								$this -> dispatch (SocketEvents :: SOCK_EXCEPTION, $event);
								
								return;
						}
						
						$socket = $this -> handleNewConnection ($newConnection);
						
						if ($this -> getTransport () == self :: TRANSPORT_SSL)
						{
								$socket -> setTransport (ClientStream :: TRANSPORT_SSL);
						}
						
						$event  = new AcceptingSucceededEvent ($this, $socket);
						$this -> dispatch (SocketEvents :: SOCK_ACCEPTED_CONN, $event);
						
						return;
				};
				
				/**
				* Insert callback into poller
				*/
				$this -> pollerInstance -> addReadStream ($resourcePointer, $acceptCallback);
				
				/**
				* Finishing up..
				*/
				$this -> currentState      = self :: STATE_LISTENING;
				$this -> resourcePointer   = $resourcePointer;
				$this -> resourcePointerId = (int) $resourcePointer;
				
				return true;
		}
		
		public function terminate ()
		{
				if (!$this -> isListening ())
				{
						return true;
				}
				
				/**
				* Close the resource
				*/
				stream_socket_shutdown ($this -> resourcePointer, STREAM_SHUT_RDWR);
				fclose                 ($this -> resourcePointer);
				
				/**
				* Remove from read streams
				*/
				$this -> pollerInstance -> removeReadStream ($this -> resourcePointerId);
				$this -> currentState = self :: STATE_CLOSED;
				
				return true;
		}
		
		protected function handleNewConnection ($resourcePointer)
		{
				return new AcceptedClientSocket ($this -> pollerInstance, $resourcePointer, $this);
		}
}