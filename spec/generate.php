<?php
namespace Bunny;

use Bunny\Exception\ClientException;
use Bunny\Protocol\AbstractFrame;
use Bunny\Protocol\ContentBodyFrame;
use Bunny\Protocol\ContentHeaderFrame;
use Bunny\Protocol\HeartbeatFrame;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use function React\Async\await;

require_once __DIR__ . "/../vendor/autoload.php";

$specFileName = "amqp-rabbitmq-0.9.1.json";
$spec = json_decode(file_get_contents(__DIR__ . "/" . $specFileName));

function dashedToCamel($dashed)
{
    return implode("", array_map(function ($s) {
        return ucfirst($s);
    }, explode("-", $dashed)));
}

function dashedToUnderscores($dashed)
{
    return strtoupper(str_replace("-", "_", $dashed));
}

/**
 * @param string $domain
 * @return string
 */
function domainToType($domain)
{
    global $spec;

    foreach ($spec->domains as $d) {
        if ($d[0] === $domain) {
            return $d[1];
        }
    }

    throw new \InvalidArgumentException("Unhandled domain '{$domain}'.");
}

/**
 * @param string $type
 * @return string
 */
function amqpTypeToPhpType($type)
{
    if (in_array($type, ["octet", "short", "long", "longlong"])) {
        return "int";
    } elseif (in_array($type, ["shortstr", "longstr"])) {
        return "string";
    } elseif (in_array($type, ["bit"])) {
        return "bool";
    } elseif (in_array($type, ["table"])) {
        return "array";
    } elseif (in_array($type, ["timestamp"])) {
        return "\\DateTime";
    } else {
        throw new \InvalidArgumentException("Unhandled type '{$type}'.");
    }
}

function amqpTypeToConsume($type)
{
    switch ($type) {
        case "octet":
            return "\$buffer->consumeUint8()";
        case "table":
            return "\$this->consumeTable(\$buffer)";
        case "longstr":
            return "\$buffer->consume(\$buffer->consumeUint32())";
        case "shortstr":
            return "\$buffer->consume(\$buffer->consumeUint8())";
        case "short":
            return "\$buffer->consumeInt16()";
        case "long":
            return "\$buffer->consumeInt32()";
        case "longlong":
            return "\$buffer->consumeInt64()";
        case "timestamp":
            return "\$this->consumeTimestamp(\$buffer)";
        default:
            throw new \InvalidArgumentException("Unhandled type '{$type}'.");
    }
}

function amqpTypeToAppend($type, $e)
{
    switch ($type) {
        case "octet":
            return "\$buffer->appendUint8({$e})";
        case "table":
            return "\$this->appendTable({$e}, \$buffer)";
        case "longstr":
            return "\$buffer->appendUint32(strlen({$e})); \$buffer->append({$e})";
        case "shortstr":
            return "\$buffer->appendUint8(strlen({$e})); \$buffer->append({$e})";
        case "short":
            return "\$buffer->appendInt16({$e})";
        case "long":
            return "\$buffer->appendInt32({$e})";
        case "longlong":
            return "\$buffer->appendInt64({$e})";
        case "timestamp":
            return "\$this->appendTimestamp({$e}, \$buffer)";
        default:
            throw new \InvalidArgumentException("Unhandled type '{$type}'.");
    }
}

/**
 * @param string $type
 * @param string $e
 * @return array
 */
function amqpTypeToLength($type, $e)
{
    switch ($type) {
        case "octet":
            return [1, null];
        case "table":
            return [null, null];
        case "longstr":
            return [4, "strlen({$e})"];
        case "shortstr":
            return [1, "strlen({$e})"];
        case "short":
            return [2, null];
        case "long":
            return [4, null];
        case "longlong":
            return [8, null];
        case "timestamp":
            return [8, null];
        default:
            throw new \InvalidArgumentException("Unhandled type '{$type}'.");
    }
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
$protocolReaderContent .= " * THIS CLASS IS GENERATED FROM {$specFileName}. **DO NOT EDIT!**\n";
$protocolReaderContent .= " *\n";
$protocolReaderContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$protocolReaderContent .= " */\n";
$protocolReaderContent .= "trait ProtocolReaderGenerated\n";
$protocolReaderContent .= "{\n";
$protocolReaderContent .= "\n";
$protocolReaderContent .= "    /**\n";
$protocolReaderContent .= "     * Consumes AMQP table from buffer.\n";
$protocolReaderContent .= "     * \n";
$protocolReaderContent .= "     * @param Buffer \$originalBuffer\n";
$protocolReaderContent .= "     * @return array\n";
$protocolReaderContent .= "     */\n";
$protocolReaderContent .= "    abstract public function consumeTable(Buffer \$originalBuffer): array;\n\n";
$protocolReaderContent .= "    /**\n";
$protocolReaderContent .= "     * Consumes packed bits from buffer.\n";
$protocolReaderContent .= "     *\n";
$protocolReaderContent .= "     * @return array<mixed>\n";
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
$protocolWriterContent .= "\n";
$protocolWriterContent .= "\n";
$protocolWriterContent .= "/**\n";
$protocolWriterContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} protocol writer\n";
$protocolWriterContent .= " *\n";
$protocolWriterContent .= " * THIS CLASS IS GENERATED FROM {$specFileName}. **DO NOT EDIT!**\n";
$protocolWriterContent .= " *\n";
$protocolWriterContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$protocolWriterContent .= " */\n";
$protocolWriterContent .= "trait ProtocolWriterGenerated\n";
$protocolWriterContent .= "{\n";
$protocolWriterContent .= "\n";

$protocolWriterContent .= "    /**\n";
$protocolWriterContent .= "     * Appends AMQP table to buffer.\n";
$protocolWriterContent .= "     *\n";
$protocolWriterContent .= "     * @param array \$table\n";
$protocolWriterContent .= "     * @param Buffer \$originalBuffer\n";
$protocolWriterContent .= "     */\n";
$protocolWriterContent .= "    abstract public function appendTable(array \$table, Buffer \$originalBuffer);\n\n";

$protocolWriterContent .= "    /**\n";
$protocolWriterContent .= "     * Appends packed bits to buffer.\n";
$protocolWriterContent .= "     *\n";
$protocolWriterContent .= "     * @param array \$bits\n";
$protocolWriterContent .= "     * @param Buffer \$buffer\n";
$protocolWriterContent .= "     */\n";
$protocolWriterContent .= "    abstract public function appendBits(array \$bits, Buffer \$buffer);\n\n";

$protocolWriterContent .= "    /**\n";
$protocolWriterContent .= "     * Appends AMQP protocol header to buffer.\n";
$protocolWriterContent .= "     *\n";
$protocolWriterContent .= "     * @param Buffer \$buffer\n";
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
$appendMethodFrameContent .= "     *\n";
$appendMethodFrameContent .= "     * @param MethodFrame \$frame\n";
$appendMethodFrameContent .= "     * @param Buffer \$buffer\n";
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
$connectionContent .= "use Bunny\\Protocol\\MethodConnectionCloseFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\MethodFrame;\n";
$connectionContent .= "use Bunny\\Protocol\\ProtocolReader;\n";
$connectionContent .= "use Bunny\\Protocol\\ProtocolWriter;\n";
$connectionContent .= "use React\\EventLoop\\Loop;\n";
$connectionContent .= "use React\\EventLoop\\TimerInterface;\n";
$connectionContent .= "use React\\Promise\\Deferred;\n";
$connectionContent .= "use React\\Promise\\Promise;\n";
$connectionContent .= "use React\\Socket\\ConnectionInterface;\n";
$connectionContent .= "use function React\\Async\\await;\n";
$connectionContent .= "\n";
$connectionContent .= "/**\n";
$connectionContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} client methods\n";
$connectionContent .= " *\n";
$connectionContent .= " * THIS CLASS IS GENERATED FROM {$specFileName}. **DO NOT EDIT!**\n";
$connectionContent .= " *\n";
$connectionContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$connectionContent .= " */\n";
$connectionContent .= "final class Connection\n";
$connectionContent .= "{\n";
$connectionContent .= "    protected ?TimerInterface \$heartbeatTimer = null;\n";
$connectionContent .= "\n";
$connectionContent .= "    /** @var float microtime of last write */\n";
$connectionContent .= "    protected float \$lastWrite = 0.0;\n";
$connectionContent .= "\n";
$connectionContent .= "    private array \$cache = [];\n";
$connectionContent .= "\n";
$connectionContent .= "    /** @var array<array{filter: (callable(AbstractFrame): bool), promise: Deferred}> */\n";
$connectionContent .= "    private array \$awaitList = [];\n";
$connectionContent .= "\n";
$connectionContent .= "    public function __construct(\n";
$connectionContent .= "        private readonly Client \$client,\n";
$connectionContent .= "        private readonly ConnectionInterface \$connection,\n";
$connectionContent .= "        private readonly Buffer \$readBuffer,\n";
$connectionContent .= "        private readonly Buffer \$writeBuffer,\n";
$connectionContent .= "        private readonly ProtocolReader \$reader,\n";
$connectionContent .= "        private readonly ProtocolWriter \$writer,\n";
$connectionContent .= "        private readonly Channels \$channels,\n";
$connectionContent .= "        private readonly array \$options = [],\n";
$connectionContent .= "    ) {\n";
$connectionContent .= "        \$this->connection->on('data', function (string \$data): void {\n";
$connectionContent .= "            \$this->readBuffer->append(\$data);\n";
$connectionContent .= "\n";
$connectionContent .= "            while ((\$frame = \$this->reader->consumeFrame(\$this->readBuffer)) !== null) {\n";
$connectionContent .= "                \$frameInAwaitList = false;\n";
$connectionContent .= "                foreach (\$this->awaitList as \$index => \$frameHandler) {\n";
$connectionContent .= "                    if (\$frameHandler['filter'](\$frame)) {\n";
$connectionContent .= "                        unset(\$this->awaitList[\$index]);\n";
$connectionContent .= "                        \$frameHandler['promise']->resolve(\$frame);\n";
$connectionContent .= "                        \$frameInAwaitList = true;\n";
$connectionContent .= "                    }\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frameInAwaitList) {\n";
$connectionContent .= "                    continue;\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame->channel === 0) {\n";
$connectionContent .= "                    \$this->onFrameReceived(\$frame);\n";
$connectionContent .= "                    continue;\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (!\$this->channels->has(\$frame->channel)) {\n";
$connectionContent .= "                    throw new ClientException(\n";
$connectionContent .= "                        \"Received frame #{\$frame->type} on closed channel #{\$frame->channel}.\"\n";
$connectionContent .= "                    );\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                \$this->channels->get(\$frame->channel)->onFrameReceived(\$frame);\n";
$connectionContent .= "            }\n";
$connectionContent .= "        });\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    public function disconnect(int \$code, string \$reason): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$this->connectionClose(\$code, 0, 0, \$reason);\n";
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
$connectionContent .= "     *\n";
$connectionContent .= "     * @param AbstractFrame \$frame\n";
$connectionContent .= "     */\n";
$connectionContent .= "    private function onFrameReceived(AbstractFrame \$frame): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        if (\$frame instanceof MethodConnectionCloseFrame) {\n";
$connectionContent .= "            \$this->disconnect(Constants::STATUS_CONNECTION_FORCED, \"Connection closed by server: ({\$frame->replyCode}) \" . \$frame->replyText);\n";
$connectionContent .= "            throw new ClientException('Connection closed by server: ' . \$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$frame instanceof ContentHeaderFrame) {\n";
$connectionContent .= "            \$this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got header frame on connection channel (#0).');\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$frame instanceof ContentBodyFrame) {\n";
$connectionContent .= "            \$this->disconnect(Constants::STATUS_UNEXPECTED_FRAME, 'Got body frame on connection channel (#0).');\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$frame instanceof HeartbeatFrame) {\n";
$connectionContent .= "            return;\n";
$connectionContent .= "        }\n";
$connectionContent .= "\n";
$connectionContent .= "        throw new ClientException('Unhandled frame ' . get_class(\$frame) . '.');\n";
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
$connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$connectionContent .= "                    \$this->connectionCloseOk();\n";
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
$connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$connectionContent .= "                }\n";
$connectionContent .= "\n";
$connectionContent .= "                if (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$connectionContent .= "                    \$this->connectionCloseOk();\n";
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


$channelMethodsContent = "<?php\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "declare(strict_types=1);\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "namespace Bunny;\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "use Bunny\\Protocol;\n";
$channelMethodsContent .= "use React\\Promise;\n";
$channelMethodsContent .= "\n";
$channelMethodsContent .= "/**\n";
$channelMethodsContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} channel methods\n";
$channelMethodsContent .= " *\n";
$channelMethodsContent .= " * THIS CLASS IS GENERATED FROM {$specFileName}. **DO NOT EDIT!**\n";
$channelMethodsContent .= " *\n";
$channelMethodsContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$channelMethodsContent .= " */\n";
$channelMethodsContent .= "trait ChannelMethods\n";
$channelMethodsContent .= "{\n";
$channelMethodsContent .= "\n";

$channelMethodsContent .= "    /**\n";
$channelMethodsContent .= "     * Returns underlying client instance.\n";
$channelMethodsContent .= "     */\n";
$channelMethodsContent .= "    abstract public function getClient(): Connection;\n\n";

$channelMethodsContent .= "    /**\n";
$channelMethodsContent .= "     * Returns channel id.\n";
$channelMethodsContent .= "     */\n";
$channelMethodsContent .= "    abstract public function getChannelId(): int;\n\n";

foreach ($spec->classes as $class) {

    $classIdConstant = "Constants::" . dashedToUnderscores("class-" . $class->name);

    $consumeMethodFrameContent .= "if (\$classId === {$classIdConstant}) {\n";
    $consumeMethodFrameContent .= "            ";

    foreach ($class->methods as $method) {
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
        $content .= " * AMQP '{$class->name}.{$method->name}' (class #{$class->id}, method #{$method->id}) frame.\n";
        $content .= " *\n";
        $content .= " * THIS CLASS IS GENERATED FROM {$specFileName}. **DO NOT EDIT!**\n";
        $content .= " *\n";
        $content .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
        $content .= " */\n";
        $content .= "class {$className} extends MethodFrame\n";
        $content .= "{\n";
        $content .= "\n";

        $consumeContent = "                \$frame = new {$className}();\n";
        $appendContent = "";
        $clientAppendContent = "";

        $properties = "";
        $gettersSetters = "";
        $bitVars = [];
        $appendBitExpressions = [];
        $clientAppendBitExpressions = [];
        $clientArguments = [];
        $clientSetters = [];
        $channelClientArguments = ["\$this->getChannelId()"];
        $channelArguments = [];
        $hasNowait = false;

        if ($class->id !== 10) {
            $clientArguments[] = "int \$channel";
            $clientSetters[] = "\$frame->channel = \$channel;";
        }

        if (isset($method->content) && $method->content) {
            $clientArguments[] = "string \$body";
            $clientArguments[] = "array \$headers = []";

            $channelArguments[] = "string \$body";
            $channelArguments[] = "array \$headers = []";
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
                throw new \InvalidArgumentException("{$class->name}.{$method->name}({$argument->name})");
            }

            if ($argument->name === "nowait") {
                $hasNowait = true;
            }

            $name = lcfirst(dashedToCamel($argument->name));
            if ($class->id === 10 && $method->id === 50 || $class->id === 20 && $method->id === 40) {
                if ($name === "classId") {
                    $name = "closeClassId";
                } elseif ($name === "methodId") {
                    $name = "closeMethodId";
                }
            } elseif ($class->id === 40 && $method->id === 10 && $name === "type") {
                $name = "exchangeType";
            }

            if ($type === "bit") {
                if ($previousType !== "bit") {
                    $staticPayloadSize += 1;
                }

            } else {
                list($staticSize, $dynamicSize) = amqpTypeToLength($type, "\${$name}");

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
        foreach ([
            ...array_filter($method->arguments, static fn (\stdClass $argument): bool => !(isset($argument->{'default-value'}) || (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof \stdClass))),
            ...array_filter($method->arguments, static fn (\stdClass $argument): bool => isset($argument->{'default-value'}) || (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof \stdClass)),
        ] as $argument) {
            if (isset($argument->type)) {
                $type = $argument->type;
            } elseif (isset($argument->domain)) {
                $type = domainToType($argument->domain);
            } else {
                throw new \InvalidArgumentException("{$class->name}.{$method->name}({$argument->name})");
            }

            $name = lcfirst(dashedToCamel($argument->name));
            if ($class->id === 10 && $method->id === 50 || $class->id === 20 && $method->id === 40) {
                if ($name === "classId") {
                    $name = "closeClassId";
                } elseif ($name === "methodId") {
                    $name = "closeMethodId";
                }
            } elseif ($class->id === 40 && $method->id === 10 && $name === "type") {
                $name = "exchangeType";
            }
            $properties .= "    /** @var " . amqpTypeToPhpType($type) . (amqpTypeToPhpType($type) === 'array' ? '<mixed>' : '') . " */\n";
            $defaultValue = null;
            if (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof \stdClass) {
                $defaultValue = "[]";
            } elseif (isset($argument->{'default-value'})) {
                $defaultValue = var_export($argument->{'default-value'}, true);
            }
            $properties .= "    public \${$name}" . ($defaultValue !== null ? " = {$defaultValue}" : "") . ";\n\n";

            if (strpos($name, "reserved") !== 0) {
                $clientArguments[] = amqpTypeToPhpType($type) . ' $' . $name . ($defaultValue !== null ? " = {$defaultValue}" : "");
                $channelArguments[] = amqpTypeToPhpType($type) . ' $' . $name . ($defaultValue !== null ? " = {$defaultValue}" : "");
                $channelClientArguments[] = "\${$name}";
            }
        }

        foreach ($method->arguments as $argument) {
            if (isset($argument->type)) {
                $type = $argument->type;
            } elseif (isset($argument->domain)) {
                $type = domainToType($argument->domain);
            } else {
                throw new \InvalidArgumentException("{$class->name}.{$method->name}({$argument->name})");
            }

            $name = lcfirst(dashedToCamel($argument->name));
            if ($class->id === 10 && $method->id === 50 || $class->id === 20 && $method->id === 40) {
                if ($name === "classId") {
                    $name = "closeClassId";
                } elseif ($name === "methodId") {
                    $name = "closeMethodId";
                }
            } elseif ($class->id === 40 && $method->id === 10 && $name === "type") {
                $name = "exchangeType";
            }

            if ($type === "bit") {
                $bitVars[] = "\$frame->{$name}";
            } else {
                if ($previousType === "bit") {
                    $consumeContent .= "                list(" . implode(", ", $bitVars) . ") = \$this->consumeBits(\$buffer, " . count($bitVars) . ");\n";
                    $bitVars = [];
                }

                $consumeContent .= "                \$frame->{$name} = " . amqpTypeToConsume($type) . ";\n";
            }

            if ($type === "bit") {
                $appendBitExpressions[] = "\$frame->{$name}";
                $clientAppendBitExpressions[] = "\${$name}";
            } else {
                if ($previousType === "bit") {
                    $appendContent .= "            \$this->appendBits([" . implode(", ", $appendBitExpressions) . "], \$buffer);\n";
                    $appendBitExpressions = [];
                    $clientAppendContent .= "        \$this->writer->appendBits([" . implode(", ", $clientAppendBitExpressions) . "], \$buffer);\n";
                    $clientAppendBitExpressions = [];
                }
                $appendContent .= "            " . amqpTypeToAppend($type, "\$frame->{$name}") . ";\n";
                if (strpos($name, "reserved") === 0) {
                    $clientAppendContent .= "        " . amqpTypeToAppend($type, "0") . ";\n";
                } elseif ($type === "table") {
                    $clientAppendContent .= "        \$this->writer->appendTable(\${$name}, \$buffer);\n";
                } else {
                    $clientAppendContent .= "        " . amqpTypeToAppend($type, "\${$name}") . ";\n";
                }
            }

            $previousType = $type;

            if (strpos($name, "reserved") !== 0) {
                $clientSetters[] = "\$frame->{$name} = \${$name};";
            }
        }

        if ($previousType === "bit") {
            $appendContent .= "            \$this->appendBits([" . implode(", ", $appendBitExpressions) . "], \$buffer);\n";
            $appendBitExpressions = [];
            $clientAppendContent .= "        \$this->writer->appendBits([" . implode(", ", $clientAppendBitExpressions) . "], \$buffer);\n";
            $clientAppendBitExpressions = [];
        }

        if ($previousType === "bit") {
            $consumeContent .= "                list(" . implode(", ", $bitVars) . ") = \$this->consumeBits(\$buffer, " . count($bitVars) . ");\n";
            $bitVars = [];
        }

        $content .= $properties;

        $methodIdConstant = "Constants::" . dashedToUnderscores("method-" . $class->name . "-" . $method->name);

        $content .= "    public function __construct()\n";
        $content .= "    {\n";
        $content .= "        parent::__construct({$classIdConstant}, {$methodIdConstant});\n";

        if ($class->id === 10) {
            $content .= "        \$this->channel = Constants::CONNECTION_CHANNEL;\n";
        }

        $content .= "    }\n\n";
        $content .= $gettersSetters;
        $content .= "}\n";
        file_put_contents(__DIR__ . "/../src/Protocol/{$className}.php", $content);

        $consumeMethodFrameContent .= "if (\$methodId === {$methodIdConstant}) {\n";
        $consumeMethodFrameContent .= $consumeContent;
        $consumeMethodFrameContent .= "            } else";

        $appendMethodFrameContent .= "if (\$frame instanceof {$className}) {\n";
        $appendMethodFrameContent .= $appendContent;
        $appendMethodFrameContent .= "        } else";

        $methodName = dashedToCamel(($class->name !== "basic" ? $class->name . "-" : "") . $method->name);

        if (!isset($method->direction) || $method->direction === "CS") {
            $connectionContent .= "    public function " . lcfirst($methodName) . "(" . implode(", ", $clientArguments) . "): bool" . (isset($method->synchronous) && $method->synchronous ? "|Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame") : "") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "") . "\n";
            $connectionContent .= "    {\n";
            if ($static) {
                $connectionContent .= "        \$buffer = \$this->writeBuffer;\n";
                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "        \$ck = serialize([\$channel, \$headers, \$exchange, \$routingKey, \$mandatory, \$immediate]);\n";
                    $connectionContent .= "        \$c = \$this->cache[\$ck] ?? null;\n";
                    $connectionContent .= "        \$flags = \$off0 = \$len0 = \$off1 = \$len1 = 0;\n";
                    $connectionContent .= "        \$contentTypeLength = \$contentType = \$contentEncodingLength = \$contentEncoding = \$headersBuffer = \$deliveryMode = \$priority = \$correlationIdLength = \$correlationId = \$replyToLength = \$replyTo = \$expirationLength = \$expiration = \$messageIdLength = \$messageId = \$timestamp = \$typeLength = \$type = \$userIdLength = \$userId = \$appIdLength = \$appId = \$clusterIdLength = \$clusterId = null;\n";
                    $connectionContent .= "        if (\$c) { \$buffer->append(\$c[0]); }\n";
                    $connectionContent .= "        else {\n";
                    $connectionContent .= "        \$off0 = \$buffer->getLength();\n";
                }
                $connectionContent .= "        \$buffer->appendUint8(" . Constants::FRAME_METHOD . ");\n";
                $connectionContent .= "        \$buffer->appendUint16(" . ($class->id === 10 ? Constants::CONNECTION_CHANNEL : "\$channel") . ");\n";
                $connectionContent .= "        \$buffer->appendUint32(" . implode(" + ", $payloadSizeExpressions) . ");\n";
            } else {
                $connectionContent .= "        \$buffer = new Buffer();\n";
            }

            $connectionContent .= "        \$buffer->appendUint16({$class->id});\n";
            $connectionContent .= "        \$buffer->appendUint16({$method->id});\n";
            $connectionContent .= $clientAppendContent;

            if ($static) {
                $connectionContent .= "        \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";
            } else {
                $connectionContent .= "        \$frame = new Protocol\\MethodFrame({$class->id}, {$method->id});\n";
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
                $connectionContent .= "        \$s = 14;\n";


                foreach ([
                             ContentHeaderFrame::FLAG_CONTENT_TYPE => ["content-type", 1, "\$contentTypeLength = strlen(\$contentType)"],
                             ContentHeaderFrame::FLAG_CONTENT_ENCODING => ["content-encoding", 1, "\$contentEncodingLength = strlen(\$contentEncoding)"],
                             ContentHeaderFrame::FLAG_DELIVERY_MODE => ["delivery-mode", 1, null],
                             ContentHeaderFrame::FLAG_PRIORITY => ["priority", 1, null],
                             ContentHeaderFrame::FLAG_CORRELATION_ID => ["correlation-id", 1, "\$correlationIdLength = strlen(\$correlationId)"],
                             ContentHeaderFrame::FLAG_REPLY_TO => ["reply-to", 1, "\$replyToLength = strlen(\$replyTo)"],
                             ContentHeaderFrame::FLAG_EXPIRATION => ["expiration", 1, "\$expirationLength = strlen(\$expiration)"],
                             ContentHeaderFrame::FLAG_MESSAGE_ID => ["message-id", 1, "\$messageIdLength = strlen(\$messageId)"],
                             ContentHeaderFrame::FLAG_TIMESTAMP => ["timestamp", 8, null],
                             ContentHeaderFrame::FLAG_TYPE => ["type", 1, "\$typeLength = strlen(\$type)"],
                             ContentHeaderFrame::FLAG_USER_ID => ["user-id", 1, "\$userIdLength = strlen(\$userId)"],
                             ContentHeaderFrame::FLAG_APP_ID => ["app-id", 1, "\$appIdLength = strlen(\$appId)"],
                             ContentHeaderFrame::FLAG_CLUSTER_ID => ["cluster-id", 1, "\$clusterIdLength = strlen(\$clusterId)"],
                         ] as $flag => $property
                ) {
                    list($propertyName, $staticSize, $dynamicSize) = $property;
                    $connectionContent .= "        if (isset(\$headers['{$propertyName}'])) {\n";
                    $connectionContent .= "            \$flags |= {$flag};\n";
                    $connectionContent .= "            \$" . lcfirst(dashedToCamel($propertyName)) . " = \$headers['{$propertyName}'];\n";
                    if ($staticSize) {
                        $connectionContent .= "            \$s += {$staticSize};\n";
                    }
                    if ($dynamicSize) {
                        $connectionContent .= "            \$s += {$dynamicSize};\n";
                    }
                    $connectionContent .= "            unset(\$headers['{$propertyName}']);\n";
                    $connectionContent .= "        }\n";
                }

                $connectionContent .= "        if (!empty(\$headers)) {\n";
                $connectionContent .= "            \$flags |= " . ContentHeaderFrame::FLAG_HEADERS . ";\n";
                $connectionContent .= "            \$this->writer->appendTable(\$headers, \$headersBuffer = new Buffer());\n";
                $connectionContent .= "            \$s += \$headersBuffer->getLength();\n";
                $connectionContent .= "        }\n";

                $connectionContent .= "        \$buffer->appendUint8(" . Constants::FRAME_HEADER . ");\n";
                $connectionContent .= "        \$buffer->appendUint16(\$channel);\n";
                $connectionContent .= "        \$buffer->appendUint32(\$s);\n";
                $connectionContent .= "        \$buffer->appendUint16({$class->id});\n";
                $connectionContent .= "        \$buffer->appendUint16(0);\n";
                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "        \$len0 = \$buffer->getLength() - \$off0;\n";
                    $connectionContent .= "        }\n";
                }
                $connectionContent .= "        \$buffer->appendUint64(strlen(\$body));\n";
                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "        if (\$c) { \$buffer->append(\$c[1]); }\n";
                    $connectionContent .= "        else {\n";
                    $connectionContent .= "        \$off1 = \$buffer->getLength();\n";
                }
                $connectionContent .= "        \$buffer->appendUint16(\$flags);\n";

                foreach ([
                             ContentHeaderFrame::FLAG_CONTENT_TYPE => "\$buffer->appendUint8(\$contentTypeLength); \$buffer->append(\$contentType);",
                             ContentHeaderFrame::FLAG_CONTENT_ENCODING => "\$buffer->appendUint8(\$contentEncodingLength); \$buffer->append(\$contentEncoding);",
                             ContentHeaderFrame::FLAG_HEADERS => "\$buffer->append(\$headersBuffer);",
                             ContentHeaderFrame::FLAG_DELIVERY_MODE => "\$buffer->appendUint8(\$deliveryMode);",
                             ContentHeaderFrame::FLAG_PRIORITY => "\$buffer->appendUint8(\$priority);",
                             ContentHeaderFrame::FLAG_CORRELATION_ID => "\$buffer->appendUint8(\$correlationIdLength); \$buffer->append(\$correlationId);",
                             ContentHeaderFrame::FLAG_REPLY_TO => "\$buffer->appendUint8(\$replyToLength); \$buffer->append(\$replyTo);",
                             ContentHeaderFrame::FLAG_EXPIRATION => "\$buffer->appendUint8(\$expirationLength); \$buffer->append(\$expiration);",
                             ContentHeaderFrame::FLAG_MESSAGE_ID => "\$buffer->appendUint8(\$messageIdLength); \$buffer->append(\$messageId);",
                             ContentHeaderFrame::FLAG_TIMESTAMP => "\$this->writer->appendTimestamp(\$timestamp, \$buffer);",
                             ContentHeaderFrame::FLAG_TYPE => "\$buffer->appendUint8(\$typeLength); \$buffer->append(\$type);",
                             ContentHeaderFrame::FLAG_USER_ID => "\$buffer->appendUint8(\$userIdLength); \$buffer->append(\$userId);",
                             ContentHeaderFrame::FLAG_APP_ID => "\$buffer->appendUint8(\$appIdLength); \$buffer->append(\$appId);",
                             ContentHeaderFrame::FLAG_CLUSTER_ID => "\$buffer->appendUint8(\$clusterIdLength); \$buffer->append(\$clusterId);",
                         ] as $flag => $property
                ) {
                    $connectionContent .= "        if (\$flags & {$flag}) {\n";
                    $connectionContent .= "            {$property}\n";
                    $connectionContent .= "        }\n";
                }

                $connectionContent .= "        \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";

                if ($class->id === 60 && $method->id === 40) {
                    $connectionContent .= "        \$len1 = \$buffer->getLength() - \$off1;\n";
                    $connectionContent .= "        }\n";
                    $connectionContent .= "        if (!\$c) {\n";
                    $connectionContent .= "            \$this->cache[\$ck] = [\$buffer->read(\$len0, \$off0), \$buffer->read(\$len1, \$off1)];\n";
                    $connectionContent .= "            if (count(\$this->cache) > 100) { reset(\$this->cache); unset(\$this->cache[key(\$this->cache)]); }\n";
                    $connectionContent .= "        }\n";
                }

                $connectionContent .= "        for (\$payloadMax = \$this->client->frameMax - 8 /* frame preface and frame end */, \$i = 0, \$l = strlen(\$body); \$i < \$l; \$i += \$payloadMax) {\n";
                $connectionContent .= "            \$payloadSize = \$l - \$i; if (\$payloadSize > \$payloadMax) { \$payloadSize = \$payloadMax; }\n";
                $connectionContent .= "            \$buffer->appendUint8(" . Constants::FRAME_BODY . ");\n";
                $connectionContent .= "            \$buffer->appendUint16(\$channel);\n";
                $connectionContent .= "            \$buffer->appendUint32(\$payloadSize);\n";
                $connectionContent .= "            \$buffer->append(substr(\$body, \$i, \$payloadSize));\n";
                $connectionContent .= "            \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";
                $connectionContent .= "        }\n";
            }

            if (isset($method->synchronous) && $method->synchronous && $hasNowait) {
                $connectionContent .= "        \$this->flushWriteBuffer();\n";
                $connectionContent .= "        if (!\$nowait) {\n";
                $connectionContent .= "            return \$this->await" . $methodName . "Ok(" . ($class->id !== 10 ? "\$channel" : "") . ");\n";
                $connectionContent .= "        }\n";
                $connectionContent .= "        return false;\n";
            } elseif (isset($method->synchronous) && $method->synchronous) {
                $connectionContent .= "        \$this->flushWriteBuffer();\n";
                $connectionContent .= "        return \$this->await" . $methodName . "Ok(" . ($class->id !== 10 ? "\$channel" : "") . ");\n";
            } else {
                $connectionContent .= "        \$this->flushWriteBuffer();\n";
                $connectionContent .= "        return false;\n";
            }

            $connectionContent .= "    }\n\n";
        }

        if (!isset($method->direction) || $method->direction === "SC") {
            $connectionContent .= "    public function await" . $methodName . "(" . ($class->id !== 10 ? "int \$channel" : "") . "): Protocol\\{$className}" . ($class->id === 60 && $method->id === 71 ? '|Protocol\\' . str_replace("GetOk", "GetEmpty", $className) : "") . "\n";
            $connectionContent .= "    {\n";

            // async await
            $connectionContent .= "        \$deferred = new Deferred();\n";
            $connectionContent .= "        \$this->awaitList[] = [\n";
            $connectionContent .= "            'filter' => function (Protocol\\AbstractFrame \$frame)" . ($class->id !== 10 ? " use (\$channel)" : "") . ": bool {\n";
            $connectionContent .= "                if (\$frame instanceof Protocol\\{$className}" . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
            $connectionContent .= "                    return true;\n";
            $connectionContent .= "                }\n";
            $connectionContent .= "\n";

            if ($class->id === 60 && $method->id === 71) {
                $connectionContent .= "                if (\$frame instanceof Protocol\\" . str_replace("GetOk", "GetEmpty", $className) . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
                $connectionContent .= "                    return true;\n";
                $connectionContent .= "                }\n";
                $connectionContent .= "\n";
            }

            if ($class->id !== 10) {
                $connectionContent .= "                if (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
                $connectionContent .= "                    \$this->channelCloseOk(\$channel);\n";
                $connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
                $connectionContent .= "                }\n";
                $connectionContent .= "\n";
            }

            $connectionContent .= "                if (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
            $connectionContent .= "                    \$this->connectionCloseOk();\n";
            $connectionContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
            $connectionContent .= "                }\n";
            $connectionContent .= "\n";
            $connectionContent .= "                return false;\n";
            $connectionContent .= "          },\n";
            $connectionContent .= "          'promise' => \$deferred,\n";
            $connectionContent .= "        ];\n";
            $connectionContent .= "        return await(\$deferred->promise());\n";
            $connectionContent .= "    }\n\n";
        }

        if ($class->id !== 10 &&
            $class->id !== 20 &&
            $class->id !== 30 &&
            (!isset($method->direction) || $method->direction === "CS")
        ) {
            $channelMethodsContent .= "    /**\n";
            $channelMethodsContent .= "     * Calls {$class->name}.{$method->name} AMQP method.\n";
            $channelMethodsContent .= "     */\n";
            $channelMethodsContent .= "    public function " . lcfirst($methodName) . "(" . implode(", ", $channelArguments) . "): bool" . (isset($method->synchronous) && $method->synchronous ? "|Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame") : "") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "") . "\n";
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

$consumeMethodFrameContent .= " {\n";
$consumeMethodFrameContent .= "            throw new InvalidClassException(\$classId);\n";
$consumeMethodFrameContent .= "        }\n\n";
$consumeMethodFrameContent .= "        \$frame->classId = \$classId;\n";
$consumeMethodFrameContent .= "        \$frame->methodId = \$methodId;\n";
$consumeMethodFrameContent .= "\n";
$consumeMethodFrameContent .= "        return \$frame;\n";
$consumeMethodFrameContent .= "    }\n\n";

$protocolReaderContent .= $consumeMethodFrameContent;
$protocolReaderContent .= "}\n";
file_put_contents(__DIR__ . "/../src/Protocol/ProtocolReaderGenerated.php", $protocolReaderContent);

$appendMethodFrameContent .= " {\n";
$appendMethodFrameContent .= "            throw new ProtocolException('Unhandled method frame ' . get_class(\$frame) . '.');\n";
$appendMethodFrameContent .= "        }\n";
$appendMethodFrameContent .= "    }\n\n";

$protocolWriterContent .= $appendMethodFrameContent;
$protocolWriterContent .= "}\n";
file_put_contents(__DIR__ . "/../src/Protocol/ProtocolWriterGenerated.php", $protocolWriterContent);

$connectionContent .= "    public function startHeartbeatTimer(): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$this->heartbeatTimer = Loop::addTimer(\$this->options['heartbeat'], [\$this, 'onHeartbeat']);\n";
$connectionContent .= "        \$this->connection->on('drain', [\$this, 'onHeartbeat']);\n";
$connectionContent .= "    }\n";
$connectionContent .= "\n";
$connectionContent .= "    /**\n";
$connectionContent .= "     * Callback when heartbeat timer timed out.\n";
$connectionContent .= "     */\n";
$connectionContent .= "    public function onHeartbeat(): void\n";
$connectionContent .= "    {\n";
$connectionContent .= "        \$now = microtime(true);\n";
$connectionContent .= "        \$nextHeartbeat = (\$this->lastWrite ?: \$now) + \$this->options['heartbeat'];\n";
$connectionContent .= "\n";
$connectionContent .= "        if (\$now >= \$nextHeartbeat) {\n";
$connectionContent .= "            \$this->writer->appendFrame(new HeartbeatFrame(), \$this->writeBuffer);\n";
$connectionContent .= "            \$this->flushWriteBuffer();\n";
$connectionContent .= "\n";
$connectionContent .= "            \$this->heartbeatTimer = Loop::addTimer(\$this->options['heartbeat'], [\$this, 'onHeartbeat']);\n";
$connectionContent .= "            if (is_callable(\$this->options['heartbeat_callback'] ?? null)) {\n";
$connectionContent .= "                \$this->options['heartbeat_callback'](\$this);\n";
$connectionContent .= "            }\n";
$connectionContent .= "        } else {\n";
$connectionContent .= "            \$this->heartbeatTimer = Loop::addTimer(\$nextHeartbeat - \$now, [\$this, 'onHeartbeat']);\n";
$connectionContent .= "        }\n";
$connectionContent .= "    }\n";
$connectionContent .= "}\n";
file_put_contents(__DIR__ . "/../src/Connection.php", $connectionContent);

$channelMethodsContent .= "}\n";
file_put_contents(__DIR__ . "/../src/ChannelMethods.php", $channelMethodsContent);
