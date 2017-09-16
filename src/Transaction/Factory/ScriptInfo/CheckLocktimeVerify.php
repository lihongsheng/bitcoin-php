<?php

namespace BitWasp\Bitcoin\Transaction\Factory\ScriptInfo;


use BitWasp\Bitcoin\Locktime;
use BitWasp\Bitcoin\Script\Interpreter\Number;
use BitWasp\Bitcoin\Script\Opcodes;
use BitWasp\Bitcoin\Script\Parser\Operation;
use BitWasp\Bitcoin\Script\ScriptInterface;

class CheckLocktimeVerify
{
    /**
     * @var int
     */
    private $nLockTime;

    /**
     * @var Locktime
     */
    private $locktime;

    /**
     * CheckLocktimeVerify constructor.
     * @param int $nLockTime
     */
    public function __construct($nLockTime)
    {
        $this->checkLockTimeRange($nLockTime);

        $this->nLockTime = $nLockTime;
        $this->locktime = new Locktime();
    }

    /**
     * @param Operation[] $chunks
     * @param bool $fMinimal
     * @return static
     */
    public static function fromDecodedScript(array $chunks, $fMinimal = false)
    {
        if (count($chunks) !== 3) {
            throw new \RuntimeException("Invalid number of items for CLTV");
        }

        if (!$chunks[0]->isPush()) {
            throw new \InvalidArgumentException('CLTV script had invalid value for time');
        }

        if ($chunks[1]->getOp() !== Opcodes::OP_CHECKLOCKTIMEVERIFY) {
            throw new \InvalidArgumentException('CLTV script invalid opcode');
        }

        if ($chunks[2]->getOp() !== Opcodes::OP_DROP) {
            throw new \InvalidArgumentException('CLTV script invalid opcode');
        }

        $numLockTime = Number::buffer($chunks[0]->getData(), $fMinimal, 5);

        return new static($numLockTime->getInt());
    }

    /**
     * @param ScriptInterface $script
     * @return CheckLocktimeVerify
     */
    public static function fromScript(ScriptInterface $script)
    {
        return static::fromDecodedScript($script->getScriptParser()->decode());
    }

    /**
     * @param int $nLockTime
     */
    private function checkLockTimeRange($nLockTime)
    {
        if ($nLockTime < 0) {
            throw new \RuntimeException("locktime cannot be negative");
        }

        if ($nLockTime > Locktime::INT_MAX) {
            throw new \RuntimeException("nLockTime exceeds maximum value");
        }
    }

    /**
     * @param int $nLockTime
     * @param bool $isLockedToBlock
     */
    private function checkAgainstRange($nLockTime, $isLockedToBlock)
    {
        if ($isLockedToBlock) {
            if ($nLockTime > Locktime::BLOCK_MAX) {
                throw new \RuntimeException("This CLTV is locked to block-height, but timeNowOrBlock was in timestamp range");
            }
        } else {
            if ($nLockTime < Locktime::BLOCK_MAX) {
                throw new \RuntimeException("This CLTV is locked to timetsamp, but timeNowOrBlock was in block-height range");
            }
        }
    }

    /**
     * @return int
     */
    public function getLocktime()
    {
        return $this->nLockTime;
    }

    /**
     * @return bool
     */
    public function isLockedToBlock()
    {
        return $this->locktime->isLockedToBlock($this->nLockTime);
    }

    /**
     * @param int $timeNowOrBlock
     * @return bool
     */
    public function isSpendable($timeNowOrBlock)
    {
        $this->checkLockTimeRange($timeNowOrBlock);
        $this->checkAgainstRange($timeNowOrBlock, $this->isLockedToBlock());

        return $timeNowOrBlock >= $this->nLockTime;
    }
}