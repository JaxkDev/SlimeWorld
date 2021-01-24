<?php
/*
 	SlimeWorld, a pmmp plugin implementing Hypixel's slime world format.
    Copyright (C) 2021  JaxkDev [JaxkDev@gmail.com]

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published
    by the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace JaxkDev\SlimeWorld;

class BitSet{

	const BYTE_SIZE = 8;
	const BITMAP = [
		0b1,
		0b10,
		0b100,
		0b1000,
		0b10000,
		0b100000,
		0b1000000,
		0b10000000
	];

	private string $bitset;

	private function __construct(string $bitset){
		$this->bitset = $bitset;
	}

	public function getBitset(): string{
		return $this->bitset;
	}

	public static function fromString(string $bitset): BitSet{
		return new BitSet($bitset);
	}

	public static function fromRoughSize(int $size): BitSet{
		return new BitSet(str_repeat(chr(0), $size));
	}

	public static function fromSetSize(int $size): BitSet{
		return self::fromRoughSize((int)ceil($size/8));
	}

	public function set(int $value, bool $state = true): void{
		$index = (int)floor($value/self::BYTE_SIZE);
		$bit = self::BITMAP[$value%self::BYTE_SIZE];
		if(($diff = ($index-(strlen($this->bitset)-1))) > 0){
			$this->bitset .= str_repeat(chr(0), $diff);
		}
		$this->bitset[$index] = $state ? chr(ord($this->bitset[$index]) | $bit) : chr(ord($this->bitset[$index]) & ~$bit);
	}

	public function get(int $value): bool{
		$index = (int)floor($value/self::BYTE_SIZE);
		$bit = self::BITMAP[$value%self::BYTE_SIZE];
		if(strlen($this->bitset)-1 < $index){
			return false;
		}
		return ((ord($this->bitset[$index]) & $bit) > 0);

	}

	public function roughLength(): int{
		return strlen($this->bitset)*8;
	}
}