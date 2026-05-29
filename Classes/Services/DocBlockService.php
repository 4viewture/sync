<?php
declare(strict_types=1);

namespace FourViewture\Sync\Services;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

class DocBlockService
{
    public function getDescription(string $docComment): string
    {
        if (!$docComment) {
            return '';
        }

        $config = new ParserConfig([]);
        $lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);

        $tokens = new TokenIterator($lexer->tokenize($docComment));
        $phpDocNode = $phpDocParser->parse($tokens);

        $description = '';
        foreach ($phpDocNode->children as $child) {
            if ($child instanceof PhpDocTextNode) {
                $description .= $child->text . ' ';
                continue;
            }
            if($child instanceof PhpDocTagNode) {
                $description .= $child->name . ': ' . $child->value;
            }
        }

        return trim($description);
    }
}
