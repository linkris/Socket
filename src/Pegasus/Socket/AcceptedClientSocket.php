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
use Pegasus\Socket\Poller\PollerInterface;

class AcceptedClientSocket extends ClientSocket
{
		private $parentSocket;
		
		
		public function __construct (PollerInterface $pollerInstance, $resourcePointer, SocketInterface $server)
		{
				/**
				* Set resource pointer.
				*/
				$this -> resourcePointer   = $resourcePointer;
				$this -> resourcePointerId = (int) $resourcePointer;
				
				/**
				* Determine remote address and port.
				*/
				$remoteAddress             = $this -> remoteAddress ();
				$address                   = substr ($remoteAddress, 0, strrpos ($remoteAddress, ':'));
				$port                      = substr ($remoteAddress, strrpos ($remoteAddress, ':') + 1);
				
				/**
				* Initiate stuff.
				*/
				parent :: __construct ($pollerInstance, $address, $port);
				
				/**
				* Change status
				*/
				$this -> currentState     = ClientSocket :: STATE_CONNECTED;
				
				/**
				* Set parent
				*/
				$this -> parentSocket     = $server;
		}
		
		public function getParent ()
		{
				return $this -> parentSocket;
		}
}