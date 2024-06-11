<?php

namespace Laravel\Prompts;

use Laravel\Prompts\Support\Result;
use Override;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;

abstract class AsyncPrompt extends Prompt
{
    protected static ReadableResourceStream $stdin;
    
    #[Override]
    public static function fakeKeyPresses(array $keys, callable $closure): void
    {
        static::$stdin ??= new ReadableResourceStream(STDIN);
        foreach ($keys as $key) {
            Loop::get()->futureTick(function () use ($key) {
                static::$stdin->emit('data', [$key]);
            });
        }
    }

    #[Override]
    public function runLoop(callable $callable): mixed
    {
        /**
         * @var  Result|null  $result
         */
        $result = null;

        static::$stdin ??= new ReadableResourceStream(STDIN);
        static::$stdin->on('data', function (string $key) use ($callable, &$result) {
            $result = $callable($key);

            if ($result instanceof Result) {
                Loop::stop();
            }
        });

        Loop::run();
        static::$stdin->removeAllListeners();

        if ($result === null) {
            throw new \RuntimeException('Prompt did not return a result.');
        }

        return $result->value;
    }
}
