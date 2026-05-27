<?php

declare(strict_types=1);

namespace practice\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\session\SessionFactory;

final class BuilderModeCommand extends Command{

	public function __construct(){
		parent::__construct('buildermode', 'Command for build', null, ['build']);
		$this->setPermission('buildermode.command');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$sender instanceof Player){
			return;
		}
		$session = SessionFactory::get($sender);

		if($session === null){
			return;
		}

		$result = !$session->isBuilderMode();

        $session->setBuilderMode($result);
        $sender->sendMessage(TextFormat::colorize("&aYou are now " . ($result ? "entering" : "leaving") . " builder mode."));
        
	}
}