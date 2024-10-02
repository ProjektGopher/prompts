<?php

namespace Laravel\Prompts;

use Closure;
use React\EventLoop\Loop;
use React\Promise\Promise;
use RuntimeException;

class Spinner extends AsyncPrompt
{
    /**
     * How long to wait between rendering each frame.
     */
    public int $interval = 100;

    /**
     * The number of times the spinner has been rendered.
     */
    public int $count = 0;

    /**
     * Create a new Spinner instance.
     */
    public function __construct(public string $message = '')
    {
        //
    }

    /**
     * Render the spinner and execute the callback.
     *
     * @template TReturn of mixed
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function spin(Closure $callback): mixed
    {
        $this->capturePreviousNewLines();

        try {
            $this->hideCursor();
            $this->render();

            $loop = Loop::get();

            $timer = $loop->addPeriodicTimer($this->interval, function () {
                $this->render();
                $this->count++;
            });
            
            $resolver = function (callable $resolve, callable $reject) use ($callback, $loop, $timer) {
                $result = $callback();
                $loop->cancelTimer($timer);
                $this->eraseRenderedLines();
            
                $resolve($result);
            };
            
            $promise = new Promise($resolver);

            Loop::run();
            
            return $promise;
        } catch (\Throwable $e) {
            $this->eraseRenderedLines();

            throw $e;
        }
    }

    /**
     * Disable prompting for input.
     *
     * @throws \RuntimeException
     */
    public function prompt(): never
    {
        throw new RuntimeException('Spinner cannot be prompted.');
    }

    /**
     * Get the current value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * Clear the lines rendered by the spinner.
     */
    protected function eraseRenderedLines(): void
    {
        $lines = explode(PHP_EOL, $this->prevFrame);
        $this->moveCursor(-999, -count($lines) + 1);
        $this->eraseDown();
    }
}
