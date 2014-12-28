<?php

//namespace \Blondie101010\Scheduler;

/**
 * ScheduledJob
 * 
 * Encapsulate a job in a frequency controller that can be either time or count (tick) based. 
 * 
 * Instead of having to manage grids of tasks that require processing based on different criteria, each task has its own check to determine if it
 * needs to be run.
 * 
 * Note that no parameters are passed dynamically to the job and if it requires to do so, it must handle it internally.  That is usually achieved
 * with a call back in its owner's object to a method like jobGetNextArg() for example.
 * 
 * @package drivers
 * @author Julie Pelletier
 * @copyright 2014
 * @version $Id$
 * @access public
 */
class ScheduledJob
{
    private $job;                       // Job implementation.
    private $mode;                      // Count/tick ('c') or time ('t') based.
    private $interval;                  // Number of seconds (for 't') or ticks (for 'c').
    private $count      = 0;            // Number of times the job has been run.
    private $limit;                     // Maximum number of times this job should be run.  $this->count is disabled when !$limit.
    private $detach;                    // Should this job be forked to a new process?
    private $secret;
    private $startTime  = null;         // Optional start time to begin running this job.

    /**
     * ScheduledJob::__construct()
     * 
     * @param string    $mode:          't' for time-based, or 'c' for counter (tick) based.  Note that 'c' mode is most useful to set task
     *                                  priority, 0 being the highest.
     * @param int       $interval:      Number of seconds (for 't') or ticks (for 'c')
     * @param int       $cursor:        Offset before the job is run.  The cursor is 0 by default which means that the job will run on the first
     *                                  check.  Setting a $cursor for a time based job will cause a delay of $cursor * $interval before it gets 
     *                                  run the first time.  A 'c' based job will not get run immediately unless $this->cursor >= $this->interval.   
     * @param int       $limit:         Number of times the job should be run.  Default is unlimited.
     * @param int       $startTime:     Timestamp of when the job should start being run.  When provided in 't' mode, the $cursor is ignored.
     * @param bool      $detach:        Should the job be detached (forked) from the current process?  Note that a forked process needs to be
     *                                  totally independant from the existing process in terms of file handles and that any output to stdout or
     *                                  known memory locations will either be ignored or cause unexpected results.  This means that output should
     *                                  normally go to a file or a database (with a new connection).  
     * @param bool      $secret:        Security measure only used for update requests. 
     * @return void
     */
    public function __construct(Job $job, $mode, $interval = 0, $cursor = null, $limit = 0, $startTime = null, $detach = false, $secret = null)
    {
        $this->job = $job;
        $this->mode = strtolower($mode);                        // Forcing lower case to prevent common unimportant mistakes.
        $this->interval = $interval;                            // A 0 interval will cause the job to be run each time.
		$this->limit = $limit;
        $this->detach = $detach;
        $this->secret = $secret;

        switch ($mode) {
            case 't':   // Time interval.
                // Initial delay before it gets run.  Defaults to now.
                $this->next = ((int) $cursor) * $this->interval + ($startTime ? time() : $startTime);
                break;

            case 'c':   // Count (tick) interval.
                $this->startTime = $startTime;

                // Position in the imaginary occurance grid, defaults to $interval so that it gets run the first time. 
                $this->cursor = ($cursor === null) ? $interval : $cursor;
                break;

            default:
                throw new Exception("Unknown mode $mode in {__FILE__}:{__LINE__}.");
        } // switch
    } // __construct()


    /**
     * Scheduler::authenticate()
     * 
     * Validate the secret provided.
     * 
     * @param mixed $secret:    Refer to __construct() for details.
     * @return True on success or false on failure.
     */
    public function authenticate($secret)
    {
        if ($secret === $this->secret) {
            return true;
        } // if
        else {
            return false;
        }
    } // authenticate()


    /**
     * ScheduledJob::update()
     * 
     * @param array     $options:    Array of options (properties) to set.
     * @return True on success or false on failure.
     */
    public function update($options, $secret = null)
    {
        if (!$this->authenticate($secret)) {
            return false;
        } // if

        $properties = ['job', 'mode', 'interval', 'limit', 'detach', 'secret'];

        foreach ($options as $option => $value)
        {
            if (in_array($option, $properties)) {
                $this->$option = $value;
            } // if
        } // foreach

        return true;
    } // update()


    /**
     * ScheduledJob::run()
     * 
     * @return True if it was run or false otherwise.  (not very important to check)
     */
    public function run()
    {
        if ($this->limit && $this->count >= $this->limit) {
            return false;
        } // if

        switch ($this->mode) {
            case 't':   // Time interval.
                if (time() >= $this->next) {
                    $this->next = time() + $this->interval;
                } // if
                else {
                    return false;
                } // else
                break;

            case 'c':   // Count (tick) interval.
                if (time() >= $this->startTime && ++ $this->cursor >= $this->interval) {
                    $this->cursor = 0;
                } // if
                else {
                    return false;
                } // else
                break;

            // Default is already covered in __construct().
        } // check()

        if ($this->detach) {
            if (($pid = pcntl_fork()) == -1) {
                trigger_error('Could not fork in ScheduledJob.');
                return false;
            } // if
            elseif (!$pid) {
                $this->job->run();
                exit();                                         // End child.
            } // elseif
        } // if
        else {
            $this->job->run();
        } // else

        if ($this->limit) {
            $this->count ++;                                
        } // if

        return true;                                            // Job was run.  Note that this in no way indicates what happened in the job itself.
    } // run()
} // ScheduledJob







