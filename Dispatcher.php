<?php namespace NetForce\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Dispatcher
{
    /**
     * The registered event listeners.
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string|array  $events
     * @param  mixed  $listener
     * @param  int  $priority
     * @return void
     */
    public function listen($events, $listener, $priority = 0)
    {
        //$priority = sprintf('p:%s', str_pad($priority, 6, '0', STR_PAD_LEFT));

        foreach ((array) $events as $event) {
            $this->listeners[$event][$priority][] = $this->makeListener($listener);
        }
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function fire($event, $payload = [], $halt = false)
    {
        // When the given "event" is actually an object we will assume it is an event
        // object and use the class as the event name and this event itself as the
        // payload to the handler, which makes object based events quite simple.
        list($event, $payload) = $this->parseEventAndPayload($event, $payload);

        $responses = [];

        foreach ($this->getListeners($event) as $listener) {
            $response = $listener($event, $payload);

            // If a response is returned from the listener and event halting is enabled
            // we will just return this response, and not call the rest of the event
            // listeners. Otherwise we will add the response on the response list.
            if ($halt && ! is_null($response)) {
                return $response;
            }

            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and firing every one in our sequence.
            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Closure|string  $listener
     * @return \Closure
     */
    public function makeListener($listener)
    {
        if (is_string($listener)) {
            return $this->createClassListener($listener);
        }

        return function ($event, $payload) use ($listener) {
            return $listener(...array_values($payload));
        };
    }

    /**
     * Create a class based listener using the IoC container.
     *
     * @param  string  $listener
     * @return \Closure
     */
    public function createClassListener($listener)
    {
        return function ($event, $payload) use ($listener) {
            return call_user_func_array($this->createClassCallable($listener), $payload);
        };
    }

    /**
     * Create the class based event callable.
     *
     * @param  string  $listener
     * @return callable
     */
    protected function createClassCallable($listener)
    {
        list($class, $method) = $this->parseClassCallable($listener);

        return [$this->makeClass($class), $method];
    }

    /**
     * Create objeto by class.
     * 
     * @param string $class
     * @return mixed
     */
    protected function makeClass($class)
    {
        return new $class();
    }

    /**
     * Parse the class listener into class and method.
     *
     * @param  string  $listener
     * @return array
     */
    protected function parseClassCallable($listener)
    {
        return Str::parseCallback($listener, 'handle');
    }

    /**
     * Get all of the listeners for a given event name.
     *
     * @param  string  $eventName
     * @return array
     */
    public function getListeners($eventName)
    {
        $listeners = $this->listeners[$eventName] ? $this->listeners[$eventName] : [];

        // Ordenar por prioridade
        krsort($listeners);
        $result = [];
        foreach ($listeners as $list) {
            foreach ($list as $listener) {
                $result[] = $listener;
            }
        }
        //$listeners = call_user_func_array('array_merge', $listeners);

        return $result;
    }

    /**
     * Parse the given event and payload and prepare them for dispatching.
     *
     * @param  mixed  $event
     * @param  mixed  $payload
     * @return array
     */
    protected function parseEventAndPayload($event, $payload)
    {
        if (is_object($event)) {
            list($payload, $event) = [[$event], get_class($event)];
        }

        return [$event, Arr::wrap($payload)];
    }

    /**
     * Check if event has listeners.
     *
     * @param  string|object  $event
     * @return bool
     */
    public function has($event)
    {
        list($event, $payload) = $this->parseEventAndPayload($event, []);

        return array_key_exists($event, $this->listeners);
    }
}