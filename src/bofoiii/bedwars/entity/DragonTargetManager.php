<?php

/**
 * Special Thanks For: SandhyR, MadeAja, GamakCZ
 */

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\game\Game;
use pocketmine\block\Bed;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\WorldException;

class DragonTargetManager
{

    public const MAX_DRAGON_MID_DIST = 100; // Dragon will rotate when will be distanced 64 blocks from map center

    /** @var Game $plugin */
    public Game $plugin;

    /** @var Vector3[] $baits */
    public array $baits = [];
    /** @var Vector3 $mid */
    public Vector3 $mid; // Used when all the blocks the are broken

    /** @var EnderDragon[] $dragons */
    public array $dragons = [];

    /** @var Random $random */
    public Random $random;

    /**
     * DrgaonManager constructor.
     * @param Game $plugin
     * @param Vector3 $mid
     */
    public function __construct(Game $plugin, Vector3 $mid)
    {
        $this->plugin = $plugin;
        $this->mid = $mid;
        $this->random = new Random();
    }


    /**
     * @param string $team
     * @return EnderDragon
     */
    public function getDragon(string $team): EnderDragon
    {
        return $this->dragons[$team];
    }

    /**
     * @param string $teamName
     * @return Vector3
     */
    public function getDragonTarget(string $teamName): Vector3
    {
        foreach ($this->plugin->players as $player) {
            if (!in_array(strtolower($player->getName()), $this->getDragon($teamName)->array_of_team)) {
                $this->addBaitByTeam($this->plugin->getTeam($player), $player);
            }
        }

        if (empty($this->baits)) {
            return $this->mid;
        }
        if (!isset($this->baits[$teamName])) {
            return $this->mid;
        }
        if (empty($this->baits[$teamName])) {
            return $this->mid;
        }
        foreach ($this->baits[$teamName] as $key => $pos) {
            $check = $pos;
            $this->baits[$teamName] = [];
            return $check;
        }
        return $this->mid;
    }

    /**
     * @param EnderDragon $dragon
     *
     * @param int $x
     * @param int $y
     * @param int $z
     */
    public function removeBlock(EnderDragon $dragon, int $x, int $y, int $z): void
    {
        if (!$this->plugin->world->getBlock(new Vector3($x, $y, $z)) instanceof Bed) {
            try {
                $this->plugin->world->setBlock(new Vector3($x, $y, $z), VanillaBlocks::AIR());
            } catch (\InvalidArgumentException|WorldException) {
            }
            $dragon->changeRotation(true);
        }
    }

    public function addDragon(string $team): void
    {
        $findSpawnPos = function (Vector3 $mid): Vector3 {
            $randomAngle = mt_rand(0, 359);
            $x = ((DragonTargetManager::MAX_DRAGON_MID_DIST - 5) * cos($randomAngle)) + $mid->getX();
            $z = ((DragonTargetManager::MAX_DRAGON_MID_DIST - 5) * sin($randomAngle)) + $mid->getZ();

            return new Vector3($x, $mid->getY(), $z);
        };
        $dragon = new EnderDragon(Location::fromObject($findSpawnPos($this->mid), $this->plugin->world));
        $this->dragons[$team] = $dragon;
        $dragon->targetManager = $this;
        $dragon->owner_team = $team;
        $rows = [];
        foreach ($this->plugin->players as $player) {
            if ($this->plugin->getTeam($player) == $team) {
                $rows[] = strtolower($player->getName());
            }
        }
        $dragon->array_of_team = $rows;
        $dragon->color = $this->plugin->data["teamColor"][$team];
        $dragon->lookAt($this->getDragonTarget($team));
        $dragon->setNameTag($this->plugin->data["teamColor"][$team] . "§l" . $team . " Dragon");
        $dragon->spawnToAll();
    }


    public function addBaitByTeam(string $teamName, Player $sender)
    {
        $this->addBait($teamName, $sender->getPosition()->asVector3());
    }

    /**
     * @param string $team
     * @param Vector3 $baitPos
     */
    public function addBait(string $team, Vector3 $baitPos)
    {
        $this->baits[$team][] = $baitPos;
    }
}
