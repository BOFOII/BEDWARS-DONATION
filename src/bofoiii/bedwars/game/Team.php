<?php

/**
 * Special Thanks For: MadeAja
 */

namespace bofoiii\bedwars\game;

use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Team
{


    /** @var Player[] $players */
    protected array $players = [];

    /** @var string $color */
    protected string $color;

    /** @var string $name */
    protected string $name;

    /** @var bool $hasBed */
    protected bool $hasBed = true;

    /** @var array $armorUpdates */

    /** @var int $dead */
    public int $dead = 0;

    /** @var bool $oneNotify */
    private bool $oneNotify = true;

    private array $vector;


    /** @var array $upgrades */
    public array $upgrades = [
        'sharpenedSwords' => 0,
        'armorProtection' => 0,
        'hasteManiac' => 0,
        'generator' => 1,
        'healPool' => 0,
        'dragon' => 0,
        'trap' => 0,
        'alarm' => 0,
        'offensive' => 0,
        'fatigue' => 0,
        'slotTrap' => ['index' => 0, 'price' => 1, 'slot' => [
            0 => ["name" => "Trap #1: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 1, "lore" => "§7The first enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b1 Diamond"]],
            1 => ["name" => "Trap #2: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 2, "lore" => "§7The second enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b2 Diamond"]],
            2 => ["name" => "Trap #3: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 3, "lore" => "§7The third enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b3 Diamond"]],
        ]]];


    /**
     * Team constructor.
     * @param string $name
     * @param string $color
     */
    public function __construct(string $name, string $color)
    {
        $this->name = $name;
        $this->color = $color;
    }

    public function hasTrap(): bool
    {
        if ($this->upgrades['trap'] === 1) {
            return true;
        }
        return false;
    }

    public function hasAlarm(): bool
    {
        if ($this->upgrades['alarm'] === 1) {
            return true;
        }
        return false;
    }

    public function hasOffensive(): bool
    {
        if ($this->upgrades['offensive'] === 1) {
            return true;
        }
        return false;
    }

    public function hasFatigue(): bool
    {
        if ($this->upgrades['fatigue'] === 1) {
            return true;
        }
        return false;
    }

    public function hasHealPool(): bool
    {
        if ($this->upgrades['healPool'] === 1) {
            return true;
        }
        return false;
    }

    public function thisTeam(Player $player): bool
    {
        if (isset($this->players[strtolower($player->getName())])) {
            return true;
        }
        return false;
    }

    public function resetTrapByIndex(string $name)
    {
        $slot = $this->upgrades['slotTrap']['activatedBySlot'][$name]['index'];
        $this->upgrades['slotTrap']['index'] -= 1;
        $this->upgrade($name, 3);
        switch ($slot) {
            case 0:
                $this->upgrades['slotTrap']['slot'][0] = ["name" => "Trap #1: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 1, "lore" => "§7The first enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b1 Diamond"]];
                break;
            case 1:
                $this->upgrades['slotTrap']['slot'][1] = ["name" => "Trap #2: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 1, "lore" => "§7The first enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b2 Diamond"]];
                break;
            case 2:
                $this->upgrades['slotTrap']['slot'][2] = ["name" => "Trap #3: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 1, "lore" => "§7The first enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b3 Diamond"]];
                break;
        }
        unset($this->upgrades['slotTrap']['activatedBySlot'][$name]);
    }


    public function setTrap(array $itemData, string $name): bool
    {
        if ($this->upgrades['slotTrap']['index'] === 3) {
            return false;
        }
        $index = $this->upgrades['slotTrap']['index'];
        $this->upgrades['slotTrap']['slot'][$index]['name'] = $itemData['item']['nameSlot'];
        $this->upgrades['slotTrap']['slot'][$index]['item'] = ['id' => $itemData['item']['id'], 'damage' => 0, 'count' => 1, 'lore' => $itemData['item']['loreSlot']];
        $this->upgrades['slotTrap']['index'] += 1;
        $this->upgrades['slotTrap']['activatedBySlot'][$name] = ['trap' => $name, 'index' => $index];
        return true;
    }

    /**
     * @return array
     */
    public function getTrap(): array
    {
        return $this->upgrades['slotTrap'];
    }


    /**
     * @param bool $oneNotify
     */
    public function setOneNotify(bool $oneNotify): void
    {
        $this->oneNotify = $oneNotify;
    }

    /**
     * @return bool
     */
    public function isOneNotify(): bool
    {
        return $this->oneNotify;
    }

    /**
     * @param Player $player
     */
    public function add(Player $player): void
    {
        $this->players[strtolower($player->getName())] = [
            'player' => $player,
            'armor' => 0,
            'knocker' => [
                'id' => null,
                'time' => null],
            'killstreak' => [
                'count' => 1,
                'time' => null
            ],
            'tools' => [
                'axe' => 0,
                'pickaxe' => 0,
                'axeBOOL' => false,
                'pickaxeBOOL' => false,
                'shearsBOOL' => false]];
    }

    public function remove(Player $player): void
    {
        unset($this->players[strtolower($player->getName())]);
    }


    public function setUpdatePropertyTools(Player $player, $type)
    {
        $this->players[strtolower($player->getName())]['tools'][$type]++;
    }

    public function getPropertyTools(Player $player, $type)
    {
        return $this->players[strtolower($player->getName())]['tools'][$type];
    }

    public function setBoolPropertyTools(Player $player, $type)
    {
        $this->players[strtolower($player->getName())]['tools'][$type . "BOOL"] = true;
    }

    public function resetPropertyTools(Player $player, $identifier)
    {
        $this->players[strtolower($player->getName())]['tools'][$identifier] = 1;
    }

    public function getMaxPropertyToolsByName(Player $player, $type): bool
    {
        if ($this->players[strtolower($player->getName())]['tools'][$type] === 4) {
            return false;
        }
        return true;
    }

    public function hasHaste(): bool
    {
        if ($this->upgrades['hasteManiac'] > 0) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getPlayers(): array
    {
        return $this->players;
    }


    /**
     * @param BOOL $state
     */
    public function updateBedState(bool $state): void
    {
        $this->hasBed = $state;
    }

    /**
     * @return BOOL
     */
    public function hasBed(): bool
    {
        return $this->hasBed;
    }

    /**
     * @param Player $player
     * @param int $armor
     */
    public function setArmor(Player $player, int $armor)
    {
        $this->players[strtolower($player->getName())]['armor'] = $armor;
    }

    /**
     * @param Player $player
     * @return int|null
     */
    public function getArmor(Player $player): ?int
    {
        return $this->players[strtolower($player->getName())]['armor'];
    }

    /**
     * @param Player $player
     * @param bool $shears
     */
    public function setShears(Player $player, bool $shears)
    {
        $this->players[strtolower($player->getName())]['tools']['shearsBOOL'] = $shears;
    }

    /**
     * @param Player $player
     * @return bool|null
     */
    public function hasShears(Player $player): ?bool
    {
        if ($this->players[strtolower($player->getName())]['tools']['shearsBOOL'] === true) {
            return true;
        }
        return false;
    }

    /**
     * @param Player $player
     * @return bool|null
     */
    public function hasAxe(Player $player): ?bool
    {
        if ($this->players[strtolower($player->getName())]['tools']['axeBOOL'] === true) {
            return true;
        }
        return false;
    }

    /**
     * @param Player $player
     * @return bool|null
     */
    public function hasPickaxe(Player $player): ?bool
    {
        if ($this->players[strtolower($player->getName())]['tools']['pickaxeBOOL'] === true) {
            return true;
        }
        return false;
    }


    /**
     * @param string $property
     * @param int $operator
     */
    public function upgrade(string $property, int $operator = 1): void
    {

        switch ($operator) {
            case 0:
                $this->upgrades[$property] -= 1;
                break;
            case 1:
                $this->upgrades[$property] += 1;
                break;
            case 2:
                $this->upgrades[$property] = 1;
                break;
            case 3:
                $this->upgrades[$property] = 0;
                break;
        }
    }

    /**
     * @param string $property
     * @return int|bool
     */
    public function getUpgrade(string $property)
    {
        return $this->upgrades[$property];
    }

    public function reset(): void
    {
        $this->upgrades = [
            'sharpenedSwords' => 0,
            'armorProtection' => 0,
            'hasteManiac' => 0,
            'generator' => 1,
            'healPool' => 0,
            'dragon' => 0,
            'trap' => 0,
            'alarm' => 0,
            'offensive' => 0,
            'fatigue' => 0,
            'slotTrap' => ['index' => 0, 'price' => 1, 'slot' => [
                0 => ["name" => "Trap #1: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 1, "lore" => "§7The first enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b1 Diamond"]],
                1 => ["name" => "Trap #2: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 2, "lore" => "§7The second enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b2 Diamond"]],
                2 => ["name" => "Trap #3: No Trap!", "item" => ["id" => 241, "damage" => 15, "count" => 3, "lore" => "§7The third enemy walk into your base will trigger this trap!\n\n§7Purchasing a trap will queue it here. Its cost will scale based on the number of traps queued.\n\n§7Next trap: §b3 Diamond"]],
            ]]];
        $this->hasBed = true;
        $this->oneNotify = true;
        $this->players = [];
        $this->dead = 0;
        $this->vector = [];
    }
    /**
     * @param $x
     * @param $y
     * @param $z
     * @param int $radius
     */
    public function setVector($x, $y, $z, int $radius = 10)
    {
        $this->vector = ["x" => $x, "y" => $y, "z" => $z, "radius" => $radius];
    }

    public function asVector3(): Vector3
    {
        return new Vector3((float)$this->vector["x"], (float)$this->vector["y"], (float)$this->vector["z"]);
    }

    public function getRadius(): float
    {
        return (int)$this->vector["radius"] ?? 10;
    }
}