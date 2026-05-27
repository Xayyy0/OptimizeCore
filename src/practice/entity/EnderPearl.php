<?php

declare(strict_types=1);

namespace practice\entity;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\EnderPearl as ProjectileEnderPearl;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\player\Player;
use pocketmine\world\particle\EndermanTeleportParticle;
use pocketmine\world\sound\EndermanTeleportSound;

class EnderPearl extends ProjectileEnderPearl
{
    protected float $gravity = 0.135;
    protected float $drag = 0.095;

    public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $shootingEntity, $nbt);
        $this->setScale(0.6);
    }

    protected function onHit(ProjectileHitEvent $event): void
    {
        $owner = $this->getOwningEntity();
        if (!$owner instanceof Player || !$owner->isAlive()) {
            return;
        }

        $oldY = $owner->getPosition()->y;
        $newPos = $event->getRayTraceResult()->getHitVector();

        $this->applyFallDamage($owner, $oldY, $newPos->y);

        $this->playTeleportEffects($owner);
        $owner->teleport($newPos);
        $this->sendSmoothTeleport($owner);
        $this->playTeleportEffects($owner);

        $this->flagForDespawn();
    }

    protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult): void
    {
        parent::onHitBlock($blockHit, $hitResult);

        $owner = $this->getOwningEntity();
        if (!$owner instanceof Player || !$owner->isAlive()) {
            return;
        }

        if ($blockHit->getTypeId() === BlockTypeIds::INVISIBLE_BEDROCK) {
            $oldY = $owner->getPosition()->y;
            $newPos = $hitResult->getHitVector();

            $this->applyFallDamage($owner, $oldY, $newPos->y);

            $this->playTeleportEffects($owner);
            $owner->teleport($newPos);
            $this->sendSmoothTeleport($owner);
            $this->playTeleportEffects($owner);
        }
    }

    private function sendSmoothTeleport(Player $player): void
    {
        $location = $player->getLocation();

        // MovePlayerPacket corregido con todos los parámetros correctos
        $pk = MovePlayerPacket::create(
            $player->getId(),                       // actorRuntimeId
            $player->getOffsetPosition($location),  // position
            $location->pitch,                       // pitch
            $location->yaw,                         // yaw
            $location->yaw,                         // headYaw
            MovePlayerPacket::MODE_NORMAL,          // mode
            $player->onGround,                      // onGround
            0,                                      // ridingActorRuntimeId
            0,                                      // teleportCause
            0,                                      // teleportItem  ← Aquí estaba el error
            0                                       // tick
        );

        NetworkBroadcastUtils::broadcastPackets($player->getViewers(), [$pk]);

        // Paquete adicional para más suavidad
        $actorPk = MoveActorAbsolutePacket::create(
            $player->getId(),
            $player->getOffsetPosition($location),
            $location->pitch,
            $location->yaw,
            $location->yaw,
            0
        );

        NetworkBroadcastUtils::broadcastPackets($player->getViewers(), [$actorPk]);
    }

    private function applyFallDamage(Player $player, float $oldY, float $newY): void
    {
        if ($newY >= $oldY - 0.1) {
            return;
        }

        $fallDistance = $oldY - $newY;
        if ($fallDistance > 3.0) {
            $damage = ($fallDistance - 3) * 2; // Ajusta según prefieras

            $player->attack(new \pocketmine\event\entity\EntityDamageEvent(
                $player,
                \pocketmine\event\entity\EntityDamageEvent::CAUSE_FALL,
                $damage
            ));
        }
    }

    private function playTeleportEffects(Player $player): void
    {
        $pos = $player->getPosition();
        $world = $player->getWorld();
        $world->addParticle($pos, new EndermanTeleportParticle());
        $world->addSound($pos, new EndermanTeleportSound());
    }
}