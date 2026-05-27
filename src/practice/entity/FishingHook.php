<?php
declare(strict_types=1);

namespace practice\entity;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\RayTraceResult;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\math\Vector3;

final class FishingHook extends Throwable {

    protected function getInitialSizeInfo() : EntitySizeInfo {
        return new EntitySizeInfo(0.25, 0.25);
    }

    protected function getInitialDragMultiplier() : float {
        return 0.05;
    }

    protected function getInitialGravity() : float {
        return 0.09;
    }

    public static function getNetworkTypeId() : string {
        return EntityIds::FISHING_HOOK;
    }

    protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void {

        $owner = $this->getOwningEntity();

        if (!$owner instanceof Player) {
            $this->flagForDespawn();
            return;
        }

        $damage = 0.1;

        $event = new EntityDamageByChildEntityEvent(
            $owner,
            $this,
            $entityHit,
            EntityDamageEvent::CAUSE_PROJECTILE,
            $damage
        );

        $event->call();

        if (!$event->isCancelled()) {
            $entityHit->attack($event);
        }

        $this->setMotion(new Vector3(0.0, 0.0, 0.0));
        $this->flagForDespawn();
    }

    protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void {
        $this->setMotion(new Vector3(0.0, 0.0, 0.0));
        $this->setPosition($hitResult->hitVector);
    }

    protected function entityBaseTick(int $tickDiff = 1) : bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        $owner = $this->getOwningEntity();

        if (!$owner instanceof Player) {
            $this->flagForDespawn();
            return true;
        }

        if (
            !$owner->isOnline() ||
            !$owner->isAlive() ||
            $owner->isClosed() ||
            !$owner->getInventory()->getItemInHand()->equals(VanillaItems::FISHING_ROD(), false, false) ||
            $owner->getPosition()->distance($this->getPosition()) >= 50.0
        ) {
            $this->flagForDespawn();
            return true;
        }

        return $hasUpdate;
    }
}