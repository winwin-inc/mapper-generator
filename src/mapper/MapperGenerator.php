<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\attribute\Mapper;

class MapperGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private readonly Parser $parser;
    private readonly Standard $printer;

    public function __construct(private readonly ValueConverter $converter)
    {
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->createForVersion(PhpVersion::getHostVersion());
        $this->printer = new Standard();
    }

    public function getConverter(): ValueConverter
    {
        return $this->converter;
    }

    public function replaceInFile(string $file): void
    {
        $code = $this->generate($file);
        if (null !== $code) {
            file_put_contents($file, $code);
        }
    }

    public function generate(string $file): ?string
    {
        $code = file_get_contents($file);
        if (!str_contains($code, Mapper::class)) {
            return null;
        }
        $stmts = $this->parser->parse($code);
        $nodeTraverser = new NodeTraverser();
        $visitor = new MapperVisitor($this->converter, $this->parser);
        $visitor->setLogger($this->logger);
        $nodeTraverser->addVisitor($visitor);
        $modified = $nodeTraverser->traverse($stmts);
        if (empty($visitor->getMappers())) {
            return null;
        }

        return $this->printer->prettyPrintFile($modified);
    }
}
