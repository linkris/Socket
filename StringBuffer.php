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

class StringBuffer
{
		// Buffer
		private $s_Buffer = null;
		private $i_Length = 0;
		private $s_lineEnd = "\n";
		
		/**
		* Sets the default line delimiter
		*
		* @param  string $s_newEnding
		* @return void
		*/
		public function setLineEnding ($s_newEnding)
		{
				$this -> s_lineEnd = (string) $s_newEnding;
		}
		
		/**
		* Returns all of the buffer
		*
		* @return string
		*/
		public function __toString ()
		{
				return $this -> s_Buffer;
		}
		
		/**
		* Same as __toString ()
		*/
		public function toString ()
		{
				return $this -> s_Buffer;
		}
		
		/**
		* Returns an array of lines in the buffer
		*
		* @return array|false
		*/
		public function toArray ()
		{
				return $this -> getLines ();
		}
		
		
		/**
		* Appends the buffer with data
		*
		* @param string $s_String
		* @return bool
		*/
		public function appendBuffer ($s_String)
		{
				$this -> s_Buffer .= (string) $s_String;
				$this -> i_Length += strlen  ($s_String);
				
				return true;
		}
		
		/**
		* Prepends the buffer with data
		*
		* @pararm string $s_String
		* @return true
		*/
		public function prependBuffer ($s_String)
		{
				$this -> s_Buffer  = $s_String .  $this -> s_Buffer;
				$this -> i_Length += strlen ($s_String);
				
				return true;
		}

		/**
		* Returns bool whether the buffer is filled
		*
		* @return bool
		*/
		public function hasBuffer ()
		{
				return ($this -> i_Length > 0);
		}
		
		/**
		* Returns bool whether the buffer has atleast one line
		*
		* @return bool
		*/
		public function hasLine ()
		{
				return stripos ($this -> s_Buffer, $this -> s_lineEnd);
		}
		
		/**
		* Returns the size in bytes of the buffer
		*
		* @return int
		*/
		public function getSize ()
		{
				return $this -> i_Length;
		}
				
		/**
		* Removes a defined length of bytes from the beginning or end of the buffer
		*
		* @param  int $i_Length Length to remove
		* @param  bool $b_Prepend Remove from beginning or end?
		* @throws InvalidArgumentException Length is negative
		* @return true
		*/
		public function removeLength ($i_Length, $b_Prepend = true)
		{
				if ($i_Length < 0)
				{
						throw new InvalidArgumentException ('StringBuffer :: removeLength () expects parameter 1 to be positive.');
				} else if ($i_Length > $this -> i_Length)
				{
						$this -> s_Buffer = null;
						$this -> i_Length = 0;
						
						return true;
				}
				
				// Remove from beginning or end?
				if ($b_Prepend)
				{
						$this -> s_Buffer  = substr ($this -> s_Buffer, $i_Length);
						$this -> i_Length -= $i_Length;
				} else
				{
						$this -> s_Buffer  = substr ($this -> s_Buffer, 0, $i_Length);
						$this -> i_Length -= $i_Length;
				}
				
				return true;
		}
		
		/**
		* Returns all of the buffer, without removing it
		*
		* @return string
		*/
		public function getBuffer ()
		{
				return $this -> s_Buffer;
		}
		
		/**
		* Clears all content of the buffer
		*
		* @return true
		*/
		public function flushBuffer ()
		{
				$this -> s_Buffer = null;
				$this -> i_Lenght = 0;
				
				return true;
		}
		
		/**
		* Returns one line from the buffer
		* or false if it has none.
		*
		* @return string|false
		public function getLine ()
		{
				if (!$this -> hasLine ())
				{
						return false;
				}
				
				$i_Offset         = stripos ($this -> s_Buffer, $this -> s_lineEnd);
				$s_theLine        = substr ($this -> s_Buffer, 0, $i_Offset);
				$this -> s_Buffer = substr ($this -> s_Buffer, $i_Offset + strlen ($this -> s_lineEnd) + 1);
				$this -> i_Size  -= ($i_Offset + strlen ($this -> s_lineEnd));
				
				return $s_theLine;
		}
		
		/**
		* Returns as many lines from the buffer as possible
		* as an array, or false if it has none.
	    *
		* @return array|false
		*/
		public function getLines ()
		{
				if (!$this -> hasLine ())
				{
						return false;
				}
				
				$i_Offset         = strripos ($this -> s_Buffer, $this -> s_lineEnd);
				$s_Lines          = substr ($this -> s_Buffer, 0, $i_Offset);
				$this -> s_Buffer = substr ($this -> s_Buffer, $i_Offset + strlen ($this -> s_lineEnd) + 1);
				$this -> i_Size  -= ($i_Offset + strlen ($this -> s_lineEnd));
				
				return explode ($this -> s_lineEnd, $s_Lines);
		}
}