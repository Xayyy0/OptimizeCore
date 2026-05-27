<?php

declare(strict_types=1);

namespace practice\party\duel\type;

use pocketmine\event\player\PlayerItemUseEvent;

use pocketmine\item\MushroomStew;

use pocketmine\item\VanillaItems;

use practice\party\duel\Duel;

final class Soup extends Duel

{

    public function onItemUse(PlayerItemUseEvent $event): void

    {

        $player = $event->getPlayer();

        $item = $event->getItem();

        if (!$item instanceof MushroomStew) {

            return;

        }

        $event->cancel();

        $newHealth = $player->getHealth() + 9.0;

        $maxHealth = $player->getMaxHealth();

        if ($newHealth > $maxHealth) {

            $newHealth = $maxHealth;

        }

        $player->setHealth($newHealth);

        $player->getWorld()->addParticle($player->getPosition()->add(0, 0, 0), new \pocketmine\world\particle\HeartParticle());

        $player->getWorld()->addSound($player->getPosition(), new \pocketmine\world\sound\TotemUseSound());

        $hand = $player->getInventory()->getItemInHand();

        $hand->setCount($hand->getCount() - 1);

        $player->getInventory()->setItemInHand($hand);

        $player->getInventory()->addItem(VanillaItems::BOWL());

    }

}