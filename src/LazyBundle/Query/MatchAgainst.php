<?php
/**
 * MatchAgainst
 *
 * Definition for MATCH AGAINST MySQL instruction to be used in DQL Queries
 *
 * Usage: MATCH_AGAINST(column[, column, ;;.], :text)
 */
namespace LazyBundle\Query;

use Doctrine\ORM\Query\AST\ASTException;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\InputParameter;
use Doctrine\ORM\Query\AST\Literal;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;

/**
 * "MATCH_AGAINST" "(" {StateFieldPathExpression ","}* Literal ")"
 */
class MatchAgainst extends FunctionNode {
    /**
     * @var PathExpression[]
     */
    public $columns = [];

    /**
     * @var InputParameter
     */
    public $needle;

    /**
     * @var Literal
     */
    public $mode;

    /**
     * @param Parser $parser
     *
     * @throws QueryException
     */
    public function parse(Parser $parser) {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        do {
            $this->columns[] = $parser->StateFieldPathExpression();
            $parser->match(Lexer::T_COMMA);
        } while ($parser->getLexer()->isNextToken(Lexer::T_IDENTIFIER));
        $this->needle = $parser->InParameter();
        while ($parser->getLexer()->isNextToken(Lexer::T_STRING)) {
            $this->mode = $parser->Literal();
        }
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * @param SqlWalker $sqlWalker
     *
     * @return string
     * @throws ASTException
     */
    public function getSql(SqlWalker $sqlWalker) {
        $haystack = null;
        $first = true;
        foreach ($this->columns as $column) {
            $first ? $first = false : $haystack .= ', ';
            $haystack .= $column->dispatch($sqlWalker);
        }
        $query = 'MATCH('.$haystack.') AGAINST ('.$this->needle->dispatch($sqlWalker);
        if ($this->mode) {
            $query .= ' '.$this->mode->dispatch($sqlWalker).' )';
        } else {
            $query .= ' )';
        }

        return $query;
    }

}
