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

use pocketmine\entity\Entity;
use pocketmine\entity\Zombie;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Plugin extends PluginBase implements Listener{
	public function onEnable(){
		$this->saveResource("world2.slime", true);
		LevelProviderManager::addProvider(SlimeProvider::class);

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick): void{
			var_dump(SlimeFile::read($this->getDataFolder()."anvilWorld.slime")->loadChunks()[0]);
		}), 0);
		//$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerJoin(PlayerJoinEvent $event): void{
		$this->getServer()->getDefaultLevel()->loadChunk(0, 0);
		$this->getServer()->getDefaultLevel()->addEntity(Entity::createEntity(Entity::ZOMBIE, $this->getServer()->getDefaultLevel(), Zombie::createBaseNBT(new Vector3(4, 66, 4))));

	}
}