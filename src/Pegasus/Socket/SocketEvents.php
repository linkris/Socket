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

final class SocketEvents
{
		/**
		* Event thrown when a connection attempt on a client socket succeeded.
		*/
		const SOCK_CONNECTED         = 'socket.connected';
		
		/**
		* Event thrown when a connection was terminated by the remote machine.
		* So this will not be thrown when the user calls disconnect ().
		*/
		const SOCK_DISCONNECTED      = 'sock.disconnected';
		
		/**
		* Event thrown when an error occurred during some non-blocking operation.
		*/
		const SOCK_EXCEPTION         = 'sock.exception';
		
		/**
		* Event thrown when a server sock has accepted a new connection
		* and the connection is ready to be used.
		*/
		const SOCK_ACCEPTED_CONN     = 'sock.accepted.conn';
		
		/**
		* Event thrown when data has been read from the socket and is avaliable
		* in the internal buffer
		*/
		const SOCK_READ_QUEUE_FILLED = 'sock.readable';
		
		/**
		* Event thrown when the internal write buffer is depleted.
		*/
		const SOCK_WRITE_QUEUE_DRAIN = 'sock.write.drain';
}