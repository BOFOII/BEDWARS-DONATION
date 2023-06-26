<?php

namespace bofoiii\bedwars\item;

use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalItemDataHandlers;

class ItemFactory {

    public static function registerItems() : void{
		$item = ExtraBedWarsItem::IRON_GOLEM_SPAWN_EGG();
		self::registerSimpleItem(ItemTypeNames::IRON_GOLEM_SPAWN_EGG, $item, ["iron_golem_spawn_egg"]);
	}

    private static function registerSimpleItem(string $id, Item $item, array $stringToItemParserNames) : void{
		GlobalItemDataHandlers::getDeserializer()->map($id, fn() => clone $item);
		GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData($id));

		foreach($stringToItemParserNames as $name){
			StringToItemParser::getInstance()->register($name, fn() => clone $item);
		}
	}

}