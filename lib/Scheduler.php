<?php

//namespace \Blondie101010\Scheduler;

class Scheduler
{
    protected $schedule         = [];

    protected $key; 
    protected $pipe;
    protected $scheduleFile;                                    // File which includes the schedule initially and gets updated when we die.

    protected $allowFatal;


    /**
     * Scheduler::__construct()
     * 
     * @param mixed $pipe:                                      // Named pipe filename used to receive job requests.
     * @param mixed $key:                                       // Key or array of keys used to identify if a request is authorized or not.
     *                                                          // If the $key is an array, authentication will simply check if the key exists in
     *                                                          // the array.
     *                                                          // Note that this is most useful on multi-developer systems.
     * @param mixed $scheduleFile:                              // Optional filename where to load the initial schedule and save it in __destruct().
     *                                                          // Use of a streamWrapper can make it flexible enough to read and write the schedule
     *                                                          // on different systems, including a DB. 
     * @param bool  $allowFatal:                                // Allow called jobs to cause the scheduler to abort.  This is not usually needed.
     * @return void
     */
    public function __construct($pipe = '', $key = '', $scheduleFile = null, $allowFatal = FALSE)
    {
        $this->pipe = $pipe;
        $this->key = $key;
        $this->scheduleFile = $scheduleFile;
        $this->allowFatal = $allowFatal;
    } // __construct()


    /**
     * Scheduler::authenticate()
     * 
     * Validate that the key provided is allowed.
     * 
     * @param mixed $key:   Refer to __construct() for details.
     * @return True on success or false on failure.
     */
    protected function authenticate($key)
    {
        if ($key === $this->key || is_array($this->key) && in_array($key, $this->key, true)) {
            return true;
        } // if
        else {
            return false;
        }
    } // authenticate()


    /**
     * Scheduler::scheduleJob()
     * 
     * Add the specified job to the schedule.  
     * 
     * This is an internal factory.
     * 
     * @param Job       $job:       A class that implements \Blondie101010\Scheduler\Job.
     * @param char      $mode:      (*) 't' for time-based, or 'c' for counter (tick) based.  Note that 'c' mode is most useful to set task 
     *                              priority, 0 being the highest.
     * @param array     $options:   Array of the following settings:
     *                                  Name        Default Description
     *                                  ----------- ------- ---------------------------------------------------------------------------------
     *                                  key:        ''      Refer to __construct() for details.
     *                                  interval:   null    Number of seconds (for 't') or ticks (for 'c').
     *                                  cursor:     null    Offset before the job is run.  The cursor is 0 by default which means that the 
     *                                                      job will run on the first check.  Setting a $cursor for a time based job will 
     *                                                      cause a delay of $cursor * $interval before it gets run the first time.  A 'c' 
     *                                                      based job will not get run immediately unless $this->cursor >= $this->interval.   
     *                                  limit:      null    Number of times the job should be run.  Default is unlimited.
     *                                  startTime:  null    Timestamp of when the job should start being run.
     *                                  detach:     false   Should the job be detached (forked) from the current process? 
     *                                  secret:     null    Secret that is only required to validate security on update requests.  Since 
     *                                                      update requests can only be done with an id, both always go together.
     *                                  id:         null    Id of the request.  This is only used when a change request may be needed.  If 
     *                                                      the id is already defined, the job is not scheduled and false is returned.
     *                                  fatal:      true    Should an exception be considered fatal or ignored?
     * @return true on job creation or false on error.
     */
    public function scheduleJob(Job $job, $mode, $options = array())
    {
        // Set defaults.
        $defaults = ['key' => '', 'interval' => null, 'limit' => null, 'startTime' => null, 'secret' => null, 'id' => null, 'detach' => false, 'fatal' => true];
        $options = array_merge($defaults, $options);
        
        if (!$this->authenticate($options['key'])) {
            return false;
        }
        try {
            $scheduledJob = new ScheduledJob($job, $mode, $options['interval'], $options['cursor'], $options['limit'], $options['startTime'], 
                                             $options['detach'], $options['secret']);
        } catch (Exception $e) {
            if ($fatal && $this->allowFatal) {
                throw $e;                                       // Just rethrow the authorized exception.
            } // if
        } // catch

        $scheduleEntry = ['sJob' => $scheduledJob];

        if ($options['fatal'] && $this->allowFatal) {           // Kept here as it must not be in the job's power to decide this.
            $scheduleEntry['fatal'] = true;
        } // if

        if ($options['id']) {
            if (array_key_exists($options['id'], $this->schedule)) {
                return false;
            } // if
            
            $this->schedule[$options['id']] = $scheduleEntry;
        } // if
        else {
            $this->schedule[] = $scheduleEntry;
        } // else

        return true;
    } // scheduleJob()


    /**
     * Scheduler::run()
     * 
     * @return bool true if all jobs were run successfully or false if any error happens
     */
    public function run()
    {
        $res = true;

        foreach ($this->schedule as $schedule) {
            $res |= $schedule['sJob']->run();
        } // foreach

        return $res;
    } // run()
} // Scheduler






