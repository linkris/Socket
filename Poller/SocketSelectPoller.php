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
namespace Pegasus\Socket\Poller;
use \OutOfBoundsException,
	\RuntimeException;

class SocketSelectPoller implements PollerInterface
{
        private $readStreams;
        private $readListeners;

        private $writeStreams;
        private $writeListeners;

        private $exceptStreams;
        private $exceptListeners;

        private $allowedBlockTime;

		
        public function addReadStream ($stream, callable $listener)
        {
            $id                          = (int) $stream;
            $this -> readStreams   [$id] = $stream;
            $this -> readListeners [$id] = $listener;
        }

        public function removeReadStream ($stream)
        {
                $id = (int) $stream;

                if (!isset ($this -> readStreams [$id])) 
				{
                        throw new OutOfBoundsException ('Read stream not avaliable.');
                }

                unset ($this -> readStreams   [$id],
                       $this -> readListeners [$id]
                );
        }

        public function addWriteStream ($stream, callable $listener)
        {
                $id                           = (int) $stream;
                $this -> writeStreams   [$id] = $stream;
                $this -> writeListeners [$id] = $listener;
        }

        public function removeWriteStream ($stream)
        {
            $id = (int) $stream;

            if (!isset ($this -> writeStreams [$id]))
			{
                    throw new OutOfBoundsException ('Write stream is not avaliable.');
            }

            unset ($this -> writeStreams   [$id],
                   $this -> writeListeners [$id]
            );
        }

        public function addExceptStream ($stream, callable $listener)
        {
                $id                            = (int) $stream;
                $this -> exceptStreams   [$id] = $stream;
                $this -> exceptListeners [$id] = $listener;
        }

        public function removeExceptStream ($stream)
        {
                $id = (int) $stream;

                if (!isset ($this -> exceptStreams [$id])) 
				{
                       throw new OutOfBoundsException ('Except stream is not avaliable.');
                }

                unset ($this -> exceptStreams   [$id],
                       $this -> exceptListeners [$id]
                );
        }

        public function setAllowedBlockTime ($time)
        {
                $this -> allowedBlockTime = (float) $time;
        }

        public function getAllowedBlockTimeSeconds ()
        {
				if (empty ($this -> allowedBlockTime) || $this -> allowedBlockTime < 0)
				{
						return ['seconds'      => null,
						        'microseconds' => null
						];
				}
				
                $seconds      = floor ($this -> allowedBlockTime);
				$microseconds = intval (($this -> allowedBlockTime - $seconds) * 1e6);
				
				return ['seconds'      => $seconds,
				        'microseconds' => $microseconds
				];
        }

        public function poll ()
        {
                $read    = $this -> readStreams   ?: [];
                $write   = $this -> writeStreams  ?: [];
                $except  = $this -> exceptStreams ?: [];
                $timeout = $this -> getAllowedBlockTimeSeconds ();
				
				if ((count ($read) + count ($write) + count ($except)) == 0)
				{
						return;
				}
				
                if (false === $select = stream_select ($read, $write, $except, $timeout ['seconds'], $timeout ['microseconds'])) 
				{
                        throw new RuntimeException ('Fatal error while attempting to perform stream_select ().');
                } else if ($select === 0)
				{
                        return;
                }
				
                /**
                * Readability indicates:
                * 1. Data is avaliable to read
                * 2. Connection can be accepted
                * 3. End-of-file reached
                */
                foreach ($read as $stream) 
				{
                        $id       = (int) $stream;
                        $listener = $this -> readListeners [$id];

                        call_user_func ($listener, $this, $stream);
                }

                /**
                * Writability indicates:
                * 1. Writing will not block
                * 2. Connection established.
                */
                foreach ($write as $stream) 
				{
                        $id       = (int) $stream;
                        $listener = $this -> writeListeners [$id];

                        call_user_func ($listener, $this, $stream);
                }

                /**
                * Exceptability indicates:
                * 1. Out-of-Band data is avalaliable
                * 2. Connection attempt failed
                */
                foreach ($except as $stream)
				{
                        $id       = (int) $stream;
                        $listener = $this -> exceptListeners [$id];

                        call_user_func ($listener, $this, $stream);
                }

                return;
        }
}
