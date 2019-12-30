# Pixelflutclient for [Chaostreff-Flensburgs](https://github.com/chaostreff-flensburg) Pixelflutproject

written in PHP. 

## pflutserver

- generates pixelmap array of RGB per Pixel of given Image keyed by the x/y position
```
['100,200' => ['FF','FF','FF']]
```
- creates a socket server 

## pflutclient

- connects to pflutserver and polls for pixelmap array
- spawns 10 Threads: each thread connects to the Chaos-Pixelflut-Server and submit the Pixel (RGB,X,Y)

pflutserver and pflutclient need root-privileges because both write there pids to /var/run
