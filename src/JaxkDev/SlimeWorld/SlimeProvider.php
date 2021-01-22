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
use pocketmine\level\Level;

class SlimeProvider extends BaseLevelProvider{

	/**
	 * @inheritDoc
	 */
	protected function readChunk(int $chunkX, int $chunkZ): ?Chunk{
		var_dump("Read chunk {$chunkX}:{$chunkZ}.");
		// TODO: Implement readChunk() method.
		return null;
	}

	protected function writeChunk(Chunk $chunk): void{
		var_dump("Write chunk ".$chunk->getX().":".$chunk->getZ().".");
		// TODO: Implement writeChunk() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function getProviderName(): string{
		return "slime";
	}

	/**
	 * @inheritDoc
	 */
	public function getWorldHeight(): int{
		return 256;
		// TODO: Implement getWorldHeight() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function isValid(string $path): bool{
		var_dump($path." is that valid ?");
		return false;
		// TODO: Implement isValid() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function generate(string $path, string $name, int $seed, string $generator, array $options = []){
		// TODO: Implement generate() method.
		var_dump("Generate ?");
		//Keeping level.dat in pure nbt format simply because the size is negligible compared to chunks.
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
	public function close(){
		// TODO: Implement close() method.
	}
}