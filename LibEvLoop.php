<?php

namespace React\EventLoop;

/**
 * @see https://github.com/m4rw3r/php-libev
 * @see https://gist.github.com/1688204
 */
class LibEvLoop implements LoopInterface
{
    private $loop;
    private $readEvents = array();
    private $writeEvents = array();
    private $timers = array();
    private $suspended = false;

    public function __construct()
    {
        $this->loop = new \libev\EventLoop();
    }

    public function addReadStream($stream, $listener)
    {
        $this->readEvents[(int)$stream] = $this->addStream($stream, $listener, \libev\IOEvent::READ);
    }

    public function addWriteStream($stream, $listener)
    {
        $this->writeEvents[(int)$stream] = $this->addStream($stream, $listener, \libev\IOEvent::WRITE);
    }

    public function removeReadStream($stream)
    {
        $this->readEvents[(int)$stream]->stop();
        unset($this->readEvents[(int)$stream]);
    }

    public function removeWriteStream($stream)
    {
        $this->writeEvents[(int)$stream]->stop();
        unset($this->writeEvents[(int)$stream]);
    }

    public function removeStream($stream)
    {
        if (isset($this->readEvents[(int)$stream])) {
            $this->removeReadStream($stream);
        }

        if (isset($this->writeEvents[(int)$stream])) {
            $this->removeWriteStream($stream);
        }
    }

    private function addStream($stream, $listener, $flags)
    {
        $listener = $this->wrapStreamListener($stream, $listener);
        $event = new \libev\IOEvent($listener, $stream, $flags);
        $this->loop->add($event);

        return $event;
    }

    private function wrapStreamListener($stream, $listener)
    {
        return function ($event) use ($stream, $listener) {
            if (feof($stream)) {
                $event->stop();
                return;
            }

            call_user_func($listener, $stream);
        };
    }

    public function addTimer($interval, $callback)
    {
        return $this->createTimer($interval, $callback, 0);
    }

    public function addPeriodicTimer($interval, $callback)
    {
        return $this->createTimer($interval, $callback, 1);
    }

    public function cancelTimer($signature)
    {
        $this->loop->remove($this->timers[$signature]);
    }

    private function createTimer($interval, $callback, $periodic)
    {
        $obj = (object) array();
        $signature = spl_object_hash($obj);
        $callback = $this->wrapTimerCallback($signature, $callback);

        if ($periodic) {
            $timer = new \libev\PeriodicEvent($callback, 1, $interval);
        } else {
            $timer = new \libev\TimerEvent($callback, $interval);
        }
        $this->timers[$signature] = $timer;
        $this->loop->add($timer);

        return $signature;
    }

    private function wrapTimerCallback($signature, $callback)
    {
        $loop = $this;

        return function ($event) use ($signature, $callback, $loop) {
            call_user_func($callback, $signature, $loop);
        };
    }

    public function tick()
    {
        $this->loop->run(\libev\EventLoop::RUN_ONCE);
    }

    public function run()
    {
        // @codeCoverageIgnoreStart
        if ($this->suspended) {
            $this->suspended = false;
            $this->loop->resume();
        } else {
            $this->loop->run();
        }
        // @codeCoverageIgnoreEnd
    }

    public function stop()
    {
        // @codeCoverageIgnoreStart
        $this->loop->suspend();
        $this->suspended = true;
        // @codeCoverageIgnoreEnd
    }
}
