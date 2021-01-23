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

use AssertionError;
use pocketmine\nbt\tag\CompoundTag;

class SlimeWorld{

	const FORMAT_HEADER = 0xB10B;
	const FORMAT_VERSIONS = [1,3]; //Not seen any v2's unsure if it was public if so i do not know the changes.
	const FORMAT_CURRENT_VERSION = 3;

	public static function fromFile(string $file): SlimeWorld{
		$data = file_get_contents($file);
		$bs = new SlimeBinaryStream($data);

		/**
		 * “Slime” file format
		 * https://pastebin.com/raw/EVCNAmkw
		 */

		/**
		 * 2 bytes - magic = 0xB10B
		 */
		$header = $bs->getShort();
		if($header !== SlimeWorld::FORMAT_HEADER){
			throw new AssertionError("Header invalid.");
		}

		/**
		 * 1 byte (ubyte) - version, current = 0x03
		 */
		$version = $bs->getByte();
		if(!in_array($version, SlimeWorld::FORMAT_VERSIONS, true)){
			throw new AssertionError("Version '{$version}' not supported.");
		}

		/**
		 * 2 bytes (short) - xPos of chunk lowest x & lowest z
		 * 2 bytes (short) - zPos
		 * 2 bytes (ushort) - width
		 * 2 bytes (ushort) - depth
		 */
		$minX = $bs->getShort();
		$minZ = $bs->getShort();
		$width = $bs->getShort();
		$depth = $bs->getShort();

		/**
		 * [depends] - chunk bitmask
		 * -> each chunk is 1 bit: 0 if all air (missing), 1 if present
		 * -> chunks are ordered zx, meaning
		 * -> the last byte has unused bits on the right
		 * -> size is ceil((width*depth) / 8) bytes
		 */
		$chunkBitmaskLength = (int)(ceil(($width*$depth)/8));
		$chunkBitmask = $bs->get($chunkBitmaskLength);

		/**
		 * 4 bytes (int) - compressed chunks size
		 * 4 bytes (int) - uncompressed chunks size
		 * 		<array of chunks> (size determined from bitmask)
		 *		compressed using zstd
		 */
		$chunks = $bs->readCompressed();

		/**
		 * 4 bytes (int) - compressed tile entities size
		 * 4 bytes (int) - uncompressed tile entities size
		 *		<array of tile entity nbt compounds>
		 *		same format as mc,
		 *		inside an nbt list named “tiles”, in global compound, no gzip anywhere
		 *		compressed using zstd
		 */
		$tileEntities = $bs->readCompressedCompound();
		//var_dump($tileEntities);

		$entities = null;

		if($version === 3){
			/**
			 * 1 byte (boolean) - has entities
			 * [if has entities]
			 * 		4 bytes (int) compressed entities size
			 * 		4 bytes (int) uncompressed entities size
			 * 			<array of entity nbt compounds>
			 * 			Same format as mc EXCEPT optional “CustomId”
			 * 			in side an nbt list named “entities”, in global compound
			 *			Compressed using zstd
			 */
			if($bs->getBool()){ //Bool hasEntities
				$entities = $bs->readCompressedCompound();
				//var_dump($entities);
			}

			/**
			 * 4 bytes (int) - compressed “extra” size
			 * 4 bytes (int) - uncompressed “extra” size
			 * [depends] - compound tag compressed using zstd
			 */
			//What is this 'extra'... (For now ignore it.)
			$bs->readCompressed();
		}

		if(!$bs->feof()){
			throw new AssertionError("No data is expected however there is still data left unread...");
		}

		return new SlimeWorld($version, $minX, $minZ, $width, $depth, $chunkBitmask, $chunks, $tileEntities, $entities);
	}



	private int $version;
	private int $minZ;
	private int $minX;
	private int $depth;
	private int $width;
	// Bitmask
	private $chunkStates;
	// Raw data
	private $chunks;
	private CompoundTag $tileEntities;
	private ?CompoundTag $entities;

	public function __construct(int $version, int $minX, int $minZ, int $width, int $depth, $chunkStates, $chunks,
								CompoundTag $tileEntities, ?CompoundTag $entities = null){
		$this->version = $version;
		$this->minZ = $minZ;
		$this->minX = $minX;
		$this->width = $width;
		$this->depth = $depth;
		$this->chunkStates = $chunkStates;
		$this->chunks = $chunks;
		$this->tileEntities = $tileEntities;
		$this->entities = $entities;
	}
}