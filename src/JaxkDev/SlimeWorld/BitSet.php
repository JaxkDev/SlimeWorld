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

	/** @var int[] */
	private array $bitset;

	/**
	 * BitSet constructor.
	 * @param int[] $bitset eg [255,255,232,123] (0-255)
	 */
	public function __construct(array $bitset){
		$this->bitset = $bitset;
	}

	/**
	 * @return int[]
	 */
	public function getBitset(): array{
		return $this->bitset;
	}

	public function getBitsetString(): string{
		return implode("", array_map(function($v){
			return chr($v);
		}, $this->bitset));
	}

	public static function fromBitsetString(string $bitset): BitSet{
		return new BitSet(array_map(function($v){
			return ord($v);
		}, str_split($bitset)));
	}

	public function set(int $value, bool $state = true): void{
		$part = (int)floor($value/self::BYTE_SIZE);
		$index = $value%self::BYTE_SIZE;
		if(!array_key_exists($part, $this->bitset)){
			$this->bitset[$part] = 0;
		}
		$this->bitset[$part] = $state ? ($this->bitset[$part] | self::BITMAP[$index]) : ($this->bitset[$part] & ~self::BITMAP[$index]);
	}

	public function get(int $value): bool{
		$part = (int)floor($value/self::BYTE_SIZE);
		$index = $value%self::BYTE_SIZE;
		if(!array_key_exists($part, $this->bitset)){
			return false;
		}
		return (($this->bitset[$part] & self::BITMAP[$index]) > 0);

	}

	public function roughLength(): int{
		return sizeof($this->bitset)*8;
	}
}