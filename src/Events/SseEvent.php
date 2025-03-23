<?php

namespace ElliottLawson\LaravelMcp\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event for Server-Sent Events.
 */
class SseEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The event data.
     */
    public $data;

    /**
     * The event name.
     */
    public ?string $event;

    /**
     * The event ID.
     */
    public ?string $id;

    /**
     * The connection ID.
     */
    public string $connectionId;

    /**
     * Create a new event instance.
     *
     * @param mixed $data The event data
     * @param string|null $event The event name
     * @param string|null $id The event ID
     * @param string $connectionId The connection ID
     */
    public function __construct($data, ?string $event = null, ?string $id = null, string $connectionId = '')
    {
        $this->data = $data;
        $this->event = $event;
        $this->id = $id;
        $this->connectionId = $connectionId;
    }
}
