<?php
namespace LazyBundle\Test;

use LazyBundle\Util\SharedMemory;

class SharedMemoryTest extends \PHPUnit_Framework_TestCase
{
    public function testIsCreatingNewBlock()
    {
        $memory = new SharedMemory();
        $this->assertInstanceOf('\LazyBundle\Util\SharedMemory', $memory);

        $memory->write('Sample');
        $data = $memory->read();
        $this->assertEquals('Sample', $data);
    }

    public function testIsCreatingNewBlockWithId()
    {
        $memory = new SharedMemory(897);
        $this->assertInstanceOf('\LazyBundle\Util\SharedMemory', $memory);
        $this->assertEquals(897, $memory->getId());

        $memory->write('Sample 2');
        $data = $memory->read();
        $this->assertEquals('Sample 2', $data);
    }

    public function testIsMarkingBlockForDeletion()
    {
        $memory = new SharedMemory(897);
        $memory->delete();
        $data = $memory->read();
        $this->assertEquals('Sample 2', $data);
    }

    public function testIsPersistingNewBlockWithoutId()
    {
        $memory = new SharedMemory();
        $this->assertInstanceOf('\LazyBundle\Util\SharedMemory', $memory);
        $memory->write('Sample 3');
        unset($memory);

        $memory = new SharedMemory();
        $data = $memory->read();
        $this->assertEquals('Sample 3', $data);
    }
}