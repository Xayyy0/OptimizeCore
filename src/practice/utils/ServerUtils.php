<?php

declare(strict_types=1);

namespace practice\utils;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\world\Position;

class ServerUtils{

	public static function playSound(
		string $sound,
		Position $position,
		float $volume = 1000,
		float $pitch = 1,
		?array $targets = null
	) : void{

		NetworkBroadcastUtils::broadcastPackets(
			$targets ?? $position->getWorld()->getPlayers(),
			[
				PlaySoundPacket::create(
					$sound,
					$position->x,
					$position->y,
					$position->z,
					$volume,
					$pitch,
					null
				)
			]
		);

	}

	public static function spawnActor(
		string $actor,
		Location $location,
		?array $targets = null
	) : void{

		$packet = new AddActorPacket();

		$packet->type = $actor;
		$packet->actorRuntimeId = Entity::nextRuntimeId();
		$packet->actorUniqueId = 1;
		$packet->position = $location;
		$packet->yaw = $location->yaw;
		$packet->syncedProperties = new PropertySyncData([], []);

		NetworkBroadcastUtils::broadcastPackets(
			$targets ?? $location->getWorld()->getPlayers(),
			[$packet]
		);

	}

}