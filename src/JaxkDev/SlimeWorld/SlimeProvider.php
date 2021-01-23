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

use pocketmine\level\format\Chunk;
use pocketmine\level\format\io\BaseLevelProvider;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\Level;
use pocketmine\level\LevelException;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\StringTag;
use UnexpectedValueException;

class SlimeProvider extends BaseLevelProvider{
	private ?SlimeFile $slimeFile;

	protected function loadLevelData() : void{
		$compressedLevelData = @file_get_contents($this->getPath() . "level.slime");
		if($compressedLevelData === false){
			throw new LevelException("Failed to read level.slime (permission denied or doesn't exist)");
		}
		$rawLevelData = @zstd_uncompress($compressedLevelData);
		if($rawLevelData === false){
			throw new LevelException("Failed to decompress level.slime contents (probably corrupted)");
		}
		$nbt = new BigEndianNBTStream();
		try{
			$levelData = $nbt->read($rawLevelData);
		}catch(UnexpectedValueException $e){
			throw new LevelException("Failed to decode level.slime (" . $e->getMessage() . ")", 0, $e);
		}

		if(!($levelData instanceof CompoundTag) or !$levelData->hasTag("SlimeVersion", ByteTag::class)){
			throw new LevelException("Invalid level.slime");
		}

		$this->levelData = $levelData;

		//Load slime file if it exists.
		$this->slimeFile = file_exists($this->getPath()."levelData.slime") ?
			SlimeFile::read($this->getPath()."levelData.slime") : null;
	}

	public function saveLevelData(){
		$nbt = new BigEndianNBTStream();
		$buffer = @zstd_compress($nbt->write($this->levelData));
		if($buffer === false){
			throw new LevelException("Failed to compress level data.");
		}
		file_put_contents($this->getPath() . "level.slime", $buffer);

		//Save slime file.
		if($this->slimeFile !== null){
			$this->slimeFile->write($this->getPath()."levelData.slime");
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function readChunk(int $chunkX, int $chunkZ): ?Chunk{
		var_dump("Read chunk {$chunkX}:{$chunkZ}.");
		// return $this->chunks[Level::chunkHash($chunkX, $chunkZ)];
		return null;
	}

	// AKA saveChunk, so just updating the data in memory
	// see $this->saveLevelData() for saving.
	// Note, Generate new SlimeFile if null.
	protected function writeChunk(Chunk $chunk): void{
		var_dump("Write chunk ".$chunk->getX().":".$chunk->getZ().".");
		// TODO: Implement writeChunk() method.
	}

	/**
	 * @inheritDoc
	 */
	public function getWorldHeight(): int{
		return 256;
	}

	/**
	 * @inheritDoc
	 */
	public static function isValid(string $path): bool{
		return file_exists($path . "/level.slime");
	}

	/**
	 * @inheritDoc
	 */
	public static function generate(string $path, string $name, int $seed, string $generator, array $options = []){
		$levelData = new CompoundTag("", [
			//Some vanilla fields
			new ByteTag("Difficulty", Level::getDifficultyFromString((string) ($options["difficulty"] ?? "normal"))),
			new LongTag("LastPlayed", time()),
			new StringTag("LevelName", $name),
			new LongTag("RandomSeed", $seed),
			new IntTag("SpawnX", 0),
			new IntTag("SpawnY", 32767),
			new IntTag("SpawnZ", 0),
			new LongTag("Time", 0),
			new IntTag("rainTime", 0),

			//Slime
			new ByteTag("SlimeVersion", SlimeFile::FORMAT_CURRENT_VERSION),

			//Additional PocketMine-MP fields
			new CompoundTag("GameRules", []),
			new ByteTag("hardcore", ($options["hardcore"] ?? false) === true ? 1 : 0),
			new StringTag("generatorName", GeneratorManager::getGeneratorName($generator)),
			new StringTag("generatorOptions", $options["preset"] ?? "")
		]);

		$nbt = new BigEndianNBTStream();
		$buffer = @zstd_compress($nbt->write($levelData));
		if($buffer === false){
			throw new LevelException("Failed to compress generated level.slime");
		}
		if(!is_dir($path)){
			@mkdir($path);
		}
		file_put_contents($path . "level.slime", $buffer);
	}

	/**
	 * @inheritDoc
	 */
	public function getGenerator(): string{
		return $this->levelData->getString("generatorName", "");
	}

	/**
	 * @inheritDoc
	 */
	public function getGeneratorOptions(): array{
		return ["preset" => $this->levelData->getString("generatorOptions", "")];
	}

	/**
	 * @inheritDoc
	 */
	public function getDifficulty(): int{
		return $this->levelData->getByte("Difficulty", Level::DIFFICULTY_NORMAL);
	}

	/**
	 * @inheritDoc
	 */
	public function setDifficulty(int $difficulty){
		$this->levelData->setByte("Difficulty", $difficulty);
	}

	/**
	 * @inheritDoc
	 */
	public function close(){}

	/**
	 * @inheritDoc
	 */
	public static function getProviderName(): string{
		return "slime";
	}
}