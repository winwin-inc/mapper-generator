<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use Doctrine\Common\Annotations\Reader;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use wenbinye\mapper\annotations\Mapper;

class MapperGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var \PhpParser\Parser
     */
    private $parser;

    /**
     * @var Reader
     */
    private $annotationReader;
    /**
     * @var Standard
     */
    private $printer;

    /**
     * MapperGenerator constructor.
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard();
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
        if (strpos($code, Mapper::class)) {
            return null;
        }
        $stmts = $this->parser->parse($code);
        $nodeTraverser = new NodeTraverser();
        $visitor = new MapperVisitor($this->annotationReader);
        $visitor->setLogger($this->logger);
        $nodeTraverser->addVisitor($visitor);
        $modified = $nodeTraverser->traverse($stmts);
        if (empty($visitor->getMappers())) {
            return null;
        }

        return $this->printer->prettyPrintFile($modified);
    }
}
