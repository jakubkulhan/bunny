<?php
namespace Bunny;

use Bunny\Protocol\ContentHeaderFrame;

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
        return "boolean";
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
$protocolReaderContent .= "    abstract public function consumeTable(Buffer \$originalBuffer);\n\n";
$protocolReaderContent .= "    /**\n";
$protocolReaderContent .= "     * Consumes packed bits from buffer.\n";
$protocolReaderContent .= "     *\n";
$protocolReaderContent .= "     * @param Buffer \$buffer\n";
$protocolReaderContent .= "     * @param int \$n\n";
$protocolReaderContent .= "     * @return array\n";
$protocolReaderContent .= "     */\n";
$protocolReaderContent .= "    abstract public function consumeBits(Buffer \$buffer, \$n);\n\n";

$consumeMethodFrameContent = "";
$consumeMethodFrameContent .= "    /**\n";
$consumeMethodFrameContent .= "     * Consumes AMQP method frame.\n";
$consumeMethodFrameContent .= "     *\n";
$consumeMethodFrameContent .= "     * @param Buffer \$buffer\n";
$consumeMethodFrameContent .= "     * @return MethodFrame\n";
$consumeMethodFrameContent .= "     */\n";
$consumeMethodFrameContent .= "    public function consumeMethodFrame(Buffer \$buffer)\n";
$consumeMethodFrameContent .= "    {\n";
$consumeMethodFrameContent .= "        \$classId = \$buffer->consumeUint16();\n";
$consumeMethodFrameContent .= "        \$methodId = \$buffer->consumeUint16();\n";
$consumeMethodFrameContent .= "\n";
$consumeMethodFrameContent .= "        ";

$protocolWriterContent = "<?php\n";
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
$protocolWriterContent .= "    public function appendProtocolHeader(Buffer \$buffer)\n";
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
$appendMethodFrameContent .= "    public function appendMethodFrame(MethodFrame \$frame, Buffer \$buffer)\n";
$appendMethodFrameContent .= "    {\n";
$appendMethodFrameContent .= "        \$buffer->appendUint16(\$frame->classId);\n";
$appendMethodFrameContent .= "        \$buffer->appendUint16(\$frame->methodId);\n";
$appendMethodFrameContent .= "\n";
$appendMethodFrameContent .= "        ";

$clientMethodsContent = "<?php\n";
$clientMethodsContent .= "namespace Bunny;\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "use Bunny\\Exception\\ClientException;\n";
$clientMethodsContent .= "use Bunny\\Protocol;\n";
$clientMethodsContent .= "use Bunny\\Protocol\\Buffer;\n";
$clientMethodsContent .= "use React\\Promise\\Deferred;\n";
$clientMethodsContent .= "use React\\Promise\\PromiseInterface;\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "/**\n";
$clientMethodsContent .= " * AMQP-{$spec->{'major-version'}}-{$spec->{'minor-version'}}-{$spec->{'revision'}} client methods\n";
$clientMethodsContent .= " *\n";
$clientMethodsContent .= " * THIS CLASS IS GENERATED FROM {$specFileName}. **DO NOT EDIT!**\n";
$clientMethodsContent .= " *\n";
$clientMethodsContent .= " * @author Jakub Kulhan <jakub.kulhan@gmail.com>\n";
$clientMethodsContent .= " */\n";
$clientMethodsContent .= "trait ClientMethods\n";
$clientMethodsContent .= "{\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /** @var array */\n";
$clientMethodsContent .= "    private \$cache = [];\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Returns AMQP protocol reader.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return Protocol\\ProtocolReader\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function getReader();\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Returns read buffer.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return Buffer\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function getReadBuffer();\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Returns AMQP protocol writer.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return Protocol\\ProtocolWriter\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function getWriter();\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Returns write buffer.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return Buffer\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function getWriteBuffer();\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Reads data from stream to read buffer.\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function feedReadBuffer();\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Writes all data from write buffer to stream.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return boolean|PromiseInterface\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function flushWriteBuffer();\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Enqueues given frame for later processing.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @param Protocol\\AbstractFrame \$frame\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function enqueue(Protocol\\AbstractFrame \$frame);\n";
$clientMethodsContent .= "\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * Returns frame max size.\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return int\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    abstract protected function getFrameMax();\n";
$clientMethodsContent .= "\n";


$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * @param int \$channel\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return Protocol\\ContentHeaderFrame|PromiseInterface\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    public function awaitContentHeader(\$channel)\n";
$clientMethodsContent .= "    {\n";
$clientMethodsContent .= "        if (\$this instanceof Async\\Client) {\n";
$clientMethodsContent .= "            \$deferred = new Deferred();\n";
$clientMethodsContent .= "            \$this->addAwaitCallback(function (\$frame) use (\$deferred, \$channel) {\n";
$clientMethodsContent .= "                if (\$frame instanceof Protocol\\ContentHeaderFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    \$deferred->resolve(\$frame);\n";
$clientMethodsContent .= "                    return true;\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    \$this->channelCloseOk(\$channel)->done(function () use (\$frame, \$deferred) {\n";
$clientMethodsContent .= "                        \$deferred->reject(new ClientException(\$frame->replyText, \$frame->replyCode));\n";
$clientMethodsContent .= "                    });\n";
$clientMethodsContent .= "                    return true;\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$clientMethodsContent .= "                    \$this->connectionCloseOk()->done(function () use (\$frame, \$deferred) {\n";
$clientMethodsContent .= "                        \$deferred->reject(new ClientException(\$frame->replyText, \$frame->replyCode));\n";
$clientMethodsContent .= "                    });\n";
$clientMethodsContent .= "                    return true;\n";
$clientMethodsContent .= "                }\n";
$clientMethodsContent .= "                return false;\n";
$clientMethodsContent .= "            });\n";
$clientMethodsContent .= "            return \$deferred->promise();\n";
$clientMethodsContent .= "        } else {\n";
$clientMethodsContent .= "            for (;;) {\n";
$clientMethodsContent .= "                while ((\$frame = \$this->getReader()->consumeFrame(\$this->getReadBuffer())) === null) {\n";
$clientMethodsContent .= "                    \$this->feedReadBuffer();\n";
$clientMethodsContent .= "                }\n";
$clientMethodsContent .= "                if (\$frame instanceof Protocol\\ContentHeaderFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    return \$frame;\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    \$this->channelCloseOk(\$channel);\n";
$clientMethodsContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$clientMethodsContent .= "                    \$this->connectionCloseOk();\n";
$clientMethodsContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$clientMethodsContent .= "                } else {\n";
$clientMethodsContent .= "                    \$this->enqueue(\$frame);\n";
$clientMethodsContent .= "                }\n";
$clientMethodsContent .= "            }\n";
$clientMethodsContent .= "        }\n";
$clientMethodsContent .= "        throw new \\LogicException('This statement should be never reached.');\n";
$clientMethodsContent .= "    }\n\n";
$clientMethodsContent .= "    /**\n";
$clientMethodsContent .= "     * @param int \$channel\n";
$clientMethodsContent .= "     *\n";
$clientMethodsContent .= "     * @return Protocol\\ContentBodyFrame|PromiseInterface\n";
$clientMethodsContent .= "     */\n";
$clientMethodsContent .= "    public function awaitContentBody(\$channel)\n";
$clientMethodsContent .= "    {\n";
$clientMethodsContent .= "        if (\$this instanceof Async\\Client) {\n";
$clientMethodsContent .= "            \$deferred = new Deferred();\n";
$clientMethodsContent .= "            \$this->addAwaitCallback(function (\$frame) use (\$deferred, \$channel) {\n";
$clientMethodsContent .= "                if (\$frame instanceof Protocol\\ContentBodyFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    \$deferred->resolve(\$frame);\n";
$clientMethodsContent .= "                    return true;\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    \$this->channelCloseOk(\$channel)->done(function () use (\$frame, \$deferred) {\n";
$clientMethodsContent .= "                        \$deferred->reject(new ClientException(\$frame->replyText, \$frame->replyCode));\n";
$clientMethodsContent .= "                    });\n";
$clientMethodsContent .= "                    return true;\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$clientMethodsContent .= "                    \$this->connectionCloseOk()->done(function () use (\$frame, \$deferred) {\n";
$clientMethodsContent .= "                        \$deferred->reject(new ClientException(\$frame->replyText, \$frame->replyCode));\n";
$clientMethodsContent .= "                    });\n";
$clientMethodsContent .= "                    return true;\n";
$clientMethodsContent .= "                }\n";
$clientMethodsContent .= "                return false;\n";
$clientMethodsContent .= "            });\n";
$clientMethodsContent .= "            return \$deferred->promise();\n";
$clientMethodsContent .= "        } else {\n";
$clientMethodsContent .= "            for (;;) {\n";
$clientMethodsContent .= "                while ((\$frame = \$this->getReader()->consumeFrame(\$this->getReadBuffer())) === null) {\n";
$clientMethodsContent .= "                    \$this->feedReadBuffer();\n";
$clientMethodsContent .= "                }\n";
$clientMethodsContent .= "                if (\$frame instanceof Protocol\\ContentBodyFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    return \$frame;\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
$clientMethodsContent .= "                    \$this->channelCloseOk(\$channel);\n";
$clientMethodsContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
$clientMethodsContent .= "                    \$this->connectionCloseOk();\n";
$clientMethodsContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
$clientMethodsContent .= "                } else {\n";
$clientMethodsContent .= "                    \$this->enqueue(\$frame);\n";
$clientMethodsContent .= "                }\n";
$clientMethodsContent .= "            }\n";
$clientMethodsContent .= "        }\n";
$clientMethodsContent .= "        throw new \\LogicException('This statement should be never reached.');\n";
$clientMethodsContent .= "    }\n\n";

$channelMethodsContent = "<?php\n";
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
$channelMethodsContent .= "     * \n";
$channelMethodsContent .= "     * @return AbstractClient\n";
$channelMethodsContent .= "     */\n";
$channelMethodsContent .= "    abstract public function getClient();\n\n";

$channelMethodsContent .= "    /**\n";
$channelMethodsContent .= "     * Returns channel id.\n";
$channelMethodsContent .= "     * \n";
$channelMethodsContent .= "     * @return int\n";
$channelMethodsContent .= "     */\n";
$channelMethodsContent .= "    abstract public function getChannelId();\n\n";

foreach ($spec->classes as $class) {

    $classIdConstant = "Constants::" . dashedToUnderscores("class-" . $class->name);

    $consumeMethodFrameContent .= "if (\$classId === {$classIdConstant}) {\n";
    $consumeMethodFrameContent .= "            ";

    foreach ($class->methods as $method) {
        $className = "Method" . ucfirst($class->name) . dashedToCamel($method->name) . "Frame";
        $content = "<?php\n";
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
        $channelDocComment = "";
        $hasNowait = false;

        if ($class->id !== 10) {
            $clientArguments[] = "\$channel";
            $clientSetters[] = "\$frame->channel = \$channel;";
        }

        if (isset($method->content) && $method->content) {
            $clientArguments[] = "\$body";
            $clientArguments[] = "array \$headers = []";

            $channelArguments[] = "\$body";
            $channelArguments[] = "array \$headers = []";
            $channelDocComment .= "     * @param string \$body\n";
            $channelDocComment .= "     * @param array \$headers\n";
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
            $properties .= "    /** @var " . amqpTypeToPhpType($type) . " */\n";
            $defaultValue = null;
            if (isset($argument->{'default-value'}) && $argument->{'default-value'} instanceof \stdClass) {
                $defaultValue = "[]";
            } elseif (isset($argument->{'default-value'})) {
                $defaultValue = var_export($argument->{'default-value'}, true);
            }
            $properties .= "    public \${$name}" . ($defaultValue !== null ? " = {$defaultValue}" : "") . ";\n\n";

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
                    $clientAppendContent .= "        \$this->getWriter()->appendBits([" . implode(", ", $clientAppendBitExpressions) . "], \$buffer);\n";
                    $clientAppendBitExpressions = [];
                }
                $appendContent .= "            " . amqpTypeToAppend($type, "\$frame->{$name}") . ";\n";
                if (strpos($name, "reserved") === 0) {
                    $clientAppendContent .= "        " . amqpTypeToAppend($type, "0") . ";\n";
                } elseif ($type === "table") {
                    $clientAppendContent .= "        \$this->getWriter()->appendTable(\${$name}, \$buffer);\n";
                } else {
                    $clientAppendContent .= "        " . amqpTypeToAppend($type, "\${$name}") . ";\n";
                }
            }

            $previousType = $type;

            if (strpos($name, "reserved") !== 0) {
                $clientArguments[] = "\${$name}" . ($defaultValue !== null ? " = {$defaultValue}" : "");
                $clientSetters[] = "\$frame->{$name} = \${$name};";

                $channelArguments[] = "\${$name}" . ($defaultValue !== null ? " = {$defaultValue}" : "");
                $channelDocComment .= "     * @param " . amqpTypeToPhpType($type) . " \${$name}\n";
                $channelClientArguments[] = "\${$name}";
            }
        }

        if ($previousType === "bit") {
            $appendContent .= "            \$this->appendBits([" . implode(", ", $appendBitExpressions) . "], \$buffer);\n";
            $appendBitExpressions = [];
            $clientAppendContent .= "        \$this->getWriter()->appendBits([" . implode(", ", $clientAppendBitExpressions) . "], \$buffer);\n";
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
        file_put_contents(__DIR__ . "/../src/Bunny/Protocol/{$className}.php", $content);

        $consumeMethodFrameContent .= "if (\$methodId === {$methodIdConstant}) {\n";
        $consumeMethodFrameContent .= $consumeContent;
        $consumeMethodFrameContent .= "            } else";

        $appendMethodFrameContent .= "if (\$frame instanceof {$className}) {\n";
        $appendMethodFrameContent .= $appendContent;
        $appendMethodFrameContent .= "        } else";

        $methodName = dashedToCamel(($class->name !== "basic" ? $class->name . "-" : "") . $method->name);

        if (!isset($method->direction) || $method->direction === "CS") {
            $clientMethodsContent .= "    public function " . lcfirst($methodName) . "(" . implode(", ", $clientArguments) . ")\n";
            $clientMethodsContent .= "    {\n";
            if ($static) {
                $clientMethodsContent .= "        \$buffer = \$this->getWriteBuffer();\n";
                if ($class->id === 60 && $method->id === 40) {
                    $clientMethodsContent .= "        \$ck = serialize([\$channel, \$headers, \$exchange, \$routingKey, \$mandatory, \$immediate]);\n";
                    $clientMethodsContent .= "        \$c = isset(\$this->cache[\$ck]) ? \$this->cache[\$ck] : null;\n";
                    $clientMethodsContent .= "        \$flags = 0; \$off0 = 0; \$len0 = 0; \$off1 = 0; \$len1 = 0; \$contentTypeLength = null; \$contentType = null; \$contentEncodingLength = null; \$contentEncoding = null; \$headersBuffer = null; \$deliveryMode = null; \$priority = null; \$correlationIdLength = null; \$correlationId = null; \$replyToLength = null; \$replyTo = null; \$expirationLength = null; \$expiration = null; \$messageIdLength = null; \$messageId = null; \$timestamp = null; \$typeLength = null; \$type = null; \$userIdLength = null; \$userId = null; \$appIdLength = null; \$appId = null; \$clusterIdLength = null; \$clusterId = null;\n";
                    $clientMethodsContent .= "        if (\$c) { \$buffer->append(\$c[0]); }\n";
                    $clientMethodsContent .= "        else {\n";
                    $clientMethodsContent .= "        \$off0 = \$buffer->getLength();\n";
                }
                $clientMethodsContent .= "        \$buffer->appendUint8(" . Constants::FRAME_METHOD . ");\n";
                $clientMethodsContent .= "        \$buffer->appendUint16(" . ($class->id === 10 ? Constants::CONNECTION_CHANNEL : "\$channel") . ");\n";
                $clientMethodsContent .= "        \$buffer->appendUint32(" . implode(" + ", $payloadSizeExpressions) . ");\n";
            } else {
                $clientMethodsContent .= "        \$buffer = new Buffer();\n";
            }

            $clientMethodsContent .= "        \$buffer->appendUint16({$class->id});\n";
            $clientMethodsContent .= "        \$buffer->appendUint16({$method->id});\n";
            $clientMethodsContent .= $clientAppendContent;

            if ($static) {
                $clientMethodsContent .= "        \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";
            } else {
                $clientMethodsContent .= "        \$frame = new Protocol\\MethodFrame({$class->id}, {$method->id});\n";
                $clientMethodsContent .= "        \$frame->channel = " . ($class->id === 10 ? Constants::CONNECTION_CHANNEL : "\$channel") . ";\n";
                $clientMethodsContent .= "        \$frame->payloadSize = \$buffer->getLength();\n";
                $clientMethodsContent .= "        \$frame->payload = \$buffer;\n";
                $clientMethodsContent .= "        \$this->getWriter()->appendFrame(\$frame, \$this->getWriteBuffer());\n";
            }

            if (isset($method->content) && $method->content) {
                if (!$static) {
                    $clientMethodsContent .= "        \$buffer = \$this->getWriteBuffer();\n";
                }

                // FIXME: respect max body size agreed upon connection.tune
                $clientMethodsContent .= "        \$s = 14;\n";


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
                    $clientMethodsContent .= "        if (isset(\$headers['{$propertyName}'])) {\n";
                    $clientMethodsContent .= "            \$flags |= {$flag};\n";
                    $clientMethodsContent .= "            \$" . lcfirst(dashedToCamel($propertyName)) . " = \$headers['{$propertyName}'];\n";
                    if ($staticSize) {
                        $clientMethodsContent .= "            \$s += {$staticSize};\n";
                    }
                    if ($dynamicSize) {
                        $clientMethodsContent .= "            \$s += {$dynamicSize};\n";
                    }
                    $clientMethodsContent .= "            unset(\$headers['{$propertyName}']);\n";
                    $clientMethodsContent .= "        }\n";
                }

                $clientMethodsContent .= "        if (!empty(\$headers)) {\n";
                $clientMethodsContent .= "            \$flags |= " . ContentHeaderFrame::FLAG_HEADERS . ";\n";
                $clientMethodsContent .= "            \$this->getWriter()->appendTable(\$headers, \$headersBuffer = new Buffer());\n";
                $clientMethodsContent .= "            \$s += \$headersBuffer->getLength();\n";
                $clientMethodsContent .= "        }\n";

                $clientMethodsContent .= "        \$buffer->appendUint8(" . Constants::FRAME_HEADER . ");\n";
                $clientMethodsContent .= "        \$buffer->appendUint16(\$channel);\n";
                $clientMethodsContent .= "        \$buffer->appendUint32(\$s);\n";
                $clientMethodsContent .= "        \$buffer->appendUint16({$class->id});\n";
                $clientMethodsContent .= "        \$buffer->appendUint16(0);\n";
                if ($class->id === 60 && $method->id === 40) {
                    $clientMethodsContent .= "        \$len0 = \$buffer->getLength() - \$off0;\n";
                    $clientMethodsContent .= "        }\n";
                }
                $clientMethodsContent .= "        \$buffer->appendUint64(strlen(\$body));\n";
                if ($class->id === 60 && $method->id === 40) {
                    $clientMethodsContent .= "        if (\$c) { \$buffer->append(\$c[1]); }\n";
                    $clientMethodsContent .= "        else {\n";
                    $clientMethodsContent .= "        \$off1 = \$buffer->getLength();\n";
                }
                $clientMethodsContent .= "        \$buffer->appendUint16(\$flags);\n";

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
                             ContentHeaderFrame::FLAG_TIMESTAMP => "\$this->getWriter()->appendTimestamp(\$timestamp, \$buffer);",
                             ContentHeaderFrame::FLAG_TYPE => "\$buffer->appendUint8(\$typeLength); \$buffer->append(\$type);",
                             ContentHeaderFrame::FLAG_USER_ID => "\$buffer->appendUint8(\$userIdLength); \$buffer->append(\$userId);",
                             ContentHeaderFrame::FLAG_APP_ID => "\$buffer->appendUint8(\$appIdLength); \$buffer->append(\$appId);",
                             ContentHeaderFrame::FLAG_CLUSTER_ID => "\$buffer->appendUint8(\$clusterIdLength); \$buffer->append(\$clusterId);",
                         ] as $flag => $property
                ) {
                    $clientMethodsContent .= "        if (\$flags & {$flag}) {\n";
                    $clientMethodsContent .= "            {$property}\n";
                    $clientMethodsContent .= "        }\n";
                }

                $clientMethodsContent .= "        \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";

                if ($class->id === 60 && $method->id === 40) {
                    $clientMethodsContent .= "        \$len1 = \$buffer->getLength() - \$off1;\n";
                    $clientMethodsContent .= "        }\n";
                    $clientMethodsContent .= "        if (!\$c) {\n";
                    $clientMethodsContent .= "            \$this->cache[\$ck] = [\$buffer->read(\$len0, \$off0), \$buffer->read(\$len1, \$off1)];\n";
                    $clientMethodsContent .= "            if (count(\$this->cache) > 100) { reset(\$this->cache); unset(\$this->cache[key(\$this->cache)]); }\n";
                    $clientMethodsContent .= "        }\n";
                }

                $clientMethodsContent .= "        for (\$payloadMax = \$this->getFrameMax() - 8 /* frame preface and frame end */, \$i = 0, \$l = strlen(\$body); \$i < \$l; \$i += \$payloadMax) {\n";
                $clientMethodsContent .= "            \$payloadSize = \$l - \$i; if (\$payloadSize > \$payloadMax) { \$payloadSize = \$payloadMax; }\n";
                $clientMethodsContent .= "            \$buffer->appendUint8(" . Constants::FRAME_BODY . ");\n";
                $clientMethodsContent .= "            \$buffer->appendUint16(\$channel);\n";
                $clientMethodsContent .= "            \$buffer->appendUint32(\$payloadSize);\n";
                $clientMethodsContent .= "            \$buffer->append(substr(\$body, \$i, \$payloadSize));\n";
                $clientMethodsContent .= "            \$buffer->appendUint8(" . Constants::FRAME_END . ");\n";
                $clientMethodsContent .= "        }\n";
            }

            if (isset($method->synchronous) && $method->synchronous && $hasNowait) {
                $clientMethodsContent .= "        if (\$nowait) {\n";
                $clientMethodsContent .= "            return \$this->flushWriteBuffer();\n";
                $clientMethodsContent .= "        } else {\n";
                $clientMethodsContent .= "            \$this->flushWriteBuffer();\n";
                $clientMethodsContent .= "            return \$this->await" . $methodName . "Ok(" . ($class->id !== 10 ? "\$channel" : "") . ");\n";
                $clientMethodsContent .= "        }\n";
            } elseif (isset($method->synchronous) && $method->synchronous) {
                $clientMethodsContent .= "        \$this->flushWriteBuffer();\n";
                $clientMethodsContent .= "        return \$this->await" . $methodName . "Ok(" . ($class->id !== 10 ? "\$channel" : "") . ");\n";
            } else {
                $clientMethodsContent .= "        return \$this->flushWriteBuffer();\n";
            }

            $clientMethodsContent .= "    }\n\n";
        }

        if (!isset($method->direction) || $method->direction === "SC") {
            $clientMethodsContent .= "    /**\n";
            if ($class->id !== 10) {
                $clientMethodsContent .= "     * @param int \$channel\n";
                $clientMethodsContent .= "     *\n";
            }
            $clientMethodsContent .= "     * @return Protocol\\{$className}" . ($class->id === 60 && $method->id === 71 ? "|Protocol\\" . str_replace("GetOk", "GetEmpty", $className) : "") . "|PromiseInterface\n";
            $clientMethodsContent .= "     */\n";
            $clientMethodsContent .= "    public function await" . $methodName . "(" . ($class->id !== 10 ? "\$channel" : "") . ")\n";
            $clientMethodsContent .= "    {\n";

            // async await
            $clientMethodsContent .= "        if (\$this instanceof Async\\Client) {\n";
            $clientMethodsContent .= "            \$deferred = new Deferred();\n";
            $clientMethodsContent .= "            \$this->addAwaitCallback(function (\$frame) use (\$deferred" . ($class->id !== 10 ? ", \$channel" : "") . ") {\n";
            $clientMethodsContent .= "                if (\$frame instanceof Protocol\\{$className}" . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
            $clientMethodsContent .= "                    \$deferred->resolve(\$frame);\n";
            $clientMethodsContent .= "                    return true;\n";
            if ($class->id === 60 && $method->id === 71) {
                $clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\" . str_replace("GetOk", "GetEmpty", $className) . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
                $clientMethodsContent .= "                    \$deferred->resolve(\$frame);\n";
                $clientMethodsContent .= "                    return true;\n";
            }
            if ($class->id !== 10) {
                $clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
                $clientMethodsContent .= "                    \$this->channelCloseOk(\$channel)->done(function () use (\$frame, \$deferred) {\n";
                $clientMethodsContent .= "                        \$deferred->reject(new ClientException(\$frame->replyText, \$frame->replyCode));\n";
                $clientMethodsContent .= "                    });\n";
                $clientMethodsContent .= "                    return true;\n";
            }
            $clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
            $clientMethodsContent .= "                    \$this->connectionCloseOk()->done(function () use (\$frame, \$deferred) {\n";
            $clientMethodsContent .= "                        \$deferred->reject(new ClientException(\$frame->replyText, \$frame->replyCode));\n";
            $clientMethodsContent .= "                    });\n";
            $clientMethodsContent .= "                    return true;\n";
            $clientMethodsContent .= "                }\n";
            $clientMethodsContent .= "                return false;\n";
            $clientMethodsContent .= "            });\n";
            $clientMethodsContent .= "            return \$deferred->promise();\n";
            $clientMethodsContent .= "        } else {\n";

            // sync await
            $clientMethodsContent .= "            for (;;) {\n";
            $clientMethodsContent .= "                while ((\$frame = \$this->getReader()->consumeFrame(\$this->getReadBuffer())) === null) {\n";
            $clientMethodsContent .= "                    \$this->feedReadBuffer();\n";
            $clientMethodsContent .= "                }\n";
            $clientMethodsContent .= "                if (\$frame instanceof Protocol\\{$className}" . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
            $clientMethodsContent .= "                    return \$frame;\n";
            if ($class->id === 60 && $method->id === 71) {
                $clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\" . str_replace("GetOk", "GetEmpty", $className) . ($class->id !== 10 ? " && \$frame->channel === \$channel" : "") . ") {\n";
                $clientMethodsContent .= "                    return \$frame;\n";
            }
            if ($class->id !== 10) {
                $clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodChannelCloseFrame && \$frame->channel === \$channel) {\n";
                $clientMethodsContent .= "                    \$this->channelCloseOk(\$channel);\n";
                $clientMethodsContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
            }
            $clientMethodsContent .= "                } elseif (\$frame instanceof Protocol\\MethodConnectionCloseFrame) {\n";
            $clientMethodsContent .= "                    \$this->connectionCloseOk();\n";
            $clientMethodsContent .= "                    throw new ClientException(\$frame->replyText, \$frame->replyCode);\n";
            $clientMethodsContent .= "                } else {\n";
            $clientMethodsContent .= "                    \$this->enqueue(\$frame);\n";
            $clientMethodsContent .= "                }\n";
            $clientMethodsContent .= "            }\n";
            $clientMethodsContent .= "        }\n";
            $clientMethodsContent .= "        throw new \\LogicException('This statement should be never reached.');\n";
            $clientMethodsContent .= "    }\n\n";
        }

        if ($class->id !== 10 &&
            $class->id !== 20 &&
            $class->id !== 30 &&
            (!isset($method->direction) || $method->direction === "CS")
        ) {
            $channelMethodsContent .= "    /**\n";
            $channelMethodsContent .= "     * Calls {$class->name}.{$method->name} AMQP method.\n";
            $channelMethodsContent .= "     *\n";
            $channelMethodsContent .= $channelDocComment;
            $channelMethodsContent .= "     *\n";
            $channelMethodsContent .= "     * @return boolean|Promise\\PromiseInterface" . (isset($method->synchronous) && $method->synchronous ? "|Protocol\\" . dashedToCamel("method-" . $class->name . "-" . $method->name . "-ok-frame") : "") . ($class->id === 60 && $method->id === 70 ? "|Protocol\\MethodBasicGetEmptyFrame" : "") . "\n";
            $channelMethodsContent .= "     */\n";
            $channelMethodsContent .= "    public function " . lcfirst($methodName) . "(" . implode(", ", $channelArguments) . ")\n";
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
file_put_contents(__DIR__ . "/../src/Bunny/Protocol/ProtocolReaderGenerated.php", $protocolReaderContent);

$appendMethodFrameContent .= " {\n";
$appendMethodFrameContent .= "            throw new ProtocolException('Unhandled method frame ' . get_class(\$frame) . '.');\n";
$appendMethodFrameContent .= "        }\n";
$appendMethodFrameContent .= "    }\n\n";

$protocolWriterContent .= $appendMethodFrameContent;
$protocolWriterContent .= "}\n";
file_put_contents(__DIR__ . "/../src/Bunny/Protocol/ProtocolWriterGenerated.php", $protocolWriterContent);

$clientMethodsContent .= "}\n";
file_put_contents(__DIR__ . "/../src/Bunny/ClientMethods.php", $clientMethodsContent);

$channelMethodsContent .= "}\n";
file_put_contents(__DIR__ . "/../src/Bunny/ChannelMethods.php", $channelMethodsContent);
