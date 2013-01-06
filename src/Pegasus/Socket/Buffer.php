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

class Buffer
{
        private $buffer;
		
		public function length ()
		{
				return strlen ($this -> buffer);
		}

        public function isEmpty ()
        {
                return $this -> length () == 0;
        }

        public function append ($data)
        {
                $this -> buffer .= (string) $data;
        }

        public function prepend ($data)
        {
                $this -> buffer = (string) $data . $this -> buffer;
        }

        public function consume ($length)
        {
                $line           = substr ($this -> buffer, 0, $length);
                $this -> buffer = substr ($this -> buffer, $length);

                return $line;
        }

        public function truncate ($length)
        {
                $this -> buffer = substr ($this -> buffer, $length);
        }

        public function flush ()
        {
                $this -> buffer = '';
        }

        public function toString ()
        {
                return $this -> buffer;
        }
}
