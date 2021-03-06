<?php
namespace Procrastinator;

use PHPUnit\Framework\TestCase;
use Procrastinator\Deferred\Builder;
use Procrastinator\Deferred\Deferred;
use Procrastinator\Executor\Executor;
use Procrastinator\Scheduler\Scheduler;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class ManagerTest extends TestCase
{
    /** @var DeferralManager */
    private $manager;

    /** @var Deferred|MockObject */
    private $deferred1;

    /** @var Deferred|MockObject */
    private $deferred2;

    /** @var Scheduler|MockObject */
    private $scheduler;

    /** @var Executor|MockObject */
    private $executor;

    public function setUp()
    {
        $this->deferred1 = $this->createMock(Deferred::class);
        $this->deferred2 = $this->createMock(Deferred::class);
        $this->scheduler = $this->createMock(Scheduler::class);
        $this->executor = $this->createMock(Executor::class);
        $this->manager = new DeferralManager($this->scheduler, $this->executor);
    }

    public function testRegisteringDeferred()
    {
        $this->mockGetName($this->deferred1);

        $this->assertFalse($this->manager->has('testname'));
        $this->assertSame($this->manager, $this->manager->register($this->deferred1));
        $this->assertTrue($this->manager->has('testname'));
        $this->assertSame($this->deferred1, $this->manager->get('testname'));
    }

    public function testReRegisteringDeferredWithSameNameOverrides()
    {
        $this->mockGetName($this->deferred1);
        $this->mockGetName($this->deferred2);

        $this->assertSame($this->manager, $this->manager->register($this->deferred1));
        $this->assertTrue($this->manager->has('testname'));
        $this->assertSame($this->deferred1, $this->manager->get('testname'));
        $this->assertSame($this->manager, $this->manager->register($this->deferred2));
        $this->assertTrue($this->manager->has('testname'));
        $this->assertSame($this->deferred2, $this->manager->get('testname'));
    }

    public function testCallingScheduleCallsSchedulerAndFreezesManager()
    {
        $this->mockGetName($this->deferred1, 'test1');
        $this->mockGetName($this->deferred2, 'test2');

        $this->manager->register($this->deferred1);
        $this->manager->register($this->deferred2);

        $this->scheduler
            ->expects($this->once())
            ->method('schedule')
            ->will($this->returnCallback([$this, 'assertIsExecutableManager']));

        $executableManager = $this->manager->schedule();
        $this->assertInstanceOf(ExecutableManager::class, $executableManager);
    }

    public function testSchedulerIsNotCalledWhenNoDefferedsArePresent()
    {
        $this->scheduler->expects($this->never())->method('schedule');
        $this->assertNull($this->manager->schedule());
    }

    public function testSchedulingResetsDeferreds()
    {
        $this->mockGetName($this->deferred1, 'test1');
        $this->mockGetName($this->deferred2, 'test2');

        $this->manager->register($this->deferred1);
        $this->manager->register($this->deferred2);

        $this->scheduler
            ->expects($this->once())
            ->method('schedule')
            ->will($this->returnCallback([$this, 'assertIsExecutableManager']));

        $this->manager->schedule();
        $this->manager->schedule();
    }

    public function testExecuteCallsExecutor()
    {
        $this->mockGetName($this->deferred1, 'test1');
        $this->mockGetName($this->deferred2, 'test2');
        $this->executor
            ->expects($this->at(0))
            ->method('startExecution')
            ->will($this->returnCallback([$this, 'assertIsExecutableManager']));
        $this->executor
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->deferred1);
        $this->executor
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->deferred2);
        $this->executor
            ->expects($this->at(3))
            ->method('endExecution')
            ->will($this->returnCallback([$this, 'assertIsExecutableManager']));
        $this->manager
            ->register($this->deferred1);
        $this->manager
            ->register($this->deferred2);
        $executable = $this->manager->schedule();

        $executable->execute();
    }

    private function mockGetName(Deferred $deferred, $name = 'testname')
    {
        /** @var $deferred MockObject */
        $deferred
            ->expects($this->any())
            ->method('getName')
            ->with()
            ->will($this->returnValue($name));
    }

    public function assertIsExecutableManager(ExecutableManager $manager)
    {
        $this->assertSame($this->executor, $manager->getExecutor());
        $deferreds = $manager->getAll();
        $this->assertCount(2, $deferreds);
        $this->assertSame('test1', $deferreds[0]->getName());
        $this->assertSame('test2', $deferreds[1]->getName());
    }

    public function testNewDeferredReturnsNewBuilder()
    {
        $builder1 = $this->manager->newDeferred();
        $builder2 = $this->manager->newDeferred();
        $this->assertInstanceOf(Builder::class, $builder1);
        $this->assertInstanceOf(Builder::class, $builder2);
        $this->assertNotSame($builder1, $builder2);
    }
}
