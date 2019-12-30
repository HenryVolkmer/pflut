<?php

declare(ticks=1);

class PflutServer
{
	private $_map = [];
	private $_socket;

	public $img;
	public $x = 0;
	public $y = 0;
	public $w = 200;
	public $h = 200;
	public $bind = '0.0.0.0';
	public $port = 8000;

	public function __construct()
	{
		if (!file_exists('config.json')) {
			exit ("config.json missing!");
		}

		$config = json_decode(file_get_contents('config.json'));
		foreach ($config as $prop => $setting) {
            $this->{$prop} = $setting;
        }

        if (!file_exists($this->img)) {
        	exit("File " . $this->img . " not statable!");
        }

		$img = new \Imagick($this->img);

		if (!$img) {
			exit("File " . $this->img . " is no image!");
		}

		$sighandler = function (int $signo,$siginfo) {
			pcntl_signal_dispatch();
			echo "close socket - release binding\n";
			socket_close($this->_socket);
			exit();
		};

		
		pcntl_signal(SIGTERM, $sighandler);
		pcntl_signal(SIGINT, $sighandler);

		/**
		 * from left to right, then new line
		 */
		$map = $img->exportImagePixels(
			0,
			0,
			$this->w,
			$this->h,
			'RGB',
			\Imagick::PIXEL_CHAR
		);

		$cnt = 0;
		$y = 0;
		$x = 0;

		foreach ($map as $color) {

			$cnt++;
			
			if ($cnt === 4) {
				$cnt = 0;

				$x++;

				if ($x > $this->w) {
					$y++;
					$x = 0;
				}

				continue;
			}

			$this->addToMap($x,$y,$color);

		}

		$this->handle();
	}

	public function addToMap($x,$y,$color)
	{
		$x += $this->x;
		$y += $this->y;
		$this->_map[$x.','.$y][] = $color;
	}

	public function handle()
	{
		
		$this->_socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
		socket_bind($this->_socket, $this->bind,$this->port);
		socket_listen($this->_socket);
		socket_set_nonblock($this->_socket);

		$map = json_encode($this->_map) . "\n";

		echo "Waiting for connections on {$this->bind}:{$this->port}\n";
		while (true) {

			usleep(200);

			$client_socket = socket_accept($this->_socket);

			if (!$client_socket) {
				continue;
			}

			socket_write($client_socket,$map,strlen($map));
			socket_close($client_socket);
		}
	}	

}

new PflutServer();
