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
use pocketmine\level\format\SubChunk;
use pocketmine\level\Level;
use pocketmine\level\LevelException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;

class SlimeFile{

	const FORMAT_HEADER = 0xB10B;
	const FORMAT_VERSIONS = [1,3]; //Not seen any v2's unsure if it was public if so i do not know the changes.
	const FORMAT_CURRENT_VERSION = 3;

	public static function generateFromChunks(array $chunks): SlimeFile{
		$sf = new SlimeFile();
		$sf->setChunks($chunks);
		return $sf;
	}

	public static function read(string $data): SlimeFile{
		//$data = file_get_contents($file);
		$bs = new SlimeBinaryStream($data);

		$header = $bs->getShort();
		if($header !== SlimeFile::FORMAT_HEADER){
			throw new AssertionError("Header invalid.");
		}

		$version = $bs->getByte();
		if(!in_array($version, SlimeFile::FORMAT_VERSIONS, true)){
			throw new AssertionError("Version '{$version}' not supported.");
		}

		$minX = $bs->getSignedShort();
		$minZ = $bs->getSignedShort();
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
		$chunkBitmask = BitSet::fromString($bs->get($chunkBitmaskLength));

		/**
		 * 4 bytes (int) - compressed chunks size
		 * 4 bytes (int) - uncompressed chunks size
		 * 		<array of chunks> (size determined from bitmask)
		 *		compressed using zstd
		 */
		$rawChunks = $bs->readCompressed();

		$tileEntities = $bs->readCompressedCompound();

		/** @var Array<int, CompoundTag[]> $tileMap */
		$tileMap = [];
		if($tileEntities->hasTag("tiles", ListTag::class)){
			/** @var CompoundTag $tag */
			foreach($tileEntities->getListTag("tiles")->getValue() as $tag){
				if(!$tag instanceof CompoundTag) throw new LevelException("Invalid tiles nbt");
				/** @var IntTag|null $x */
				$x = $tag->getTagValue("x", IntTag::class);
				/** @var IntTag|null $z */
				$z = $tag->getTagValue("z", IntTag::class);
				if($z === null or $x === null) throw new LevelException("Tile does not have x/z int tag.");
				$tileMap[Level::chunkHash($x >> 4, $z >> 4)][] = $tag;
			}
		}

		/** @var Array<int, CompoundTag[]> $entityMap */
		$entityMap = [];

		if($version === 3){
			if($bs->getBool()){ //Bool hasEntities
				$entities = $bs->readCompressedCompound();
				if($entities->hasTag("entities", ListTag::class)){
					/** @var CompoundTag $tag */
					foreach($entities->getListTag("entities")->getValue() as $tag){
						if(!$tag instanceof CompoundTag) throw new LevelException("Invalid entities nbt");
						$pos = $tag->getTagValue("Pos", ListTag::class);
						if($pos === null) throw new LevelException("Entity does not have Pos list tag.");
						$entityMap[Level::chunkHash($pos[0]->getValue() >> 4, $pos[2]->getValue() >> 4)][] = $tag;
					}
				}
			}

			//No extra block data.
			$bs->readCompressedCompound();
		}

		if(!$bs->feof()){
			throw new AssertionError("No data is expected however there is still data left unread...");
		}

		$sf = new SlimeFile($version);
		$sf->minX = $minX;
		$sf->minZ = $minZ;
		$sf->maxX = ($width-1)+$minX;
		$sf->maxZ = ($depth-1)+$minZ;
		$sf->width = $width;
		$sf->depth = $depth;
		$sf->chunkStates = $chunkBitmask;
		$sf->rawChunks = $rawChunks;
		$sf->tileMap = $tileMap;
		$sf->entityMap = $entityMap;
		return $sf;
	}

	public function write(): string{
		$bs = new SlimeBinaryStream();
		$bs->putShort(SlimeFile::FORMAT_HEADER);
		$bs->putByte(SlimeFile::FORMAT_CURRENT_VERSION);
		$bs->putShort($this->minX); //Signed
		$bs->putShort($this->minZ); //Signed
		$bs->putShort($this->width);
		$bs->putShort($this->depth);
		$bs->put($this->chunkStates->getBitset());
		$bs->writeCompressed($this->rawChunks);
		$nbt = new CompoundTag();
		$list = new ListTag("tiles");
		foreach(array_values($this->tileMap) as $tileTags){
			foreach($tileTags as $tag){
				$list->push($tag);
			}
		}
		$nbt->setTag($list);
		$bs->writeCompressedCompound($nbt);
		$hasEntities = true;
		$bs->putBool($hasEntities);
		if($hasEntities){
			$nbt = new CompoundTag();
			$list = new ListTag("entities");
			foreach(array_values($this->entityMap) as $entityTags){
				foreach($entityTags as $tag){
					$list->push($tag);
				}
			}
			$nbt->setTag($list);
			$bs->writeCompressedCompound($nbt);
		}
		$bs->writeCompressedCompound(new CompoundTag()); //No extra block data.
		return $bs->buffer;
	}


	private int $version;
	private int $minZ, $maxZ;
	private int $minX, $maxX;
	private int $depth;
	private int $width;
	private BitSet $chunkStates;
	private string $rawChunks;
	/** @var Array<int, CompoundTag[]>  */
	private array $tileMap;
	/** @var Array<int, CompoundTag[]>  */
	private array $entityMap;

	private function __construct(int $version = self::FORMAT_CURRENT_VERSION){
		$this->version = $version;
	}

	/**
	 * @return Chunk[]
	 */
	public function getChunks(): array{
		$bs = new SlimeBinaryStream($this->rawChunks);
		$chunks = [];
		for($i = 0; $i < $this->chunkStates->roughLength(); $i++){
			if($this->chunkStates->get($i) === false) continue;

			$chunkX = (($i % $this->width) + $this->minX);
			$chunkZ = (int)floor(($i / $this->width) + $this->minZ);

			$heightMap = [];
			for($i2 = 0; $i2 < 256; $i2++){
				$heightMap[] = $bs->getInt();
			}
			$biomes = $bs->get(256);
			$bitmask = BitSet::fromString($bs->get(2));
			$subchunks = [];
			for($i3 = 0; $i3 < $bitmask->roughLength(); $i3++){
				if($bitmask->get($i3) === false) continue;

				$blockLight = $bs->get(2048);
				$blocks = $bs->get(4096);
				$data = $bs->get(2048);
				$skylight = $bs->get(2048);

				//skip hypixel blocks if any.
				$_hp = $bs->getShort();
				$bs->get($_hp);
				$subchunks[] = new SubChunk($blocks, $data, $skylight, $blockLight);
			}
			$chunk = new Chunk($chunkX, $chunkZ, $subchunks, $this->entityMap[Level::chunkHash($chunkX, $chunkZ)] ?? [],
				$this->tileMap[Level::chunkHash($chunkX, $chunkZ)] ?? [], $biomes, $heightMap);
			$chunk->setGenerated();
			$chunk->setPopulated();
			$chunks[] = $chunk;
		}
		return $chunks;
	}

	/**
	 * @param Chunk[] $chunks
	 */
	public function setChunks(array $chunks): void{
		$chunkCoords = array_map(function(Chunk $chunk){
			return [$chunk->getX(),$chunk->getZ()];
		}, array_values($chunks));

		$this->calculateChunkBounds($chunkCoords);

		//Re-calculate chunk bitset.
		$this->chunkStates = BitSet::fromRoughSize((int)(ceil(($this->width*$this->depth)/8)));
		foreach($chunkCoords as $chunkCoord){
			$relX = $chunkCoord[0] - $this->minX;
			$relZ = $chunkCoord[1] - $this->minZ;
			$idx = ($relZ*$this->width) + $relX;
			$this->chunkStates->set($idx);
		}

		//Rebuild chunk data.
		$bs = new SlimeBinaryStream();
		foreach($chunks as $chunk){
			//Heightmap.
			foreach($chunk->getHeightMapArray() as $height){
				$bs->putInt($height);
			}
			//Biomes
			$bs->put($chunk->getBiomeIdArray());

			//Subchunk bitset
			$bitset = BitSet::fromSetSize(16);
			for($y = 0; $y < 16; $y++){
				$subChunk = $chunk->getSubChunk($y);
				if(!$subChunk->isEmpty(false)) $bitset->set($y);
			}
			$bs->put($bitset->getBitset());

			for($y = 0; $y < 16; $y++){
				$subChunk = $chunk->getSubChunk($y);
				if($bitset->get($y) === false) continue;
				$bs->put($subChunk->getBlockLightArray());
				//if(strlen($subChunk->getBlockLightArray()) !== 2048) throw new ChunkException("Invalid block light");
				$bs->put($subChunk->getBlockIdArray());
				//if(strlen($subChunk->getBlockIdArray()) !== 4096) throw new ChunkException("Invalid block ids");
				$bs->put($subChunk->getBlockDataArray());
				//if(strlen($subChunk->getBlockDataArray()) !== 2048) throw new ChunkException("Invalid block data");
				$bs->put($subChunk->getBlockSkyLightArray());
				//if(strlen($subChunk->getBlockSkyLightArray()) !== 2048) throw new ChunkException("Invalid block skylight");
				$bs->putShort(0); //Hypixel blocks
			}
			$chunkHash = Level::chunkHash($chunk->getX(), $chunk->getZ());
			$this->entityMap[$chunkHash] = [];
			foreach($chunk->getSavableEntities() as $entity){
				$entity->saveNBT();
				$this->entityMap[$chunkHash][] = $entity->namedtag;
			}
			$this->tileMap[$chunkHash] = [];
			foreach($chunk->getTiles() as $tile){
				$this->tileMap[$chunkHash][] = $tile->saveNBT();
			}
		}
		$this->rawChunks = $bs->getBuffer();
	}

	/**
	 * Recalculates, min/max X/Z and width/depth.
	 * @param int[][] $chunks
	 */
	public function calculateChunkBounds(array $chunks): void{
		$this->maxX = ($this->minX = $chunks[0][0]);
		$this->maxZ = ($this->minZ = $chunks[0][1]);
		foreach($chunks as $chunk){
			[$x, $z] = $chunk;
			if($x > $this->maxX) $this->maxX = $x;
			if($x < $this->minX) $this->minX = $x;
			if($z > $this->maxZ) $this->maxZ = $z;
			if($z < $this->minZ) $this->minZ = $z;
		}
		$this->width = ($this->maxX-$this->minX)+1;
		$this->depth = ($this->maxZ-$this->minZ)+1;
		/*if((($this->width*$this->depth)/8) > 10000){
			throw new LevelException("Slime has not been tested to this extreme amount of chunks (".(($this->width*$this->depth)/8).") (empty or not).");
		}*/
	}
}