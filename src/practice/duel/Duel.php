<?php

declare(strict_types=1);

namespace practice\duel;

use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use practice\Practice;
use practice\session\Session;
use practice\session\SessionFactory;
use practice\utils\ServerUtils;
use practice\duel\statistic\DuelStatistics;
use practice\world\async\WorldDeleteAsync;
use practice\world\WorldFactory;

class Duel
{
    protected DuelStatistics $statistics;

    public const TYPE_NODEBUFF = 0;
    public const TYPE_POTPVP = 1;
    public const TYPE_BOXING = 2;
    public const TYPE_BRIDGE = 3;
    public const TYPE_BATTLERUSH = 4;
    public const TYPE_FIST = 5;
    public const TYPE_GAPPLE = 6;
    public const TYPE_SUMO = 7;
    public const TYPE_FINALUHC = 8;
    public const TYPE_CAVEUHC = 9;
    public const TYPE_BUILDUHC = 10;
    public const TYPE_COMBO = 11;
    public const TYPE_SG = 12;
    public const TYPE_HG = 13;
    public const TYPE_SOUP = 14;
    public const TYPE_DEBUFF = 15;
    public const TYPE_MIDFIGHTS = 16;

    public const STARTING = 0;
    public const RUNNING = 1;
    public const RESTARTING = 2;

    public function __construct(
        protected int $id,
        protected int $typeId,
        protected string $worldName,
        protected bool $ranked,
        protected Session $firstSession,
        protected Session $secondSession,
        protected World $world,
        protected int $status = self::STARTING,
        protected int $starting = 5,
        protected int $running = 0,
        protected int $restarting = 5,
        protected string $winner = '',
        protected string $loser = '',
        protected bool $canDrop = true,
        protected array $spectators = [],
        protected array $blocks = []
    ) {
        $this->statistics = new DuelStatistics($this);
        $this->prepare();
        $this->init();
    }

    protected function prepare(): void
    {
        $worldName = $this->worldName;
        $world = $this->world;

        $firstSession = $this->firstSession;
        $secondSession = $this->secondSession;

        $world->setTime(World::TIME_DAY);
        $world->stopTime();

        $worldData = WorldFactory::get($worldName);
        $firstPosition = $worldData->getFirstPosition();
        $secondPosition = $worldData->getSecondPosition();

        $firstPlayer = $firstSession->getPlayer();
        $secondPlayer = $secondSession->getPlayer();

        if ($firstPlayer !== null && $secondPlayer !== null) {
            $firstPlayer->setGamemode(GameMode::SURVIVAL());
            $secondPlayer->setGamemode(GameMode::SURVIVAL());

            $firstPlayer->getArmorInventory()->clearAll();
            $firstPlayer->getInventory()->clearAll();
            $secondPlayer->getArmorInventory()->clearAll();
            $secondPlayer->getInventory()->clearAll();

            $firstPlayer->setNoClientPredictions();
            $secondPlayer->setNoClientPredictions();

            $firstSession->getInventory(strtolower(DuelFactory::getName($this->typeId)))?->giveKit();
            $secondSession->getInventory(strtolower(DuelFactory::getName($this->typeId)))?->giveKit();

            $firstPlayer->teleport(Position::fromObject($firstPosition->add(0.5, 0, 0.5), $world));
            $secondPlayer->teleport(Position::fromObject($secondPosition->add(0.5, 0, 0.5), $world));
        }
    }

    protected function init(): void
    {
    }

    public function getFirstSession(): Session
    {
        return $this->firstSession;
    }

    public function getSecondSession(): Session
    {
        return $this->secondSession;
    }

    public function getStatistics(): DuelStatistics
    {
        return $this->statistics;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTypeId(): int
    {
        return $this->typeId;
    }

    public function isRanked(): bool
    {
        return $this->ranked;
    }

    public function canDrop(): bool
    {
        return $this->canDrop;
    }

    public function isEnded(): bool
    {
        return $this->status === self::RESTARTING;
    }

    public function isPlayer(Player $player): bool
    {
        return $this->firstSession->getXuid() === $player->getXuid() ||
               $this->secondSession->getXuid() === $player->getXuid();
    }

    public function scoreboard(Player $player): array
    {
        switch ($this->status) {
            case self::STARTING:
                return [' &fMatch starting'];

            case self::RESTARTING:
                return [' &fMatch ended'];

            default:
                if ($this->isSpectator($player)) {
                    return [
                        ' &fDuration: &b' . gmdate('i:s', $this->running),
                        ' &fSpectators: &b' . count($this->spectators)
                    ];
                }

                $opponent = $this->getOpponent($player);
                $session = SessionFactory::get($player);
                
                $lines = [
                    ' &fDuration: &b' . gmdate('i:s', $this->running),
                    ' &r&r'
                ];

                if ($this->typeId === self::TYPE_BOXING && $session !== null) {
                    $stats = $this->statistics->getStatistic($session);
                    $oppStats = $this->statistics->getStatistic(SessionFactory::get($opponent));
                    
                    $diff = $stats->getHits() - $oppStats->getHits();
                    $leadColor = $diff >= 0 ? "&a+" : "&c";
                    
                    $lines[] = ' &fHits: &b' . $stats->getHits() . " &7(" . $leadColor . $diff . "&7)";
                    $lines[] = ' &fCombo: &b' . $stats->getCurrentCombo();
                    $lines[] = ' &7&r';
                }

                $lines[] = ' &aYour ping: &f' . $player->getNetworkSession()->getPing();
                $lines[] = ' &cTheir ping: &f' . $opponent->getNetworkSession()->getPing();
                
                return $lines;
        }
    }

    public function isSpectator(Player $player): bool
    {
        return isset($this->spectators[spl_object_hash($player)]);
    }

    public function getOpponent(Player|Session $player): ?Player
    {
        if ($this->firstSession->getXuid() === $player->getXuid()) {
            return $this->secondSession->getPlayer();
        }
        return $this->firstSession->getPlayer();
    }

    public function addSpectator(Player $player): void
    {
        $this->spectators[spl_object_hash($player)] = $player;
    }

    public function removeSpectator(Player $player): void
    {
        $hash = spl_object_hash($player);
        if (!$this->isSpectator($player)) {
            return;
        }
        unset($this->spectators[$hash]);
    }

    public function handleBreak(BlockBreakEvent $event): void
    {
        $block = $event->getBlock();
        if (!isset($this->blocks[(string) $block->getPosition()])) {
            $event->cancel();
            return;
        }
        unset($this->blocks[(string) $block->getPosition()]);
    }

    public function handlePlace(BlockPlaceEvent $event): void
    {
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $this->blocks[(string) $block->getPosition()] = $block;
        }
    }

    public function handleDamage(EntityDamageEvent $event): void
    {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }

        $finalHealth = $player->getHealth() - $event->getFinalDamage();

        if (!$this->isRunning()) {
            $event->cancel();
            return;
        }

        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();

            if ($damager instanceof Player) {
                $damagerSession = SessionFactory::get($damager);

                if ($damagerSession !== null) {
                    $damagerStats = $this->statistics->getStatistic($damagerSession);
                    $damagerStats->addDamageDealt($event->getFinalDamage());
                    $damagerStats->addHit();

                    $opponent = $this->getOpponent($damager);

                    if ($opponent !== null) {
                        $opponentSession = SessionFactory::get($opponent);

                        if ($opponentSession !== null) {
                            $this->statistics->getStatistic($opponentSession)->resetCombo();
                        }
                    }

                    if ($damager->getFallDistance() > 0.08 && !$damager->isOnGround() && !$damager->isFlying() && !$damager->isSwimming() && !$damager->getEffects()->has(\pocketmine\entity\effect\VanillaEffects::BLINDNESS()) && !$damager->getLocation()->getWorld()->getBlock($damager->getLocation()->subtract(0, 0.1, 0))->isSolid()) {
                        $damagerStats->addCritic();
                    }

                    if ($this->typeId === self::TYPE_BOXING && $damagerStats->getHits() >= 100) {
                        $this->finish($opponent);
                    }
                }
            }
        }

        if ($finalHealth <= 0.00) {
            if ($this->typeId === self::TYPE_BOXING) {
                $player->setHealth($player->getMaxHealth());
                $event->cancel();
                return;
            }

            $event->cancel();

            $location = $player->getLocation();
            ServerUtils::spawnActor("minecraft:lightning_bolt", $location);
            ServerUtils::playSound("ambient.weather.thunder", $location);

            $player->setGamemode(GameMode::SPECTATOR());
            $this->finish($player);
        }
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    public function getConsumableLabel(): ?string
    {
        return match ($this->typeId) {
            self::TYPE_GAPPLE, self::TYPE_BUILDUHC, self::TYPE_FINALUHC,self::TYPE_CAVEUHC => 'Gapples',
            self::TYPE_SOUP, self::TYPE_HG, self::TYPE_SG => 'Soups',
            self::TYPE_NODEBUFF, self::TYPE_POTPVP, self::TYPE_DEBUFF => 'Pots',
            self::TYPE_BRIDGE, self::TYPE_BATTLERUSH, self::TYPE_MIDFIGHTS => 'Points',
            default => null
        };
    }

    public function finish(Player $loser): void
    {
        $firstSession = $this->firstSession;
        $secondSession = $this->secondSession;

        $this->statistics->getFirstStatistic()->calculateConsumables($this->typeId);
        $this->statistics->getSecondStatistic()->calculateConsumables($this->typeId);

        if ($loser->getName() === $firstSession->getName()) {
            $winnerPlayer = $secondSession->getPlayer();
            $loserPlayer = $firstSession->getPlayer();
            $winnerSession = $secondSession;
            $loserSession = $firstSession;
        } else {
            $winnerPlayer = $firstSession->getPlayer();
            $loserPlayer = $secondSession->getPlayer();
            $winnerSession = $firstSession;
            $loserSession = $secondSession;
        }

        $this->winner = $winnerPlayer?->getName() ?? 'Unknown';
        $this->loser = $loser->getName();

        if ($loserPlayer !== null) {
            $world = $loserPlayer->getWorld();
            $pos = $loserPlayer->getPosition();

            foreach (array_merge($loserPlayer->getInventory()->getContents(), $loserPlayer->getArmorInventory()->getContents()) as $item) {
                if (!$item->isNull()) {
                    $itemEntity = $world->dropItem($pos, $item);
                    if ($itemEntity !== null) {
                        $itemEntity->setPickupDelay(32767);
                    }
                }
            }

            $loserPlayer->getInventory()->clearAll();
            $loserPlayer->getArmorInventory()->clearAll();

            $loserPlayer->setGamemode(GameMode::SPECTATOR());
        }

        $firstStats = $this->statistics->getFirstStatistic();
        $secondStats = $this->statistics->getSecondStatistic();

        $firstPlayer = $firstSession->getPlayer();
        $secondPlayer = $secondSession->getPlayer();

        $label = $this->getConsumableLabel();

        $line = "§7";
        $summary = "§l§5Match Results" . PHP_EOL .
                   "§r§fWinner: §5" . $this->winner . PHP_EOL .
                   "§r§fDuration: §5" . gmdate('i:s', $this->running) . PHP_EOL .
                   $line . PHP_EOL;
        
        $spectatorNames = array_map(fn(Player $p) => $p->getName(), $this->spectators);
        $spectatorLine = "Spectator(" . count($this->spectators) . "): §f" . (count($this->spectators) > 0 ? PHP_EOL . implode(", ", $spectatorNames) : "None");

        if ($this->winner === $firstSession->getName()) {
            $orderedSessions = [$firstSession, $secondSession];
        } else {
            $orderedSessions = [$secondSession, $firstSession];
        }

        $fullStatsMsg = "";
        foreach ($orderedSessions as $session) {
            $pStats = $this->statistics->getStatistic($session);
            $statsMsg = "§l§5" . $session->getName() . " §r" . PHP_EOL .
                        "§fHits: §5" . $pStats->getHits() . PHP_EOL .
                        "§fCritics: §5" . $pStats->getCritics() . PHP_EOL .
                        "§fDamage: §5" . $pStats->getDamageDealt() . PHP_EOL;

            if ($label !== null) {
                $value = ($label === "Points") ? $pStats->getPoints() : $pStats->getTotalPotions();
                $statsMsg .= "§f" . $label . ": §5" . $value . PHP_EOL;
            }

            if ($this->typeId === self::TYPE_BOXING) {
                $statsMsg .= "§fMax Combo: §5" . $pStats->getMaxCombo() . PHP_EOL;
            }
            
            $fullStatsMsg .= $statsMsg . PHP_EOL;
        }

        $finalMessage = $summary . $fullStatsMsg . $spectatorLine . PHP_EOL . $line;
        
        $firstPlayer?->sendMessage($finalMessage);
        $secondPlayer?->sendMessage($finalMessage);
        foreach ($this->spectators as $spectator) {
            $spectator->sendMessage($finalMessage);
        }

        $winnerPlayer?->sendTitle(
            TextFormat::colorize('&l'),
            TextFormat::colorize('&7'),
            10, 60, 20
        );
        $loserPlayer?->sendTitle(
            TextFormat::colorize('&l&cDEFEAT!&r'),
            TextFormat::colorize('&a' . $this->winner . '&7 won the fight!'),
            10, 60, 20
        );

        if ($this->ranked) {
            $winnerElo = $winnerSession->getElo();
            $loserElo = $loserSession->getElo();
            $elms = self::calculateElo($loserElo, $winnerElo);

            $winnerSession->addElo($elms[0]);
            $loserSession->removeElo($elms[1]);
            
            $winnerNewElo = $winnerSession->getElo();
            $loserNewElo = $loserSession->getElo();

            Server::getInstance()->broadcastMessage(TextFormat::colorize(
                "&7Elo Changes: &a" . $winnerSession->getName() . " &7+&a" . $elms[0] . " &7(&f" . $winnerNewElo . "&7) &c" . $loserSession->getName() . " &7-&c" . $elms[1] . " &7(&f" . $loserNewElo . "&7)"
            ));
        }

        $this->log();

        $this->status = self::RESTARTING;
    }

    public static function calculateElo(int $loser, int $winner): array
    {
        $expectedScoreA = 1 / (1 + (pow(10, ($loser - $winner) / 400)));
        $expectedScoreB = abs(1 / (1 + (pow(10, ($winner - $loser) / 400))));

        $winnerElo = $winner + intval(32 * (1 - $expectedScoreA));
        $loserElo = $loser + intval(32 * (0 - $expectedScoreB));

        return [
            $winnerElo - $winner,
            abs($loser - $loserElo)
        ];
    }

    protected function log(): void
    {
        $webhook = new Webhook(
            $this->ranked
                ? Practice::getInstance()->getConfig()->get('webhook-ranked-duels', '')
                : Practice::getInstance()->getConfig()->get('webhook-unranked-duels', '')
        );

        $message = new Message();
        $embed = new Embed();

        if ($this->winner === $this->firstSession->getName()) {
            $winner = $this->firstSession;
            $loser = $this->secondSession;
        } else {
            $winner = $this->secondSession;
            $loser = $this->firstSession;
        }

        $embed->setColor(hexdec('00a6ff'));

        $winnerStats = $this->statistics->getStatistic($winner);
        $loserStats = $this->statistics->getStatistic($loser);

        $label = $this->getConsumableLabel();

        if ($this->typeId === self::TYPE_BOXING) {
            $winnerStatsStr = '**Hits:** ' . $winnerStats->getHits() . PHP_EOL . '**Max Combo:** ' . $winnerStats->getMaxCombo();
            $loserStatsStr = '**Hits:** ' . $loserStats->getHits() . PHP_EOL . '**Max Combo:** ' . $loserStats->getMaxCombo();
        } else {
            $consumableLog1 = ($label !== null) ? PHP_EOL . '**' . $label . ':** ' . $winnerStats->getTotalPotions() : "";
            $consumableLog2 = ($label !== null) ? PHP_EOL . '**' . $label . ':** ' . $loserStats->getTotalPotions() : "";

            $winnerStatsStr = '**Hits:** ' . $winnerStats->getHits() . PHP_EOL . '**Critics:** ' . $winnerStats->getCritics() . PHP_EOL . '**Damage:** ' . $winnerStats->getDamageDealt() . $consumableLog1;
            $loserStatsStr = '**Hits:** ' . $loserStats->getHits() . PHP_EOL . '**Critics:** ' . $loserStats->getCritics() . PHP_EOL . '**Damage:** ' . $loserStats->getDamageDealt() . $consumableLog2;
        }

        $title = ($this->ranked ? 'RANKED' : 'UNRANKED') . ' - ' . DuelFactory::getName($this->typeId);
        $embed->setTitle($title);
        
        $winnerTitle = '**Winner:** ' . $winner->getName() . ($this->ranked ? ' [' . $winner->getElo() . ']' : '');
        $loserTitle = '**Loser:** ' . $loser->getName() . ($this->ranked ? ' [' . $loser->getElo() . ']' : '');

        $embed->addField($winnerTitle, $winnerStatsStr);
        $embed->addField($loserTitle, $loserStatsStr);
        $embed->setFooter('Duration: ' . gmdate('i:s', $this->running));

        $message->addEmbed($embed);
        $webhook->send($message);
    }

    public function handleItemUse(PlayerItemUseEvent $event): void
    {
    }

    public function handleMove(PlayerMoveEvent $event): void
    {
    }

    public function update(): void
    {
        $firstPlayer = $this->firstSession->getPlayer();
        $secondPlayer = $this->secondSession->getPlayer();

        switch ($this->status) {
            case self::STARTING:
                if ($this->starting <= 0) {
                    $this->status = self::RUNNING;

                    if ($firstPlayer !== null && $firstPlayer->hasNoClientPredictions()) {
                        $firstPlayer->setNoClientPredictions(false);
                    }
                    if ($secondPlayer !== null && $secondPlayer->hasNoClientPredictions()) {
                        $secondPlayer->setNoClientPredictions(false);
                    }

                    $firstPlayer?->sendMessage(TextFormat::colorize('&l&eMatch started'));
                    $secondPlayer?->sendMessage(TextFormat::colorize('&l&eMatch started'));

                    $firstPlayer?->sendTitle('', TextFormat::colorize(''), 1, 1, 1);
                    $secondPlayer?->sendTitle('', TextFormat::colorize(''), 1, 1, 1);
                    return;
                }

                $color = match ($this->starting) {
                    5, 4 => '&a',
                    3, 2 => '&e',
                    1  => '&c',
                    default => '&7'
                };

                $msg = TextFormat::colorize($color . $this->starting . '&7...');
                $title = TextFormat::colorize($color . $this->starting);

                $firstPlayer?->sendMessage(TextFormat::colorize('&7The match will start in ' . $msg));
                $secondPlayer?->sendMessage(TextFormat::colorize('&7The match will start in ' . $msg));

                $firstPlayer?->sendTitle($title, TextFormat::colorize('&7Get ready!'), 5, 20, 5);
                $secondPlayer?->sendTitle($title, TextFormat::colorize('&7Get ready!'), 5, 20, 5);

                $this->starting--;
                break;

            case self::RUNNING:
                $this->running++;
                break;

            case self::RESTARTING:
                if ($this->restarting <= 0) {
                    $defaultWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();
                    $spawn = $defaultWorld?->getSpawnLocation();

                    $winnerPlayer = ($this->winner === $this->firstSession->getName()) ? $firstPlayer : $secondPlayer;
                    $loserPlayer = ($this->winner === $this->firstSession->getName()) ? $secondPlayer : $firstPlayer;

                    // Regenerar vida y quitar efectos SOLO al ir al lobby
                    if ($firstPlayer !== null) {
                        $firstPlayer->getEffects()->clear();
                        $firstPlayer->setHealth($firstPlayer->getMaxHealth());
                        $firstPlayer->getHungerManager()->setFood(20);
                        $firstPlayer->getHungerManager()->setSaturation(20.0);
                    }
                    if ($secondPlayer !== null) {
                        $secondPlayer->getEffects()->clear();
                        $secondPlayer->setHealth($secondPlayer->getMaxHealth());
                        $secondPlayer->getHungerManager()->setFood(20);
                        $secondPlayer->getHungerManager()->setSaturation(20.0);
                    }

                    if ($winnerPlayer !== null) {
                        $winnerPlayer->getInventory()->clearAll();
                        $winnerPlayer->getArmorInventory()->clearAll();
                    }

                    if ($firstPlayer !== null) {
                        $firstPlayer->teleport($spawn);
                    }

                    if ($secondPlayer !== null) {
                        $secondPlayer->teleport($spawn);
                    }

                    if ($loserPlayer !== null) {
                        $loserPlayer->setGamemode(GameMode::SURVIVAL());
                    }

                    $this->firstSession->giveLobbyItems();
                    $this->secondSession->giveLobbyItems();

                    $this->firstSession->setDuel(null);
                    $this->secondSession->setDuel(null);

                    foreach ($this->spectators as $spectator) {
                        $session = SessionFactory::get($spectator);
                        $session?->setDuel(null);
                        $session?->giveLobbyItems();
                        $spectator->setGamemode(GameMode::SURVIVAL());
                        $spectator->teleport($spawn);
                    }

                    $this->delete();
                    return;
                }
                $this->restarting--;
                break;
        }
    }

    public function delete(): void
    {
        Practice::getInstance()->getServer()->getWorldManager()->unloadWorld($this->world);
        Practice::getInstance()->getServer()->getAsyncPool()->submitTask(new WorldDeleteAsync(
            'duel-' . $this->id,
            Practice::getInstance()->getServer()->getDataPath() . 'worlds'
        ));
        DuelFactory::remove($this->id);
    }
}
