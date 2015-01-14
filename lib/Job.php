<?php

namespace Blondie101010\Scheduler;

/**
 * Job
 * 
 * Job interface definition with a single requirement:  a run() method.
 * 
 * @package scheduler
 * @author Julie Pelletier
 * @copyright 2014
 * @version $Id$
 * @access public
 */
interface Job
{
    /**
     * run()
     * 
     * Run the job.
     * 
     * @return void Use exceptions or trigger errors for error handling.
     */
    public function run();
} // Job


