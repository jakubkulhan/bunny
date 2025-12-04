<?php

declare(strict_types=1);

namespace Bunny;

use Bunny\Protocol\ContentHeaderFrame;
use InvalidArgumentException;
use stdClass;
use function array_filter;
use function array_map;
use function array_unshift;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_decode;
use function lcfirst;
use function sprintf;
use function str_replace;
use function strpos;
use function strtoupper;
use function ucfirst;
use function var_export;

require_once __DIR__ . '/../vendor/autoload.php';

$specFileName = 'amqp-rabbitmq-0.9.1.json';
$spec = json_decode(file_get_contents(__DIR__ . '/' . $specFileName));

function dashedToCamel(string $dashed): string
{
    return implode("", array_map(static fn ($s): string  => ucfirst($s), explode('-', $dashed)));
}

function dashedToUnderscores($dashed): string
{
    return strtoupper(str_replace('-', '_', $dashed));
}

function domainToType(string $domain): string
{
    global $spec;

    foreach ($spec->domains as $d) {
        if ($d[0] === $domain) {
            return $d[1];
        }
    }

    throw new InvalidArgumentException(sprintf('Unhandled domain \'%s\'.', $domain));
}

function indent(string $value, string $prefix): string
{
    return implode("\n", array_map(static fn (string $s): string => sprintf('%s%s', $prefix, $s), explode("\n", $value)));
}

function amqpTypeToPhpType(string $type): string
{
    return match ($type) {
        'octet', 'short', 'long', 'longlong' => 'int',
        'shortstr', 'longstr' => 'string',
        'bit' => 'bool',
        'table' => 'array',
        'timestamp' => '\\DateTime',
    };
}


function amqpTypeToConsume($type): string
{
    return match ($type) {
        'octet' => "\$buffer->consumeUint8()",
        'table' => "\$this->consumeTable(\$buffer)",
        'longstr' => "\$buffer->consume(\$buffer->consumeUint32())",
        'shortstr' => "\$buffer->consume(\$buffer->consumeUint8())",
        'short' => "\$buffer->consumeInt16()",
        'long' => "\$buffer->consumeInt32()",
        'longlong' => "\$buffer->consumeInt64()",
        'timestamp' => "\$this->consumeTimestamp(\$buffer)",
    };
}

function amqpTypeToAppend(string $type, string $e): string
{
    return match ($type) {
        'octet' => "\$buffer->appendUint8($e)",
        'table' => "\$this->appendTable($e, \$buffer)",
        'longstr' => "\$buffer->appendUint32(strlen($e));\n\$buffer->append($e)",
        'shortstr' => "\$buffer->appendUint8(strlen($e));\n\$buffer->append($e)",
        'short' => "\$buffer->appendInt16($e)",
        'long' => "\$buffer->appendInt32($e)",
        'longlong' => "\$buffer->appendInt64($e)",
        'timestamp' => "\$this->appendTimestamp($e, \$buffer)",
    };
}

/**
 * @return array{0: int, 1: string}
 */
function amqpTypeToLength(string $type, string $e): array
{
    return match ($type) {
        'octet' => [1, null],
        'table' => [null, null],
        'longstr' => [4, "strlen($e)"],
        'shortstr' => [1, "strlen($e)"],
        'short' => [2, null],
        'long' => [4, null],
        'longlong' => [8, null],
        'timestamp' => [8, null],
    };
}

$protocolReaderContent = "<?php\n";
$protocolReaderContent .= "\n";
$protocolReaderContent .= "declare(strict_types=1);\n";
$protocolReaderContent .= "\n";
$protocolReaderContent .= "namespace Bunny\\Protocol;\n";
$protocolReaderContent .= "\n";
$protocolReaderContent .= "use Bunny\\Constants;\n";
$protocolReaderContent .= "use Bunny\\Exception\\InvalidClassException;\n";
$protocolReaderContent .= "use Bunny\\Exception\\InvalidMethodException;\n";
$protocolReaderContent .= "\n";
$protocolReaderContent .= "/**\n";
$protocolReaderContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} protocol reader\n";
$protocolReaderContent .= " *\n";
$protocolReaderContent .= " * THIS CLASS IS GENERATED FROM $specFileName. **DO NOT EDIT!**\n";
$protocolReaderContent .= " *\n";
$protocolReaderContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$protocolReaderContent .= " */\n";
$protocolReaderContent .= "trait ProtocolReaderGenerated\n";
$protocolReaderContent .= "{\n";
$protocolReaderContent .= "    /**\n";
$protocolReaderContent .= "     * Consumes AMQP table from buffer.\n";
$protocolReaderContent .= "     *\n";
$protocolReaderContent .= "     * @return array<string,mixed>\n";
$protocolReaderContent .= "     */\n";
$protocolReaderContent .= "    abstract public function consumeTable(Buffer \$originalBuffer): array;\n\n";
$protocolReaderContent .= "    /**\n";
$protocolReaderContent .= "     * Consumes packed bits from buffer.\n";
$protocolReaderContent .= "     *\n";
$protocolReaderContent .= "     * @return list<mixed>\n";
$protocolReaderContent .= "     */\n";
$protocolReaderContent .= "    abstract public function consumeBits(Buffer \$buffer, int \$n): array;\n\n";

$consumeMethodFrameContent = "";
$consumeMethodFrameContent .= "    /**\n";
$consumeMethodFrameContent .= "     * Consumes AMQP method frame.\n";
$consumeMethodFrameContent .= "     */\n";
$consumeMethodFrameContent .= "    public function consumeMethodFrame(Buffer \$buffer): MethodFrame\n";
$consumeMethodFrameContent .= "    {\n";
$consumeMethodFrameContent .= "        \$classId = \$buffer->consumeUint16();\n";
$consumeMethodFrameContent .= "        \$methodId = \$buffer->consumeUint16();\n";
$consumeMethodFrameContent .= "\n";
$consumeMethodFrameContent .= "        ";

$protocolWriterContent = "<?php\n";
$protocolWriterContent .= "\n";
$protocolWriterContent .= "declare(strict_types=1);\n";
$protocolWriterContent .= "\n";
$protocolWriterContent .= "namespace Bunny\\Protocol;\n";
$protocolWriterContent .= "\n";
$protocolWriterContent .= "use Bunny\\Exception\\ProtocolException;\n";
$protocolWriterContent .= "use function sprintf;\n";
$protocolWriterContent .= "use function strlen;\n";
$protocolWriterContent .= "\n";
$protocolWriterContent .= "/**\n";
$protocolWriterContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} protocol writer\n";
$protocolWriterContent .= " *\n";
$protocolWriterContent .= " * THIS CLASS IS GENERATED FROM $specFileName. **DO NOT EDIT!**\n";
$protocolWriterContent .= " *\n";
$protocolWriterContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$protocolWriterContent .= " */\n";
$protocolWriterContent .= "trait ProtocolWriterGenerated\n";
$protocolWriterContent .= "{\n";

$protocolWriterContent .= "    /**\n";
$protocolWriterContent .= "     * Appends AMQP table to buffer.\n";
$protocolWriterContent .= "     *\n";
$protocolWriterContent .= "     * @param array<string,mixed> \$table\n";
$protocolWriterContent .= "     */\n";
$protocolWriterContent .= "    abstract public function appendTable(array \$table, Buffer \$originalBuffer): void;\n\n";

$protocolWriterContent .= "    /**\n";
$protocolWriterContent .= "     * Appends packed bits to buffer.\n";
$protocolWriterContent .= "     *\n";
$protocolWriterContent .= "     * @param list<bool> \$bits\n";
$protocolWriterContent .= "     */\n";
$protocolWriterContent .= "    abstract public function appendBits(array \$bits, Buffer \$buffer): void;\n\n";

$protocolWriterContent .= "    /**\n";
$protocolWriterContent .= "     * Appends AMQP protocol header to buffer.\n";
$protocolWriterContent .= "     */\n";
$protocolWriterContent .= "    public function appendProtocolHeader(Buffer \$buffer): void\n";
$protocolWriterContent .= "    {\n";
$protocolWriterContent .= "        \$buffer->append('AMQP');\n";
$protocolWriterContent .= "        \$buffer->appendUint8(0);\n";
$protocolWriterContent .= "        \$buffer->appendUint8({$spec->{'major-version'}});\n";
$protocolWriterContent .= "        \$buffer->appendUint8({$spec->{'minor-version'}});\n";
$protocolWriterContent .= "        \$buffer->appendUint8({$spec->{'revision'}});\n";
$protocolWriterContent .= "    }\n\n";

$appendMethodFrameContent = "";
$appendMethodFrameContent .= "    /**\n";
$appendMethodFrameContent .= "     * Appends AMQP method frame to buffer.\n";
$appendMethodFrameContent .= "     */\n";
$appendMethodFrameContent .= "    public function appendMethodFrame(MethodFrame \$frame, Buffer \$buffer): void\n";
$appendMethodFrameContent .= "    {\n";
$appendMethodFrameContent .= "        \$buffer->appendUint16(\$frame->classId);\n";
$appendMethodFrameContent .= "        \$buffer->appendUint16(\$frame->methodId);\n";
$appendMethodFrameContent .= "\n";
$appendMethodFrameContent .= "        ";

$connectionContent = "<?php\n";
$connectionContent .= "\n";
$connectionContent .= "declare(strict_types=1);\n";
$connectionContent .= "\n";
$connectionContent .= "namespace Bunny;\n";
$connectionContent .= "\n";
$connectionContent .= "use Bunny\\Exception\\ClientException;\n";
$connectionContent .= "use Bunny\\Protocol\\AbstractFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\Buffer;\n";
$connectionContent .= "use Bunny\\Protocol\\ContentBodyFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\ContentHeaderFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\HeartbeatFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\MethodChannelCloseFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\MethodConnectionCloseFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\MethodFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\ProtocolReader;\n";
$connectionContent .= "use Bunny\\Protocol\\ProtocolWriter;\n";
$connectionContent .= "use Closure;\n";
$connectionContent .= "use Evenement\\EventEmitterInterface;\n";
$connectionContent .= "use Evenement\\EventEmitterTrait;\n";
$connectionContent .= "use React\\EventLoop\\Loop;\n";
$connectionContent .= "use React\\EventLoop\\TimerInterface;\n";
$connectionContent .= "use React\\Promise\\Deferred;\n";
$connectionContent .= "use React\\Promise\\Promise;\n";
$connectionContent .= "use React\\Socket\\ConnectionInterface;\n";
$connectionContent .= "use Throwable;\n";
$connectionContent .= "use function React\\Async\\await;\n";
$connectionContent .= "use function count;\n";
$connectionContent .= "use function is_callable;\n";
$connectionContent .= "use function key;\n";
$connectionContent .= "use function microtime;\n";
$connectionContent .= "use function reset;\n";
$connectionContent .= "use function serialize;\n";
$connectionContent .= "use function sprintf;\n";
$connectionContent .= "use function strlen;\n";
$connectionContent .= "use function substr;\n";
$connectionContent .= "\n";
$connectionContent .= "/**\n";
$connectionContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} client methods\n";
$connectionContent .= " *\n";
$connectionContent .= " * THIS CLASS IS GENERATED FROM $specFileName. **DO NOT EDIT!**\n";
$connectionContent .= " *\n";
$connectionContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$connectionContent .= " */\n";
$connectionContent .= "final class Connection implements EventEmitterInterface\n";
$connectionContent .= "{\n";
$connectionContent .= "    use EventEmitterTrait;\n";
$connectionContent .= "\n";
$connectionContent .= "    protected ?TimerInterface \$heartbeatTimer = null;\n";
$connectionContent .= "\n";
$connectionContent .= "    protected float \$lastWrite = 0.0;\n";
$connectionContent .= "\n";
$connectionContent .= "    /** @var array<string,mixed> */\n";
$connectionContent .= "    private array \$cache = [];\n";
$connectionContent .= "\n";
$connectionContent .= "    /** @var array<array{filter: (callable(\Bunny\Protocol\AbstractFrame): bool), promise: \React\Promise\Deferred<\Bunny\Protocol\AbstractFrame>}> */\n";
$connectionContent .= "    private array \$awaitList = [];\n";
$connectionContent .= "\n";
$connectionContent .= "    /** @param (Closure(): int) \$frameMax */\n";
$connectionContent .= "    public function __construct(\n";
$connectionContent .= "        private readonly ClientInterface \$client,\n";
$connectionContent .= "        private readonly ConnectionInterface \$connection,\n";
$connectionContent .= "        private readonly Buffer \$readBuffer,\n";
$connectionContent .= "        private readonly Buffer \$writeBuffer,\n";
$connectionContent .= "        private readonly ProtocolReader \$reader,\n";
$connectionContent .= "        private readonly ProtocolWriter \$writer,\n";
$connectionContent .= "        private readonly Channels \$channels,\n";
$connectionContent .= "        private readonly Configuration \$configuration,\n";
$connectionContent .= "        private readonly Closure \$frameMax,\n";
$connectionContent .= "    ) {\n";
$connectionContent .= "        \$this->connection->on('close', function (): void {\n";
$connectionContent .= "            if (!\$this->client->canDisconnect()) {\n";
$connectionContent .= "                return;\n";
$connectionContent .= "            }\n";
$connectionContent .= "\n";
$connectionContent .= "            \$this->client->disconnect(0, 'Connection lost', ClientInterface::RAW_CONNECTION_INACTIVE);\n";
$connectionContent .= "        });\n";
$connectionContent .= "        \$this->connection->on('data', function (string \$data): void {\n";
$connectionContent .= "            \$this->readBuffer->append(\$data);\n";
$connectionContent .= "\n";
$connectionContent .= "            while ((\$frame = \$this->reader->consumeFrame(\$this->readBuffer)) !== null) {\n";
$connectionContent .= "                \$frameInAwaitList = false;\n";
$connectionContent .= "                foreach (\$this->awaitList as \$index => \$frameHandler) {\n";
$connectionContent .= "                    try {\n";
$connectionContent .= "                        if (\$frameHandler['filter'](\$frame)) {\n";
$connectionContent .= "                            unset(\$this->awaitList[\$index]);\n";
$connectionContent .= "                            \$frameHandler['promise']->resolve(\$frame);\n";
$connectionContent .= "                            \$frameInAwaitList = true;\n";
$connectionContent .= "                        }\n";
$connectionContent .= "                    } catch (Throwable \$exception) {\n";
$connectionContent .= "                        unset(\$this->awaitList[\$index]);\n";
$connectionContent .= "                        \$frameHandler['promise']->reject(\$exception);\n";
$connectionContent .= "                        \$frameInAwaitList = true;\n";
$connectionContent .= "                    }\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frameInAwaitList && !\$frame instanceof MethodConnectionCloseFrame && !\$frame instanceof MethodChannelCloseFrame) {\n";
$connectionContent .= "                    continue;\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                try {\n";
$connectionContent .= "                    if (\$frame->channel === 0) {\n";
$connectionContent .= "                        \$this->onFrameReceived(\$frame);\n";
$connectionContent .= "                        continue;\n";
$connectionContent .= "                    }\n";
$connectionContent .= "\n";
$connectionContent .= "                    if (!\$this->channels->has(\$frame->channel)) {\n";
$connectionContent .= "                        throw new ClientException(sprintf('Received frame #%d on closed channel #%d.', \$frame->type, \$frame->channel));\n";
$connectionContent .= "                    }\n";
$connectionContent .= "\n";
$connectionContent .= "                    \$this->channels->get(\$frame->channel)->onFrameReceived(\$frame);\n";
$connectionContent .= "                } catch (Throwable \$error) {\n";
$connectionContent .= "                    \$this->emit('error', [\$error]);\n";
$connectionContent .= "                }\n";
$connectionContent .= "            }\n";
$connectionContent .= "        });\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    public function disconnect(int \$code, string \$reason, bool \$connectionStatus = ClientInterface::RAW_CONNECTION_ACTIVE): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        if (\$connectionStatus === ClientInterface::RAW_CONNECTION_ACTIVE) {\n";
$connectionContent .= "            \$this->connectionClose(\$code, 0, 0, \$reason);\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        \$this->connection->close();\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$this->heartbeatTimer === null) {\n";
$connectionContent .= "            return;\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        Loop::cancelTimer(\$this->heartbeatTimer);\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    /**\n";
$connectionContent .= "     * Callback after connection-level frame has been received.\n";
$connectionContent .= "     */\n";
$connectionContent .= "    private function onFrameReceived(AbstractFrame \$frame): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        if (\$frame instanceof MethodConnectionCloseFrame) {\n";
$connectionContent .= "            \$this->client->disconnect(Constants::STATUS_CONNECTION_FORCED, sprintf('Connection closed by server: (%d) %s', \$frame->replyCode, \$frame->replyText), ClientInterface::RAW_CONNECTION_INACTIVE);\n";
$connectionContent .= "\n";
$connectionContent .= "            throw new ClientException(sprintf('Connection closed by server: %s', \$frame->replyText), \$frame->replyCode);\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$frame instanceof ContentHeaderFrame) {\n";
$connectionContent .= "            \$this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got header frame on connection channel (#0).');\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$frame instanceof ContentBodyFrame) {\n";
$connectionContent .= "            \$this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got body frame on connection channel (#0).');\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$frame instanceof HeartbeatFrame) {\n";
$connectionContent .= "            return;\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        throw new ClientException(sprintf('Unhandled frame %s.', \$frame::class));\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    public function appendProtocolHeader(): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$this->writer->appendProtocolHeader(\$this->writeBuffer);\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    public function flushWriteBuffer(): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$data = \$this->writeBuffer->read(\$this->writeBuffer->getLength());\n";
$connectionContent .= "        \$this->writeBuffer->discard(strlen(\$data));\n";
$connectionContent .= "\n";
$connectionContent .= "        \$this->lastWrite = microtime(true);\n";
$connectionContent .= "        if (!\$this->connection->write(\$data)) {\n";
$connectionContent .= "            await(new Promise(function (callable \$resolve): void {\n";
$connectionContent .= "                \$this->connection->once('drain', static fn () => \$resolve(null));\n";
$connectionContent .= "            }));\n";
$connectionContent .= "        }\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    public function awaitContentHeader(int \$channel): ContentHeaderFrame\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$deferred = new Deferred();\n";
$connectionContent .= "        \$this->awaitList[] = [\n";
$connectionContent .= "            'filter' => function (AbstractFrame \$frame) use (\$channel): bool {\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\ContentHeaderFrame && \$frame->channel === \$channel) {\n";
$connectionContent .= "                    return true;\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
$connectionContent .= "                    \$this->channelCloseOk(\$channel);\n";
$connectionContent .= "\n";
$connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$connectionContent .= "                    \$this->connectionCloseOk();\n";
$connectionContent .= "\n";
$connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                return false;\n";
$connectionContent .= "            },\n";
$connectionContent .= "            'promise' => \$deferred,\n";
$connectionContent .= "        ];\n";
$connectionContent .= "\n";
$connectionContent .= "        return await(\$deferred->promise());\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    public function awaitContentBody(int \$channel): ContentBodyFrame\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$deferred = new Deferred();\n";
$connectionContent .= "        \$this->awaitList[] = [\n";
$connectionContent .= "            'filter' => function (AbstractFrame \$frame) use (\$channel): bool {\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\ContentBodyFrame && \$frame->channel === \$channel) {\n";
$connectionContent .= "                    return true;\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
$connectionContent .= "                    \$this->channelCloseOk(\$channel);\n";
$connectionContent .= "\n";
$connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$connectionContent .= "                    \$this->connectionCloseOk();\n";
$connectionContent .= "\n";
$connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                return false;\n";
$connectionContent .= "            },\n";
$connectionContent .= "            'promise' => \$deferred,\n";
$connectionContent .= "        ];\n";
$connectionContent .= "\n";
$connectionContent .= "        return await(\$deferred->promise());\n";
$connectionContent .= "    }\n\n";


$channelMethodsContent = "<?php\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "declare(strict_types=1);\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "namespace Bunny;\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "/**\n";
$channelMethodsContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} channel methods\n";
$channelMethodsContent .= " *\n";
$channelMethodsContent .= " * THIS CLASS IS GENERATED FROM $specFileName. **DO NOT EDIT!**\n";
$channelMethodsContent .= " *\n";
$channelMethodsContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$channelMethodsContent .= " */\n";
$channelMethodsContent .= "trait ChannelMethods\n";
$channelMethodsContent .= "{\n";

$channelMethodsContent .= "    /**\n";
$channelMethodsContent .= "     * Returns underlying client instance.\n";
$channelMethodsContent .= "     */\n";
$channelMethodsContent .= "    abstract public function getClient(): Connection;\n\n";

$channelMethodsContent .= "    /**\n";
$channelMethodsContent .= "     * Returns channel id.\n";
$channelMethodsContent .= "     */\n";
$channelMethodsContent .= "    abstract public function getChannelId(): int;\n\n";

foreach ($spec->classes as $class) {
    $classIdConstant = 'Constants::' . dashedToUnderscores('class-' . $class->name);

    $consumeMethodFrameContent .= "if (\$classId === $classIdConstant) {\n";
    $consumeMethodFrameContent .= "            ";

    foreach ($class->methods as $i => $method) {
        $className = "Method" . ucfirst($class->name) . dashedToCamel($method->name) . "Frame";
        $content = "<?php\n";
        $content .= "\n";
        $content .= "declare(strict_types=1);\n";
        $content .= "\n";
        $content .= "namespace Bunny\\Protocol;\n";
        $content .= "\n";
        $content .= "use Bunny\\Constants;\n";
        $content .= "\n";
        $content .= "/**\n";
        $content .= " * AMQP '$class->name.$method->name' (class #$class->id, method #$method->id) frame.\n";
        $content .= " *\n";
        $content .= " * THIS CLASS IS GENERATED FROM $specFileName. **DO NOT EDIT!**\n";
        $content .= " *\n";
        $content .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
        $content .= " */\n";
        $content .= "class $className extends MethodFrame\n";
        $content .= "{\n";

        $consumeContent = "                \$frame = new $className();\n";
        $appendContent = "";
        $clientAppendContent = "";

        $properties = "";
        $gettersSetters = "";
        $bitVars = [];
        $appendBitExpressions = [];
        $clientAppendBitExpressions = [];
        $clientArguments = [];
        $clientArgumentsTypeHint = [];
        $clientSetters = [];
        $channelClientArguments = ["\$this->getChannelId()"];
        $channelArguments = [];
        $channelArgumentsTypeHint = [];
        $hasNowait = false;

        if ($class->id !== 10) {
            $clientArguments[] = "int \$channel";
            $clientSetters[] = "\$frame->channel = \$channel;";
        }

        if (isset($method->content) && $method->content) {
            $clientArguments[] = "string \$body";
            $clientArguments[] = "array \$headers = []";
            $clientArgumentsTypeHint[] = "@param array<string,mixed> \$headers";

            $channelArguments[] = "string \$body";
            $channelArguments[] = "array \$headers = []";
            $channelArgumentsTypeHint[] = "@param array<string,mixed> \$headers";
            $channelClientArguments[] = "\$body";
            $channelClientArguments[] = "\$headers";
        }

        $static = true;
        $staticPayloadSize = 4; // class-id + method-id shorts
        $payloadSizeExpressions = [];

        $previousType = null;
        foreach ($method->arguments as $argument) {
            if (isset($argument->type)) {
                $type = $argument->type;
            } elseif (isset($argument->domain)) {
                $type = domainToType($argument->domain);
            } else {
                throw new InvalidArgumentException("{$class->name}.{$method->name}({$argument->name})");
            }

            if ($argument->name === 'nowait') {
                $hasNowait = true;
            }

            $name = lcfirst(dashedToCamel($argument->name));
            if ($class->id === 10 && $method->id === 50 || $class->id === 20 && $method->id === 40) {
                if ($name === 'classId') {
                    $name = 'closeClassId';
                } elseif ($name === 'methodId') {
                    $name = 'closeMethodId';
                }
            } elseif ($class->id === 40 && $method->id === 10 && $name === 'type') {
                $name = 'exchangeType';
            }

            if ($type === 'bit') {
                if ($previousType !== 'bit') {
                    $staticPayloadSize += 1;
                }
            } else {
                [$staticSize, $dynamicSize] = amqpTypeToLength($type, "\$$name");

                if ($staticSize === null && $dynamicSize === null) {
                    $static = false;
                    break;
                }

                if ($staticSize !== null) {
                    $staticPayloadSize += $staticSize;
                }

                if ($dynamicSize !== null) {
                    $payloadSizeExpressions[] = $dynamicSize;
                }
            }

            $previousType = $type;
        }

        array_unshift($payloadSizeExpressions, $staticPayloadSize);

        $previousType = null;
        foreach (
            [
                ...array_filter($method->arguments, static fn (stdClass $argument): bool => !(isset($argument->{'default-value'}) || (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof stdClass))),
                ...array_filter($method->arguments, static fn (stdClass $argument): bool => isset($argument->{'default-value'}) || (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof stdClass)),
            ] as $argument
        ) {
            if (isset($argument->type)) {
                $type = $argument->type;
            } elseif (isset($argument->domain)) {
                $type = domainToType($argument->domain);
            } else {
                throw new InvalidArgumentException("{$class->name}.{$method->name}({$argument->name})");
            }

            $name = lcfirst(dashedToCamel($argument->name));
            if ($class->id === 10 && $method->id === 50 || $class->id === 20 && $method->id === 40) {
                if ($name === 'classId') {
                    $name = 'closeClassId';
                } elseif ($name === 'methodId') {
                    $name = 'closeMethodId';
                }
            } elseif ($class->id === 40 && $method->id === 10 && $name === 'type') {
                $name = 'exchangeType';
            }

            $phpType = amqpTypeToPhpType($type);
            if ($phpType === 'array') {
                $properties .= "    /** @var array<mixed> */\n";
            }
            $defaultValue = null;
            if (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof stdClass) {
                $defaultValue = '[]';
            } elseif (isset($argument->{'default-value'})) {
                $defaultValue = var_export($argument->{'default-value'}, true);
            }

            $properties .= "    public $phpType \$$name" . ($defaultValue !== null ? " = $defaultValue" : "") . ";\n\n";

            if (strpos($name, 'reserved') !== 0) {
                $clientArguments[] = $phpType . " \$" . $name . ($defaultValue !== null ? " = $defaultValue" : "");
                $channelArguments[] = $phpType . " \$" . $name . ($defaultValue !== null ? " = $defaultValue" : "");
                if ($phpType === 'array') {
                    $clientArgumentsTypeHint[] = "@param array<string,mixed> \$". $name;
                    $channelArgumentsTypeHint[] = "@param array<string,mixed> \$". $name;
                }
                $channelClientArguments[] = "\$$name";
            }
        }

        foreach ($method->arguments as $argument) {
            if (isset($argument->type)) {
                $type = $argument->type;
            } elseif (isset($argument->domain)) {
                $type = domainToType($argument->domain);
            } else {
                throw new InvalidArgumentException("{$class->name}.{$method->name}({$argument->name})");
            }

            $name = lcfirst(dashedToCamel($argument->name));
            if ($class->id === 10 && $method->id === 50 || $class->id === 20 && $method->id === 40) {
                if ($name === 'classId') {
                    $name = 'closeClassId';
                } elseif ($name === 'methodId') {
                    $name = 'closeMethodId';
                }
            } elseif ($class->id === 40 && $method->id === 10 && $name === 'type') {
                $name = 'exchangeType';
            }

            if ($type === 'bit') {
                $bitVars[] = "\$frame->$name";
            } else {
                if ($previousType === 'bit') {
                    $consumeContent .= "                [" . implode(', ', $bitVars) . "] = \$this->consumeBits(\$buffer, " . count($bitVars) . ");\n";
                    $bitVars = [];
                }

                $consumeContent .= "                \$frame->$name = " . amqpTypeToConsume($type) . ";\n";
            }

            if ($type === 'bit') {
                $appendBitExpressions[] = "\$frame->$name";
                $clientAppendBitExpressions[] = "\$$name";
            } else {
                if ($previousType === 'bit') {
                    $appendContent .= "            \$this->appendBits([" . implode(', ', $appendBitExpressions) . "], \$buffer);\n";
                    $appendBitExpressions = [];
                    $clientAppendContent .= "        \$this->writer->appendBits([" . implode(', ', $clientAppendBitExpressions) . "], \$buffer);\n";
                    $clientAppendBitExpressions = [];
                }

                $appendContent .= indent(amqpTypeToAppend($type, "\$frame->$name"), '            ') . ";\n";
                if (strpos($name, 'reserved') === 0) {
                    $clientAppendContent .= indent(amqpTypeToAppend($type, '0'), '        ') . ";\n";
                } elseif ($type === 'table') {
                    $clientAppendContent .= "        \$this->writer->appendTable(\$$name, \$buffer);\n";
                } else {
                    $clientAppendContent .= indent(amqpTypeToAppend($type, "\$$name"), '        ') . ";\n";
                }
            }

            $previousType = $type;

            if (strpos($name, 'reserved') !== 0) {
                $clientSetters[] = "\$frame->$name = \$$name;";
            }
        }

        if ($previousType === 'bit') {
            $appendContent .= "            \$this->appendBits([" . implode(", ", $appendBitExpressions) . "], \$buffer);\n";
            $appendBitExpressions = [];
            $clientAppendContent .= "        \$this->writer->appendBits([" . implode(", ", $clientAppendBitExpressions) . "], \$buffer);\n";
            $clientAppendBitExpressions = [];
        }

        if ($previousType === 'bit') {
            $consumeContent .= "                [" . implode(", ", $bitVars) . "] = \$this->consumeBits(\$buffer, " . count($bitVars) . ");\n";
            $bitVars = [];
        }

        $content .= $properties;

        $methodIdConstant = 'Constants::' . dashedToUnderscores('method-' . $class->name . '-' . $method->name);

        $content .= "    public function __construct()\n";
        $content .= "    {\n";
        $content .= "        parent::__construct($classIdConstant, $methodIdConstant);\n";

        if ($class->id === 10) {
            $content .= "\n        \$this->channel = Constants::CONNECTION_CHANNEL;\n";
        }

        $content .= "    }\n";
        $content .= $gettersSetters;
        $content .= "}\n";
        file_put_contents(__DIR__ . "/../src/Protocol/$className.php", $content);

        $consumeMethodFrameContent .= "if (\$methodId === $methodIdConstant) {\n";
        $consumeMethodFrameContent .= $consumeContent;
        $consumeMethodFrameContent .= "            } else";

        $appendMethodFrameContent .= "if (\$frame instanceof $className) {\n";
        $appendMethodFrameContent .= $appendContent;
        $appendMethodFrameContent .= "        } else";

        $methodName = dashedToCamel(($class->name !== 'basic' ? $class->name . '-' : "") . $method->name);

        if (!isset($method->direction) || $method->direction === 'CS') {

            if (isset($method->synchronous) && $method->synchronous) {
                $returnType = "Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "");
            }
            if (count($clientArgumentsTypeHint) > 0 || $hasNowait || (!isset($method->synchronous) || !$method->synchronous || $hasNowait)) {
                $connectionContent .= "    /**\n";
                if (count($clientArgumentsTypeHint) > 0) {
                    $connectionContent .= "     * " . implode("\n     * ", $channelArgumentsTypeHint) . "\n";
                }
                if ($hasNowait) {
                    if (count($clientArgumentsTypeHint) > 0) {
                        $connectionContent .= "     *\n";
                    }
                    $connectionContent .= "     * @return (\$nowait is false ? $returnType : false)\n";
                } elseif (!isset($method->synchronous) || !$method->synchronous || $hasNowait) {
                    if (count($clientArgumentsTypeHint) > 0) {
                        $connectionContent .= "     *\n";
                    }
                    $connectionContent .= "     * @return false\n";
                }

                $connectionContent .= "     */\n";
            }
            $connectionContent .= "    public function " . lcfirst($methodName) . "(" . implode(", ", $clientArguments) . "): ". ((!isset($method->synchronous) || !$method->synchronous || $hasNowait) ? "bool" : "") . (isset($method->synchronous) && $method->synchronous ? (($hasNowait ? "|" : "") . "Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame")) : "") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "") . "\n";
            $connectionContent .= "    {\n";

            $indent = "";
            if ($static) {
                $connectionContent .= "        \$buffer = \$this->writeBuffer;\n";
                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "        \$ck = serialize([\$channel, \$headers, \$exchange, \$routingKey, \$mandatory, \$immediate]);\n";
                    $connectionContent .= "        \$c = \$this->cache[\$ck] ?? null;\n";
                    $connectionContent .= "        \$flags = \$off0 = \$len0 = \$off1 = \$len1 = 0;\n";
                    $connectionContent .= "        \$contentTypeLength = \$contentType = \$contentEncodingLength = \$contentEncoding = \$headersBuffer = \$deliveryMode = \$priority = \$correlationIdLength = \$correlationId = \$replyToLength = \$replyTo = \$expirationLength = \$expiration = \$messageIdLength = \$messageId = \$timestamp = \$typeLength = \$type = \$userIdLength = \$userId = \$appIdLength = \$appId = \$clusterIdLength = \$clusterId = null;\n";
                    $connectionContent .= "        if (\$c) {\n";
                    $connectionContent .= "            \$buffer->append(\$c[0]);\n";
                    $connectionContent .= "        } else {\n";
                    $connectionContent .= "            \$off0 = \$buffer->getLength();\n";
                    $indent = '    ';
                }
                $connectionContent .= $indent."        \$buffer->appendUint8(" . Constants::FRAME_METHOD . ");\n";
                $connectionContent .= $indent."        \$buffer->appendUint16(" . ($class->id === 10 ? Constants::CONNECTION_CHANNEL : "\$channel") . ");\n";
                $connectionContent .= $indent."        \$buffer->appendUint32(" . implode(" + ", $payloadSizeExpressions) . ");\n";
            } else {
                $connectionContent .= "        \$buffer = new Buffer();\n";
            }

            $connectionContent .= $indent."        \$buffer->appendUint16($class->id);\n";
            $connectionContent .= $indent."        \$buffer->appendUint16($method->id);\n";
            $connectionContent .= $indent.implode("\n".$indent, explode("\n", $clientAppendContent));

            if ($static) {
                $connectionContent .= "        \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";
            } else {
                $connectionContent .= "        \$frame = new MethodFrame($class->id, $method->id);\n";
                $connectionContent .= "        \$frame->channel = " . ($class->id === 10 ? Constants::CONNECTION_CHANNEL : "\$channel") . ";\n";
                $connectionContent .= "        \$frame->payloadSize = \$buffer->getLength();\n";
                $connectionContent .= "        \$frame->payload = \$buffer;\n";
                $connectionContent .= "        \$this->writer->appendFrame(\$frame, \$this->writeBuffer);\n";
            }

            if (isset($method->content) && $method->content) {
                if (!$static) {
                    $connectionContent .= "        \$buffer = \$this->writeBuffer;\n";
                }

                // FIXME: respect max body size agreed upon connection.tune
                $connectionContent .= $indent."        \$s = 14;\n";
                $connectionContent .= "\n";

                foreach (
                    [
                        ContentHeaderFrame::FLAG_CONTENT_TYPE => ['content-type', 1, "\$contentTypeLength = strlen(\$contentType)"],
                        ContentHeaderFrame::FLAG_CONTENT_ENCODING => ['content-encoding', 1, "\$contentEncodingLength = strlen(\$contentEncoding)"],
                        ContentHeaderFrame::FLAG_DELIVERY_MODE => ['delivery-mode', 1, null],
                        ContentHeaderFrame::FLAG_PRIORITY => ['priority', 1, null],
                        ContentHeaderFrame::FLAG_CORRELATION_ID => ['correlation-id', 1, "\$correlationIdLength = strlen(\$correlationId)"],
                        ContentHeaderFrame::FLAG_REPLY_TO => ['reply-to', 1, "\$replyToLength = strlen(\$replyTo)"],
                        ContentHeaderFrame::FLAG_EXPIRATION => ['expiration', 1, "\$expirationLength = strlen(\$expiration)"],
                        ContentHeaderFrame::FLAG_MESSAGE_ID => ['message-id', 1, "\$messageIdLength = strlen(\$messageId)"],
                        ContentHeaderFrame::FLAG_TIMESTAMP => ['timestamp', 8, null],
                        ContentHeaderFrame::FLAG_TYPE => ['type', 1, "\$typeLength = strlen(\$type)"],
                        ContentHeaderFrame::FLAG_USER_ID => ['user-id', 1, "\$userIdLength = strlen(\$userId)"],
                        ContentHeaderFrame::FLAG_APP_ID => ['app-id', 1, "\$appIdLength = strlen(\$appId)"],
                        ContentHeaderFrame::FLAG_CLUSTER_ID => ['cluster-id', 1, "\$clusterIdLength = strlen(\$clusterId)"],
                    ] as $flag => $property
                ) {
                    [$propertyName, $staticSize, $dynamicSize] = $property;
                    $connectionContent .= $indent."        if (\$" . lcfirst(dashedToCamel($propertyName)) . " = \$headers['$propertyName'] ?? null) {\n";
                    $connectionContent .= $indent."            \$flags |= $flag;\n";
                    if ($staticSize) {
                        $connectionContent .= $indent."            \$s += $staticSize;\n";
                    }

                    if ($dynamicSize) {
                        $connectionContent .= $indent."            \$s += $dynamicSize;\n";
                    }

                    $connectionContent .= $indent."            unset(\$headers['$propertyName']);\n";
                    $connectionContent .= $indent."        }\n";
                    $connectionContent .= "\n";
                }

                $connectionContent .= $indent."        if (!empty(\$headers)) {\n";
                $connectionContent .= $indent."            \$flags |= " . ContentHeaderFrame::FLAG_HEADERS . ";\n";
                $connectionContent .= $indent."            \$this->writer->appendTable(\$headers, \$headersBuffer = new Buffer());\n";
                $connectionContent .= $indent."            \$s += \$headersBuffer->getLength();\n";
                $connectionContent .= $indent."        }\n";
                $connectionContent .= "\n";

                $connectionContent .= $indent."        \$buffer->appendUint8(" . Constants::FRAME_HEADER . ");\n";
                $connectionContent .= $indent."        \$buffer->appendUint16(\$channel);\n";
                $connectionContent .= $indent."        \$buffer->appendUint32(\$s);\n";
                $connectionContent .= $indent."        \$buffer->appendUint16($class->id);\n";
                $connectionContent .= $indent."        \$buffer->appendUint16(0);\n";
                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "            \$len0 = \$buffer->getLength() - \$off0;\n";
                    $connectionContent .= "        }\n";
                }

                $connectionContent .= "\n";
                $connectionContent .= "        \$buffer->appendUint64(strlen(\$body));\n";
                $connectionContent .= "\n";

                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "        if (\$c) {\n";
                    $connectionContent .= "            \$buffer->append(\$c[1]);\n";
                    $connectionContent .= "        } else {\n";
                    $connectionContent .= "            \$off1 = \$buffer->getLength();\n";
                }

                $connectionContent .= $indent."        \$buffer->appendUint16(\$flags);\n";

                foreach (
                    [
                        ContentHeaderFrame::FLAG_CONTENT_TYPE => "\$buffer->appendUint8(\$contentTypeLength);\n\$buffer->append(\$contentType);",
                        ContentHeaderFrame::FLAG_CONTENT_ENCODING => "\$buffer->appendUint8(\$contentEncodingLength);\n\$buffer->append(\$contentEncoding);",
                        ContentHeaderFrame::FLAG_HEADERS => "\$buffer->append(\$headersBuffer);",
                        ContentHeaderFrame::FLAG_DELIVERY_MODE => "\$buffer->appendUint8(\$deliveryMode);",
                        ContentHeaderFrame::FLAG_PRIORITY => "\$buffer->appendUint8(\$priority);",
                        ContentHeaderFrame::FLAG_CORRELATION_ID => "\$buffer->appendUint8(\$correlationIdLength);\n\$buffer->append(\$correlationId);",
                        ContentHeaderFrame::FLAG_REPLY_TO => "\$buffer->appendUint8(\$replyToLength);\n\$buffer->append(\$replyTo);",
                        ContentHeaderFrame::FLAG_EXPIRATION => "\$buffer->appendUint8(\$expirationLength);\n\$buffer->append(\$expiration);",
                        ContentHeaderFrame::FLAG_MESSAGE_ID => "\$buffer->appendUint8(\$messageIdLength);\n\$buffer->append(\$messageId);",
                        ContentHeaderFrame::FLAG_TIMESTAMP => "\$this->writer->appendTimestamp(\$timestamp, \$buffer);",
                        ContentHeaderFrame::FLAG_TYPE => "\$buffer->appendUint8(\$typeLength);\n\$buffer->append(\$type);",
                        ContentHeaderFrame::FLAG_USER_ID => "\$buffer->appendUint8(\$userIdLength);\n\$buffer->append(\$userId);",
                        ContentHeaderFrame::FLAG_APP_ID => "\$buffer->appendUint8(\$appIdLength);\n\$buffer->append(\$appId);",
                        ContentHeaderFrame::FLAG_CLUSTER_ID => "\$buffer->appendUint8(\$clusterIdLength);\n\$buffer->append(\$clusterId);",
                    ] as $flag => $property
                ) {
                    $connectionContent .= $indent."        if (\$flags & $flag) {\n";
                    $connectionContent .= indent($property, $indent."            ")."\n";
                    $connectionContent .= $indent."        }\n\n";
                }

                $connectionContent .= $indent."        \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";

                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= $indent."        \$len1 = \$buffer->getLength() - \$off1;\n";
                    $connectionContent .= "        }\n\n";
                    $connectionContent .= "        if (!\$c) {\n";
                    $connectionContent .= "            \$this->cache[\$ck] = [\$buffer->read(\$len0, \$off0), \$buffer->read(\$len1, \$off1)];\n";
                    $connectionContent .= "            if (count(\$this->cache) > 100) {\n";
                    $connectionContent .= "                reset(\$this->cache);\n";
                    $connectionContent .= "                unset(\$this->cache[key(\$this->cache)]);\n";
                    $connectionContent .= "            }\n";
                    $connectionContent .= "        }\n\n";
                }

                $connectionContent .= "        for (\$payloadMax = (\$this->frameMax)() - 8 /* frame preface and frame end */, \$i = 0, \$l = strlen(\$body); \$i < \$l; \$i += \$payloadMax) {\n";
                $connectionContent .= "            \$payloadSize = \$l - \$i;\n";
                $connectionContent .= "            if (\$payloadSize > \$payloadMax) {\n";
                $connectionContent .= "                \$payloadSize = \$payloadMax;\n";
                $connectionContent .= "            }\n\n";
                $connectionContent .= "            \$buffer->appendUint8(" . Constants::FRAME_BODY . ");\n";
                $connectionContent .= "            \$buffer->appendUint16(\$channel);\n";
                $connectionContent .= "            \$buffer->appendUint32(\$payloadSize);\n";
                $connectionContent .= "            \$buffer->append(substr(\$body, \$i, \$payloadSize));\n";
                $connectionContent .= "            \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";
                $connectionContent .= "        }\n\n";
            }

            if (isset($method->synchronous) && $method->synchronous && $hasNowait) {
                $connectionContent .= "        \$this->flushWriteBuffer();\n";
                $connectionContent .= "\n";
                $connectionContent .= "        if (!\$nowait) {\n";
                $connectionContent .= "            return \$this->await" . $methodName . "Ok(" . ($class->id !== 10 ? "\$channel" : "") . ");\n";
                $connectionContent .= "        }\n";
                $connectionContent .= "\n";
                $connectionContent .= "        return false;\n";
            } elseif (isset($method->synchronous) && $method->synchronous) {
                $connectionContent .= "        \$this->flushWriteBuffer();\n";
                $connectionContent .= "\n";
                $connectionContent .= "        return \$this->await" . $methodName . "Ok(" . ($class->id !== 10 ? "\$channel" : "") . ");\n";
            } else {
                $connectionContent .= "        \$this->flushWriteBuffer();\n";
                $connectionContent .= "\n";
                $connectionContent .= "        return false;\n";
            }

            $connectionContent .= "    }\n\n";
        }

        if (!isset($method->direction) || $method->direction === 'SC') {
            $connectionContent .= "    public function await" . $methodName . "(" . ($class->id !== 10 ? "int \$channel" : "") . "): Protocol\\$className" . ($class->id === 60 && $method->id === 71 ? '|Protocol\\' . str_replace("GetOk", "GetEmpty", $className) : "") . "\n";
            $connectionContent .= "    {\n";

            // async await
            $connectionContent .= "        \$deferred = new Deferred();\n";
            $connectionContent .= "        \$this->awaitList[] = [\n";
            $connectionContent .= "            'filter' => function (Protocol\\AbstractFrame \$frame)" . ($class->id !== 10 ? " use (\$channel)" : "") . ": bool {\n";

            if ($class->id !== 10 || $method->id !== 50) {
                $connectionContent .= "                if (\$frame instanceof Protocol\\$className" . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
                $connectionContent .= "                    return true;\n";
                $connectionContent .= "                }\n";
                $connectionContent .= "\n";
            }

            if ($class->id === 60 && $method->id === 71) {
                $connectionContent .= "                if (\$frame instanceof Protocol\\" . str_replace("GetOk", "GetEmpty", $className) . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
                $connectionContent .= "                    return true;\n";
                $connectionContent .= "                }\n";
                $connectionContent .= "\n";
            }

            if ($class->id !== 10) {
                $connectionContent .= "                if (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
                $connectionContent .= "                    \$this->channelCloseOk(\$channel);\n";
                $connectionContent .= "\n";
                $connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
                $connectionContent .= "                }\n";
                $connectionContent .= "\n";
            }

            $connectionContent .= "                if (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
            $connectionContent .= "                    \$this->connectionCloseOk();\n";
            $connectionContent .= "\n";
            $connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
            $connectionContent .= "                }\n";
            $connectionContent .= "\n";
            $connectionContent .= "                return false;\n";
            $connectionContent .= "            },\n";
            $connectionContent .= "            'promise' => \$deferred,\n";
            $connectionContent .= "        ];\n";
            $connectionContent .= "\n";
            $connectionContent .= "        return await(\$deferred->promise());\n";
            $connectionContent .= "    }\n\n";
        }

        if (
            $class->id !== 10 &&
            $class->id !== 20 &&
            $class->id !== 30 &&
            (!isset($method->direction) || $method->direction === 'CS')
        ) {

            if (isset($method->synchronous) && $method->synchronous) {
                $returnType = "Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "");
            }

            $channelMethodsContent .= "    /**\n";
            $channelMethodsContent .= "     * Calls {$class->name}.{$method->name} AMQP method.\n";
            if (count($channelArgumentsTypeHint) > 0) {
                $channelMethodsContent .= "     *";
                $channelMethodsContent .= "\n     * ";
                $channelMethodsContent .= implode("\n     * ", $channelArgumentsTypeHint);
                $channelMethodsContent .= "\n";
            }
            if ($hasNowait) {
                $channelMethodsContent .= "     *\n";
                $channelMethodsContent .= "     * @return (\$nowait is false ? $returnType : false)\n";
            } elseif (!isset($method->synchronous) || !$method->synchronous || $hasNowait) {
                $channelMethodsContent .= "     *\n";
                $channelMethodsContent .= "     * @return false\n";
            }
            $channelMethodsContent .= "     */\n";
            $channelMethodsContent .= "    public function " . lcfirst($methodName) . "(" . implode(", ", $channelArguments) . "): ". ((!isset($method->synchronous) || !$method->synchronous || $hasNowait) ? "bool" : "") . (isset($method->synchronous) && $method->synchronous ? (($hasNowait ? "|" : "") . "Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame")) : "") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "") . "\n";
            $channelMethodsContent .= "    {\n";
            $channelMethodsContent .= "        return \$this->getClient()->" . lcfirst($methodName) . "(" . implode(", ", $channelClientArguments) . ");\n";
            $channelMethodsContent .= "    }\n\n";
        }
    }

    $consumeMethodFrameContent .= " {\n";
    $consumeMethodFrameContent .= "                throw new InvalidMethodException(\$classId, \$methodId);\n";
    $consumeMethodFrameContent .= "            }\n";
    $consumeMethodFrameContent .= "        } else";
}

$channelMethodsContent = rtrim($channelMethodsContent, "\n");
$channelMethodsContent .= "\n";

$consumeMethodFrameContent .= " {\n";
$consumeMethodFrameContent .= "            throw new InvalidClassException(\$classId);\n";
$consumeMethodFrameContent .= "        }\n\n";
$consumeMethodFrameContent .= "        \$frame->classId = \$classId;\n";
$consumeMethodFrameContent .= "        \$frame->methodId = \$methodId;\n";
$consumeMethodFrameContent .= "\n";
$consumeMethodFrameContent .= "        return \$frame;\n";
$consumeMethodFrameContent .= "    }\n";

$protocolReaderContent .= $consumeMethodFrameContent;
$protocolReaderContent .= "}\n";
file_put_contents(__DIR__ . '/../src/Protocol/ProtocolReaderGenerated.php', $protocolReaderContent);

$appendMethodFrameContent .= " {\n";
$appendMethodFrameContent .= "            throw new ProtocolException(sprintf('Unhandled method frame %s.', \$frame::class));\n";
$appendMethodFrameContent .= "        }\n";
$appendMethodFrameContent .= "    }\n";

$protocolWriterContent .= $appendMethodFrameContent;
$protocolWriterContent .= "}\n";
file_put_contents(__DIR__ . '/../src/Protocol/ProtocolWriterGenerated.php', $protocolWriterContent);

$connectionContent .= "    public function startHeartbeatTimer(): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$this->heartbeatTimer = Loop::addTimer(\$this->configuration->heartbeat, \$this->onHeartbeat(...));\n";
$connectionContent .= "        \$this->connection->on('drain', \$this->onHeartbeat(...));\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    /**\n";
$connectionContent .= "     * Callback when heartbeat timer timed out.\n";
$connectionContent .= "     */\n";
$connectionContent .= "    private function onHeartbeat(): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$now = microtime(true);\n";
$connectionContent .= "        \$nextHeartbeat = (\$this->lastWrite ?: \$now) + \$this->configuration->heartbeat;\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$now >= \$nextHeartbeat) {\n";
$connectionContent .= "            \$this->writer->appendFrame(new HeartbeatFrame(), \$this->writeBuffer);\n";
$connectionContent .= "            \$this->flushWriteBuffer();\n";
$connectionContent .= "\n";
$connectionContent .= "            \$this->heartbeatTimer = Loop::addTimer(\$this->configuration->heartbeat, \$this->onHeartbeat(...));\n";
$connectionContent .= "            if (is_callable(\$this->configuration->heartbeatCallback)) {\n";
$connectionContent .= "                (\$this->configuration->heartbeatCallback)(\$this);\n";
$connectionContent .= "            }\n";
$connectionContent .= "        } else {\n";
$connectionContent .= "            \$this->heartbeatTimer = Loop::addTimer(\$nextHeartbeat - \$now, \$this->onHeartbeat(...));\n";
$connectionContent .= "        }\n";
$connectionContent .= "    }\n";
$connectionContent .= "}\n";
file_put_contents(__DIR__ . '/../src/Connection.php', $connectionContent);

$channelMethodsContent .= "}\n";
file_put_contents(__DIR__ . '/../src/ChannelMethods.php', $channelMethodsContent);
