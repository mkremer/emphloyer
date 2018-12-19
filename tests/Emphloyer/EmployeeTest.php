<?php

namespace Emphloyer;

class EmployeeTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        $this->employee = new Employee();

        $this->tempPath = __DIR__ . "/_files/tmp";

        if (is_dir($this->tempPath)) {
            system('rm -rf ' . escapeshellarg($this->tempPath));
        }

        mkdir($this->tempPath);
    }

    public function testValidOptions()
    {
        $options = array('only' => array('special'));
        $employee = new Employee($options);
        $this->assertEquals($options, $employee->getOptions());

        $options = array('exclude' => array('special'));
        $employee = new Employee($options);
        $this->assertEquals($options, $employee->getOptions());
    }

    public function testInvalidOptionsThrowsException()
    {
        $invalids = array(
            array('only' => 'special'),
            array('exclude' => 'special'),
        );

        $fails = 0;
        foreach ($invalids as $options) {
            try {
                $employee = new Employee($options);
                $this->fail("InvalidArgumentException was expected.");
            } catch (\InvalidArgumentException $e) {
                ++$fails;
            }
        }
        $this->assertEquals(2, $fails);
    }

    public function shortSleep()
    {
        usleep(100000);
    }

    public function shortSleepAndFail()
    {
        usleep(100000);
        throw new \Exception();
    }

    public function testIsFree()
    {
        $this->assertTrue($this->employee->isFree());
        $this->employee->work($this->createMock('Emphloyer\Job'));
        $this->assertFalse($this->employee->isFree());
    }

    public function testGetJob()
    {
        $this->assertNull($this->employee->getJob());

        $job = $this->createMock('Emphloyer\Job');
        $this->employee->work($job);
        $this->assertEquals($job, $this->employee->getJob());
    }

    public function testRejectsJobWhileItIsNotFree()
    {
        $this->employee->work($this->getCompletingJob());
        $this->assertFalse($this->employee->isFree());

        try {
            $this->employee->work($this->getCompletingJob());
            $this->fail("Expected an EmployeeNotFreeException");
        } catch (Exceptions\EmployeeNotFreeException $e) {
        }
    }

    public function getCompletingJob()
    {
        $job = $this->createMock('Emphloyer\Job');
        $job->expects($this->any())
            ->method('perform')
            ->will($this->returnCallback(array($this, 'shortSleep')));
        return $job;
    }

    public function testCannotBeFreedUntilJobIsCompletedOrFailed()
    {
        $this->employee->work($this->getCompletingJob());
        $this->assertFalse($this->employee->isFree());

        try {
            $this->employee->free();
            $this->fail("Shouldn't be able to free a busy employee");
        } catch (Exceptions\EmployeeIsBusyException $exception) {
        }

        usleep(200000);
        $this->employee->free();
        $this->assertTrue($this->employee->isFree());

        $this->employee->work($this->getFailingJob());
        $this->assertFalse($this->employee->isFree());

        try {
            $this->employee->free();
            $this->fail("Shouldn't be able to free a busy employee");
        } catch (Exceptions\EmployeeIsBusyException $exception) {
        }

        usleep(200000);
        $this->employee->free();
        $this->assertTrue($this->employee->isFree());
    }

    public function getFailingJob()
    {
        $job = $this->createMock('Emphloyer\Job');
        $job->expects($this->any())
            ->method('perform')
            ->will($this->returnCallback(array($this, 'shortSleepAndFail')));
        return $job;
    }

    public function testReportsWorkState()
    {
        $this->employee->work($this->getCompletingJob());
        $this->assertEquals(Employee::BUSY, $this->employee->getWorkState());
        usleep(200000);
        $this->assertEquals(Employee::COMPLETE, $this->employee->getWorkState());
        $this->employee->free();

        $this->employee->work($this->getFailingJob());
        $this->assertEquals(Employee::BUSY, $this->employee->getWorkState());
        usleep(200000);
        $this->assertEquals(Employee::FAILED, $this->employee->getWorkState());
    }

    public function testReportWorkStateAndWaitForCompletion()
    {
        $this->employee->work($this->getCompletingJob());
        $this->assertEquals(Employee::BUSY, $this->employee->getWorkState());
        $this->assertEquals(Employee::COMPLETE, $this->employee->getWorkState(true));
    }

    public function testStopEmployee()
    {
        $this->employee->work($this->getCompletingJob());
        $this->assertTrue($this->employee->isBusy());
        $workPid = $this->employee->getWorkPid();
        $this->assertNotNull($workPid);

        $this->employee->stop();
        $this->assertFalse(posix_kill($workPid, 0));
        $this->assertFalse($this->employee->isBusy());
        $this->assertEquals(Employee::FAILED, $this->employee->getWorkState());
    }
}
