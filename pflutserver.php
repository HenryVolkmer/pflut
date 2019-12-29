<?php

class PflutServer
{
	private $_map = [];
	private $_socket;

	public $img;
	public $x = 0;
	public $y = 0;
	public $w = 200;
	public $h = 200;

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
		$y = $this->y;
		$x = $this->x;

		foreach ($map as $color) {

			$cnt++;
			
			if ($cnt === 4) {
				$cnt = 0;

				$x++;

				if ($x > $this->w) {
					$y++;
					$x = $this->x;
				}

				continue;
			}

			$this->addToMap($x,$y,$color);

		}

		$this->handle();
	}

	public function addToMap($x,$y,$color)
	{
		$this->_map[$x.','.$y][] = $color;
	}

	public function handle()
	{
		$this->_socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
		socket_bind($this->_socket, '0.0.0.0',8000);
		socket_listen($this->_socket);
		socket_set_nonblock($this->_socket);

		$map = json_encode($this->_map) . "\n";

		echo "Waiting connections\n";
		while (true) {

			usleep(500);

			$client_socket = socket_accept($this->_socket);

			if (!$client_socket) {
				continue;
			}

			socket_write($client_socket,$map,strlen($map));
			socket_close($client_socket);
		}
	}	

}

(new PflutServer());
