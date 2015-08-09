Scheduler
=========

PHP job / task scheduler that allows tick (count) or time based scheduling, initial delay, background processing, and the maximum number of executions.


Here is a simple working example to run a job every 2 seconds and another every four ticks with a tick of 0.25s:

<pre>
use Blondie101010\Scheduler;

require "vendor/autoload.php";

class MyJob implements Blondie101010\Scheduler\Job {
        private $count = 0;

        public function run() {
                $this->count ++;
                echo "MyJob run {$this->count} at " . (time()) . PHP_EOL;
        } // run()
} // MyJob

class MyJob2 implements Blondie101010\Scheduler\Job {
        private $count = 0;

        public function run() {
                $this->count ++;
                echo "MyJob2 run {$this->count} at " . (time()) . PHP_EOL;
        } // run()
} // MyJob

$scheduler = new Blondie101010\Scheduler\Scheduler;

$scheduler->scheduleJob(new MyJob, 't', ['interval' => 2, 'limit' => 8]);
$scheduler->scheduleJob(new MyJob2, 'c', ['interval' => 4, 'limit' => 5]);

for ($i = 0; $i < 60; $i ++) {  // note that in real life, this would be a high level processing loop
        $scheduler->run();
        usleep(250000);         // tick of about 0.25s (not counting processing)
} // for
</pre>
