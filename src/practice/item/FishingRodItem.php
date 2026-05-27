<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\FishingRod;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use pocketmine\entity\animation\ArmSwingAnimation;
use practice\entity\FishingHook;

class FishingRodItem extends FishingRod {

    public function __construct() {
        parent::__construct(new ItemIdentifier(ItemTypeIds::FISHING_ROD), 'Fishing Rod');
    }

    public function getThrowForce(): float {
        return 1.8;
    }

    public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult {
        // Buscar si ya hay un hook activo del jugador
        $hasHook = false;
        foreach ($player->getWorld()->getEntities() as $entity) {
            if ($entity instanceof FishingHook && $entity->getOwningEntity() === $player) {
                if (!$entity->isFlaggedForDespawn() && !$entity->isClosed()) {
                    $entity->flagForDespawn();
                    $player->broadcastAnimation(new ArmSwingAnimation($player));
                }
                $hasHook = true;
            }
        }

        if ($hasHook) {
            return ItemUseResult::SUCCESS();
        }

        // Lanzar nuevo hook
        $location = $player->getLocation();
        $projectile = $this->createEntity(Location::fromObject(
            $player->getEyePos(),
            $player->getWorld(),
            $location->yaw,
            $location->pitch
        ), $player);

        $projectile->setMotion($directionVector->multiply($this->getThrowForce())->add(0, 0.2, 0));

        $projectileEv = new \pocketmine\event\entity\ProjectileLaunchEvent($projectile);
        $projectileEv->call();

        if ($projectileEv->isCancelled()) {
            $projectile->flagForDespawn();
            return ItemUseResult::FAIL();
        }

        $projectile->spawnToAll();

        $location->getWorld()->addSound($location, new ThrowSound());
        $player->broadcastAnimation(new ArmSwingAnimation($player));

        return ItemUseResult::SUCCESS();
    }

    protected function createEntity(Location $location, Player $thrower): Projectile {
        return new FishingHook($location, $thrower);
    }
}