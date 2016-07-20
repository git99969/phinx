<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\ConfigInterface;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;
use Phinx\Console\Command\Rollback;

class RollbackTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigInterface|array
     */
    protected $config = array();

    /**
     * @var InputInterface $input
     */
    protected $input;

    /**
     * @var OutputInterface $output
     */
    protected $output;

    /**
     * Default Test Environment
     */
    const DEFAULT_TEST_ENVIRONMENT = 'development';

    protected function setUp()
    {
        $this->config = new Config(array(
            'paths' => array(
                'migrations' => __FILE__,
            ),
            'environments' => array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'development',
                'development' => array(
                    'adapter' => 'mysql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 3006,
                )
            )
        ));

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Rollback());

        /** @var Rollback $command */
        $command = $application->find('rollback');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('rollback')
                    ->with(self::DEFAULT_TEST_ENVIRONMENT, null, false, true);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $display = $commandTester->getDisplay();

        $this->assertRegExp('/no environment specified/', $display);

        // note that the default order is by creation-time
        $this->assertRegExp('/ordering by creation-time/', $display);
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Rollback());

        /** @var Rollback $command */
        $command = $application->find('rollback');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('rollback')
                    ->with('fakeenv', null, false, true);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '--environment' => 'fakeenv'), array('decorated' => false));
        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
    }

    public function testDatabaseNameSpecified()
    {
        $application = new PhinxApplication('testing');
        $application->add(new Rollback());

        /** @var Rollback $command */
        $command = $application->find('rollback');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('rollback')
                    ->with(self::DEFAULT_TEST_ENVIRONMENT, null, false);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));
        $this->assertRegExp('/using database development/', $commandTester->getDisplay());
    }
    
    public function testStartTimeVersionOrder()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Rollback());

        // setup dependencies
        $this->config['version_order'] = \Phinx\Config\Config::VERSION_ORDER_START_TIME;

        $command = $application->find('rollback');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));
        $managerStub->expects($this->once())
                    ->method('rollback')
                    ->with(self::DEFAULT_TEST_ENVIRONMENT, null, false, true);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));
        $this->assertRegExp('/ordering by start-time/', $commandTester->getDisplay());
    }
    
    public function testWithDate()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');

        $date = '20160101';
        $target = '20160101000000';
        $rollbackStub = $this->getMock('\Phinx\Console\Command\Rollback', array('getTargetFromDate'));
        $rollbackStub->expects($this->once())
                    ->method('getTargetFromDate')
                    ->with($date)
                    ->will($this->returnValue($target));

        $application->add($rollbackStub);

        // setup dependencies
        $command = $application->find('rollback');

        // mock the manager class
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));
        $managerStub->expects($this->once())
                    ->method('rollback')
                    ->with(self::DEFAULT_TEST_ENVIRONMENT, $target, false, false);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '-d' => $date), array('decorated' => false));
    }

    /**
     * @dataProvider getTargetFromDataProvider
     */
    public function testGetTargetFromDate($date, $expectedTarget)
    {
        $rollbackCommand = new Rollback();
        $this->assertEquals($expectedTarget, $rollbackCommand->getTargetFromDate($date));
    }

    public function getTargetFromDataProvider()
    {
        return [
            'Date with only year' => [
                '2015', '20150101000000'
            ],
            'Date with year and month' => [
                '201409', '20140901000000'
            ],
            'Date with year, month and day' => [
                '20130517', '20130517000000'
            ],
            'Date with year, month, day and hour' => [
                '2013051406', '20130514060000'
            ],
            'Date with year, month, day, hour and minutes' => [
                '201305140647', '20130514064700'
            ],
            'Date with year, month, day, hour, minutes and seconds' => [
                '20130514064726', '20130514064726'
            ]
        ];
    }


    /**
     * @dataProvider getTargetFromDateThrowsExceptionDataProvider
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid date. Format is YYYY[MM[DD[HH[II[SS]]]]].
     */
    public function testGetTargetFromDateThrowsException($invalidDate)
    {
        $rollbackCommand = new Rollback();
        $rollbackCommand->getTargetFromDate($invalidDate);
    }
    
    public function getTargetFromDateThrowsExceptionDataProvider()
    {
        return [
            ['20'],
            ['2015060522354698'],
            ['invalid']
        ];
    }

    public function testStarTimeVersionOrderWithDate()
    {
        $application = new \Phinx\Console\PhinxApplication('testing');
        $application->add(new Rollback());

        // setup dependencies
        $this->config['version_order'] = \Phinx\Config\Config::VERSION_ORDER_START_TIME;

        $command = $application->find('rollback');

        // mock the manager class
        $targetDate = '20150101';
        $managerStub = $this->getMock('\Phinx\Migration\Manager', array(), array($this->config, $this->input, $this->output));
        $managerStub->expects($this->once())
                    ->method('rollback')
                    ->with(self::DEFAULT_TEST_ENVIRONMENT, '20150101000000', false, false);

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), '-d' => $targetDate), array('decorated' => false));
        $this->assertRegExp('/ordering by start-time/', $commandTester->getDisplay());
    }
}
