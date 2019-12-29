<?php

declare (ticks=1);

class PflutClient
{
	private $_map;
	private $_pidfile;

	public $target = '151.217.111.34';
	public $port = 1234;
	public $maxthreads = 1;

	public $isThread = false;

	/**
	 * @var array pids of running threads
	 */ 
	private $_startedThreadsPids = [];

	public function __construct()
	{
		$sighandler = function (int $signo,$siginfo) {

			echo $signo;

			if ($this->isThread) {
				exit();
			}


			foreach ($this->getThreads() as $pid) {
				posix_kill((int) $pid,SIGTERM);
				$this->log("kill " . $pid);
			}

			pcntl_signal_dispatch();

			unlink($this->_pidfile);

			exit();
		};

		pcntl_signal(SIGTERM, $sighandler);
		pcntl_signal(SIGINT, $sighandler);

		$args = $_SERVER['argv'];
        unset ($args[0]);

        if (!isset ($args[1])) {
        	exit("no host defined!\n");
        }

        /**
         * pid? Then this is a thread and the given pid
         * is the pid of the parent.
         */
        if (isset($args[2])) {
        	$this->_pidfile = "/var/run/" . basename(__CLASS__) . '_' . $args[2] . '.pid';

        	if (!file_exists($this->_pidfile)) {
        		$this->log("pid file " . $this->_pidfile . " not found\n");
        		exit("No Parentprocess running!\n");
        	}
			$this->isThread = true;

			file_put_contents(
				$this->_pidfile, 
				getmypid() . "\n",
				FILE_APPEND
			);
		} else {
			/**
			 * this is a parent Process
			 */
			$this->_pidfile = "/var/run/" . basename(__CLASS__) . '_' . getmypid() . '.pid';
			touch($this->_pidfile);

			$this->isThread = false;


			/**
			 * spawn threads
			 */
			for ($i=0;$i<$this->maxthreads;$i++) {
				exec ('php ' . __FILE__ . ' ' . $args[1] . ' ' . getmypid() . ' > /dev/null 2>&1 &');
			}
		}
        
        while (true) {

        	usleep(20);

        	if (!$this->isThread) {

	        	$map = $this->getMap($args[1]);

		        if (!$map) {
		        	$this->log("Servers response is fishy!");
		        	continue;
			    }
		        
		    	$this->log("map ok");
		    	file_put_contents('map.json', $map);
        		continue;
        	}

		    $this->handle();
	    }
	}

	public function getMap($host)
	{
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$host = explode(":", $host);
		$port = (isset($host[1]) ? $host[1] : 8000);

		$this->log("connect socket\n");

		socket_connect($socket, $host[0],$port);

		$map = '';
		while ($response = socket_read($socket,1000,PHP_NORMAL_READ)) {
			$map .= $response;
		}

		socket_close($socket);

		$this->log("ready read socket\n");

		return $map;
	}

	public function log($msg)
	{
		if ($this->isThread) {
			$msg = 'THREAD | ' . $msg;
		}
		file_put_contents("log", print_r($msg,true) . "\n",FILE_APPEND);
	}

	public function handle()
	{
		if (!file_exists('map.json')) {
			return;
		}

		$map = file_get_contents('map.json');

	    try {
        	$map = json_decode($map,true);
        } catch (Catchable $e) {
        	$this->log("Server sent invalid json!");
        	return;
        }

		$this->processMap($map);
	}

	public function processMap(array $map)
	{
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		$this->log("connecting to " . $this->target . ":" . $this->port);

		socket_connect($socket, $this->target,$this->port);

		$this->log("connected");

		foreach ($map as $cords => $color) {

			$cords = explode(",",$cords);

			$payload = sprintf(
				"PX %d %d %02x%02x%02x\n",
				$cords[0],
				$cords[1],
				$color[0], 
				$color[1], 
				$color[2], 
			);

			$this->log($payload);

			socket_send($socket,$payload,strlen($payload),MSG_DONTROUTE);
		}

		socket_close($socket);
	}

	protected function getThreads(): array
	{
		return explode("\\n",file_get_contents($this->_pidfile));
	}
}

new PflutClient();