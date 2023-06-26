<?php

namespace bofoiii\bedwars\item;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\Villager;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier as IID;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\utils\CloningRegistryTrait;
use pocketmine\world\World;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static IronGolemSpawnEgg IRON_GOLEM_SPAWN_EGG()
 */
final class ExtraBedWarsItem
{
    use CloningRegistryTrait;

    const IRON_GOLEM_SPAWN_EGG = 20300;

    private function __construct()
    {
        //NOOP
    }

    protected static function register(string $name, Item $item): void
    {
        self::_registryRegister($name, $item);
    }

    /**
     * @return Item[]
     * @phpstan-return array<string, Item>
     */
    public static function getAll(): array
    {
        //phpstan doesn't support generic traits yet :(
        /** @var Item[] $result */
        $result = self::_registryGetAll();
        return $result;
    }

    protected static function setup(): void
    {
        self::register("iron_golem_spawn_egg", new IronGolemSpawnEgg(new IID(self::IRON_GOLEM_SPAWN_EGG), "Iron Golem Spawn Egg"));
    }
}
