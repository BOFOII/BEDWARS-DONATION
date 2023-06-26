<?php

namespace bofoiii\bedwars\entity;

use bofoiii\bedwars\game\Game;
use pocketmine\block\Block;
use pocketmine\block\Flowable;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Egg as ProjectileEgg;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

class Egg extends ProjectileEgg {

    private array $positions = [];
    public ?Game $game;
    public ?Player $owner;

    public function __construct(Location $location, ?CompoundTag $nbt = null, ?Player $shootingEntity = null)
    {
        parent::__construct($location, $shootingEntity, $nbt);
    }

    public function entityBaseTick(int $tickDiff = 1): bool
	{
		if ($this->closed || !$this->game instanceof Game || !$this->owner instanceof Player)  {
			$this->flagForDespawn();
			return false;
		}

		return true;
	}

    protected function move(float $dx, float $dy, float $dz): void
    {
        parent::move($dx, $dy, $dz);
        $pos = $this->getPosition();
        if ($this->getWorld()->getBlockAt((int) $pos->getX(), (int) $pos->getY(), (int) $pos->getZ()) instanceof Flowable) {
            $this->positions[] = $this->getPosition()->asVector3();
            $this->positions[] = $this->getPosition()->subtract(1, 0, 0);
            $this->positions[] = $this->getPosition()->subtract(0, 0, 1);
            $this->positions[] = $this->getPosition()->add(1, 0, 0);
            $this->positions[] = $this->getPosition()->add(0, 0, 1);
        }
    }

    protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult): void
    {
        parent::onHitBlock($blockHit, $hitResult);
        $this->run();
    }

    public function run(): void
    {
        $this->flagForDespawn();

        foreach($this->positions as $pos) {
            if (!$this->getWorld()->getBlock($pos, false, false) instanceof Flowable) {
                continue;
            }   

            $this->getWorld()->setBlock($pos, VanillaBlocks::WOOL());
        }
    }
}