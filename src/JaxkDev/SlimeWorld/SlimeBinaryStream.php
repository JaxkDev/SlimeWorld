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
use pocketmine\utils\BinaryStream;

class SlimeBinaryStream extends BinaryStream{
	public function readCompressed(): string{
		$compressedSize = $this->getInt();
		$uncompressedSize = $this->getInt();
		$decompressed = zstd_uncompress($this->get($compressedSize));
		if($decompressed === false){
			throw new AssertionError("Failed to decompress data.");
		}
		if(strlen($decompressed) !== $uncompressedSize){
			throw new AssertionError("Uncompressed data size '".strlen($decompressed).
				"' does not match expected '{$uncompressedSize}'");
		}
		return $decompressed;
	}
}