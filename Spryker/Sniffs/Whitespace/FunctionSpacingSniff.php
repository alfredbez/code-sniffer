<?php
/**
 * (c) Spryker Systems GmbH copyright protected.
 */

namespace Spryker\Sniffs\Whitespace;

use PHP_CodeSniffer_Tokens;

class FunctionSpacingSniff implements \PHP_CodeSniffer_Sniff
{

    /**
     * @inheritdoc
     */
    public function register()
    {
        return [T_FUNCTION];
    }

    /**
     * @inheritdoc
     */
    public function process(\PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();

        $openingBraceIndex = $phpCsFile->findNext(T_OPEN_CURLY_BRACKET, $stackPointer + 1);
        if (!$openingBraceIndex) {
            $openingParenthesisIndex = $phpCsFile->findNext(T_OPEN_PARENTHESIS, $stackPointer + 1);
            $closingParenthesisIndex = $tokens[$openingParenthesisIndex]['parenthesis_closer'];

            $semicolonIndex = $phpCsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, $closingParenthesisIndex + 1, null, true);

            $nextContentIndex = $phpCsFile->findNext(T_WHITESPACE, $semicolonIndex + 1, null, true);

            if ($tokens[$nextContentIndex]['line'] - $tokens[$semicolonIndex]['line'] <= 1) {
                $fix = $phpCsFile->addFixableError('Every function/method needs a newline afterwards', $closingParenthesisIndex, 'Abstract');
                if ($fix) {
                    $phpCsFile->fixer->addNewline($semicolonIndex);
                }
            }

            return;
        }

        $closingBraceIndex = $tokens[$openingBraceIndex]['scope_closer'];

        // Ignore closures
        $nextIndex = $phpCsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, $closingBraceIndex + 1, null, true);
        if (in_array($tokens[$nextIndex]['content'], [';', ',', ')'])) {
            return;
        }

        $nextContentIndex = $phpCsFile->findNext(T_WHITESPACE, $closingBraceIndex + 1, null, true);

        if ($tokens[$nextContentIndex]['line'] - $tokens[$closingBraceIndex]['line'] <= 1) {
            $fix = $phpCsFile->addFixableError('Every function/method needs a newline afterwards', $closingBraceIndex, 'Concrete');
            if ($fix) {
                $phpCsFile->fixer->addNewline($closingBraceIndex);
            }
        }
    }

}