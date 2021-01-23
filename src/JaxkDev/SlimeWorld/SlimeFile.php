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
use pocketmine\level\format\Chunk;
use pocketmine\nbt\tag\CompoundTag;

class SlimeFile{

	const FORMAT_HEADER = 0xB10B;
	const FORMAT_VERSIONS = [1,3]; //Not seen any v2's unsure if it was public if so i do not know the changes.
	const FORMAT_CURRENT_VERSION = 3;

	public static function read(string $file): SlimeFile{
		$data = file_get_contents($file);
		$bs = new SlimeBinaryStream($data);

		$header = $bs->getShort();
		if($header !== SlimeFile::FORMAT_HEADER){
			throw new AssertionError("Header invalid.");
		}

		$version = $bs->getByte();
		if(!in_array($version, SlimeFile::FORMAT_VERSIONS, true)){
			throw new AssertionError("Version '{$version}' not supported.");
		}

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
		$chunkBitmask = BitSet::fromBitsetString($bs->get($chunkBitmaskLength));

		/**
		 * 4 bytes (int) - compressed chunks size
		 * 4 bytes (int) - uncompressed chunks size
		 * 		<array of chunks> (size determined from bitmask)
		 *		compressed using zstd
		 */
		$rawChunks = $bs->readCompressed();

		$tileEntities = $bs->readCompressedCompound();
		//var_dump($tileEntities);

		$entities = null;

		if($version === 3){
			if($bs->getBool()){ //Bool hasEntities
				$entities = $bs->readCompressedCompound();
				//var_dump($entities);
			}

			//What is this 'extra'... (For now ignore it.)
			$bs->readCompressedCompound();
		}

		if(!$bs->feof()){
			throw new AssertionError("No data is expected however there is still data left unread...");
		}

		return new SlimeFile($version, $minX, $minZ, $width, $depth, $chunkBitmask, $rawChunks, $tileEntities, $entities);
	}

	public function write(string $file): void{
		$bs = new SlimeBinaryStream();
		$bs->putShort(SlimeFile::FORMAT_HEADER);
		$bs->putShort(SlimeFile::FORMAT_CURRENT_VERSION);
		$bs->putShort($this->minX);
		$bs->putShort($this->minZ);
		$bs->putShort($this->width);
		$bs->putShort($this->depth);
		$bs->put($this->chunkStates->getBitsetString());
		$bs->writeCompressed($this->rawChunks);
		$bs->writeCompressedCompound($this->tileEntities);
		$hasEntities = $this->entities !== null;
		$bs->putBool($hasEntities);
		if($hasEntities){
			$bs->writeCompressedCompound($this->entities);
		}
		$bs->writeCompressedCompound(new CompoundTag()); //'Extra' *shrug*
		file_put_contents($file, $bs->buffer);
	}


	public int $version;
	public int $minZ;
	public int $minX;
	public int $depth;
	public int $width;
	public BitSet $chunkStates;
	public string $rawChunks;
	public CompoundTag $tileEntities;
	public ?CompoundTag $entities;

	public function __construct(int $version, int $minX, int $minZ, int $width, int $depth, BitSet $chunkStates,
								string $rawChunks, CompoundTag $tileEntities, ?CompoundTag $entities = null){
		$this->version = $version;
		$this->minZ = $minZ;
		$this->minX = $minX;
		$this->width = $width;
		$this->depth = $depth;
		$this->chunkStates = $chunkStates;
		$this->rawChunks = $rawChunks;
		$this->tileEntities = $tileEntities;
		$this->entities = $entities;
	}

	/**
	 * @return Chunk[]
	 */
	public function loadChunks(): array{
		$bs = new SlimeBinaryStream($this->rawChunks);
		$chunks = [];
		for($i = 0; $i < $this->chunkStates->roughLength(); $i++){
			if($this->chunkStates->get($i) === false) continue;
			$heightMap = [];
			for($i2 = 0; $i2 < 256; $i2++){
				$heightMap[] = $bs->getInt();
			}
			$biomes = $bs->get(256);
			$bitmask = BitSet::fromBitsetString($bs->get(2));
			for($i3 = 0; $i3 < $bitmask->roughLength(); $i3++){
				if($bitmask->get($i3) === false) continue;

				$blockLight = $bs->get(2048);
				$blocks = $bs->get(4096);
				$data = $bs->get(2048);
				$skylight = $bs->get(2048);

				//skip hypixel blocks if any.
				$_hp = $bs->getShort();
				$bs->get($_hp);
				//TODO Subchunk here.
			}
			//TODO Create chunk here.
		}
		//TODO All chunks here.
		return [];
	}

	/**
	 * @param Chunk[] $chunks
	 */
	public function saveChunks(array $chunks): void{
		//TODO Overwrite all chunk data with the given chunks.
	}
}