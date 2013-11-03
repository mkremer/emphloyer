<?php

namespace Emphloyer;

/**
 * The Workshop class runs the show.
 */
class Workshop {
  protected $boss;
  protected $run = false;
  protected $forkHooks;

  /**
   * @param \Emphloyer\Boss $boss
   * @param \Emphloyer\Job\ForkHookChain $forkHooks
   * @param int $numberOfEmployees
   * @return \Emphloyer\Workshop
   */
  public function __construct(Boss $boss, Job\ForkHookChain $forkHooks, $numberOfEmployees = 1) {
    $this->boss = $boss;
    $this->forkHooks = $forkHooks;

    for ($i = 0; $i < $numberOfEmployees; $i++) {
      $this->boss->allocateEmployee(new Employee($this->forkHooks));
    }
  }

  /**
   * Run the process.
   * @param bool $keepGoing Keep running or stop after one cycle.
   */
  public function run($keepGoing = true) {
    $this->run = $keepGoing;
    do {
      $this->boss->delegateWork();
      $this->boss->updateProgress();
    } while($this->run);
  }

  /**
   * Stop the process, this waits for all running jobs to end.
   */
  public function stop() {
    $this->run = false;
    $this->boss->waitOnEmployees();
    $this->boss->updateProgress();
  }

  /**
   * Stop the process immediately, this kills jobs mid-process.
   */
  public function stopNow() {
    $this->run = false;
    $this->boss->stopEmployees();
    $this->boss->updateProgress();
  }
}