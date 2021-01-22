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
use pocketmine\plugin\PluginBase;
use pocketmine\utils\BinaryStream;

class Loader extends PluginBase{

	public function onEnable(){
		$this->saveResource("test.slime", true);

		$data = file_get_contents($this->getDataFolder()."test.slime");
		$bs = new BinaryStream($data);

		/**
		 * “Slime” file format
		 * https://pastebin.com/raw/EVCNAmkw
		 */

		/**
		 * 2 bytes - magic = 0xB10B
		 */
		$header = $bs->getShort();
		if($header !== 0xB10B){
			throw new AssertionError("Header invalid.");
		}
		//var_dump($header);

		/**
		 * 1 byte (ubyte) - version, current = 0x03
		 */
		$version = $bs->getByte();
		if($version < 1 or $version > 3){
			throw new AssertionError("Version '{$version}' not supported.");
		}
		//var_dump($version);

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
		//var_dump([$minX, $minZ, $width, $depth]);

		/**
		 * [depends] - chunk bitmask
		 * -> each chunk is 1 bit: 0 if all air (missing), 1 if present
		 * -> chunks are ordered zx, meaning
		 * -> the last byte has unused bits on the right
		 * -> size is ceil((width*depth) / 8) bytes
		 */
		$chunkBitmaskLength = (int)ceil(($width*$depth)/8);
		//var_dump($chunkBitmaskLength);
		$chunkBitmask = $bs->get($chunkBitmaskLength);
		//TODO ^
		//var_dump($chunkBitmask);

		/**
		 * 4 bytes (int) - compressed chunks size
		 * 4 bytes (int) - uncompressed chunks size
		 * 		<array of chunks> (size determined from bitmask)
  		 *		compressed using zstd
		 */
		$compressedChunksSize = $bs->getInt();
		$uncompressedChunksSize = $bs->getInt();
		//var_dump([$compressedChunksSize, $uncompressedChunksSize]);

		$decompressedChunks = zstd_uncompress($bs->get($compressedChunksSize));
		//var_dump($decompressedChunks);
		//var_dump(strlen($decompressedChunks) === $uncompressedChunksSize);
		if(strlen($decompressedChunks) !== $uncompressedChunksSize){
			throw new AssertionError("Uncompressed chunks size '".strlen($decompressedChunks).
				"' does not match expected '{$uncompressedChunksSize}'");
		}

		/**
		 * 4 bytes (int) - compressed tile entities size
		 * 4 bytes (int) - uncompressed tile entities size
		 *		<array of tile entity nbt compounds>
		 *		same format as mc,
		 *		inside an nbt list named “tiles”, in global compound, no gzip anywhere
		 *		compressed using zstd
		 */
		$compressedTileEntitiesSize = $bs->getInt();
		$uncompressedTileEntitiesSize = $bs->getInt();
		//var_dump([$compressedTileEntitiesSize, $uncompressedTileEntitiesSize]);

		$decompressedTileEntities = zstd_uncompress($bs->get($compressedTileEntitiesSize));
		//var_dump($decompressedTileEntities);
		//var_dump(strlen($decompressedTileEntities) === $uncompressedTileEntitiesSize);
		if(strlen($decompressedTileEntities) !== $uncompressedTileEntitiesSize){
			throw new AssertionError("Uncompressed tile entities size '".strlen($decompressedTileEntities).
				"' does not match expected '{$uncompressedTileEntitiesSize}'");
		}

		if($version >= 3){
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
			$hasEntities = $bs->getBool();
			if($hasEntities){
				$compressedEntitiesSize = $bs->getInt();
				$uncompressedEntitiesSize = $bs->getInt();
				//var_dump([$compressedEntitiesSize, $uncompressedEntitiesSize]);

				$decompressedEntities = zstd_uncompress($bs->get($compressedEntitiesSize));
				//var_dump($decompressedEntities);
				//var_dump(strlen($decompressedEntities) === $uncompressedEntitiesSize);
				if(strlen($decompressedEntities) !== $uncompressedEntitiesSize){
					throw new AssertionError("Uncompressed entities size '".strlen($decompressedEntities).
						"' does not match expected '{$uncompressedEntitiesSize}'");
				}
			}

			/**
			 * 4 bytes (int) - compressed “extra” size
			 * 4 bytes (int) - uncompressed “extra” size
			 * [depends] - compound tag compressed using zstd
			 */
			//What is this 'extra'... hmmm
			$compressedExtraSize = $bs->getInt();
			$uncompressedExtraSize = $bs->getInt();
			//var_dump([$compressedExtraSize, $uncompressedExtraSize]);

			$decompressedExtra = zstd_uncompress($bs->get($compressedExtraSize));
			//var_dump($decompressedExtra);
			//var_dump(strlen($decompressedExtra) === $uncompressedExtraSize);
			if(strlen($decompressedExtra) !== $uncompressedExtraSize){
				throw new AssertionError("Uncompressed Extra size '".strlen($decompressedExtra).
					"' does not match expected '{$uncompressedExtraSize}'");
			}
		}

		if(!$bs->feof()){
			throw new AssertionError("Data left unread...");
		}
	}
}