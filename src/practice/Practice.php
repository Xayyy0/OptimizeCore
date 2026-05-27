<?php

declare(strict_types=1);

namespace practice;

use pocketmine\data\bedrock\EntityLegacyIds;
use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\PotionTypeIdMap;
use pocketmine\data\bedrock\PotionTypeIds;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use practice\arena\ArenaFactory;
use practice\arena\command\ArenaCommand;
use practice\command\PingCommand;
use practice\command\SpawnCommand;
use practice\command\WhoCommand;
use practice\command\BuilderModeCommand;
use practice\database\mysql\MySQL;
use practice\database\mysql\queries\QueryAsync;
use practice\database\mysql\Table;
use practice\duel\command\DuelCommand;
use practice\duel\DuelFactory;
use practice\entity\EnderPearl;
use practice\entity\SplashPotion;
use practice\entity\FishingHook;
use practice\item\EnderPearlItem;
use practice\item\GoldenHeadItem;
use practice\item\SplashPotionItem;
use practice\item\FishingRodItem;
use practice\kit\command\KitCommand;
use practice\kit\KitFactory;
use practice\party\duel\DuelFactory as PartyDuelFactory;
use practice\session\SessionFactory;
use practice\world\generator\VoidGenerator;
use practice\world\WorldFactory;

final class Practice extends PluginBase{

	public const IS_DEVELOPING = false;

	static private ?self $instance = null;

	protected function onLoad() : void{
		self::$instance = $this;

		$this->saveResource('config.yml');
		$this->saveResource('kits.yml');

		MySQL::setCredentials($this->getConfig()->get('database'));
	}

	protected function onEnable() : void{
		$this->setup();
		$this->createTables();

		$this->registerEntities();
		$this->registerItems();
		$this->registerGenerators();
		$this->registerHandlers();
		$this->registerCommands();
		$this->unregisterCommands();

		ArenaFactory::loadAll();
		KitFactory::loadAll();
		SessionFactory::loadAll();
		WorldFactory::loadAll();

		DuelFactory::task();
		PartyDuelFactory::task();
		SessionFactory::task();
        $this->getServer()->getLogger()->info("plugin pendejo fue activado, ");
	}

	protected function setup() : void{
		$config = $this->getConfig();

		$this->getServer()->getNetwork()->setName(TextFormat::colorize($config->get('server-motd', '')));
		$this->getServer()->getQueryInformation()->setMaxPlayerCount($config->get('server-max-players', 200));
	}

	protected function createTables() : void{
		MySQL::runAsync(new QueryAsync(Table::DUEL_STATS));
		MySQL::runAsync(new QueryAsync(Table::PLAYER_SETTINGS));
		MySQL::runAsync(new QueryAsync(Table::PLAYER_INVENTORIES));
	}

	protected function registerEntities() : void{
		EntityFactory::getInstance()->register(EnderPearl::class, static function(World $world, CompoundTag $nbt) : EnderPearl{
			return new EnderPearl(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
		}, ['ThrownEnderpearl', EntityIds::ENDER_PEARL]);

		EntityFactory::getInstance()->register(SplashPotion::class, static function(World $world, CompoundTag $nbt) : SplashPotion{
			$potionType = PotionTypeIdMap::getInstance()->fromId($nbt->getShort('PotionId', PotionTypeIds::WATER));

			if($potionType === null){
				throw new SavedDataLoadingException;
			}
			return new SplashPotion(EntityDataHelper::parseLocation($nbt, $world), null, $potionType, $nbt);

		}, ['ThrownPotion', 'minecraft:potion', 'thrownpotion', EntityIds::SPLASH_POTION]);

		EntityFactory::getInstance()->register(FishingHook::class, static function(World $world, CompoundTag $nbt) : FishingHook{
			return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
		}, ['FishingHook', EntityIds::FISHING_HOOK]);
	}

	public static function getInstance() : self{
		return self::$instance;
	}

	protected function registerItems() : void{
		$this->overwriteItem(ItemTypeNames::ENDER_PEARL, new EnderPearlItem, ['ender_pearl']);
		$this->overwriteItem(ItemTypeNames::GOLD_NUGGET, new GoldenHeadItem, ['gold_nugget']);
		$this->overwriteItem(ItemTypeNames::SPLASH_POTION, $splashPotion = new SplashPotionItem, ['splash_potion'], function(Item $item) : SavedItemData{
			assert($item instanceof \pocketmine\item\SplashPotion);
			$meta = PotionTypeIdMap::getInstance()->toId($item->getType());

			return new SavedItemData(ItemTypeNames::SPLASH_POTION, $meta);
		}, function(SavedItemData $data) use ($splashPotion) : Item{
			$result = clone $splashPotion;
			$meta = $data->getMeta();
			$result->setType(PotionTypeIdMap::getInstance()->fromId($meta) ?? throw new ItemTypeDeserializeException('Unknown potion type ID ' . $meta));

			return $result;
		});

		$this->overwriteItem(
			ItemTypeNames::FISHING_ROD,
			new FishingRodItem,
			['fishing_rod', 'fishingrod']
		);
	}

	private function overwriteItem(string $id, Item $item, array $stringToItemParserNames, ?callable $serializerCallback = null, ?callable $deserializerCallback = null) : void{
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();
		(fn() => $this->itemSerializers[$item->getTypeId()] = $serializerCallback !== null ? $serializerCallback : static fn() => new SavedItemData($id))->call($serializer);
		(function() use ($id, $item, $deserializerCallback) : void{
			if(isset($this->deserializers[$id])){
				unset($this->deserializers[$id]);
			}
			$this->map($id, $deserializerCallback !== null ? $deserializerCallback : static fn(SavedItemData $_) => clone $item);
		})->call($deserializer);
		foreach($stringToItemParserNames as $name){
			StringToItemParser::getInstance()->override($name, fn() => clone $item);
		}
	}

	protected function registerGenerators() : void{
		GeneratorManager::getInstance()->addGenerator(VoidGenerator::class, 'void', fn() => null, true);
	}

	protected function registerHandlers() : void{
		$this->getServer()->getPluginManager()->registerEvents(new EventHandler(), $this);
	}

	protected function registerCommands() : void{
		$commands = [
			new SpawnCommand,
			new PingCommand,
			new WhoCommand,
			new ArenaCommand,
			new DuelCommand,
			new KitCommand,
            new BuilderModeCommand
		];

		foreach($commands as $command){
			$this->getServer()->getCommandMap()->register('Practice', $command);
		}
	}

	protected function unregisterCommands() : void{
		$commands = [
			'me',
			'kill',
			'suicide',
			'clear',
		];

		foreach($commands as $commandName){
			$command = $this->getServer()->getCommandMap()->getCommand($commandName);

			if($command !== null){
				$this->getServer()->getCommandMap()->unregister($command);
			}
		}
	}

	protected function onDisable() : void{
		ArenaFactory::saveAll();
		KitFactory::saveAll();
		SessionFactory::saveAll();
		WorldFactory::saveAll();

		DuelFactory::disable();
		PartyDuelFactory::disable();
	}
}